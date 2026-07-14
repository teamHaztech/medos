<?php

namespace App\Http\Controllers\Web;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    private function hid(): string
    {
        return Auth::user()->hospital_id;
    }

    public function index()
    {
        $hid = $this->hid();

        $items = InventoryItem::where('hospital_id', $hid)->orderBy('name')->get();

        $movements = StockMovement::where('hospital_id', $hid)
            ->with('item:id,name,unit')->orderByDesc('created_at')->limit(60)->get();

        $expiring = StockMovement::where('hospital_id', $hid)->where('type', 'receipt')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays(90))
            ->with('item:id,name')->orderBy('expiry_date')->limit(50)->get();

        $counts = [
            'items'    => $items->count(),
            'low'      => $items->filter(fn ($i) => $i->current_stock > 0 && $i->current_stock <= $i->reorder_min)->count(),
            'out'      => $items->filter(fn ($i) => $i->current_stock <= 0)->count(),
            'expiring' => $expiring->count(),
        ];

        return view('inventory.index', compact('items', 'movements', 'expiring', 'counts'));
    }

    public function move(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'item_id'      => 'required|uuid',
            'type'         => 'required|in:receipt,issue,adjustment',
            'quantity'     => 'required|integer',
            'batch_number' => 'nullable|string|max:60',
            'expiry_date'  => 'nullable|date',
            'department'   => 'nullable|string|max:120',
            'reference'    => 'nullable|string|max:120',
            'notes'        => 'nullable|string|max:500',
        ]);

        $item = InventoryItem::where('hospital_id', $hid)->findOrFail($v['item_id']);
        $qty = (int) $v['quantity'];

        $delta = match ($v['type']) {
            'receipt'    => abs($qty),
            'issue'      => -abs($qty),
            'adjustment' => $qty,
        };

        if ($delta === 0) {
            return back()->with('error', 'Quantity cannot be zero.');
        }
        if ($item->current_stock + $delta < 0) {
            return back()->with('error', 'Insufficient stock — only ' . $item->current_stock . ' ' . $item->unit . ' available.');
        }

        DB::transaction(function () use ($hid, $item, $delta, $v) {
            StockMovement::create([
                'id'                => (string) Str::uuid(),
                'hospital_id'       => $hid,
                'item_id'           => $item->id,
                'type'              => $v['type'],
                'quantity'          => $delta,
                'batch_number'      => $v['batch_number'] ?? null,
                'expiry_date'       => $v['expiry_date'] ?? null,
                'department'        => $v['department'] ?? null,
                'reference'         => $v['reference'] ?? null,
                'performed_by_name' => Auth::user()->name,
                'notes'             => $v['notes'] ?? null,
                'created_at'        => now(),
            ]);
            $item->increment('current_stock', $delta);
        });

        return back()->with('success', 'Stock ' . $v['type'] . ' recorded.');
    }

    public function storeItem(Request $request)
    {
        $hid = $this->hid();
        $v = $this->validateItem($request);
        $v['hospital_id'] = $hid;
        $v['current_stock'] = (int) ($v['current_stock'] ?? 0);
        $v['is_active'] = true;
        InventoryItem::create($v);

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, string $id)
    {
        $hid = $this->hid();
        $item = InventoryItem::where('hospital_id', $hid)->findOrFail($id);
        $v = $this->validateItem($request);
        $v['is_active'] = (bool) ($request->input('is_active', false));
        unset($v['current_stock']); // stock only changes via movements
        $item->update($v);

        return back()->with('success', 'Item updated.');
    }

    public function destroyItem(string $id)
    {
        $hid = $this->hid();
        InventoryItem::where('hospital_id', $hid)->where('id', $id)->update(['is_active' => false]);

        return back()->with('success', 'Item deactivated.');
    }

    private function validateItem(Request $request): array
    {
        return $request->validate([
            'name'          => 'required|string|max:150',
            'code'          => 'nullable|string|max:40',
            'category'      => 'required|in:' . implode(',', array_keys(InventoryItem::CATEGORIES)),
            'unit'          => 'required|in:' . implode(',', InventoryItem::UNITS),
            'reorder_min'   => 'required|integer|min:0',
            'reorder_max'   => 'required|integer|min:0',
            'current_stock' => 'nullable|integer|min:0',
        ]);
    }

    // ---------------------------------------------------------------
    // Bulk import / export
    // ---------------------------------------------------------------

    /** Export all inventory items for this hospital as a CSV (same shape as the import). */
    public function exportItems()
    {
        $items = InventoryItem::where('hospital_id', $this->hid())->orderBy('name')->get();

        $out = "name,code,category,unit,reorder_min,reorder_max,current_stock\n";
        foreach ($items as $it) {
            $out .= $this->csvRow([
                $it->name, $it->code, $it->category, $it->unit,
                $it->reorder_min, $it->reorder_max, $it->current_stock,
            ]);
        }

        return response($out, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="inventory_export_' . date('Ymd') . '.csv"',
        ]);
    }

    /** Downloadable CSV template for the bulk item import. */
    public function importTemplate()
    {
        $csv = "name,code,category,unit,reorder_min,reorder_max,current_stock\n"
             . "Surgical Gloves (M),GLV-M,surgical,box,10,100,50\n"
             . "Paracetamol 500mg,PARA500,drug,strip,20,200,80\n"
             . "Cotton Roll,COT-01,consumable,roll,5,50,15\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="inventory_import_template.csv"',
        ]);
    }

    /**
     * Bulk-import inventory items from a CSV file or pasted rows. Columns:
     * name,code,category,unit,reorder_min,reorder_max,current_stock (order-based
     * or by header row). name is required; category/unit fall back to sensible
     * defaults if unknown; items with a duplicate name are skipped so the same
     * file can be safely re-run.
     */
    public function importItems(Request $request)
    {
        $request->validate([
            'file' => 'nullable|file|max:2048',
            'rows' => 'nullable|string',
        ]);

        $raw = '';
        if ($request->hasFile('file')) {
            $raw = (string) file_get_contents($request->file('file')->getRealPath());
        } elseif ($request->filled('rows')) {
            $raw = (string) $request->input('rows');
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', trim($raw));
        if ($raw === '') {
            return back()->with('error', 'No CSV file or pasted data was provided.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));

        $defaultMap = ['name' => 0, 'code' => 1, 'category' => 2, 'unit' => 3, 'reorder_min' => 4, 'reorder_max' => 5, 'current_stock' => 6];
        $map = $defaultMap;
        $firstCells = array_map(fn ($c) => strtolower(trim($c)), str_getcsv($lines[0]));
        if (in_array('name', $firstCells, true)) {
            $map = [];
            foreach ($firstCells as $i => $c) {
                $map[$c] = $i;
            }
            array_shift($lines);
        }

        // category accepts the key or the display label; unit accepts any known unit.
        $catLookup = [];
        foreach (InventoryItem::CATEGORIES as $key => $label) {
            $catLookup[$key] = $key;
            $catLookup[strtolower($label)] = $key;
        }
        $unitSet = array_map('strtolower', InventoryItem::UNITS);

        $hid = $this->hid();
        $existing = InventoryItem::where('hospital_id', $hid)->pluck('name')
            ->map(fn ($n) => strtolower(trim($n)))->flip();

        $created = [];
        $skipped = [];
        $errors  = [];
        $seen    = [];
        $offset  = ($map !== $defaultMap) ? 2 : 1;

        foreach ($lines as $idx => $line) {
            if (count($created) + count($skipped) + count($errors) >= 1000) {
                $errors[] = ['row' => $idx + $offset, 'name' => '', 'reason' => 'row limit (1000) reached'];
                break;
            }
            $cells = str_getcsv($line);
            $cell = function (string $key) use ($cells, $map) {
                $i = $map[$key] ?? null;
                return ($i !== null && isset($cells[$i])) ? trim($cells[$i]) : '';
            };

            $rowNo = $idx + $offset;
            $name  = $cell('name');
            if ($name === '') {
                continue; // blank line
            }
            $nameKey = strtolower($name);
            if (isset($existing[$nameKey]) || isset($seen[$nameKey])) {
                $skipped[] = ['name' => $name, 'reason' => 'already exists'];
                continue;
            }

            $category = $catLookup[strtolower($cell('category'))] ?? 'other';
            $unit = in_array(strtolower($cell('unit')), $unitSet, true) ? strtolower($cell('unit')) : 'piece';

            try {
                InventoryItem::create([
                    'hospital_id'   => $hid,
                    'name'          => $name,
                    'code'          => $cell('code') ?: null,
                    'category'      => $category,
                    'unit'          => $unit,
                    'reorder_min'   => max(0, (int) $cell('reorder_min')),
                    'reorder_max'   => max(0, (int) $cell('reorder_max')),
                    'current_stock' => max(0, (int) $cell('current_stock')),
                    'is_active'     => true,
                ]);
                $created[] = ['name' => $name, 'category' => $category, 'unit' => $unit, 'stock' => max(0, (int) $cell('current_stock'))];
                $seen[$nameKey] = true;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNo, 'name' => $name, 'reason' => 'could not create item'];
            }
        }

        $parts = [count($created) . ' items imported'];
        if ($skipped) {
            $parts[] = count($skipped) . ' skipped (already exist)';
        }
        if ($errors) {
            $parts[] = count($errors) . ' had errors';
        }

        return back()
            ->with('success', implode(' · ', $parts) . '.')
            ->with('import_result', ['created' => $created, 'skipped' => $skipped, 'errors' => $errors]);
    }

    /** Build one CSV line, quoting fields that contain a comma, quote or newline. */
    private function csvRow(array $fields): string
    {
        $escaped = array_map(function ($f) {
            $f = (string) $f;
            if (preg_match('/[",\r\n]/', $f)) {
                $f = '"' . str_replace('"', '""', $f) . '"';
            }
            return $f;
        }, $fields);

        return implode(',', $escaped) . "\n";
    }
}
