<?php

namespace App\Http\Controllers\Web;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Services\DoctorSlotService;
use App\Modules\Billing\Models\Bill;
use App\Modules\Billing\Models\ServiceCharge;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Services\RegionService;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public, patient-facing "Book Online" flow (no login). Responsive web + mobile.
 * Books a FUTURE doctor slot, creates the Encounter/Appointment + a MedOS Bill
 * (which the BillObserver auto-syncs to the hospital's external ERP).
 */
class PublicBookingController extends Controller
{
    public function __construct(private DoctorSlotService $slots) {}

    private function resolveHospital(?Request $request = null): ?Hospital
    {
        if ($request && $request->filled('hospital')) {
            $h = Hospital::where('slug', $request->get('hospital'))->where('is_active', true)->first();
            if ($h) {
                session(['book_hospital_id' => $h->id]);

                return $h;
            }
        }
        if (session('book_hospital_id')) {
            $h = Hospital::where('id', session('book_hospital_id'))->where('is_active', true)->first();
            if ($h) {
                return $h;
            }
        }
        $active = Hospital::where('is_active', true)->get();
        if ($active->count() === 1) {
            session(['book_hospital_id' => $active->first()->id]);

            return $active->first();
        }

        return null;
    }

    private function consultationFee(string $hospitalId): float
    {
        $svc = ServiceCharge::where('hospital_id', $hospitalId)
            ->where('category', 'consultation')->where('is_active', true)
            ->orderBy('price')->first();

        return $svc ? (float) $svc->price : 0.0;
    }

    public function index(Request $request)
    {
        $hospital = $this->resolveHospital($request);
        if (! $hospital) {
            $hospitals = Hospital::where('is_active', true)->orderBy('name')->get(['slug', 'name', 'city']);

            return view('book.select-hospital', compact('hospitals'));
        }
        if ($hospital->country) {
            config(['medos.current_hospital_id' => $hospital->id]);
        }

        return view('book.index', [
            'hospital' => $hospital,
            'fee'      => $this->consultationFee($hospital->id),
            'currency' => RegionService::currency(),
        ]);
    }

    public function doctors(Request $request)
    {
        $hospital = $this->resolveHospital($request);
        if (! $hospital) {
            return response()->json([]);
        }

        return Staff::where('hospital_id', $hospital->id)->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'department', 'specialization'])
            ->values();
    }

    public function slots(Request $request, string $doctorId)
    {
        $hospital = $this->resolveHospital($request);
        if (! $hospital) {
            return response()->json(['days' => []]);
        }
        $doctor = Staff::where('hospital_id', $hospital->id)->where('id', $doctorId)->first();
        if (! $doctor) {
            return response()->json(['days' => []]);
        }

        return response()->json($this->slots->calendar($doctor, 14));
    }

    public function store(Request $request)
    {
        $hospital = $this->resolveHospital($request);
        if (! $hospital) {
            return back()->with('error', 'Please select a hospital first.');
        }
        config(['medos.current_hospital_id' => $hospital->id]);

        $g = strtolower(trim((string) $request->input('gender')));
        $request->merge(['gender' => in_array($g, ['male', 'female', 'other'], true) ? $g : null]);

        $v = $request->validate([
            'doctor_id'      => 'required|string|exists:staff,id',
            'date'           => 'required|date_format:Y-m-d',
            'time'           => 'required|date_format:H:i',
            'name'           => 'required|string|max:255',
            'phone'          => 'required|string|min:4|max:15',
            'gender'         => 'nullable|in:male,female,other',
            'complaint'      => 'nullable|string|max:1000',
            'payment_option' => 'nullable|in:hospital,online,insurance',
        ]);

        $doctor = Staff::where('hospital_id', $hospital->id)->where('id', $v['doctor_id'])->where('is_active', true)->first();
        if (! $doctor) {
            return back()->withInput()->with('error', 'That doctor is not available.');
        }

        $slotStart = Carbon::parse($v['date'] . ' ' . $v['time']);
        if ($slotStart->lt(now())) {
            return back()->withInput()->with('error', 'That time has already passed — pick another slot.');
        }
        if (! $this->slots->isWorkingAt($doctor, $slotStart)) {
            return back()->withInput()->with('error', 'The doctor is not available at that time.');
        }

        // Find or create patient by phone (mirrors kiosk; restores a soft-deleted row).
        $phone = preg_replace('/[^0-9+]/', '', $v['phone']);
        if (! str_starts_with($phone, '+')) {
            $phone = strlen($phone) === 10 ? '+91' . $phone : '+' . $phone;
        }
        $patient = Patient::withTrashed()->where('hospital_id', $hospital->id)->where('phone', $phone)->first();
        if ($patient && method_exists($patient, 'trashed') && $patient->trashed()) {
            $patient->restore();
        }
        if (! $patient) {
            $patient = Patient::create([
                'id'                  => Str::uuid()->toString(),
                'hospital_id'         => $hospital->id,
                'name'                => $v['name'],
                'phone'               => $phone,
                'phone_verified'      => false,
                'gender'              => $v['gender'] ?? 'unknown',
                'language_preference' => 'en',
                'created_via'         => 'online',
            ]);
        }

        $fee = $this->consultationFee($hospital->id);
        $duration = $doctor->consultation_duration_default ?? 15;
        $token = null;

        try {
            $token = DB::transaction(function () use ($hospital, $doctor, $patient, $slotStart, $duration, $fee, $v) {
                $taken = Appointment::where('doctor_id', $doctor->id)
                    ->where('slot_start', $slotStart)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->lockForUpdate()->exists();
                if ($taken) {
                    throw new \RuntimeException('slot_taken');
                }

                $encounter = Encounter::create([
                    'id'               => Str::uuid()->toString(),
                    'hospital_id'      => $hospital->id,
                    'patient_id'       => $patient->id,
                    'doctor_id'        => $doctor->id,
                    'encounter_number' => 'ENC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                    'type'             => 'consultation',
                    'status'           => 'booked',
                    'channel'          => 'web',
                    'intake_data'      => array_filter([
                        'chief_complaint' => $v['complaint'] ?? null,
                        'who'             => 'self',
                        'source'          => 'online_booking',
                    ], fn ($x) => $x !== null),
                ]);

                $tok = Appointment::generateToken($doctor->id, $doctor->department, $slotStart);

                Appointment::create([
                    'id'                         => Str::uuid()->toString(),
                    'hospital_id'                => $hospital->id,
                    'encounter_id'               => $encounter->id,
                    'patient_id'                 => $patient->id,
                    'doctor_id'                  => $doctor->id,
                    'slot_start'                 => $slotStart,
                    'slot_end'                   => $slotStart->copy()->addMinutes($duration),
                    'predicted_duration_minutes' => $duration,
                    'status'                     => 'scheduled',
                    'booking_source'             => 'online',
                    'notes'                      => $tok,
                ]);

                // Consultation bill → BillObserver auto-syncs it to the external ERP.
                Bill::create([
                    'id'                => Str::uuid()->toString(),
                    'hospital_id'       => $hospital->id,
                    'encounter_id'      => $encounter->id,
                    'patient_id'        => $patient->id,
                    'bill_number'       => 'BILL-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                    'bill_type'         => 'online',
                    'line_items'        => [[
                        'description' => 'Consultation — ' . $doctor->name,
                        'quantity'    => 1,
                        'unit_price'  => $fee,
                        'total'       => $fee,
                        'category'    => 'consultation',
                        'taxable'     => false,
                    ]],
                    'subtotal'          => $fee,
                    'tax_amount'        => 0,
                    'tax_rate'          => 0,
                    'discount_amount'   => 0,
                    'insurance_covered' => 0,
                    'deposit_applied'   => 0,
                    'patient_payable'   => $fee,
                    'total_amount'      => $fee,
                    'amount_paid'       => 0,
                    'balance_due'       => $fee,
                    'payment_status'    => 'pending',
                    'currency'          => RegionService::currencyCode(),
                    'issued_at'         => now(),
                ]);

                return $tok;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'slot_taken') {
                return back()->withInput()->with('error', 'Sorry, that slot was just booked. Please pick another time.');
            }
            throw $e;
        }

        return redirect()->route('book.confirmed', ['token' => $token]);
    }

    public function confirmed(Request $request, string $token)
    {
        $hospital = $this->resolveHospital($request);
        if (! $hospital) {
            return redirect()->route('book.index');
        }

        $appointment = Appointment::where('hospital_id', $hospital->id)
            ->where('notes', $token)
            ->whereDate('slot_start', '>=', today())
            ->with(['doctor:id,name,department', 'patient:id,name,phone'])
            ->latest('slot_start')->first();

        if (! $appointment) {
            return redirect()->route('book.index');
        }

        $bill = Bill::where('encounter_id', $appointment->encounter_id)->first();

        return view('book.confirmed', [
            'hospital'    => $hospital,
            'appointment' => $appointment,
            'bill'        => $bill,
            'currency'    => RegionService::currency(),
        ]);
    }
}
