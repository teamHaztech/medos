<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Billing\Models\ChargeItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Revenue-cycle analytics over the charge-capture ledger (charge_items).
 *
 * Every dispensed medicine and every performed lab/imaging/procedure test posts a
 * granular row into charge_items (description, quantity, unit_price, total, posted_at),
 * so this ledger — not a mocked table — is the single source of truth for what was
 * actually sold and billed. All figures below come straight from it.
 *
 * Shared by the Pharmacy and Lab insight screens; source-agnostic (pass the set of
 * ledger `source` values you care about, e.g. ['pharmacy'] or ['lab','imaging','procedure']).
 */
class RevenueInsights
{
    /**
     * Resolve a named reporting window into concrete bounds, the equal-length previous
     * window (for period-over-period deltas), a trend bucket granularity, and labels.
     *
     * @return array{
     *   start:Carbon, end:Carbon, prevStart:Carbon, prevEnd:Carbon,
     *   granularity:string, label:string, labelFormat:string
     * }
     */
    public function range(string $period): array
    {
        return match ($period) {
            'today' => [
                'start'       => now()->startOfDay(),
                'end'         => now()->endOfDay(),
                'prevStart'   => now()->subDay()->startOfDay(),
                'prevEnd'     => now()->subDay()->endOfDay(),
                'granularity' => 'hour',
                'label'       => 'Today',
                'labelFormat' => 'ga',
            ],
            'week' => [
                'start'       => now()->startOfWeek(),
                'end'         => now()->endOfWeek(),
                'prevStart'   => now()->subWeek()->startOfWeek(),
                'prevEnd'     => now()->subWeek()->endOfWeek(),
                'granularity' => 'day',
                'label'       => 'This Week',
                'labelFormat' => 'D',
            ],
            'year' => [
                'start'       => now()->startOfYear(),
                'end'         => now()->endOfYear(),
                'prevStart'   => now()->subYear()->startOfYear(),
                'prevEnd'     => now()->subYear()->endOfYear(),
                'granularity' => 'month',
                'label'       => 'This Year',
                'labelFormat' => 'M',
            ],
            default => [ // month
                'start'       => now()->startOfMonth(),
                'end'         => now()->endOfMonth(),
                // NoOverflow so e.g. Mar 31 → Feb (not "Mar 3") when subtracting a month.
                'prevStart'   => now()->subMonthNoOverflow()->startOfMonth(),
                'prevEnd'     => now()->subMonthNoOverflow()->endOfMonth(),
                'granularity' => 'day',
                'label'       => 'This Month',
                'labelFormat' => 'j',
            ],
        };
    }

    /** Base ledger query scoped to a hospital, a set of sources and a time window. */
    private function base(string $hospitalId, array $sources, Carbon $start, Carbon $end)
    {
        return ChargeItem::query()
            ->where('hospital_id', $hospitalId)
            ->whereIn('source', $sources)
            ->where('status', '!=', ChargeItem::STATUS_CANCELLED)
            ->whereNotNull('posted_at')
            ->whereBetween('posted_at', [$start, $end]);
    }

    /**
     * Headline totals for a window: billed revenue, line count (tests / medicine lines)
     * and total units (quantity dispensed).
     *
     * @return array{revenue:float, lines:int, units:float}
     */
    public function totals(string $hospitalId, array $sources, Carbon $start, Carbon $end): array
    {
        if (! Schema::hasTable('charge_items')) {
            return ['revenue' => 0.0, 'lines' => 0, 'units' => 0.0];
        }

        $row = $this->base($hospitalId, $sources, $start, $end)
            ->selectRaw('COALESCE(SUM(total),0) as revenue, COUNT(*) as lines, COALESCE(SUM(quantity),0) as units')
            ->first();

        return [
            'revenue' => round((float) ($row->revenue ?? 0), 2),
            'lines'   => (int) ($row->lines ?? 0),
            'units'   => round((float) ($row->units ?? 0), 2),
        ];
    }

    /**
     * Revenue trend as evenly-spaced buckets across the window, with empty buckets
     * filled so a chart has a continuous x-axis. Granularity comes from range().
     *
     * @return array<int, array{key:string, label:string, revenue:float, units:float, lines:int}>
     */
    public function trend(string $hospitalId, array $sources, Carbon $start, Carbon $end, string $granularity, string $labelFormat): array
    {
        [$keyExpr, $keyFormat, $step] = match ($granularity) {
            'hour'  => ["strftime('%Y-%m-%d %H', posted_at)", 'Y-m-d H', 'hour'],
            'month' => ["strftime('%Y-%m', posted_at)", 'Y-m', 'month'],
            default => ['date(posted_at)', 'Y-m-d', 'day'], // day
        };

        $data = collect();
        if (Schema::hasTable('charge_items')) {
            $data = $this->base($hospitalId, $sources, $start, $end)
                ->selectRaw("$keyExpr as bucket, COALESCE(SUM(total),0) as revenue, COALESCE(SUM(quantity),0) as units, COUNT(*) as lines")
                ->groupBy('bucket')
                ->get()
                ->keyBy('bucket');
        }

        // Walk the window in fixed steps so gaps render as zero-height bars.
        $out     = [];
        $cursor  = $start->copy();
        $ceiling = $end->copy();

        // Guard against runaway loops on absurd ranges.
        $maxBuckets = 400;
        while ($cursor <= $ceiling && $maxBuckets-- > 0) {
            $key = $cursor->format($keyFormat);
            $hit = $data->get($key);
            $out[] = [
                'key'     => $key,
                'label'   => $cursor->format($labelFormat),
                'revenue' => round((float) ($hit->revenue ?? 0), 2),
                'units'   => round((float) ($hit->units ?? 0), 2),
                'lines'   => (int) ($hit->lines ?? 0),
            ];
            $cursor->addUnit($step, 1);
        }

        return $out;
    }

