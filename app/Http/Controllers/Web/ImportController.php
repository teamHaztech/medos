<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk CSV import for a hospital's master data (patients, medicines, tests).
 * Admin-only. Every row is scoped to the acting user's hospital_id, validated,
 * and de-duplicated. Native CSV parsing (no PhpSpreadsheet) — users export
 * Excel as .csv. Mirrors the single-record create logic in AdminWebController.
 */
class ImportController extends Controller
{
    /** Column spec per import type: header => required? (used for template + validation). */
    private const COLUMNS = [
        'patients' => [
            'name' => true, 'phone' => true, 'gender' => false, 'email' => false,
            'date_of_birth' => false, 'age_approximate' => false, 'language_preference' => false,
            'blood_group' => false, 'address' => false, 'city' => false,
            'emergency_contact_name' => false, 'emergency_contact_phone' => false, 'health_id' => false,
        ],
        'medicines' => [
            'name' => true, 'generic_name' => false, 'category' => false,
            'default_dosage' => false, 'form' => false,
        ],
        'tests' => [
            'name' => true, 'type' => true, 'category' => false,
            'price' => false, 'turnaround_time' => false, 'instructions' => false,
        ],
    ];

    private const SAMPLE = [
        'patients'  => ['Anita Sharma', '9822012345', 'female', '', '1990-05-14', '', 'en', 'B+', '', 'Panaji', '', '', ''],
        'medicines' => ['Paracetamol 500mg', 'Paracetamol', 'Analgesic', '1-0-1', 'tablet'],
        'tests'     => ['Complete Blood Count', 'lab', 'Hematology', '250', '6 hours', 'Fasting not required'],
    ];

    public function index()
    {
        return view('admin.import');
    }

