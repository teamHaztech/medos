<?php

use App\Http\Controllers\Web\AdminWebController;
use App\Http\Controllers\Web\DoctorWebController;
use App\Http\Controllers\Web\KioskController;
use App\Http\Controllers\Web\WebAuthController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------
// Auth Routes
// ---------------------------------------------------------------

Route::get('login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('login', [WebAuthController::class, 'login']);
Route::post('logout', [WebAuthController::class, 'logout'])->name('logout');

// ---------------------------------------------------------------
// Admin Dashboard (auth required)
// ---------------------------------------------------------------

Route::middleware('auth')->prefix('admin')->name('web.admin.')->group(function () {
    // Viewable by all staff
    Route::get('/', [AdminWebController::class, 'dashboard'])->name('dashboard');
    Route::get('patients', [AdminWebController::class, 'patients'])->name('patients');
    Route::post('patients', [AdminWebController::class, 'storePatient'])->name('patients.store');
    Route::get('patients/{id}', [AdminWebController::class, 'patientDetail'])->name('patients.show');
    Route::put('patients/{id}', [AdminWebController::class, 'updatePatient'])->name('patients.update');
    Route::delete('patients/{id}', [AdminWebController::class, 'deletePatient'])->name('patients.delete');
    Route::get('appointments', [AdminWebController::class, 'appointments'])->name('appointments');
    Route::get('staff', [AdminWebController::class, 'staff'])->name('staff');
    Route::post('staff', [AdminWebController::class, 'storeStaff'])->name('staff.store');
    Route::put('staff/{id}', [AdminWebController::class, 'updateStaff'])->name('staff.update');
    Route::delete('staff/{id}', [AdminWebController::class, 'deleteStaff'])->name('staff.delete');
    Route::post('staff/{id}/activate', [AdminWebController::class, 'activateStaff'])->name('staff.activate');
    Route::post('appointments/{id}/check-in', [AdminWebController::class, 'checkInAppointment'])->name('appointments.checkin');
    Route::post('appointments/{id}/cancel', [AdminWebController::class, 'cancelAppointment'])->name('appointments.cancel');
    Route::get('analytics', [AdminWebController::class, 'analytics'])->name('analytics');

    // Admin-only: manage slots, tests, medicines, settings
    Route::middleware('admin')->group(function () {
        Route::get('settings', [AdminWebController::class, 'settings'])->name('settings');
        Route::post('settings', [AdminWebController::class, 'saveSettings'])->name('settings.save');
        Route::post('settings/hours', [AdminWebController::class, 'saveOperatingHours'])->name('settings.hours');
        Route::get('slots', [AdminWebController::class, 'slots'])->name('slots');
        Route::post('slots/{staffId}', [AdminWebController::class, 'updateSlots'])->name('slots.update');
        Route::get('tests', [AdminWebController::class, 'tests'])->name('tests');
        Route::post('tests', [AdminWebController::class, 'storeTest'])->name('tests.store');
        Route::put('tests/{id}', [AdminWebController::class, 'updateTest'])->name('tests.update');
        Route::delete('tests/{id}', [AdminWebController::class, 'deleteTest'])->name('tests.delete');
        Route::get('medicines', [AdminWebController::class, 'medicines'])->name('medicines');
        Route::post('medicines', [AdminWebController::class, 'storeMedicine'])->name('medicines.store');
        Route::put('medicines/{id}', [AdminWebController::class, 'updateMedicine'])->name('medicines.update');
        Route::delete('medicines/{id}', [AdminWebController::class, 'deleteMedicine'])->name('medicines.delete');
    });
});

// ---------------------------------------------------------------
// Doctor Dashboard (auth required)
// ---------------------------------------------------------------

Route::middleware('auth')->prefix('doctor')->name('web.doctor.')->group(function () {
    Route::get('/', [DoctorWebController::class, 'dashboard'])->name('dashboard');
    Route::get('stats', [DoctorWebController::class, 'stats'])->name('stats');
    Route::get('my-patients', [DoctorWebController::class, 'myPatients'])->name('patients');
    Route::get('my-patients/{id}', [DoctorWebController::class, 'patientDetail'])->name('patients.show');
    Route::get('my-appointments', [DoctorWebController::class, 'myAppointments'])->name('appointments');
    Route::get('history', [DoctorWebController::class, 'consultationHistory'])->name('history');
    Route::get('packages', [DoctorWebController::class, 'packages'])->name('packages');
    Route::post('packages', [DoctorWebController::class, 'storePackage'])->name('packages.store');
    Route::post('packages/{id}/update', [DoctorWebController::class, 'updatePackage'])->name('packages.update');
    Route::post('packages/{id}/toggle', [DoctorWebController::class, 'togglePackage'])->name('packages.toggle');
    Route::post('packages/{id}/delete', [DoctorWebController::class, 'deletePackage'])->name('packages.delete');
    Route::get('referrals', [DoctorWebController::class, 'referrals'])->name('referrals');
    Route::post('referrals/{id}/accept', [DoctorWebController::class, 'acceptReferral'])->name('referrals.accept');
    Route::post('referrals/{id}/decline', [DoctorWebController::class, 'declineReferral'])->name('referrals.decline');
    Route::post('complete/{appointmentId}', [DoctorWebController::class, 'completeConsultation'])->name('complete');
    Route::post('call-next/{appointmentId}', [DoctorWebController::class, 'callNextPatient'])->name('call-next');
    Route::post('refer-lab/{appointmentId}', [DoctorWebController::class, 'referToLab'])->name('refer-lab');
    Route::post('no-show/{appointmentId}', [DoctorWebController::class, 'markNoShow'])->name('no-show');
    Route::post('skip/{appointmentId}', [DoctorWebController::class, 'skipPatient'])->name('skip');
    Route::get('queue-json', [DoctorWebController::class, 'queueJson'])->name('queue-json');
});

// ---------------------------------------------------------------
// Lab Module
// ---------------------------------------------------------------

Route::middleware('auth')->prefix('lab')->name('web.lab.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\LabController::class, 'dashboard'])->name('dashboard');
    Route::get('slots', [\App\Http\Controllers\Web\LabController::class, 'slots'])->name('slots');
    Route::post('slots', [\App\Http\Controllers\Web\LabController::class, 'saveSlots'])->name('slots.save');
    Route::post('{id}/collect', [\App\Http\Controllers\Web\LabController::class, 'collectSample'])->name('collect');
    Route::post('{id}/status', [\App\Http\Controllers\Web\LabController::class, 'updateLabStatus'])->name('status');
    Route::get('{id}/results', [\App\Http\Controllers\Web\LabController::class, 'showResults'])->name('results');
    Route::post('{id}/results', [\App\Http\Controllers\Web\LabController::class, 'saveResults'])->name('results.save');
    Route::post('{id}/verify', [\App\Http\Controllers\Web\LabController::class, 'verify'])->name('verify');
    Route::post('{id}/acknowledge-critical', [\App\Http\Controllers\Web\LabController::class, 'acknowledgeCritical'])->name('acknowledge-critical');
});

