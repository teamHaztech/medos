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
}
