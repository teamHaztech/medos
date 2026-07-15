<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Core\Support\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        'staff' => [
            'name' => true, 'email' => true, 'role' => true, 'phone' => false,
            'department' => false, 'specialization' => false, 'qualification' => false,
            'consultation_duration_default' => false, 'password' => false,
        ],
        'services' => [
            'name' => true, 'category' => true, 'price' => true, 'code' => false,
            'gst_rate' => false, 'hsn_sac' => false, 'is_taxable' => false,
        ],
        'pharmacy_stock' => [
            'medicine_name' => true, 'batch_number' => true, 'expiry_date' => true, 'quantity' => true,
            'purchase_price' => false, 'selling_price' => false, 'supplier' => false,
        ],
    ];

    private const SAMPLE = [
        'patients'       => ['Anita Sharma', '9822012345', 'female', '', '1990-05-14', '', 'en', 'B+', '', 'Panaji', '', '', ''],
        'medicines'      => ['Paracetamol 500mg', 'Paracetamol', 'Analgesic', '1-0-1', 'tablet'],
        'tests'          => ['Complete Blood Count', 'lab', 'Hematology', '250', '6 hours', 'Fasting not required'],
        'staff'          => ['Dr. Ravi Kumar', 'ravi.kumar@example.com', 'doctor', '9822011111', 'Cardiology', 'Interventional Cardiology', 'MD DM', '15', ''],
        'services'       => ['MRI Brain', 'Radiology', '4500', 'MRI-BR', '0', '', '0'],
        'pharmacy_stock' => ['Paracetamol 500mg', 'B12345', '2027-12-31', '200', '1.50', '3.00', 'MedSupply Co'],
    ];

    /** Login credentials generated during a staff import, surfaced back to the admin. */
    private array $credentials = [];

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
            'file' => 'required|file|mimes:csv,txt,xlsx|max:5120', // 5 MB
        ]);

        $spec = self::COLUMNS[$type];

        // Parse CSV or XLSX into header-keyed rows (headers normalised to snake_case).
        $file   = $request->file('file');
        $parsed = SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalName());
        $header = $parsed['headers'];
        if ($header === []) {
            return back()->with('error', 'The file is empty.');
        }

        $missing = array_diff(array_keys(array_filter($spec)), $header);
        if (! empty($missing)) {
            return back()->with('error', 'Missing required column(s): ' . implode(', ', $missing) . '. Download the template.');
        }

        $hospitalId = Auth::user()->hospital_id;
        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $line     = 1;

        foreach ($parsed['rows'] as $row) {
            $line++;
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
                    'patients'       => $this->importPatient($row, $hospitalId),
                    'medicines'      => $this->importMedicine($row, $hospitalId),
                    'tests'          => $this->importTest($row, $hospitalId),
                    'staff'          => $this->importStaff($row, $hospitalId),
                    'services'       => $this->importService($row, $hospitalId),
                    'pharmacy_stock' => $this->importPharmacyStock($row, $hospitalId),
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

        $msg = "Imported {$imported} " . str_replace('_', ' ', $type) . ($skipped ? ", skipped {$skipped}" : '') . '.';
        return back()
            ->with($imported > 0 ? 'success' : 'error', $msg)
            ->with('import_errors', array_slice($errors, 0, 50))
            ->with('import_credentials', $this->credentials);
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

    private function importStaff(array $row, string $hospitalId): string
    {
        $email = strtolower(trim($row['email']));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "invalid email '{$row['email']}'";
        }

        $roles = ['doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_tech', 'billing_staff', 'hospital_admin', 'dentist', 'dietitian'];
        $role  = strtolower(trim($row['role']));
        if (! in_array($role, $roles, true)) {
            return "invalid role '{$row['role']}' (use " . implode('/', $roles) . ')';
        }

        if (DB::table('staff')->where('email', $email)->exists() || DB::table('users')->where('email', $email)->exists()) {
            return 'email already exists ' . $email;
        }

        $phone  = $row['phone'] ? preg_replace('/\s+/', '', $row['phone']) : null;
        $staffId = Str::uuid()->toString();
        $userId  = Str::uuid()->toString();
        $plain   = ($row['password'] ?? '') !== '' ? $row['password'] : Str::random(10);
        $dur     = is_numeric($row['consultation_duration_default'] ?? '') ? (int) $row['consultation_duration_default'] : 15;

        // Insert staff first without user_id (FK), then the user, then link back —
        // mirrors AdminWebController::storeStaff so the FK constraint is satisfied.
        DB::table('staff')->insert([
            'id'                            => $staffId,
            'hospital_id'                   => $hospitalId,
            'name'                          => $row['name'],
            'email'                         => $email,
            'phone'                         => $phone,
            'role'                          => $role,
            'department'                    => $row['department'] ?: null,
            'specialization'                => $row['specialization'] ?: null,
            'qualification'                 => $row['qualification'] ?: null,
            'consultation_duration_default' => $dur,
            'is_active'                     => true,
            'created_at'                    => now(),
            'updated_at'                    => now(),
        ]);

        DB::table('users')->insert([
            'id'          => $userId,
            'name'        => $row['name'],
            'email'       => $email,
            'password'    => Hash::make($plain),
            'phone'       => $phone,
            'role'        => $role,
            'hospital_id' => $hospitalId,
            'staff_id'    => $staffId,
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('staff')->where('id', $staffId)->update(['user_id' => $userId]);

        $this->credentials[] = ['name' => $row['name'], 'email' => $email, 'password' => $plain];

        return 'imported';
    }

    private function importService(array $row, string $hospitalId): string
    {
        if (! is_numeric($row['price'] ?? '')) {
            return "invalid price '{$row['price']}'";
        }

        if (DB::table('service_charges')->where('hospital_id', $hospitalId)
            ->whereRaw('LOWER(name) = ?', [strtolower($row['name'])])->exists()) {
            return 'already exists';
        }

        $taxable = in_array(strtolower((string) ($row['is_taxable'] ?? '')), ['1', 'true', 'yes', 'y'], true);
        $gstRate = is_numeric($row['gst_rate'] ?? '') ? (float) $row['gst_rate'] : 0.0;

        DB::table('service_charges')->insert([
            'id'          => Str::uuid()->toString(),
            'hospital_id' => $hospitalId,
            'name'        => $row['name'],
            'code'        => $row['code'] ?: null,
            'category'    => $row['category'] ?: 'other',
            'price'       => (float) $row['price'],
            'is_taxable'  => $taxable || $gstRate > 0,
            'gst_rate'    => $gstRate,
            'hsn_sac'     => $row['hsn_sac'] ?: null,
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return 'imported';
    }

    private function importPharmacyStock(array $row, string $hospitalId): string
    {
        $name = trim($row['medicine_name']);
        $medicineId = DB::table('medicines')
            ->where(fn ($q) => $q->where('hospital_id', $hospitalId)->orWhere('is_global', true))
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->value('id');
        if (! $medicineId) {
            return "medicine '{$name}' not found (add it first)";
        }

        $qty = (int) $row['quantity'];
        if ($qty <= 0) {
            return "invalid quantity '{$row['quantity']}'";
        }
        $expiry = $this->parseDate($row['expiry_date'] ?? '');
        if (! $expiry) {
            return "invalid expiry_date '{$row['expiry_date']}'";
        }

        // Same medicine + batch already stocked → skip (avoid double-counting a re-upload).
        if (DB::table('pharmacy_stock')->where('hospital_id', $hospitalId)
            ->where('medicine_id', $medicineId)
            ->where('batch_number', $row['batch_number'])->exists()) {
            return 'batch already stocked';
        }

        DB::table('pharmacy_stock')->insert([
            'id'                 => Str::uuid()->toString(),
            'hospital_id'        => $hospitalId,
            'medicine_id'        => $medicineId,
            'batch_number'       => $row['batch_number'],
            'expiry_date'        => $expiry,
            'quantity_total'     => $qty,
            'quantity_available' => $qty,
            'purchase_price'     => is_numeric($row['purchase_price'] ?? '') ? (float) $row['purchase_price'] : 0,
            'selling_price'      => is_numeric($row['selling_price'] ?? '') ? (float) $row['selling_price'] : 0,
            'supplier'           => $row['supplier'] ?: null,
            'received_at'        => now(),
            'created_at'         => now(),
            'updated_at'         => now(),
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
