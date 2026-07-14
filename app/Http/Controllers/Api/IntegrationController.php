<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Billing\Services\ChargeCapture;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Order;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * External integration endpoints (chatbot / CRM / voice-AI agent):
 *   GET  /api/v1/customer?phone=
 *   GET  /api/v1/doctor-schedule?name=&days=
 *   GET  /api/v1/my-appointments?phone=|patient_id=&include_past=
 *   POST /api/v1/book-appointment
 *   POST /api/v1/reschedule-appointment
 *   POST /api/v1/cancel-appointment
 *   POST /api/v1/book-lab-test
 * All are Sanctum-authenticated and hospital-scoped by the token's user.
 */
class IntegrationController extends Controller
{
    /**
     * The hospital this request acts on. Uses the context resolved by the
     * `resolve.hospital` middleware (X-Hospital-ID header → subdomain → the token's
     * own hospital), so ONE platform (super-admin) token can serve every hospital in
     * MedOS by sending `X-Hospital-ID: <hospital_id>`, while a hospital's own token
     * stays pinned to itself. Falls back to the token owner's hospital.
     */
    private function hid(): string
    {
        return app(HospitalContext::class)->getHospitalId() ?? Auth::user()->hospital_id;
    }

    // ---------------------------------------------------------------
    // 0. Hospital directory (map a phone line / DID → hospital_id)
    // ---------------------------------------------------------------
    public function hospitals(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = is_object($user->role) ? $user->role->value : $user->role;

        $query = Hospital::where('is_active', true);
        if ($role !== 'super_admin') {
            $query->where('id', $user->hospital_id); // a hospital token only sees its own
        }

        $hospitals = $query->orderBy('name')->get()->map(fn (Hospital $h) => [
            'hospital_id' => $h->id,
            'name'        => $h->name,
            'slug'        => $h->slug,
            'city'        => $h->city,
            'phone'       => $h->phone,
        ]);

        return response()->json(['success' => true, 'data' => ['hospitals' => $hospitals]]);
    }

    /** staff.schedule may be an array (Eloquent cast) or a JSON string — normalise to array. */
    private function scheduleArray(Staff $doctor): array
    {
        $s = $doctor->schedule;
        if (is_array($s)) {
            return $s;
        }
        return json_decode($s ?? '{}', true) ?: [];
    }

    private function matchPatientByPhone(string $hospitalId, string $phone): ?Patient
    {
        $digits = preg_replace('/\D/', '', $phone);
        $last10 = substr($digits, -10);

        return Patient::where('hospital_id', $hospitalId)
            ->where(function ($q) use ($phone, $last10) {
                $q->where('phone', $phone);
                if (strlen($last10) === 10) {
                    $q->orWhere('phone', 'like', '%' . $last10);
                }
            })
            ->first();
    }

