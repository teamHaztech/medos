<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\AccountActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if (Auth::attempt($credentials, $remember)) {
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

        // Record the failed attempt for the security log.
        AccountActivity::record(null, 'failed_login', $request, $credentials['email']);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
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
}
