<?php

use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\HospitalSetupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MedOS API v1 Routes
|--------------------------------------------------------------------------
*/

// -----------------------------------------------------------------
// WhatsApp Webhook (unauthenticated - Meta/Gupshup verification)
// -----------------------------------------------------------------
Route::prefix('whatsapp')->group(function () {
    Route::get('webhook', [\App\Http\Controllers\Api\WhatsAppWebhookController::class, 'verify'])
        ->name('whatsapp.webhook.verify');
    Route::post('webhook', [\App\Http\Controllers\Api\WhatsAppWebhookController::class, 'handle'])
        ->name('whatsapp.webhook.handle');
});

// -----------------------------------------------------------------
// Voice AI Webhooks (unauthenticated — verified by provider signature)
// -----------------------------------------------------------------
Route::prefix('voice')->group(function () {
    Route::post('webhook', [\App\Http\Controllers\Web\VoiceCallController::class, 'handleWebhook'])
        ->name('voice.webhook');
    Route::post('ai-response', [\App\Http\Controllers\Web\VoiceCallController::class, 'processAiResponse'])
        ->name('voice.ai-response');
});

// -----------------------------------------------------------------
// Authentication routes
// -----------------------------------------------------------------
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');
    Route::post('hospital/setup', [HospitalSetupController::class, 'setup'])
        ->name('hospital.setup');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('profile.update');
        Route::post('refresh-token', [AuthController::class, 'refreshToken'])->name('refresh-token');
        Route::post('register', [AuthController::class, 'register'])->name('register');
    });
});

// -----------------------------------------------------------------
// Hospital directory — auth only (no hospital context needed). A super-admin
// (platform) token lists every hospital; a hospital token gets only its own.
// Lets the voice-AI vendor map an inbound phone line / DID → hospital_id.
// -----------------------------------------------------------------
Route::middleware('auth:sanctum')->get('hospitals', [\App\Http\Controllers\Api\IntegrationController::class, 'hospitals'])
    ->name('hospitals');

