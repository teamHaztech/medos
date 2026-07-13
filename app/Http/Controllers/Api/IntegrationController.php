<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Core\Models\Staff;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * External integration endpoints (chatbot / CRM):
 *   GET  /api/v1/customer?phone=
 *   GET  /api/v1/doctor-schedule?name=&days=
 *   POST /api/v1/book-appointment
 * All are Sanctum-authenticated and hospital-scoped by the token's user.
 */
class IntegrationController extends Controller
{
    private function hid(): string
    {
        return Auth::user()->hospital_id;
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
}