    /**
     * Ranked line items within the window, grouped by what was sold/performed
     * (description = the medicine SKU line or the test name). Returns every distinct
     * item with its unit total, revenue and share — the caller slices/re-sorts for the
     * "top by revenue" vs "sold most" tables.
     *
     * @return Collection<int, object{description:string, source:string, lines:int, units:float, revenue:float, share:float}>
     */
    public function items(string $hospitalId, array $sources, Carbon $start, Carbon $end): Collection
    {
        if (! Schema::hasTable('charge_items')) {
            return collect();
        }

        $rows = $this->base($hospitalId, $sources, $start, $end)
            ->selectRaw('description, source, COUNT(*) as lines, COALESCE(SUM(quantity),0) as units, COALESCE(SUM(total),0) as revenue')
            ->groupBy('description', 'source')
            ->get();

        $totalRevenue = (float) $rows->sum('revenue') ?: 1.0;

        return $rows->map(function ($r) use ($totalRevenue) {
            $r->units   = round((float) $r->units, 2);
            $r->revenue = round((float) $r->revenue, 2);
            $r->lines   = (int) $r->lines;
            $r->share   = round(($r->revenue / $totalRevenue) * 100, 1);

            return $r;
        })->values();
    }

    /**
     * Revenue + volume split by ledger source (e.g. lab vs imaging vs procedure).
     *
     * @return Collection<int, object{source:string, lines:int, units:float, revenue:float}>
     */
    public function bySource(string $hospitalId, array $sources, Carbon $start, Carbon $end): Collection
    {
        if (! Schema::hasTable('charge_items')) {
            return collect();
        }

        return $this->base($hospitalId, $sources, $start, $end)
            ->selectRaw('source, COUNT(*) as lines, COALESCE(SUM(quantity),0) as units, COALESCE(SUM(total),0) as revenue')
            ->groupBy('source')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($r) {
                $r->revenue = round((float) $r->revenue, 2);
                $r->units   = round((float) $r->units, 2);
                $r->lines   = (int) $r->lines;

                return $r;
            });
    }

    /**
     * Bucket key for a moment at the given granularity — mirrors the SQL keys used by
     * trend() so DB-aggregated and PHP-aggregated series line up on the same axis.
     */
    public static function bucketKey(Carbon $d, string $granularity): string
    {
        return match ($granularity) {
            'hour'  => $d->format('Y-m-d H'),
            'month' => $d->format('Y-m'),
            default => $d->format('Y-m-d'),
        };
    }

    /**
     * Ordered, gap-filled empty buckets spanning [start, end] at the granularity.
     *
     * @return array<string, array{label:string, value:float}> keyed by bucketKey()
     */
    public function axis(Carbon $start, Carbon $end, string $granularity, string $labelFormat): array
    {
        $step   = match ($granularity) { 'hour' => 'hour', 'month' => 'month', default => 'day' };
        $out    = [];
        $cursor = $start->copy();
        $guard  = 400;

        while ($cursor <= $end && $guard-- > 0) {
            $out[self::bucketKey($cursor, $granularity)] = ['label' => $cursor->format($labelFormat), 'value' => 0.0];
            $cursor->addUnit($step, 1);
        }

        return $out;
    }

    /**
     * Generic count/sum time-series over ANY row set (appointments, admissions, claims…),
     * bucketed in PHP so it works for tables that aren't the charge ledger. $dateFn pulls
     * the moment from a row; $valueFn pulls the value to sum (default: count of 1).
     *
     * @return array<int, array{label:string, value:float}>
     */
    public function series(iterable $rows, callable $dateFn, Carbon $start, Carbon $end, string $granularity, string $labelFormat, ?callable $valueFn = null): array
    {
        $buckets = $this->axis($start, $end, $granularity, $labelFormat);

        foreach ($rows as $row) {
            $d = $dateFn($row);
            if (! $d) {
                continue;
            }
            $d = $d instanceof Carbon ? $d : Carbon::parse($d);
            $k = self::bucketKey($d, $granularity);
            if (isset($buckets[$k])) {
                $buckets[$k]['value'] += $valueFn ? (float) $valueFn($row) : 1;
            }
        }

        return array_values($buckets);
    }

    /** Period-over-period percentage change, guarding division by zero. */
    public static function pctChange(float $current, float $previous): int
    {
        if ($previous > 0) {
            return (int) round((($current - $previous) / $previous) * 100);
        }

        return $current > 0 ? 100 : 0;
    }
}