    /** Download a ready-to-fill CSV template for the given type. */
    public function template(string $type)
    {
        abort_unless(isset(self::COLUMNS[$type]), 404);

        $headers = array_keys(self::COLUMNS[$type]);
        $rows    = [$headers, self::SAMPLE[$type]];

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map([$this, 'csvCell'], $row)) . "\r\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="medos-' . $type . '-template.csv"',
        ]);
    }

    /** Parse an uploaded CSV and import its rows. */
    public function import(Request $request, string $type)
    {
        abort_unless(isset(self::COLUMNS[$type]), 404);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120', // 5 MB
        ]);

        $spec  = self::COLUMNS[$type];
        $path  = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return back()->with('error', 'Could not read the uploaded file.');
        }

        // Header row -> normalised keys.
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return back()->with('error', 'The file is empty.');
        }
        $header = array_map(fn ($h) => Str::of($h)->trim()->lower()->replace(' ', '_')->value(), $header);
        // Strip a UTF-8 BOM off the first header cell if present.
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
        }

        $missing = array_diff(array_keys(array_filter($spec)), $header);
        if (! empty($missing)) {
            fclose($handle);
            return back()->with('error', 'Missing required column(s): ' . implode(', ', $missing) . '. Download the template.');
        }

        $hospitalId = Auth::user()->hospital_id;
        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $line     = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($data, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($data[$i]) ? trim((string) $data[$i]) : '';
            }
            // Ensure every known column exists so handlers can read optional
            // fields even when the uploaded file omits those columns.
            foreach (array_keys($spec) as $col) {
                $row[$col] = $row[$col] ?? '';
            }

            // Required-field check.
            foreach ($spec as $col => $required) {
                if ($required && ($row[$col] ?? '') === '') {
                    $errors[] = "Row {$line}: missing {$col}";
                    continue 2;
                }
            }

            try {
                $result = match ($type) {
                    'patients'  => $this->importPatient($row, $hospitalId),
                    'medicines' => $this->importMedicine($row, $hospitalId),
                    'tests'     => $this->importTest($row, $hospitalId),
                };
            } catch (\Throwable $e) {
                $errors[] = "Row {$line}: " . $e->getMessage();
                continue;
            }

            if ($result === 'imported') {
                $imported++;
            } else {
                $skipped++;
                $errors[] = "Row {$line}: skipped ({$result})";
            }
        }

        fclose($handle);

        $msg = "Imported {$imported} " . $type . ($skipped ? ", skipped {$skipped}" : '') . '.';
        return back()
            ->with($imported > 0 ? 'success' : 'error', $msg)
            ->with('import_errors', array_slice($errors, 0, 50));
    }

    // ---- per-type row handlers (return 'imported' or a skip reason) ----

    private function importPatient(array $row, string $hospitalId): string
    {
        $phone = preg_replace('/\s+/', '', $row['phone']);
        if (preg_match('/^\d{10}$/', $phone)) {
            $phone = '+91' . $phone;
        }

        if (DB::table('patients')->where('hospital_id', $hospitalId)->where('phone', $phone)->exists()) {
            return 'duplicate phone ' . $phone;
        }

        $gender = strtolower($row['gender'] ?? '');
        $gender = in_array($gender, ['male', 'female', 'other']) ? $gender : 'unknown';

        DB::table('patients')->insert([
            'id'                      => Str::uuid()->toString(),
            'hospital_id'             => $hospitalId,
            'name'                    => $row['name'],
            'phone'                   => $phone,
            'gender'                  => $gender,
            'email'                   => $row['email'] ?: null,
            'date_of_birth'           => $this->parseDate($row['date_of_birth'] ?? ''),
            'age_approximate'         => is_numeric($row['age_approximate'] ?? '') ? (int) $row['age_approximate'] : null,
            'language_preference'     => $row['language_preference'] ?: 'en',
            'blood_group'             => $row['blood_group'] ?: null,
            'address'                 => $row['address'] ?: null,
            'city'                    => $row['city'] ?: null,
            'emergency_contact_name'  => $row['emergency_contact_name'] ?: null,
            'emergency_contact_phone' => $row['emergency_contact_phone'] ?: null,
            'abha_number'             => ($row['health_id'] ?? '') !== '' ? preg_replace('/[\s-]/', '', $row['health_id']) : null,
            'created_via'             => 'import',
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        return 'imported';
    }

    private function importMedicine(array $row, string $hospitalId): string
    {
        if (DB::table('medicines')->where('hospital_id', $hospitalId)->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [strtolower($row['name'])])->exists()) {
            return 'already exists';
        }

        DB::table('medicines')->insert([
            'id'             => Str::uuid()->toString(),
            'hospital_id'    => $hospitalId,
            'name'           => $row['name'],
            'generic_name'   => $row['generic_name'] ?: $row['name'],
            'category'       => $row['category'] ?: 'Other',
            'default_dosage' => $row['default_dosage'] ?: null,
            'form'           => $row['form'] ?: 'tablet',
            'is_global'      => false,
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return 'imported';
    }

    private function importTest(array $row, string $hospitalId): string
    {
        $validType = ['lab', 'imaging', 'procedure'];
        $ttype = strtolower($row['type']);
        if (! in_array($ttype, $validType)) {
            return "invalid type '{$row['type']}' (use lab/imaging/procedure)";
        }

        if (DB::table('available_tests')->where('hospital_id', $hospitalId)->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [strtolower($row['name'])])->exists()) {
            return 'already exists';
        }

        DB::table('available_tests')->insert([
            'id'              => Str::uuid()->toString(),
            'hospital_id'     => $hospitalId,
            'name'            => $row['name'],
            'code'            => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $row['name']), 0, 6)),
            'type'            => $ttype,
            'category'        => $row['category'] ?: $ttype,
            'price'           => is_numeric($row['price'] ?? '') ? (float) $row['price'] : 0,
            'turnaround_time' => $row['turnaround_time'] ?: null,
            'instructions'    => $row['instructions'] ?: null,
            'is_global'       => false,
            'is_active'       => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return 'imported';
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Quote a CSV cell if it contains a comma, quote, or newline. */
    private function csvCell($value): string
    {
        $value = (string) $value;
        if (preg_match('/[",\r\n]/', $value)) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