// ---------------------------------------------------------------
// Pharmacy Module
// ---------------------------------------------------------------

Route::middleware('auth')->prefix('pharmacy')->name('web.pharmacy.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\PharmacyController::class, 'dashboard'])->name('dashboard');
    Route::post('{id}/dispense', [\App\Http\Controllers\Web\PharmacyController::class, 'dispense'])->name('dispense');
    Route::get('stock', [\App\Http\Controllers\Web\PharmacyController::class, 'stock'])->name('stock');
    Route::post('stock', [\App\Http\Controllers\Web\PharmacyController::class, 'addStock'])->name('stock.store');
    Route::put('stock/{id}', [\App\Http\Controllers\Web\PharmacyController::class, 'updateStock'])->name('stock.update');
});

// ---------------------------------------------------------------
// Billing Module
// ---------------------------------------------------------------

Route::middleware('auth')->prefix('billing')->name('web.billing.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\BillingWebController::class, 'index'])->name('index');
    Route::get('create/{encounterId}', [\App\Http\Controllers\Web\BillingWebController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\Web\BillingWebController::class, 'store'])->name('store');
    Route::get('{id}', [\App\Http\Controllers\Web\BillingWebController::class, 'show'])->name('show');
    Route::post('{id}/pay', [\App\Http\Controllers\Web\BillingWebController::class, 'recordPayment'])->name('pay');
    Route::get('{id}/print', [\App\Http\Controllers\Web\BillingWebController::class, 'printReceipt'])->name('print');
});

// Print views
Route::middleware('auth')->group(function () {
    Route::get('prescriptions/{encounterId}/print', [\App\Http\Controllers\Web\BillingWebController::class, 'printPrescription'])->name('prescription.print');
    Route::get('encounters/{encounterId}/discharge', [\App\Http\Controllers\Web\BillingWebController::class, 'dischargeSummary'])->name('discharge.summary');
});

// ---------------------------------------------------------------
// Kiosk (no auth required)
// ---------------------------------------------------------------

