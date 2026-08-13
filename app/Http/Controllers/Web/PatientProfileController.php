<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Hospital;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Patient self-service profile completion.
 *
 * Registration deliberately captures only name + phone (asking more at booking
 * time causes drop-off / failed voice calls), so most profiles are thin. Instead
 * of interrogating the patient up front, we send them a signed link and let them
 * fill the rest in on their own phone, at their own pace.
 *
 * The link is a temporary signed URL — no login, but not guessable, and it
 * expires. Nothing clinical is exposed here: only the patient's own contact /
 * demographic details.
 */
class PatientProfileController extends Controller
{
    /** Fields the patient may fill in themselves. */
    public const FIELDS = [
        'email', 'gender', 'date_of_birth', 'blood_group', 'city',
        'address', 'emergency_contact_name', 'emergency_contact_phone', 'abha_number',
    ];

    /** How long a self-service link stays valid. */
    public const LINK_DAYS = 7;

    /**
     * How complete a patient's profile is, and what's still missing — so staff
     * can see at a glance who to chase.
     *
     * @return array{percent:int, filled:int, total:int, missing:array<int,string>}
     */
    public static function completeness(Patient $patient): array
    {
        $labels = [
            'email'                   => 'Email',
            'gender'                  => 'Gender',
            'date_of_birth'           => 'Date of birth',
            'blood_group'             => 'Blood group',
            'city'                    => 'City',
            'address'                 => 'Address',
            'emergency_contact_name'  => 'Emergency contact',
            'emergency_contact_phone' => 'Emergency phone',
            'abha_number'             => \App\Modules\Core\Services\RegionService::healthIdLabel(),
        ];

        $missing = [];
        foreach ($labels as $field => $label) {
            $val = $patient->{$field} ?? null;

            // An approximate age counts in place of a date of birth.
            if ($field === 'date_of_birth' && ! $val && $patient->age_approximate) {
                continue;
            }
            // gender is NOT NULL and defaults to 'unknown' — that's a placeholder,
            // not an answer, so treat it as still missing.
            if ($field === 'gender' && strtolower((string) $val) === 'unknown') {
                $val = null;
            }

            if ($val === null || $val === '') {
                $missing[] = $label;
            }
        }

        $total  = count($labels);
        $filled = $total - count($missing);

        return [
            'percent' => (int) round($filled / max(1, $total) * 100),
            'filled'  => $filled,
            'total'   => $total,
            'missing' => $missing,
        ];
    }

    /** Build the signed link we send to a patient. */
    public static function linkFor(Patient $patient): string
    {
        return URL::temporarySignedRoute(
            'patient-profile.edit',
            now()->addDays(self::LINK_DAYS),
            ['patient' => $patient->id]
        );
    }

    public function edit(Request $request, string $patient)
    {
        $p = Patient::withoutGlobalScopes()->findOrFail($patient);
        $hospital = Hospital::find($p->hospital_id);

        return view('public.patient-profile', [
            'patient'      => $p,
            'hospital'     => $hospital,
            'completeness' => self::completeness($p),
            'updateUrl'    => URL::temporarySignedRoute(
                'patient-profile.update',
                now()->addDays(self::LINK_DAYS),
                ['patient' => $p->id]
            ),
            'saved'        => (bool) $request->query('saved'),
        ]);
    }

    public function update(Request $request, string $patient)
    {
        $p = Patient::withoutGlobalScopes()->findOrFail($patient);

        $v = $request->validate([
            'email'                   => 'nullable|email|max:255',
            'gender'                  => 'nullable|in:male,female,other',
            'date_of_birth'           => 'nullable|date|before:today|after:1900-01-01',
            'blood_group'             => 'nullable|string|max:5',
            'city'                    => 'nullable|string|max:120',
            'address'                 => 'nullable|string|max:500',
            'emergency_contact_name'  => 'nullable|string|max:120',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'abha_number'             => 'nullable|string|max:20',
        ]);

        // Only overwrite with values the patient actually supplied — never blank
        // out details the hospital already holds.
        $update = [];
        foreach (self::FIELDS as $f) {
            $val = $v[$f] ?? null;
            if (is_string($val)) {
                $val = trim($val);
            }
            if ($val !== null && $val !== '') {
                $update[$f] = $f === 'abha_number' ? preg_replace('/[\s-]/', '', $val) : $val;
            }
        }

        if ($update !== []) {
            $p->forceFill($update)->save();
        }

        // Redirect back to a freshly SIGNED link with saved=1 baked into the
        // signature — appending it after signing would invalidate the signature
        // and trip the `signed` middleware (403).
        return redirect()->to(URL::temporarySignedRoute(
            'patient-profile.edit',
            now()->addDays(self::LINK_DAYS),
            ['patient' => $p->id, 'saved' => 1]
        ));
    }
}
