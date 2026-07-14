<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-hospital backup & restore in a safe, tenant-scoped JSON envelope.
 *
 * export() snapshots one hospital's rows (the hospital row + every table with a
 * hospital_id) into a portable array. import() re-inserts those rows into a
 * TARGET hospital, forcing hospital_id = target on every row so a restore can
 * never write into another tenant, and using insertOrIgnore so it is additive
 * (re-adds deleted rows, never overwrites or duplicates existing ones). The
 * `hospitals` table is intentionally never restored — you restore INTO an
 * existing hospital, never create one from a data file.
 */
class HospitalBackup
{
    public const FORMAT = 'medos-hospital-backup';
    public const VERSION = 1;

    /** Build the JSON-serialisable backup for one hospital. */
    public static function export(string $hospitalId): array
    {
        $hospital = DB::table('hospitals')->where('id', $hospitalId)->first();

        $tables = [
            'hospitals' => DB::table('hospitals')->where('id', $hospitalId)->get()
                ->map(fn ($r) => (array) $r)->all(),
        ];

        foreach (self::hospitalScopedTables() as $table) {
            $rows = DB::table($table)->where('hospital_id', $hospitalId)->get();
            if ($rows->isEmpty()) {
                continue;
            }
            $tables[$table] = $rows->map(fn ($r) => (array) $r)->all();
        }

        return [
            'format'        => self::FORMAT,
            'version'       => self::VERSION,
            'hospital_id'   => $hospitalId,
            'hospital_name' => $hospital?->name,
            'generated_at'  => now()->toDateTimeString(),
            'tables'        => $tables,
        ];
    }

    /**
     * Restore a backup into $targetHospitalId. Additive and tenant-safe.
     *
     * @return array{imported:int, skipped:int, tables:int, errors:array<string>}
     */
    public static function import(string $targetHospitalId, array $data): array
    {
        if (($data['format'] ?? null) !== self::FORMAT || ! isset($data['tables']) || ! is_array($data['tables'])) {
            throw new \InvalidArgumentException('This is not a valid MedOS hospital backup file.');
        }

        $imported = 0;
        $skipped  = 0;
        $tables   = 0;
        $errors   = [];

        DB::transaction(function () use ($data, $targetHospitalId, &$imported, &$skipped, &$tables, &$errors) {
            foreach ($data['tables'] as $table => $rows) {
                // Never create/overwrite hospitals from a data file; restore into an existing one.
                if ($table === 'hospitals' || ! is_array($rows)) {
                    continue;
                }
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'hospital_id')) {
                    continue; // table renamed/removed since the backup — skip gracefully
                }

                $cols = Schema::getColumnListing($table);
                $tables++;

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $row['hospital_id'] = $targetHospitalId;          // force tenant scope
                    $row = array_intersect_key($row, array_flip($cols)); // drop columns no longer on the table

                    try {
                        $affected = DB::table($table)->insertOrIgnore([$row]);
                        $affected ? $imported++ : $skipped++;
                    } catch (\Throwable $e) {
                        $errors[] = $table . ': ' . $e->getMessage();
                    }
                }
            }
        });

        return compact('imported', 'skipped', 'tables', 'errors');
    }

    /** All tables (besides hospitals) that carry a hospital_id column. */
    private static function hospitalScopedTables(): array
    {
        $out = [];
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        foreach ($tables as $t) {
            if ($t->name === 'hospitals') {
                continue;
            }
            if (Schema::hasColumn($t->name, 'hospital_id')) {
                $out[] = $t->name;
            }
        }

        return $out;
    }
}
