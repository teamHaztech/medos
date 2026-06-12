<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCalibration;
use App\Modules\Asset\Models\AssetMaintenanceLog;
use App\Modules\Asset\Models\AssetServiceRequest;
use App\Modules\Asset\Models\AssetWarranty;
use App\Modules\Asset\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    /** Resolve the current hospital and activate the model hospital scope. */
    private function hid(): string
    {
        $hid = Auth::user()->hospital_id;
        config(['medos.current_hospital_id' => $hid]);

        return $hid;
    }

    // ---------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------

    public function dashboard()
    {
        $this->hid();

        $within = fn (int $d) => AssetWarranty::expiringWithin($d)->count();
        $expiring = [
            30 => $within(30),
            60 => $within(60),
            90 => $within(90),
        ];

        // Active assets (in the register) and their warranty state.
        $assets = Asset::where('is_active', true)
            ->where('status', '!=', 'decommissioned')
            ->with('warranties')
            ->get();

        $withoutWarranty = $assets->filter(fn (Asset $a) => ! $a->hasActiveWarranty())->values();

        // AMC / CMC / manufacturer overview (active, non-expired).
        $contractOverview = [];
        foreach (array_keys(AssetWarranty::TYPES) as $type) {
            $contractOverview[$type] = AssetWarranty::where('warranty_type', $type)
                ->where('is_active', true)
                ->whereDate('end_date', '>=', now()->toDateString())
                ->count();
        }

        // Soonest-expiring active warranties (next 90 days).
        $expiringList = AssetWarranty::expiringWithin(90)
            ->with('asset')
            ->orderBy('end_date')
            ->limit(25)
            ->get();

        // Maintenance due soon (next 30 days) + overdue.
        $maintenanceDue = AssetMaintenanceLog::whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', now()->addDays(30)->toDateString())
            ->with('asset')
            ->orderBy('next_due_date')
            ->limit(25)
            ->get();

        // Calibrations due (next 60 days + overdue) — guarded for not-yet-migrated DBs.
        $hasCalibrations = Schema::hasTable('asset_calibrations');
        $hasTickets = Schema::hasTable('asset_service_requests');

        $calibrationDue = $hasCalibrations
            ? AssetCalibration::dueWithin(60)->with('asset')->orderBy('next_due_date')->limit(25)->get()
            : collect();

        // Open service requests / breakdowns.
        $openTickets = $hasTickets
            ? AssetServiceRequest::whereIn('status', ['open', 'in_progress'])
                ->with('asset')
                ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
                ->orderByDesc('reported_at')
                ->limit(25)
                ->get()
            : collect();

        $stats = [
            'total_assets'      => Asset::where('is_active', true)->count(),
            'under_maintenance' => Asset::where('is_active', true)->where('status', 'under_maintenance')->count(),
            'decommissioned'    => Asset::where('is_active', true)->where('status', 'decommissioned')->count(),
            'no_warranty'       => $withoutWarranty->count(),
            'vendors'           => Vendor::where('is_active', true)->count(),
            'calibration_due'   => $hasCalibrations ? AssetCalibration::dueWithin(30)->count() : 0,
            'open_tickets'      => $openTickets->count(),
        ];

        return view('admin.assets.dashboard', compact(
            'expiring', 'contractOverview', 'expiringList', 'maintenanceDue',
            'calibrationDue', 'openTickets', 'withoutWarranty', 'stats'
        ));
    }

    // ---------------------------------------------------------------
    // Asset register (list + filters)
    // ---------------------------------------------------------------

    public function index(Request $request)
    {
        $this->hid();

        $query = Asset::where('is_active', true)->with(['vendor', 'warranties']);

        if ($dept = $request->get('department')) {
            $query->where('department', $dept);
        }
        if ($type = $request->get('type')) {
            $query->where('asset_type', $type);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_name', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('manufacturer', 'like', "%{$search}%");
            });
        }

        $assets = $query->orderBy('asset_name')->get();

        // Warranty-status filter is computed (depends on dates), applied after load.
        if ($w = $request->get('warranty')) {
            $assets = $assets->filter(function (Asset $a) use ($w) {
                $active = $a->activeWarranty();
                return match ($w) {
                    'active'   => $active !== null,
                    'none'     => $active === null,
                    'expiring' => $active !== null && $active->isExpiringWithin(30),
                    'expired'  => $active === null && $a->warranties->count() > 0,
                    default    => true,
                };
            })->values();
        }

        $vendors      = Vendor::where('is_active', true)->orderBy('name')->get();
        $departments  = Asset::DEPARTMENTS;
        $types        = Asset::TYPES;
        $statuses     = Asset::STATUSES;
        $filters      = $request->only(['department', 'type', 'status', 'warranty', 'search']);

        return view('admin.assets.index', compact(
            'assets', 'vendors', 'departments', 'types', 'statuses', 'filters'
        ));
    }

    public function show(string $id)
    {
        $this->hid();

        $with = ['vendor', 'warranties', 'maintenanceLogs'];
        $hasCalibrations = Schema::hasTable('asset_calibrations');
        $hasTickets = Schema::hasTable('asset_service_requests');
        if ($hasCalibrations) {
            $with[] = 'calibrations';
        }
        if ($hasTickets) {
            $with[] = 'serviceRequests';
        }

        $asset = Asset::with($with)->findOrFail($id);

        // Avoid lazy-loading (and a query against a missing table) in the view.
        if (! $hasCalibrations) {
            $asset->setRelation('calibrations', collect());
        }
        if (! $hasTickets) {
            $asset->setRelation('serviceRequests', collect());
        }

        $vendors = Vendor::where('is_active', true)->orderBy('name')->get();

        return view('admin.assets.show', compact('asset', 'vendors'));
    }

    // ---------------------------------------------------------------
    // Asset CRUD
    // ---------------------------------------------------------------

    public function store(Request $request)
    {
        $hid = $this->hid();
        $data = $this->validateAsset($request);
        $data['hospital_id'] = $hid;

        Asset::create($data);

        return redirect()->route('web.admin.assets.index')->with('success', 'Asset added.');
    }

    public function update(Request $request, string $id)
    {
        $this->hid();
        $asset = Asset::findOrFail($id);
        $asset->update($this->validateAsset($request));

        return redirect()->route('web.admin.assets.show', $asset->id)->with('success', 'Asset updated.');
    }

    public function destroy(string $id)
    {
        $this->hid();
        Asset::where('id', $id)->update(['is_active' => false]);

        return redirect()->route('web.admin.assets.index')->with('success', 'Asset removed from the register.');
    }

    /** Decommission an asset (keeps it in the register with reason/date/disposal). */
    public function decommission(Request $request, string $id)
    {
        $this->hid();
        $asset = Asset::findOrFail($id);

        $v = $request->validate([
            'decommissioned_on'   => 'required|date',
            'decommission_reason' => 'required|string|max:500',
            'disposal_method'     => 'nullable|string|max:255',
        ]);

        $asset->update([
            'status'              => 'decommissioned',
            'decommissioned_on'   => $v['decommissioned_on'],
            'decommission_reason' => $v['decommission_reason'],
            'disposal_method'     => $v['disposal_method'] ?? null,
        ]);

        return redirect()->route('web.admin.assets.show', $asset->id)->with('success', 'Asset decommissioned.');
    }

    private function validateAsset(Request $request): array
    {
        $v = $request->validate([
            'asset_name'    => 'required|string|max:255',
            'asset_type'    => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'model'         => 'nullable|string|max:100',
            'manufacturer'  => 'nullable|string|max:150',
            'department'    => 'nullable|string|max:100',
            'location'      => 'nullable|string|max:150',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'vendor_id'     => 'nullable|uuid|exists:vendors,id',
            'status'        => 'required|in:active,under_maintenance,decommissioned',
            'notes'         => 'nullable|string|max:1000',
        ]);

        return $v;
    }

    // ---------------------------------------------------------------
    // Warranties
    // ---------------------------------------------------------------

    public function storeWarranty(Request $request, string $assetId)
    {
        $hid = $this->hid();
        $asset = Asset::findOrFail($assetId);
        $data = $this->validateWarranty($request);
        $data['hospital_id'] = $hid;
        $data['asset_id'] = $asset->id;

        if ($request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store("asset-warranties/{$hid}", 'local');
        }

        AssetWarranty::create($data);

        return redirect()->route('web.admin.assets.show', $asset->id)->with('success', 'Warranty added.');
    }

    public function updateWarranty(Request $request, string $id)
    {
        $hid = $this->hid();
        $warranty = AssetWarranty::findOrFail($id);
        $data = $this->validateWarranty($request);

        if ($request->hasFile('document')) {
            if ($warranty->document_path) {
                Storage::disk('local')->delete($warranty->document_path);
            }
            $data['document_path'] = $request->file('document')->store("asset-warranties/{$hid}", 'local');
        }

        $warranty->update($data);

        return redirect()->route('web.admin.assets.show', $warranty->asset_id)->with('success', 'Warranty updated.');
    }

    public function destroyWarranty(string $id)
    {
        $this->hid();
        $warranty = AssetWarranty::findOrFail($id);
        $assetId = $warranty->asset_id;
        if ($warranty->document_path) {
            Storage::disk('local')->delete($warranty->document_path);
        }
        $warranty->delete();

        return redirect()->route('web.admin.assets.show', $assetId)->with('success', 'Warranty removed.');
    }

    /** Renew a warranty: create a fresh record continuing from the old one, retire the old. */
    public function renewWarranty(Request $request, string $id)
    {
        $hid = $this->hid();
        $old = AssetWarranty::findOrFail($id);

        $v = $request->validate([
            'end_date'                    => 'required|date|after:today',
            'vendor_contact'              => 'nullable|string|max:255',
            'terms'                       => 'nullable|string|max:2000',
            'reminder_days_before_expiry' => 'nullable|integer|min:1|max:365',
        ]);

        // New term starts the day after the old one ends (or today if already expired).
        $start = $old->end_date && $old->end_date->isFuture() ? $old->end_date->copy()->addDay() : now();

        AssetWarranty::create([
            'hospital_id'                 => $hid,
            'asset_id'                    => $old->asset_id,
            'warranty_type'               => $old->warranty_type,
            'start_date'                  => $start->toDateString(),
            'end_date'                    => $v['end_date'],
            'vendor_contact'              => $v['vendor_contact'] ?? $old->vendor_contact,
            'terms'                       => $v['terms'] ?? $old->terms,
            'reminder_days_before_expiry' => $v['reminder_days_before_expiry'] ?? $old->reminder_days_before_expiry,
            'is_active'                   => true,
        ]);

        $old->update(['is_active' => false]); // keep as history, no longer the active cover

        return redirect()->route('web.admin.assets.show', $old->asset_id)->with('success', 'Warranty renewed.');
    }

    // ---------------------------------------------------------------
    // Calibrations
    // ---------------------------------------------------------------

    public function storeCalibration(Request $request, string $assetId)
    {
        $hid = $this->hid();
        $asset = Asset::findOrFail($assetId);

        $v = $request->validate([
            'calibrated_on'            => 'nullable|date',
            'next_due_date'            => 'nullable|date',
            'performed_by'             => 'nullable|string|max:255',
            'result'                   => 'required|in:pass,fail,adjusted',
            'reminder_days_before_due' => 'nullable|integer|min:1|max:365',
            'notes'                    => 'nullable|string|max:1000',
            'certificate'              => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        $data = [
            'hospital_id'              => $hid,
            'asset_id'                 => $asset->id,
            'calibrated_on'            => $v['calibrated_on'] ?? null,
            'next_due_date'            => $v['next_due_date'] ?? null,
            'performed_by'             => $v['performed_by'] ?? null,
            'result'                   => $v['result'],
            'reminder_days_before_due' => $v['reminder_days_before_due'] ?? 30,
            'notes'                    => $v['notes'] ?? null,
            'is_active'                => true,
        ];

        if ($request->hasFile('certificate')) {
            $data['certificate_path'] = $request->file('certificate')->store("asset-calibrations/{$hid}", 'local');
        }

        AssetCalibration::create($data);

        return redirect()->route('web.admin.assets.show', $asset->id)->with('success', 'Calibration record added.');
    }

    public function destroyCalibration(string $id)
    {
        $this->hid();
        $cal = AssetCalibration::findOrFail($id);
        $assetId = $cal->asset_id;
        if ($cal->certificate_path) {
            Storage::disk('local')->delete($cal->certificate_path);
        }
        $cal->delete();

        return redirect()->route('web.admin.assets.show', $assetId)->with('success', 'Calibration record removed.');
    }

    public function downloadCertificate(string $id)
    {
        $this->hid();
        $cal = AssetCalibration::with('asset')->findOrFail($id);

        abort_if(! $cal->certificate_path || ! Storage::disk('local')->exists($cal->certificate_path), 404);

        $ext = pathinfo($cal->certificate_path, PATHINFO_EXTENSION);
        $name = 'calibration-' . str($cal->asset?->asset_name ?? 'asset')->slug() . '.' . $ext;

        return Storage::disk('local')->download($cal->certificate_path, $name);
    }

    /** Stream a warranty document, scoped to the current hospital. */
    public function downloadDocument(string $id)
    {
        $this->hid();
        $warranty = AssetWarranty::with('asset')->findOrFail($id);

        abort_if(! $warranty->document_path || ! Storage::disk('local')->exists($warranty->document_path), 404);

        $ext = pathinfo($warranty->document_path, PATHINFO_EXTENSION);
        $name = 'warranty-' . str($warranty->asset?->asset_name ?? 'asset')->slug() . '.' . $ext;

        return Storage::disk('local')->download($warranty->document_path, $name);
    }

    private function validateWarranty(Request $request): array
    {
        $v = $request->validate([
            'warranty_type'               => 'required|in:manufacturer,amc,cmc',
            'start_date'                  => 'nullable|date',
            'end_date'                    => 'required|date',
            'vendor_contact'              => 'nullable|string|max:255',
            'terms'                       => 'nullable|string|max:2000',
            'reminder_days_before_expiry' => 'nullable|integer|min:1|max:365',
            'document'                    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        return [
            'warranty_type'               => $v['warranty_type'],
            'start_date'                  => $v['start_date'] ?? null,
            'end_date'                    => $v['end_date'],
            'vendor_contact'              => $v['vendor_contact'] ?? null,
            'terms'                       => $v['terms'] ?? null,
            'reminder_days_before_expiry' => $v['reminder_days_before_expiry'] ?? 30,
            'is_active'                   => true,
        ];
    }

    // ---------------------------------------------------------------
    // Maintenance logs
    // ---------------------------------------------------------------

    public function storeMaintenance(Request $request, string $assetId)
    {
        $hid = $this->hid();
        $asset = Asset::findOrFail($assetId);

        $v = $request->validate([
            'maintenance_type' => 'required|in:preventive,corrective',
            'performed_by'     => 'nullable|string|max:255',
            'date'             => 'required|date',
            'cost'             => 'nullable|numeric|min:0',
            'next_due_date'    => 'nullable|date',
            'notes'            => 'nullable|string|max:1000',
        ]);
        $v['hospital_id'] = $hid;
        $v['asset_id'] = $asset->id;

        AssetMaintenanceLog::create($v);

        return redirect()->route('web.admin.assets.show', $asset->id)->with('success', 'Maintenance log added.');
    }

    public function destroyMaintenance(string $id)
    {
        $this->hid();
        $log = AssetMaintenanceLog::findOrFail($id);
        $assetId = $log->asset_id;
        $log->delete();

        return redirect()->route('web.admin.assets.show', $assetId)->with('success', 'Maintenance log removed.');
    }

    // ---------------------------------------------------------------
    // Reports / export
    // ---------------------------------------------------------------

    /** CSV of the asset register + warranty status (no external library needed). */
    public function exportCsv()
    {
        $this->hid();
        $assets = Asset::where('is_active', true)->with(['vendor', 'warranties'])->orderBy('asset_name')->get();

        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="asset-register.csv"'];

        return response()->streamDownload(function () use ($assets) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Asset', 'Type', 'Serial', 'Model', 'Manufacturer', 'Department', 'Location',
                'Status', 'Vendor', 'Purchase Date', 'Purchase Cost',
                'Warranty Type', 'Warranty End', 'Warranty Status', 'Days To Expiry',
            ]);
            foreach ($assets as $a) {
                $w = $a->activeWarranty();
                $latest = $w ?? $a->warranties->first();
                $wStatus = $w ? 'Active' : ($a->warranties->count() ? 'Expired' : 'None');
                fputcsv($out, [
                    $a->asset_name, $a->asset_type, $a->serial_number, $a->model, $a->manufacturer,
                    $a->department, $a->location, $a->statusLabel(), $a->vendor?->name,
                    optional($a->purchase_date)->toDateString(), $a->purchase_cost,
                    $latest?->typeLabel(), optional($latest?->end_date)->toDateString(),
                    $wStatus, $latest?->daysToExpiry(),
                ]);
            }
            fclose($out);
        }, 'asset-register.csv', $headers);
    }

    /** Printable HTML report (browser print-to-PDF — no composer dependency). */
    public function report()
    {
        $this->hid();
        $assets = Asset::where('is_active', true)->with(['vendor', 'warranties'])->orderBy('department')->orderBy('asset_name')->get();
        $hospital = Auth::user()->hospital ?? null;

        return view('admin.assets.report', compact('assets', 'hospital'));
    }
}
