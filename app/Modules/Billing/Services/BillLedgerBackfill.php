<?php

namespace App\Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfills the charge-capture ledger (charge_items) from bills that were
 * created without posting charges (seeded / imported / legacy bills). This is
 * what makes the Billing Audit's department + GST panels reflect historical
 * bills — those panels read charge_items, while Collections read bill_payments.
 *
 * Idempotent at bill granularity: a bill that already has any charge_item is
 * skipped, so it is safe to run repeatedly and safe to call on every audit view
 * (covers future hospitals/bills automatically). Categorisation is best-effort,
 * inferred from each line's text against the medicine/test masters + keywords;
 * anything ambiguous lands in "other". Backfilled rows are marked non-taxable
 * (historical per-line GST is unknown) and tagged posted_by_name = 'Ledger backfill'.
 */
class BillLedgerBackfill
{
    /** Backfill one hospital's un-ledgered bills. Returns the number of charge_items created. */
    public static function backfillHospital(string $hospitalId): int
    {
        $billsWithCharges = DB::table('charge_items')
            ->where('hospital_id', $hospitalId)
            ->whereNotNull('bill_id')
            ->distinct()->pluck('bill_id')->all();

        $bills = DB::table('bills')
            ->where('hospital_id', $hospitalId)
            ->when(! empty($billsWithCharges), fn ($q) => $q->whereNotIn('id', $billsWithCharges))
            ->get();

        if ($bills->isEmpty()) {
            return 0;
        }

        // Master data for accurate categorisation (this hospital + global rows).
        $medicines = DB::table('medicines')
            ->where(fn ($q) => $q->where('hospital_id', $hospitalId)->orWhere('is_global', true))
            ->pluck('name')->map(fn ($n) => mb_strtolower(trim((string) $n)))->filter()->unique()->all();

        $testMap = [];
        foreach (DB::table('available_tests')
            ->where(fn ($q) => $q->where('hospital_id', $hospitalId)->orWhere('is_global', true))
            ->get(['name', 'type']) as $t) {
            $name = mb_strtolower(trim((string) $t->name));
            if ($name !== '') {
                $testMap[$name] = $t->type; // lab | imaging | procedure
            }
        }

        $created = 0;

        foreach ($bills as $bill) {
            // charge_items.patient_id is NOT NULL — resolve it or skip the bill.
            $patientId = $bill->patient_id
                ?: ($bill->encounter_id ? DB::table('encounters')->where('id', $bill->encounter_id)->value('patient_id') : null);
            if (! $patientId) {
                continue;
            }

            $items = json_decode($bill->line_items ?? '[]', true);
            if (! is_array($items) || empty($items)) {
                continue;
            }

            $status = ($bill->payment_status === 'cancelled') ? 'cancelled' : 'billed';
            $ts     = $bill->created_at ?? now();
            $rows   = [];

            foreach (array_values($items) as $i => $li) {
                if (! is_array($li)) {
                    continue;
                }
                $desc  = trim((string) ($li['description'] ?? $li['name'] ?? 'Charge'));
                $qty   = (float) ($li['quantity'] ?? 1);
                $unit  = (float) ($li['unit_price'] ?? $li['price'] ?? 0);
                $total = (float) ($li['total'] ?? ($qty * $unit));

                $rows[] = [
                    'id'             => (string) Str::uuid(),
                    'hospital_id'    => $hospitalId,
                    'patient_id'     => $patientId,
                    'encounter_id'   => $bill->encounter_id,
                    'admission_id'   => $bill->admission_id ?? null,
                    'bill_id'        => $bill->id,
                    'source'         => self::categorise($desc, $medicines, $testMap),
                    'source_ref'     => 'backfill:' . $bill->id . ':' . $i,
                    'description'    => $desc !== '' ? $desc : 'Charge',
                    'quantity'       => $qty ?: 1,
                    'unit_price'     => $unit,
                    'total'          => $total,
                    'is_taxable'     => 0,
                    'gst_rate'       => 0,
                    'status'         => $status,
                    'posted_by_name' => 'Ledger backfill',
                    'posted_at'      => $ts,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            if (! empty($rows)) {
                DB::table('charge_items')->insert($rows);
                $created += count($rows);
            }
        }

        return $created;
    }

    /** Backfill every hospital. Returns [hospitalId => count created]. */
    public static function backfillAll(): array
    {
        $out = [];
        foreach (DB::table('hospitals')->pluck('id') as $hid) {
            $out[$hid] = self::backfillHospital($hid);
        }

        return $out;
    }

    /** Map a free-text line description to a charge source. */
    private static function categorise(string $desc, array $medicines, array $testMap): string
    {
        $d = mb_strtolower($desc);

        // 1. Match against the test master (most specific).
        foreach ($testMap as $name => $type) {
            if (str_contains($d, $name)) {
                return in_array($type, ['imaging', 'procedure'], true) ? $type : 'lab';
            }
        }

        // 2. Match against the medicine master.
        foreach ($medicines as $name) {
            if ($name !== '' && str_contains($d, $name)) {
                return 'pharmacy';
            }
        }

        // 3. Keyword fallbacks.
        if (str_contains($d, 'consult')) {
            return 'consultation';
        }
        if (preg_match('/\b(oxygen|o2|cannula|iv set|iv-set|glove|syringe|needle|mask|ppe|kit|disposable|consumable|catheter|gauze|bandage|cotton|drip set|infusion set)\b/', $d)) {
            return 'consumable';
        }
        if (str_contains($d, 'registration') || str_contains($d, 'reg fee')) {
            return 'registration';
        }
        if (preg_match('/\b(x-?ray|x ray|mri|ct scan|ct-|ultrasound|usg|sonograph|scan|ecg|eeg|imaging)\b/', $d)) {
            return 'imaging';
        }
        if (preg_match('/\b(cbc|blood|urine|panel|profile|lab|biopsy|culture|serum|hba1c|lipid|thyroid|sugar|test)\b/', $d)) {
            return 'lab';
        }
        if (preg_match('/\b(room|bed|ward|icu)\b/', $d)) {
            return 'room';
        }
        if (str_contains($d, 'nursing')) {
            return 'nursing';
        }
        if (preg_match('/\b(procedure|dressing|injection|suture|surg)\b/', $d)) {
            return 'procedure';
        }
        if (preg_match('/\b(mg|ml|tab|tablet|cap|capsule|syrup|ointment|drops)\b/', $d) || preg_match('/x\s*\d+/', $d)) {
            return 'pharmacy';
        }

        return 'other';
    }
}