Route::prefix('kiosk')->name('kiosk.')->group(function () {
    Route::get('/', [KioskController::class, 'index'])->name('index');
    Route::post('select-hospital', [KioskController::class, 'selectHospital'])->name('select-hospital');
    Route::get('checkin', [KioskController::class, 'checkin'])->name('checkin');
    Route::post('checkin', [KioskController::class, 'processCheckin'])->name('checkin.process');
    Route::get('register', [KioskController::class, 'register'])->name('register');
    Route::get('lab', [KioskController::class, 'labBooking'])->name('lab');
    Route::post('lab', [KioskController::class, 'processLabBooking'])->name('lab.process');
    Route::get('check-phone', [KioskController::class, 'checkPhone'])->name('check-phone');
    Route::get('doctors', [KioskController::class, 'doctors'])->name('doctors');
    Route::get('match-doctors', [KioskController::class, 'matchDoctors'])->name('match-doctors');
    Route::get('verify-abha', [KioskController::class, 'verifyAbha'])->name('verify-abha');
    Route::post('register', [KioskController::class, 'processRegister'])->name('register.process');
    Route::get('queue-display', [KioskController::class, 'queueDisplay'])->name('queue-display');
    Route::get('room/{doctorId}', [KioskController::class, 'roomDisplay'])->name('room-display');
    Route::get('q/{doctorId}', [KioskController::class, 'patientQueueView'])->name('queue-live');
});

// ---------------------------------------------------------------
// Test Routes (dev only)
// ---------------------------------------------------------------

Route::get('/test/detect-language', function (\Illuminate\Http\Request $request) {
    $text = $request->get('text', 'Hello');
    $detector = app(\App\Modules\Multilingual\Services\LanguageDetector::class);
    return response()->json($detector->detect($text));
});

Route::get('/test/medical-dict', function (\Illuminate\Http\Request $request) {
    $term = $request->get('term', 'fever');
    $lang = $request->get('lang', 'hi');
    $dict = new \App\Modules\Multilingual\Dictionaries\MedicalDictionary();
    return response()->json([
        'lookup' => $dict->lookup($term, $lang),
        'reverse' => $dict->reverseLookup($term),
    ]);
});

// ---------------------------------------------------------------
// Root redirect
// ---------------------------------------------------------------

// ---------------------------------------------------------------
// API-like JSON endpoints (for Alpine.js fetch)
// ---------------------------------------------------------------

Route::prefix('ajax')->middleware('auth')->group(function () {
    Route::get('medicines', function (\Illuminate\Http\Request $request) {
        $q = $request->get('q', '');
        return \Illuminate\Support\Facades\DB::table('medicines')
            ->where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('generic_name', 'like', "%{$q}%")
                      ->orWhere('category', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'generic_name', 'category', 'form', 'default_dosage', 'default_frequency', 'default_duration', 'default_timing']);
    });

    Route::get('tests', function (\Illuminate\Http\Request $request) {
        $type = $request->get('type'); // lab, imaging, procedure
        return \Illuminate\Support\Facades\DB::table('available_tests')
            ->where('is_active', true)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('type')->orderBy('name')
            ->get(['id', 'name', 'code', 'type', 'category', 'price', 'turnaround_time', 'instructions']);
    });

    Route::get('doctor-slots/{doctorId}', function (string $doctorId) {
        $doctor = \Illuminate\Support\Facades\DB::table('staff')->where('id', $doctorId)->first();
        if (!$doctor) return response()->json([]);

        $schedule = json_decode($doctor->schedule ?? '{}', true);
        $duration = $doctor->consultation_duration_default ?? 15;
        $days = [];

        for ($d = 0; $d < 14; $d++) {
            $date = now()->addDays($d);
            $dayName = strtolower($date->format('l'));
            $blocks = $schedule[$dayName] ?? [];
            if (empty($blocks)) continue;

            // Get booked appointment times for this day
            $booked = \Illuminate\Support\Facades\DB::table('appointments')
                ->where('doctor_id', $doctorId)
                ->whereDate('slot_start', $date->toDateString())
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->pluck('slot_start')
                ->map(fn ($s) => \Carbon\Carbon::parse($s)->format('H:i'))
                ->toArray();

            // Generate individual time slots
            $timeSlots = [];
            foreach ($blocks as $block) {
                $start = \Carbon\Carbon::parse($date->toDateString() . ' ' . $block['start']);
                $end = \Carbon\Carbon::parse($date->toDateString() . ' ' . $block['end']);
                while ($start->copy()->addMinutes($duration)->lte($end)) {
                    $timeStr = $start->format('H:i');
                    $isBooked = in_array($timeStr, $booked);
                    $isPast = $d === 0 && $start->lt(now());
                    $timeSlots[] = [
                        'time'      => $timeStr,
                        'display'   => $start->format('g:i A'),
                        'available' => !$isBooked && !$isPast,
                        'booked'    => $isBooked,
                        'past'      => $isPast,
                    ];
                    $start->addMinutes($duration);
                }
            }

            $available = collect($timeSlots)->where('available', true)->count();

            $days[] = [
                'date'       => $date->toDateString(),
                'day'        => $date->format('D'),
                'dayFull'    => $date->format('l'),
                'dateFmt'    => $date->format('M d'),
                'is_today'   => $d === 0,
                'slots'      => $timeSlots,
                'available'  => $available,
                'total'      => count($timeSlots),
            ];
        }

        return response()->json([
            'doctor'   => ['id' => $doctor->id, 'name' => $doctor->name, 'department' => $doctor->department],
            'duration' => $duration,
            'days'     => $days,
        ]);
    });
});

