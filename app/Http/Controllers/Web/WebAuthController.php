<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\AccountActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class WebAuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            $role = is_object(Auth::user()->role) ? Auth::user()->role->value : Auth::user()->role;
            return redirect()->route($this->landingRoute($role));
        }

        return view('auth.login');
    }

    /**
     * Where each role/department lands after login (their day-to-day work area).
     */
    private function landingRoute(?string $role): string
    {
        return match ($role) {
            'doctor'        => 'web.doctor.dashboard',
            'lab_tech'      => 'web.lab.dashboard',
            'pharmacist'    => 'web.pharmacy.dashboard',
            'billing_staff' => 'web.billing.index',
            'receptionist'  => 'web.admin.appointments',
            'nurse'         => 'web.ip.dashboard',
            'dentist'       => 'web.dental.index',
            'dietitian'     => 'web.dietary.index',
            default         => 'web.admin.dashboard', // super_admin, hospital_admin
        };
    }

    /**
     * Handle a login attempt.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        // Brute-force protection: lock out after 5 failed attempts per email+IP
        // for 15 minutes. Backed by the cache (same store as the throttle
        // middleware already used on the API login).
        $key = $this->loginThrottleKey($request);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            AccountActivity::record(null, 'failed_login', $request, $credentials['email'],
                'Locked out — too many failed attempts');

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($key);
            $request->session()->regenerate();

            $user = Auth::user();
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();
            AccountActivity::record($user, 'login', $request);

            $role = is_object($user->role) ? $user->role->value : $user->role;

            return redirect()->intended(route($this->landingRoute($role)));
        }

        // Count this failed attempt toward the lockout, and log it.
        RateLimiter::hit($key, 900);
        AccountActivity::record(null, 'failed_login', $request, $credentials['email']);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /** Per-email + per-IP throttle key for login attempts. */
    private function loginThrottleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')) . '|' . $request->ip());
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request)
    {
        AccountActivity::record(Auth::user(), 'logout', $request);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Show the change-password form. Used both voluntarily and by the forced
     * flow (EnsurePasswordChanged middleware) for admin-provisioned accounts.
     */
    public function showChangePassword(Request $request)
    {
        return view('auth.change-password', [
            'forced' => (bool) ($request->user()->must_change_password ?? false),
        ]);
    }

    /** Handle a password change: verify current password, enforce policy, save. */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $v = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        if (! Hash::check($v['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }
        if (Hash::check($v['password'], $user->password)) {
            return back()->withErrors(['password' => 'Please choose a password different from your current one.']);
        }

        $user->forceFill([
            'password'             => Hash::make($v['password']),
            'password_changed_at'  => now(),
            'must_change_password' => false,
        ])->save();

        AccountActivity::record($user, 'update', $request, null, 'Changed own password');

        $role = is_object($user->role) ? $user->role->value : $user->role;

        return redirect()->route($this->landingRoute($role))->with('success', 'Password updated successfully.');
    }
}