// -----------------------------------------------------------------
// Authenticated + Hospital-scoped routes
// -----------------------------------------------------------------
Route::middleware(['auth:sanctum', 'resolve.hospital'])->group(function () {

    // Patients
    Route::apiResource('patients', \App\Modules\Patient\Controllers\PatientController::class);
    Route::get('patients-search', [\App\Modules\Patient\Controllers\PatientController::class, 'search'])
        ->name('patients.search');
    Route::get('patients/{patient}/timeline', [\App\Modules\Patient\Controllers\PatientController::class, 'timeline'])
        ->name('patients.timeline');
    Route::get('patients/{patient}/encounters', [\App\Modules\Patient\Controllers\PatientController::class, 'encounters'])
        ->name('patients.encounters');

    // Appointments
    Route::apiResource('appointments', \App\Modules\Appointment\Controllers\AppointmentController::class);
    Route::post('appointments/{appointment}/check-in', [\App\Modules\Appointment\Controllers\AppointmentController::class, 'checkIn'])
        ->name('appointments.check-in');
    Route::post('appointments/{appointment}/cancel', [\App\Modules\Appointment\Controllers\AppointmentController::class, 'cancel'])
        ->name('appointments.cancel');

    // External integration (chatbot / CRM): customer lookup, doctor schedule, booking.
    // Rate-limited to blunt patient enumeration / booking abuse (per authenticated token).
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('customer', [\App\Http\Controllers\Api\IntegrationController::class, 'customer'])->name('customer');
        Route::get('doctors', [\App\Http\Controllers\Api\IntegrationController::class, 'doctors'])->name('doctors');
        Route::get('departments', [\App\Http\Controllers\Api\IntegrationController::class, 'departments'])->name('departments');
        Route::get('doctor-schedule', [\App\Http\Controllers\Api\IntegrationController::class, 'doctorSchedule'])->name('doctor-schedule');
        Route::get('my-appointments', [\App\Http\Controllers\Api\IntegrationController::class, 'myAppointments'])->name('my-appointments');
        Route::post('register-patient', [\App\Http\Controllers\Api\IntegrationController::class, 'registerPatient'])->name('register-patient');
        Route::post('book-appointment', [\App\Http\Controllers\Api\IntegrationController::class, 'bookAppointment'])->name('book-appointment');
        Route::post('reschedule-appointment', [\App\Http\Controllers\Api\IntegrationController::class, 'rescheduleAppointment'])->name('reschedule-appointment');
        Route::post('cancel-appointment', [\App\Http\Controllers\Api\IntegrationController::class, 'cancelAppointment'])->name('cancel-appointment');
        Route::post('book-lab-test', [\App\Http\Controllers\Api\IntegrationController::class, 'bookLabTest'])->name('book-lab-test');
    });

    // Queue
    Route::prefix('queue')->name('queue.')->group(function () {
        Route::get('doctor/{doctorId}', [\App\Modules\Queue\Controllers\QueueController::class, 'doctorQueue'])->name('doctor');
        Route::post('doctor/{doctorId}/call-next', [\App\Modules\Queue\Controllers\QueueController::class, 'callNext'])->name('call-next');
        Route::get('patient/{patientId}/status', [\App\Modules\Queue\Controllers\QueueController::class, 'patientStatus'])->name('patient-status');
    });

    // ABHA
    Route::prefix('abha')->name('abha.')->group(function () {
        Route::post('verify', [\App\Modules\ABHA\Controllers\ABHAController::class, 'verify'])->name('verify');
        Route::post('link', [\App\Modules\ABHA\Controllers\ABHAController::class, 'link'])->name('link');
        Route::post('create', [\App\Modules\ABHA\Controllers\ABHAController::class, 'create'])->name('create');
        Route::get('records/{patientId}', [\App\Modules\ABHA\Controllers\ABHAController::class, 'records'])->name('records');
        Route::get('consents/{patientId}', [\App\Modules\ABHA\Controllers\ABHAController::class, 'consents'])->name('consents');
        Route::post('consents/grant', [\App\Modules\ABHA\Controllers\ABHAController::class, 'grantConsent'])->name('consents.grant');
        Route::post('consents/{consentId}/revoke', [\App\Modules\ABHA\Controllers\ABHAController::class, 'revokeConsent'])->name('consents.revoke');
    });

    // Insurance
    Route::prefix('insurance')->name('insurance.')->group(function () {
        Route::post('verify', [\App\Modules\Insurance\Controllers\InsuranceController::class, 'verify'])->name('verify');
        Route::post('pre-auth', [\App\Modules\Insurance\Controllers\InsuranceController::class, 'preAuth'])->name('pre-auth');
        Route::post('claim', [\App\Modules\Insurance\Controllers\InsuranceController::class, 'submitClaim'])->name('claim.submit');
        Route::get('claim/{transactionId}/status', [\App\Modules\Insurance\Controllers\InsuranceController::class, 'claimStatus'])->name('claim.status');
        Route::get('transactions', [\App\Modules\Insurance\Controllers\InsuranceController::class, 'transactions'])->name('transactions');
    });

    // Billing — read endpoints require billing:read, write endpoints require billing:write
    // (both satisfied by the billing:* wildcard held by admin / billing_staff tokens).
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('{billId}', [\App\Modules\Billing\Controllers\BillingController::class, 'show'])->middleware('ability:billing:read')->name('show');
        Route::post('generate', [\App\Modules\Billing\Controllers\BillingController::class, 'generate'])->middleware('ability:billing:write')->name('generate');
        Route::post('{billId}/charge', [\App\Modules\Billing\Controllers\BillingController::class, 'addCharge'])->middleware('ability:billing:write')->name('add-charge');
        Route::post('{billId}/pay', [\App\Modules\Billing\Controllers\BillingController::class, 'processPayment'])->middleware('ability:billing:write')->name('pay');
        Route::get('patient/{patientId}', [\App\Modules\Billing\Controllers\BillingController::class, 'patientBills'])->middleware('ability:billing:read')->name('patient');
        Route::get('revenue/summary', [\App\Modules\Billing\Controllers\BillingController::class, 'revenueSummary'])->middleware('ability:billing:read')->name('revenue');
    });

    // -----------------------------------------------------------------
    // Doctor-specific routes
    // -----------------------------------------------------------------
    Route::prefix('doctor')->middleware('role:doctor,hospital_admin,super_admin')->name('doctor.')->group(function () {
        Route::get('dashboard', [\App\Modules\DoctorAssist\Controllers\DoctorDashboardController::class, 'overview'])->name('dashboard');
        Route::get('queue', [\App\Modules\DoctorAssist\Controllers\DoctorDashboardController::class, 'currentQueue'])->name('queue');
        Route::get('briefing/{encounterId}', [\App\Modules\DoctorAssist\Controllers\DoctorDashboardController::class, 'patientBriefing'])->name('briefing');
        Route::post('encounters/{encounterId}/complete', [\App\Modules\DoctorAssist\Controllers\DoctorDashboardController::class, 'completeConsultation'])->name('encounters.complete');
        Route::post('encounters/{encounterId}/orders', [\App\Modules\DoctorAssist\Controllers\DoctorDashboardController::class, 'addOrders'])->name('encounters.orders');
    });

    // -----------------------------------------------------------------
    // Hospital Admin routes
    // -----------------------------------------------------------------
    Route::prefix('admin')->middleware('role:hospital_admin,super_admin')->name('admin.')->group(function () {
        Route::get('dashboard', [\App\Modules\Admin\Controllers\AdminDashboardController::class, 'overview'])->name('dashboard');
        Route::get('patient-flow', [\App\Modules\Admin\Controllers\AdminDashboardController::class, 'patientFlow'])->name('patient-flow');
        Route::get('doctor-performance', [\App\Modules\Admin\Controllers\AdminDashboardController::class, 'doctorPerformance'])->name('doctor-performance');
        Route::get('ai-performance', [\App\Modules\Admin\Controllers\AdminDashboardController::class, 'aiPerformance'])->name('ai-performance');
        Route::get('configuration', [\App\Modules\Admin\Controllers\AdminDashboardController::class, 'configuration'])->name('configuration');
        Route::put('configuration', [\App\Modules\Admin\Controllers\AdminDashboardController::class, 'updateConfiguration'])->name('configuration.update');
    });

    // -----------------------------------------------------------------
    // Analytics routes
    // -----------------------------------------------------------------
    Route::prefix('analytics')->middleware('role:hospital_admin,super_admin')->name('analytics.')->group(function () {
        Route::get('dashboard', [\App\Modules\Analytics\Controllers\AnalyticsController::class, 'dashboard'])->name('dashboard');
        Route::get('revenue', [\App\Modules\Analytics\Controllers\AnalyticsController::class, 'revenue'])->name('revenue');
        Route::get('insurance', [\App\Modules\Analytics\Controllers\AnalyticsController::class, 'insurance'])->name('insurance');
        Route::get('export', [\App\Modules\Analytics\Controllers\AnalyticsController::class, 'export'])->name('export');
    });
});