// ---------------------------------------------------------------
// WhatsApp Bot Simulator (dev/demo)
// ---------------------------------------------------------------
Route::get('chat', function (\Illuminate\Http\Request $request) {
    $hospital = null;
    if ($request->has('hospital_id')) {
        $hospital = \App\Modules\Core\Models\Hospital::find($request->hospital_id);
    } elseif (auth()->check()) {
        $hospital = auth()->user()->hospital;
    }
    if (!$hospital) {
        $hospital = \App\Modules\Core\Models\Hospital::where('is_active', true)->first();
    }
    return view('chat.index', ['hospital' => $hospital]);
})->name('chat');
Route::post('chat/send', [\App\Http\Controllers\Web\ChatController::class, 'send'])->name('chat.send');

// ---------------------------------------------------------------
// Super Admin (manage hospitals, system-wide config)
// ---------------------------------------------------------------
Route::middleware(['auth', 'super_admin'])->prefix('super-admin')->name('web.superadmin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\SuperAdminController::class, 'index'])->name('index');
    Route::get('hospitals/create', [\App\Http\Controllers\Web\SuperAdminController::class, 'createHospital'])->name('hospitals.create');
    Route::post('hospitals', [\App\Http\Controllers\Web\SuperAdminController::class, 'storeHospital'])->name('hospitals.store');
    Route::get('hospitals/{id}', [\App\Http\Controllers\Web\SuperAdminController::class, 'hospitalDetail'])->name('hospitals.show');
    Route::get('hospitals/{id}/edit', [\App\Http\Controllers\Web\SuperAdminController::class, 'editHospital'])->name('hospitals.edit');
    Route::put('hospitals/{id}', [\App\Http\Controllers\Web\SuperAdminController::class, 'updateHospital'])->name('hospitals.update');
    Route::delete('hospitals/{id}', [\App\Http\Controllers\Web\SuperAdminController::class, 'deleteHospital'])->name('hospitals.delete');
    Route::post('hospitals/{id}/staff', [\App\Http\Controllers\Web\SuperAdminController::class, 'addStaffToHospital'])->name('hospitals.staff.add');
    Route::delete('hospitals/{hospitalId}/staff/{staffId}', [\App\Http\Controllers\Web\SuperAdminController::class, 'removeStaffFromHospital'])->name('hospitals.staff.remove');
    Route::post('hospitals/{id}/admin', [\App\Http\Controllers\Web\SuperAdminController::class, 'addAdminToHospital'])->name('hospitals.admin.add');
});

// Hospital switcher (super admin only)
Route::post('switch-hospital', function (\Illuminate\Http\Request $request) {
    $hospitalId = $request->input('hospital_id');
    $hospital = \App\Modules\Core\Models\Hospital::findOrFail($hospitalId);
    $user = auth()->user();
    if ($user) {
        $user->update(['hospital_id' => $hospital->id]);
        \App\Modules\Core\Services\RegionService::reset();
    }
    return redirect()->back()->with('success', 'Switched to ' . $hospital->name);
})->middleware(['auth', 'super_admin'])->name('switch-hospital');

Route::get('/', function () {
    $role = auth()->user()?->role;
    $roleValue = is_object($role) ? $role->value : ($role ?? '');
    return match ($roleValue) {
        'super_admin' => redirect()->route('web.superadmin.index'),
        'doctor' => redirect()->route('web.doctor.dashboard'),
        'lab_tech' => redirect()->route('web.lab.dashboard'),
        'pharmacist' => redirect()->route('web.pharmacy.dashboard'),
        default => redirect()->route('web.admin.dashboard'),
    };
})->middleware('auth');
