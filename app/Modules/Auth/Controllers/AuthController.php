<?php

namespace App\Modules\Auth\Controllers;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // -----------------------------------------------------------------
    // Token ability map per role
    // -----------------------------------------------------------------

    private const ROLE_ABILITIES = [
        'super_admin'    => ['*'],
        'hospital_admin' => [
            'patients:*', 'appointments:*', 'encounters:*', 'billing:*',
            'insurance:*', 'queue:*', 'analytics:*', 'admin:*', 'staff:*',
        ],
        'doctor'         => [
            'patients:read', 'appointments:read', 'encounters:*',
            'queue:*', 'doctor:*',
        ],
        'nurse'          => [
            'patients:read', 'appointments:read', 'queue:*',
        ],
        'receptionist'   => [
            'patients:*', 'appointments:*', 'queue:*', 'billing:read',
        ],
        'billing_staff'  => [
            'patients:read', 'billing:*', 'insurance:*',
        ],
    ];

    // =================================================================
    // login
    // =================================================================

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Account is deactivated. Contact your administrator.',
            ], 403);
        }

        $abilities = self::ROLE_ABILITIES[$user->role->value] ?? [];
        // Login-issued tokens expire (default 24h) so a leaked session token
        // stops working. Long-lived integration keys are issued separately at
        // Admin → API Keys and are NOT time-boxed. TTL <= 0 disables expiry.
        $ttlHours = (int) config('medos.api_token_ttl_hours', 24);
        $token = $user->createToken('auth-token', $abilities, $ttlHours > 0 ? now()->addHours($ttlHours) : null)->plainTextToken;

        $user->load(['hospital', 'staff']);

        return response()->json([
            'success' => true,
            'data'    => [
                'token'    => $token,
                'user'     => $user,
                'hospital' => $user->hospital,
            ],
            'message' => 'Login successful.',
        ]);
    }

    // =================================================================
    // register (admin-only)
    // =================================================================

    public function register(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (! in_array($authUser->role, [UserRole::SuperAdmin, UserRole::HospitalAdmin])) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Only administrators can register new users.',
            ], 403);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'unique:users,email'],
            'password'    => ['required', 'string', Password::min(8)],
            'role'        => ['required', 'string', 'in:' . implode(',', array_column(UserRole::cases(), 'value'))],
            'hospital_id' => ['required', 'uuid', 'exists:hospitals,id'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'staff_id'    => ['nullable', 'uuid', 'exists:staff,id'],
        ]);

        // Hospital admins can only create users within their own hospital
        if ($authUser->role === UserRole::HospitalAdmin && $validated['hospital_id'] !== $authUser->hospital_id) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'You can only register users for your own hospital.',
            ], 403);
        }

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => $validated['password'],
            'role'        => $validated['role'],
            'hospital_id' => $validated['hospital_id'],
            'phone'       => $validated['phone'] ?? null,
            'staff_id'    => $validated['staff_id'] ?? null,
            'is_active'   => true,
        ]);

        // If a staff_id was provided, link the staff record back to the user
        if ($user->staff_id) {
            Staff::where('id', $user->staff_id)->update(['user_id' => $user->id]);
        }

        $user->load(['hospital', 'staff']);

        return response()->json([
            'success' => true,
            'data'    => $user,
            'message' => 'User registered successfully.',
        ], 201);
    }

    // =================================================================
    // logout
    // =================================================================

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Logged out successfully.',
        ]);
    }

    // =================================================================
    // me
    // =================================================================

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['hospital', 'staff']);

        return response()->json([
            'success' => true,
            'data'    => $user,
            'message' => 'Authenticated user retrieved.',
        ]);
    }

    // =================================================================
    // updateProfile
    // =================================================================

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'string', Password::min(8), 'confirmed'],
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $user->fresh(),
            'message' => 'Profile updated successfully.',
        ]);
    }

    // =================================================================
    // refreshToken
    // =================================================================

    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke the current token
        $user->currentAccessToken()->delete();

        // Issue a new one with the same role abilities
        $abilities = self::ROLE_ABILITIES[$user->role->value] ?? [];
        // Login-issued tokens expire (default 24h) so a leaked session token
        // stops working. Long-lived integration keys are issued separately at
        // Admin → API Keys and are NOT time-boxed. TTL <= 0 disables expiry.
        $ttlHours = (int) config('medos.api_token_ttl_hours', 24);
        $token = $user->createToken('auth-token', $abilities, $ttlHours > 0 ? now()->addHours($ttlHours) : null)->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => ['token' => $token],
            'message' => 'Token refreshed successfully.',
        ]);
    }
}
