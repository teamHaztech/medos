<?php

namespace App\Modules\Auth\Controllers;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class HospitalSetupController extends Controller
{
    /**
     * Create a new hospital and its first admin user in one step.
     */
    public function setup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hospital_name'    => ['required', 'string', 'max:255'],
            'hospital_slug'    => ['required', 'string', 'max:100', 'unique:hospitals,slug', 'alpha_dash'],
            'hospital_city'    => ['required', 'string', 'max:100'],
            'hospital_country' => ['required', 'string', 'max:100'],
            'admin_name'       => ['required', 'string', 'max:255'],
            'admin_email'      => ['required', 'email', 'unique:users,email'],
            'admin_password'   => ['required', 'string', Password::min(8)],
        ]);

        $result = DB::transaction(function () use ($validated) {
            // 1. Create the hospital
            $hospital = Hospital::create([
                'name'            => $validated['hospital_name'],
                'slug'            => $validated['hospital_slug'],
                'city'            => $validated['hospital_city'],
                'country'         => $validated['hospital_country'],
                'is_active'       => true,
                'modules_enabled' => [
                    'patients', 'appointments', 'encounters', 'billing',
                    'insurance', 'queue', 'analytics', 'whatsapp',
                ],
            ]);

            // 2. Create the admin user
            $user = User::create([
                'name'        => $validated['admin_name'],
                'email'       => $validated['admin_email'],
                'password'    => $validated['admin_password'],
                'role'        => UserRole::HospitalAdmin->value,
                'hospital_id' => $hospital->id,
                'is_active'   => true,
            ]);

            // 3. Create a staff record linked to the user
            $nameParts = explode(' ', $validated['admin_name'], 2);
            $staff = Staff::create([
                'hospital_id' => $hospital->id,
                'user_id'     => $user->id,
                'role'        => UserRole::HospitalAdmin->value,
                'first_name'  => $nameParts[0],
                'last_name'   => $nameParts[1] ?? '',
                'email'       => $validated['admin_email'],
                'is_active'   => true,
            ]);

            // Link staff back to user
            $user->update(['staff_id' => $staff->id]);

            // 4. Issue token
            $abilities = [
                'patients:*', 'appointments:*', 'encounters:*', 'billing:*',
                'insurance:*', 'queue:*', 'analytics:*', 'admin:*', 'staff:*',
            ];
            $token = $user->createToken('auth-token', $abilities)->plainTextToken;

            return [
                'token'    => $token,
                'user'     => $user->load('staff'),
                'hospital' => $hospital,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => 'Hospital setup completed successfully.',
        ], 201);
    }
}