    // ---------------------------------------------------------------
    // 1. Customer lookup by phone
    // ---------------------------------------------------------------
    public function customer(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string|max:20']);
        $hid = $this->hid();

        $patient = $this->matchPatientByPhone($hid, $request->query('phone'));
        if (! $patient) {
            return response()->json(['success' => false, 'message' => 'No customer found for that phone number.'], 404);
        }

        $upcoming = Appointment::where('hospital_id', $hid)
            ->where('patient_id', $patient->id)
            ->where('slot_start', '>=', now())
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in'])
            ->with('doctor:id,name,department')
            ->orderBy('slot_start')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'appointment_id' => $a->id,
                'doctor'         => $a->doctor?->name,
                'department'     => $a->doctor?->department,
                'date'           => optional($a->slot_start)->toDateString(),
                'time'           => optional($a->slot_start)->format('H:i'),
                'token'          => $a->notes,
                'status'         => is_object($a->status) ? $a->status->value : $a->status,
            ]);

        return response()->json(['success' => true, 'data' => [
            'patient_id'            => $patient->id,
            'name'                  => $patient->name,
            'email'                 => $patient->email,
            'phone'                 => $patient->phone,
            'gender'                => $patient->gender,
            'date_of_birth'         => optional($patient->date_of_birth)->toDateString(),
            'blood_group'           => $patient->blood_group,
            'upcoming_appointments' => $upcoming,
        ]]);
    }

    // ---------------------------------------------------------------
    // 2. Doctor schedule by (fuzzy) name
    // ---------------------------------------------------------------
    public function doctorSchedule(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'days' => 'nullable|integer|min:1|max:30',
        ]);
        $hid = $this->hid();
        $days = (int) ($request->query('days', 7));

        // Normalise: drop a leading "Dr." / "Dr" and lowercase.
        $query = strtolower(trim(preg_replace('/^\s*dr\.?\s+/i', '', $request->query('name'))));

        $doctors = Staff::where('hospital_id', $hid)->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin'])->get();

        // Substring match first, then best fuzzy similarity.
        $doctor = $doctors->first(fn ($d) => str_contains(strtolower($d->name), $query));
        if (! $doctor) {
            $best = null;
            $bestScore = 0.0;
            foreach ($doctors as $d) {
                similar_text($query, strtolower($d->name), $pct);
                if ($pct > $bestScore) {
                    $bestScore = $pct;
                    $best = $d;
                }
            }
            if ($bestScore >= 45) {
                $doctor = $best;
            }
        }

        if (! $doctor) {
            return response()->json(['success' => false, 'message' => 'No doctor matched "' . $request->query('name') . '".'], 404);
        }

        return response()->json(['success' => true, 'data' => [
            'doctor_id'             => $doctor->id,
            'name'                  => $doctor->name,
            'specialty'             => $doctor->specialization,
            'department'            => $doctor->department,
            'consultation_duration' => $doctor->consultation_duration_default ?? 15,
            'schedule'              => $this->buildSchedule($doctor, $days),
        ]]);
    }

    /** N-day list of available (free, future) time slots from the doctor's weekly schedule. */
    private function buildSchedule(Staff $doctor, int $days): array
    {
        $schedule = $this->scheduleArray($doctor);
        $duration = $doctor->consultation_duration_default ?? 15;
        $out = [];

        for ($d = 0; $d < $days; $d++) {
            $date = now()->addDays($d);
            $blocks = $schedule[strtolower($date->format('l'))] ?? [];
            if (empty($blocks)) {
                continue;
            }

            $booked = Appointment::where('doctor_id', $doctor->id)
                ->whereDate('slot_start', $date->toDateString())
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->pluck('slot_start')
                ->map(fn ($s) => Carbon::parse($s)->format('H:i'))
                ->all();

            $slots = [];
            foreach ($blocks as $block) {
                $start = Carbon::parse($date->toDateString() . ' ' . $block['start']);
                $end = Carbon::parse($date->toDateString() . ' ' . $block['end']);
                while ($start->copy()->addMinutes($duration)->lte($end)) {
                    $t = $start->format('H:i');
                    $past = $d === 0 && $start->lt(now());
                    if (! $past && ! in_array($t, $booked, true)) {
                        $slots[] = $t;
                    }
                    $start->addMinutes($duration);
                }
            }

            if ($slots) {
                $out[] = ['date' => $date->toDateString(), 'day' => $date->format('l'), 'available_slots' => $slots];
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------
    // 3. Book an appointment
    // ---------------------------------------------------------------
    public function bookAppointment(Request $request): JsonResponse
    {
        $v = $request->validate([
            'doctor_id'  => 'required|uuid',
            'patient_id' => 'nullable|uuid',
            'phone'      => 'nullable|string|max:20',
            'date'       => 'required|date_format:Y-m-d',
            'time'       => 'required|date_format:H:i',
            'notes'      => 'nullable|string|max:500',
        ]);
        $hid = $this->hid();

        $doctor = Staff::where('hospital_id', $hid)->where('id', $v['doctor_id'])->first();
        if (! $doctor || ! $doctor->is_active) {
            return response()->json(['success' => false, 'message' => 'Doctor not found or inactive.'], 422);
        }

        $patient = null;
        if (! empty($v['patient_id'])) {
            $patient = Patient::where('hospital_id', $hid)->find($v['patient_id']);
        } elseif (! empty($v['phone'])) {
            $patient = $this->matchPatientByPhone($hid, $v['phone']);
        }
        if (! $patient) {
            return response()->json(['success' => false, 'message' => 'Patient not found — pass a valid patient_id or a registered phone.'], 404);
        }

        $slotStart = Carbon::parse($v['date'] . ' ' . $v['time']);
        if ($slotStart->lt(now())) {
            return response()->json(['success' => false, 'message' => 'That slot is in the past.'], 422);
        }

        // Doctor must be working at that time.
        $schedule = $this->scheduleArray($doctor);
        $blocks = $schedule[strtolower($slotStart->format('l'))] ?? [];
        $working = false;
        foreach ($blocks as $block) {
            $bs = Carbon::parse($slotStart->toDateString() . ' ' . $block['start']);
            $be = Carbon::parse($slotStart->toDateString() . ' ' . $block['end']);
            if ($slotStart->gte($bs) && $slotStart->lt($be)) {
                $working = true;
                break;
            }
        }
        if (! $working) {
            return response()->json(['success' => false, 'message' => 'The doctor is not working at that time.'], 422);
        }

        // Fast, friendly pre-check (the authoritative guard is the atomic re-check below).
        $taken = Appointment::where('doctor_id', $doctor->id)
            ->where('slot_start', $slotStart)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->exists();
        if ($taken) {
            return response()->json(['success' => false, 'message' => 'That slot is already booked.'], 422);
        }

        $duration = $doctor->consultation_duration_default ?? 15;

        // Book atomically so two concurrent requests can't both take the same slot.
        // The encounter insert grabs the DB write lock first (serialising SQLite
        // writers); we then re-check the slot under lockForUpdate before inserting the
        // appointment. If the slot was taken in the meantime we throw to roll back.
        try {
            [$encounter, $appointment, $token] = DB::transaction(function () use ($hid, $doctor, $patient, $slotStart, $duration, $v) {
                $encounter = Encounter::create([
                    'id'               => Str::uuid()->toString(),
                    'hospital_id'      => $hid,
                    'patient_id'       => $patient->id,
                    'doctor_id'        => $doctor->id,
                    'encounter_number' => 'ENC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                    'type'             => 'consultation',
                    'status'           => 'booked',
                    'channel'          => 'web',
                    'intake_data'      => array_filter(['chief_complaint' => $v['notes'] ?? null, 'source' => 'api'], fn ($x) => $x !== null),
                ]);

                $clash = Appointment::where('doctor_id', $doctor->id)
                    ->where('slot_start', $slotStart)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->lockForUpdate()
                    ->exists();
                if ($clash) {
                    throw new \RuntimeException('SLOT_TAKEN');
                }

                $token = Appointment::generateToken($doctor->id, $doctor->department, $slotStart);

                $appointment = Appointment::create([
                    'id'                         => Str::uuid()->toString(),
                    'hospital_id'                => $hid,
                    'encounter_id'               => $encounter->id,
                    'patient_id'                 => $patient->id,
                    'doctor_id'                  => $doctor->id,
                    'status'                     => 'scheduled',
                    'slot_start'                 => $slotStart,
                    'slot_end'                   => $slotStart->copy()->addMinutes($duration),
                    'predicted_duration_minutes' => $duration,
                    'booking_source'             => 'api',
                    'notes'                      => $token,
                ]);

                return [$encounter, $appointment, $token];
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'SLOT_TAKEN') {
                return response()->json(['success' => false, 'message' => 'That slot was just booked by someone else.'], 409);
            }
            throw $e;
        }

        return response()->json(['success' => true, 'data' => [
            'appointment_id' => $appointment->id,
            'token'          => $token,
            'doctor'         => $doctor->name,
            'patient'        => $patient->name,
            'date'           => $slotStart->toDateString(),
            'time'           => $slotStart->format('H:i'),
            'status'         => 'scheduled',
        ]], 201);
    }

    // ---------------------------------------------------------------
    // 4. Check my appointments (by phone or patient_id)
    // ---------------------------------------------------------------
    public function myAppointments(Request $request): JsonResponse
    {
        $request->validate([
            'phone'        => 'nullable|string|max:20',
            'patient_id'   => 'nullable|uuid',
            'include_past' => 'nullable|boolean',
        ]);
        $hid = $this->hid();

        $patient = null;
        if ($request->filled('patient_id')) {
            $patient = Patient::where('hospital_id', $hid)->find($request->query('patient_id'));
        } elseif ($request->filled('phone')) {
            $patient = $this->matchPatientByPhone($hid, $request->query('phone'));
        }
        if (! $patient) {
            return response()->json(['success' => false, 'message' => 'Patient not found — pass a valid patient_id or a registered phone.'], 404);
        }

        $includePast = filter_var($request->query('include_past', false), FILTER_VALIDATE_BOOLEAN);

        $query = Appointment::where('hospital_id', $hid)
            ->where('patient_id', $patient->id)
            ->with('doctor:id,name,department,specialization');

        if (! $includePast) {
            $query->where('slot_start', '>=', now()->startOfDay())
                ->whereNotIn('status', ['cancelled', 'no_show']);
        }

        $appointments = $query->orderByDesc('slot_start')->limit(50)->get()
            ->map(fn ($a) => $this->appointmentPayload($a));

        return response()->json(['success' => true, 'data' => [
            'patient_id'   => $patient->id,
            'name'         => $patient->name,
            'phone'        => $patient->phone,
            'appointments' => $appointments,
        ]]);
    }

    // ---------------------------------------------------------------
    // 5. Reschedule an existing appointment (same doctor, new slot)
    // ---------------------------------------------------------------
    public function rescheduleAppointment(Request $request): JsonResponse
    {
        $v = $request->validate([
            'appointment_id' => 'required|uuid',
            'date'           => 'required|date_format:Y-m-d',
            'time'           => 'required|date_format:H:i',
        ]);
        $hid = $this->hid();

        $appointment = Appointment::where('hospital_id', $hid)->with('doctor')->find($v['appointment_id']);
        if (! $appointment) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }

        $status = is_object($appointment->status) ? $appointment->status->value : $appointment->status;
        if (in_array($status, ['completed', 'cancelled', 'no_show'], true)) {
            return response()->json(['success' => false, 'message' => "A {$status} appointment can't be rescheduled."], 422);
        }

        $doctor = $appointment->doctor;
        if (! $doctor || ! $doctor->is_active) {
            return response()->json(['success' => false, 'message' => 'The doctor is no longer available.'], 422);
        }

        $slotStart = Carbon::parse($v['date'] . ' ' . $v['time']);
        if ($slotStart->lt(now())) {
            return response()->json(['success' => false, 'message' => 'That slot is in the past.'], 422);
        }

        // Doctor must be working at the new time.
        if (! $this->doctorWorkingAt($doctor, $slotStart)) {
            return response()->json(['success' => false, 'message' => 'The doctor is not working at that time.'], 422);
        }

        $duration = $doctor->consultation_duration_default ?? 15;

        try {
            $token = DB::transaction(function () use ($appointment, $doctor, $slotStart, $duration) {
                $clash = Appointment::where('doctor_id', $doctor->id)
                    ->where('slot_start', $slotStart)
                    ->where('id', '!=', $appointment->id)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->lockForUpdate()
                    ->exists();
                if ($clash) {
                    throw new \RuntimeException('SLOT_TAKEN');
                }

                $token = Appointment::generateToken($doctor->id, $doctor->department, $slotStart);

                $appointment->update([
                    'slot_start' => $slotStart,
                    'slot_end'   => $slotStart->copy()->addMinutes($duration),
                    'status'     => 'scheduled',
                    'notes'      => $token,
                ]);

                return $token;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'SLOT_TAKEN') {
                return response()->json(['success' => false, 'message' => 'That slot was just booked by someone else.'], 409);
            }
            throw $e;
        }

        return response()->json(['success' => true, 'data' => [
            'appointment_id' => $appointment->id,
            'token'          => $token,
            'doctor'         => $doctor->name,
            'date'           => $slotStart->toDateString(),
            'time'           => $slotStart->format('H:i'),
            'status'         => 'scheduled',
        ]]);
    }

    // ---------------------------------------------------------------
    // 6. Cancel an appointment
    // ---------------------------------------------------------------
    public function cancelAppointment(Request $request): JsonResponse
    {
        $v = $request->validate([
            'appointment_id' => 'required|uuid',
            'reason'         => 'nullable|string|max:255',
        ]);
        $hid = $this->hid();

        $appointment = Appointment::where('hospital_id', $hid)->find($v['appointment_id']);
        if (! $appointment) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }

        $status = is_object($appointment->status) ? $appointment->status->value : $appointment->status;
        if ($status === 'cancelled') {
            return response()->json(['success' => true, 'data' => ['appointment_id' => $appointment->id, 'status' => 'cancelled', 'already' => true]]);
        }
        if (in_array($status, ['completed', 'in_progress'], true)) {
            return response()->json(['success' => false, 'message' => "A {$status} appointment can't be cancelled."], 422);
        }

        $appointment->update(['status' => 'cancelled']);

        // Release the linked booked encounter, if any.
        if ($appointment->encounter_id) {
            Encounter::where('id', $appointment->encounter_id)
                ->whereIn('status', ['booked', 'scheduled'])
                ->update(['status' => 'cancelled']);
        }

        return response()->json(['success' => true, 'data' => [
            'appointment_id' => $appointment->id,
            'status'         => 'cancelled',
        ]]);
    }

    // ---------------------------------------------------------------
    // 7. Book a lab test / scan (creates lab/imaging/procedure orders)
    // ---------------------------------------------------------------
    public function bookLabTest(Request $request): JsonResponse
    {
        $v = $request->validate([
            'patient_id' => 'nullable|uuid',
            'phone'      => 'nullable|string|max:20',
            'tests'      => 'nullable|array|max:20',
            'tests.*'    => 'string|max:150',
            'test'       => 'nullable|string|max:150',
            'date'       => 'nullable|date_format:Y-m-d',
            'time'       => 'nullable|date_format:H:i',
            'priority'   => 'nullable|in:routine,urgent,stat',
            'notes'      => 'nullable|string|max:500',
        ]);
        $hid = $this->hid();

        $patient = null;
        if (! empty($v['patient_id'])) {
            $patient = Patient::where('hospital_id', $hid)->find($v['patient_id']);
        } elseif (! empty($v['phone'])) {
            $patient = $this->matchPatientByPhone($hid, $v['phone']);
        }
        if (! $patient) {
            return response()->json(['success' => false, 'message' => 'Patient not found — pass a valid patient_id or a registered phone.'], 404);
        }

        // Normalise the requested tests into a single list.
        $names = collect($v['tests'] ?? [])
            ->when(! empty($v['test']), fn ($c) => $c->push($v['test']))
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();
        if ($names->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Provide at least one test via "tests" (array) or "test".'], 422);
        }

        // Resolve each test against the catalogue (type + price); default to lab if unknown.
        $catalog = DB::table('available_tests')
            ->where(fn ($q) => $q->where('hospital_id', $hid)->orWhere('is_global', true))
            ->where('is_active', true)
            ->get(['name', 'type', 'price'])
            ->keyBy(fn ($t) => mb_strtolower($t->name));

        $unmatched = [];
        $byType = ['lab' => [], 'imaging' => [], 'procedure' => []];
        foreach ($names as $name) {
            $hit = $catalog[mb_strtolower($name)] ?? null;
            $type = $hit && in_array($hit->type, ['lab', 'imaging', 'procedure'], true) ? $hit->type : 'lab';
            if (! $hit) {
                $unmatched[] = $name;
            }
            $byType[$type][] = ['name' => $hit->name ?? $name, 'price' => (float) ($hit->price ?? 0)];
        }

        $priority = $v['priority'] ?? 'routine';
        $scheduledFor = null;
        if (! empty($v['date'])) {
            $scheduledFor = Carbon::parse($v['date'] . ' ' . ($v['time'] ?? '09:00'));
        }

        $cc = app(ChargeCapture::class);
        $orders = [];
        foreach ($byType as $type => $items) {
            if (empty($items)) {
                continue;
            }

            $order = Order::create([
                'id'             => Str::uuid()->toString(),
                'hospital_id'    => $hid,
                'patient_id'     => $patient->id,
                'type'           => $type,
                'status'         => 'ordered',
                'items'          => $items,
                'priority'       => $priority,
                'notes'          => $v['notes'] ?? null,
                'scheduled_for'  => $scheduledFor,
                'booking_source' => 'api',
            ]);

            // Capture the test charges into the ledger (non-fatal — a billing hiccup
            // must never block the booking).
            try {
                $cc->captureOrder($order, 'API');
            } catch (\Throwable $e) {
                \Log::warning('[API] lab charge capture failed: ' . $e->getMessage());
            }

            $orders[] = [
                'order_id'      => $order->id,
                'type'          => $type,
                'tests'         => collect($items)->pluck('name'),
                'priority'      => $priority,
                'scheduled_for' => optional($scheduledFor)->toDateTimeString(),
                'status'        => 'ordered',
            ];
        }

        return response()->json(['success' => true, 'data' => [
            'patient_id'      => $patient->id,
            'patient'         => $patient->name,
            'orders'          => $orders,
            'unmatched_tests' => $unmatched, // booked as generic lab tests — verify names
        ]], 201);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** Is the doctor rostered to work at the given moment? */
    private function doctorWorkingAt(Staff $doctor, Carbon $moment): bool
    {
        $blocks = $this->scheduleArray($doctor)[strtolower($moment->format('l'))] ?? [];
        foreach ($blocks as $block) {
            $bs = Carbon::parse($moment->toDateString() . ' ' . $block['start']);
            $be = Carbon::parse($moment->toDateString() . ' ' . $block['end']);
            if ($moment->gte($bs) && $moment->lt($be)) {
                return true;
            }
        }

        return false;
    }

    /** Common appointment shape for list responses. */
    private function appointmentPayload(Appointment $a): array
    {
        return [
            'appointment_id' => $a->id,
            'doctor'         => $a->doctor?->name,
            'department'     => $a->doctor?->department,
            'specialty'      => $a->doctor?->specialization,
            'date'           => optional($a->slot_start)->toDateString(),
            'time'           => optional($a->slot_start)->format('H:i'),
            'token'          => $a->notes,
            'status'         => is_object($a->status) ? $a->status->value : $a->status,
        ];
    }
}
