<?php

use App\Http\Controllers\Web\AdminWebController;
use App\Http\Controllers\Web\AssetController;
use App\Http\Controllers\Web\DoctorWebController;
use App\Http\Controllers\Web\InpatientController;
use App\Http\Controllers\Web\KioskController;
use App\Http\Controllers\Web\ServiceRequestController;
use App\Http\Controllers\Web\VendorController;
use App\Http\Controllers\Web\WardController;
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
    Route::get('patients/register', [AdminWebController::class, 'registerForm'])->name('patients.register');
    Route::post('patients/verify-id', [AdminWebController::class, 'verifyHealthId'])->name('patients.verify-id');
    Route::get('patients/{id}', [AdminWebController::class, 'patientDetail'])->name('patients.show');
    Route::put('patients/{id}', [AdminWebController::class, 'updatePatient'])->name('patients.update');
    Route::delete('patients/{id}', [AdminWebController::class, 'deletePatient'])->name('patients.delete');
    Route::get('appointments', [AdminWebController::class, 'appointments'])->name('appointments');
    Route::get('appointments/schedule', [AdminWebController::class, 'scheduleForm'])->name('appointments.schedule');
    Route::post('appointments', [AdminWebController::class, 'storeAppointment'])->name('appointments.store');
    Route::post('appointments/{id}/reschedule', [AdminWebController::class, 'rescheduleAppointment'])->name('appointments.reschedule');
    Route::post('appointments/{id}/no-show', [AdminWebController::class, 'noShowAppointment'])->name('appointments.no-show');
    Route::get('queue', [AdminWebController::class, 'queue'])->name('queue');
    Route::post('queue/add', [AdminWebController::class, 'addToQueue'])->name('queue.add');
    Route::get('counter', [AdminWebController::class, 'counter'])->name('counter');
    Route::post('counter/issue', [AdminWebController::class, 'issueToken'])->name('counter.issue');
    Route::get('counter/slip/{id}', [AdminWebController::class, 'tokenSlip'])->name('counter.slip');
    Route::get('info-desk', [AdminWebController::class, 'infoDesk'])->name('info-desk');
    Route::get('staff', [AdminWebController::class, 'staff'])->name('staff');
    Route::post('staff', [AdminWebController::class, 'storeStaff'])->name('staff.store');
    Route::put('staff/{id}', [AdminWebController::class, 'updateStaff'])->name('staff.update');
    Route::delete('staff/{id}', [AdminWebController::class, 'deleteStaff'])->name('staff.delete');
    Route::post('staff/{id}/activate', [AdminWebController::class, 'activateStaff'])->name('staff.activate');
    Route::post('staff/{id}/reset-password', [AdminWebController::class, 'resetStaffPassword'])->name('staff.reset-password');
    Route::post('appointments/{id}/check-in', [AdminWebController::class, 'checkInAppointment'])->name('appointments.checkin');
    Route::post('appointments/{id}/cancel', [AdminWebController::class, 'cancelAppointment'])->name('appointments.cancel');
    Route::get('analytics', [AdminWebController::class, 'analytics'])->name('analytics');

    // Admin-only: manage slots, tests, medicines, settings
    Route::middleware('admin')->group(function () {
        Route::get('settings', [AdminWebController::class, 'settings'])->name('settings');
        Route::post('settings', [AdminWebController::class, 'saveSettings'])->name('settings.save');
        Route::post('settings/hours', [AdminWebController::class, 'saveOperatingHours'])->name('settings.hours');
        Route::post('settings/modules', [AdminWebController::class, 'updateModules'])->name('settings.modules');
        Route::post('settings/departments', [AdminWebController::class, 'saveDepartments'])->name('settings.departments');
        Route::post('settings/areas', [AdminWebController::class, 'saveAreas'])->name('settings.areas');
        Route::post('settings/gst', [AdminWebController::class, 'saveGstDetails'])->name('settings.gst');
        Route::post('settings/ai', [AdminWebController::class, 'saveAiSettings'])->name('settings.ai');
        Route::post('settings/whatsapp', [AdminWebController::class, 'saveWhatsappSettings'])->name('settings.whatsapp');
        Route::post('settings/billing-integration', [AdminWebController::class, 'saveBillingIntegration'])->name('settings.billing-integration');
        Route::post('settings/billing-integration/test', [AdminWebController::class, 'testBillingIntegration'])->name('settings.billing-integration.test');
        Route::get('api-keys', [AdminWebController::class, 'apiKeys'])->name('api-keys');
        Route::post('api-keys', [AdminWebController::class, 'createApiKey'])->name('api-keys.create');
        Route::post('api-keys/{id}/revoke', [AdminWebController::class, 'revokeApiKey'])->name('api-keys.revoke');
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

        // Asset Management (OT equipment + warranty tracking)
        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('assets/dashboard', [AssetController::class, 'dashboard'])->name('assets.dashboard');
        Route::get('assets/export', [AssetController::class, 'exportCsv'])->name('assets.export');
        Route::get('assets/report', [AssetController::class, 'report'])->name('assets.report');
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::get('assets/{id}', [AssetController::class, 'show'])->name('assets.show');
        Route::put('assets/{id}', [AssetController::class, 'update'])->name('assets.update');
        Route::delete('assets/{id}', [AssetController::class, 'destroy'])->name('assets.destroy');
        Route::post('assets/{id}/decommission', [AssetController::class, 'decommission'])->name('assets.decommission');
        // Warranties
        Route::post('assets/{assetId}/warranties', [AssetController::class, 'storeWarranty'])->name('assets.warranties.store');
        Route::put('warranties/{id}', [AssetController::class, 'updateWarranty'])->name('assets.warranties.update');
        Route::post('warranties/{id}/renew', [AssetController::class, 'renewWarranty'])->name('assets.warranties.renew');
        Route::delete('warranties/{id}', [AssetController::class, 'destroyWarranty'])->name('assets.warranties.destroy');
        Route::get('warranties/{id}/document', [AssetController::class, 'downloadDocument'])->name('assets.warranties.document');
        // Maintenance logs
        Route::post('assets/{assetId}/maintenance', [AssetController::class, 'storeMaintenance'])->name('assets.maintenance.store');
        Route::delete('maintenance/{id}', [AssetController::class, 'destroyMaintenance'])->name('assets.maintenance.destroy');
        // Calibrations
        Route::post('assets/{assetId}/calibrations', [AssetController::class, 'storeCalibration'])->name('assets.calibrations.store');
        Route::delete('calibrations/{id}', [AssetController::class, 'destroyCalibration'])->name('assets.calibrations.destroy');
        Route::get('calibrations/{id}/certificate', [AssetController::class, 'downloadCertificate'])->name('assets.calibrations.certificate');
        // Service requests / breakdown tickets
        Route::get('service-requests', [ServiceRequestController::class, 'index'])->name('tickets.index');
        Route::post('assets/{assetId}/service-requests', [ServiceRequestController::class, 'store'])->name('tickets.store');
        Route::put('service-requests/{id}', [ServiceRequestController::class, 'update'])->name('tickets.update');
        // Vendors
        Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::put('vendors/{id}', [VendorController::class, 'update'])->name('vendors.update');
        Route::delete('vendors/{id}', [VendorController::class, 'destroy'])->name('vendors.destroy');
    });
});

// ---------------------------------------------------------------
// Inpatient (IP) + ADT — wards, beds, admissions, case sheet
// ---------------------------------------------------------------

Route::middleware(['auth', 'module:inpatient'])->prefix('ip')->name('web.ip.')->group(function () {
    Route::get('/', [InpatientController::class, 'dashboard'])->name('dashboard');
    Route::get('adt', [InpatientController::class, 'adt'])->name('adt');
    Route::get('admissions', [InpatientController::class, 'admissions'])->name('admissions');
    Route::post('admissions', [InpatientController::class, 'admit'])->name('admit');
    Route::get('admissions/{id}', [InpatientController::class, 'show'])->name('show');
    Route::post('admissions/{id}/vitals', [InpatientController::class, 'storeVital'])->name('vitals.store');
    Route::post('admissions/{id}/io', [InpatientController::class, 'storeIntakeOutput'])->name('io.store');
    Route::post('admissions/{id}/notes', [InpatientController::class, 'storeNote'])->name('notes.store');
    Route::post('admissions/{id}/transfer', [InpatientController::class, 'transfer'])->name('transfer');
    Route::post('admissions/{id}/charge', [InpatientController::class, 'addCharge'])->name('charge');
    Route::post('admissions/{id}/bill', [InpatientController::class, 'compileBill'])->name('bill');
    Route::post('admissions/{id}/discharge', [InpatientController::class, 'discharge'])->name('discharge');
    Route::post('admissions/{id}/discharge/initiate', [InpatientController::class, 'initiateDischarge'])->name('discharge.initiate');
    Route::post('admissions/{id}/clearance', [InpatientController::class, 'toggleClearance'])->name('clearance');

    // Ward / bed setup (admin only)
    Route::middleware('admin')->group(function () {
        Route::get('wards', [WardController::class, 'index'])->name('wards');
        Route::post('wards', [WardController::class, 'storeWard'])->name('wards.store');
        Route::put('wards/{id}', [WardController::class, 'updateWard'])->name('wards.update');
        Route::delete('wards/{id}', [WardController::class, 'destroyWard'])->name('wards.destroy');
        Route::post('wards/{wardId}/beds', [WardController::class, 'storeBed'])->name('beds.store');
        Route::put('beds/{id}', [WardController::class, 'updateBed'])->name('beds.update');
        Route::delete('beds/{id}', [WardController::class, 'destroyBed'])->name('beds.destroy');
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
    Route::post('remove-queue/{appointmentId}', [DoctorWebController::class, 'removeFromQueue'])->name('remove-queue');
    Route::get('queue-json', [DoctorWebController::class, 'queueJson'])->name('queue-json');
});

// ---------------------------------------------------------------
// Lab Module
// ---------------------------------------------------------------

Route::middleware(['auth', 'module:lab'])->prefix('lab')->name('web.lab.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\LabController::class, 'dashboard'])->name('dashboard');
    Route::get('bookings', [\App\Http\Controllers\Web\LabController::class, 'bookings'])->name('bookings');
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

Route::middleware(['auth', 'module:pharmacy'])->prefix('pharmacy')->name('web.pharmacy.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\PharmacyController::class, 'dashboard'])->name('dashboard');
    Route::post('{id}/dispense', [\App\Http\Controllers\Web\PharmacyController::class, 'dispense'])->name('dispense');
    Route::get('stock', [\App\Http\Controllers\Web\PharmacyController::class, 'stock'])->name('stock');
    Route::post('stock', [\App\Http\Controllers\Web\PharmacyController::class, 'addStock'])->name('stock.store');
    Route::put('stock/{id}', [\App\Http\Controllers\Web\PharmacyController::class, 'updateStock'])->name('stock.update');
});

// ---------------------------------------------------------------
// Billing Module
// ---------------------------------------------------------------

Route::middleware(['auth', 'module:billing'])->prefix('billing')->name('web.billing.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\BillingWebController::class, 'index'])->name('index');
    Route::get('dashboard', [\App\Http\Controllers\Web\BillingWebController::class, 'dashboard'])->name('dashboard');
    Route::get('create/{encounterId}', [\App\Http\Controllers\Web\BillingWebController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\Web\BillingWebController::class, 'store'])->name('store');
    // Compile captured charges (charge-capture ledger) into the encounter's bill
    Route::post('compile/{encounterId}', [\App\Http\Controllers\Web\BillingWebController::class, 'compileCharges'])->name('compile');
    // Service / charge master
    Route::get('services', [\App\Http\Controllers\Web\BillingWebController::class, 'services'])->name('services');
    Route::post('services', [\App\Http\Controllers\Web\BillingWebController::class, 'storeService'])->name('services.store');
    Route::put('services/{id}', [\App\Http\Controllers\Web\BillingWebController::class, 'updateService'])->name('services.update');
    Route::delete('services/{id}', [\App\Http\Controllers\Web\BillingWebController::class, 'destroyService'])->name('services.destroy');
    // Itemized / standalone bill builder
    Route::get('new', [\App\Http\Controllers\Web\BillingWebController::class, 'newBill'])->name('new');
    Route::post('new', [\App\Http\Controllers\Web\BillingWebController::class, 'storeBill'])->name('bill.store');
    // Advances / deposits
    Route::post('deposit', [\App\Http\Controllers\Web\BillingWebController::class, 'collectDeposit'])->name('deposit');
    // Accounting export (CSV / Tally XML) — must precede the {id} route
    Route::get('export', [\App\Http\Controllers\Web\BillingWebController::class, 'exportAccounting'])->name('export');
    Route::get('{id}', [\App\Http\Controllers\Web\BillingWebController::class, 'show'])->name('show');
    Route::post('{id}/pay', [\App\Http\Controllers\Web\BillingWebController::class, 'recordPayment'])->name('pay');
    Route::post('{id}/cancel', [\App\Http\Controllers\Web\BillingWebController::class, 'cancelBill'])->name('cancel');
    Route::put('{billId}/payments/{paymentId}', [\App\Http\Controllers\Web\BillingWebController::class, 'updatePayment'])->name('payments.update');
    Route::delete('{billId}/payments/{paymentId}', [\App\Http\Controllers\Web\BillingWebController::class, 'deletePayment'])->name('payments.delete');
    Route::post('{billId}/payments/{paymentId}/refund', [\App\Http\Controllers\Web\BillingWebController::class, 'refundPayment'])->name('payments.refund');
    Route::get('{id}/print', [\App\Http\Controllers\Web\BillingWebController::class, 'printReceipt'])->name('print');
});

// Insurance claims (bill-centric)
Route::middleware(['auth', 'module:billing'])->prefix('claims')->name('web.claims.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\InsuranceWebController::class, 'index'])->name('index');
    Route::post('file/{billId}', [\App\Http\Controllers\Web\InsuranceWebController::class, 'fileClaim'])->name('file');
    Route::post('{claimId}/approve', [\App\Http\Controllers\Web\InsuranceWebController::class, 'approveClaim'])->name('approve');
    Route::post('{claimId}/deny', [\App\Http\Controllers\Web\InsuranceWebController::class, 'denyClaim'])->name('deny');
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
    Route::get('queue-display/json', [KioskController::class, 'queueDisplayJson'])->name('queue-display.json');
    Route::get('room/{doctorId}', [KioskController::class, 'roomDisplay'])->name('room-display');
    Route::get('q/{doctorId}', [KioskController::class, 'patientQueueView'])->name('queue-live');
});

// ---------------------------------------------------------------
// Public "Book Online" — patient-facing web & mobile booking (no auth)
// ---------------------------------------------------------------
Route::prefix('book')->name('book.')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\PublicBookingController::class, 'index'])->name('index');
    Route::get('doctors', [\App\Http\Controllers\Web\PublicBookingController::class, 'doctors'])->name('doctors');
    Route::get('slots/{doctorId}', [\App\Http\Controllers\Web\PublicBookingController::class, 'slots'])->name('slots');
    Route::post('/', [\App\Http\Controllers\Web\PublicBookingController::class, 'store'])->name('store');
    Route::get('confirmed/{token}', [\App\Http\Controllers\Web\PublicBookingController::class, 'confirmed'])->name('confirmed');
});

// ---------------------------------------------------------------
// Vaccination — records + due tracking + vaccine master
// ---------------------------------------------------------------
Route::middleware(['auth', 'module:vaccination'])->prefix('vaccination')->name('web.vaccination.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\VaccinationController::class, 'index'])->name('index');
    Route::get('certificate/{patient}', [\App\Http\Controllers\Web\VaccinationController::class, 'certificate'])->name('certificate');
    Route::post('record', [\App\Http\Controllers\Web\VaccinationController::class, 'record'])->name('record');
    Route::post('vaccines', [\App\Http\Controllers\Web\VaccinationController::class, 'storeVaccine'])->name('vaccines.store');
    Route::put('vaccines/{id}', [\App\Http\Controllers\Web\VaccinationController::class, 'updateVaccine'])->name('vaccines.update');
    Route::delete('vaccines/{id}', [\App\Http\Controllers\Web\VaccinationController::class, 'destroyVaccine'])->name('vaccines.destroy');
});

// ---------------------------------------------------------------
// Dietary — meal master + prescribe meals + delivery tracking
// ---------------------------------------------------------------
Route::middleware(['auth', 'module:dietary'])->prefix('dietary')->name('web.dietary.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\DietaryController::class, 'index'])->name('index');
    Route::post('order', [\App\Http\Controllers\Web\DietaryController::class, 'orderDiet'])->name('order');
    Route::post('orders/{id}/discontinue', [\App\Http\Controllers\Web\DietaryController::class, 'discontinueOrder'])->name('orders.discontinue');
    Route::post('assessment', [\App\Http\Controllers\Web\DietaryController::class, 'storeAssessment'])->name('assessment');
    Route::post('diets', [\App\Http\Controllers\Web\DietaryController::class, 'storeDiet'])->name('diets.store');
    Route::put('diets/{id}', [\App\Http\Controllers\Web\DietaryController::class, 'updateDiet'])->name('diets.update');
    Route::delete('diets/{id}', [\App\Http\Controllers\Web\DietaryController::class, 'destroyDiet'])->name('diets.destroy');
});

// ---------------------------------------------------------------
// Consent Management — form repository + patient consents + compliance
// ---------------------------------------------------------------
Route::middleware(['auth', 'module:consent'])->prefix('consent')->name('web.consent.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\ConsentController::class, 'index'])->name('index');
    Route::post('request', [\App\Http\Controllers\Web\ConsentController::class, 'request'])->name('request');
    Route::post('{id}/sign', [\App\Http\Controllers\Web\ConsentController::class, 'sign'])->name('sign');
    Route::post('{id}/status', [\App\Http\Controllers\Web\ConsentController::class, 'setStatus'])->name('status');
    Route::post('forms', [\App\Http\Controllers\Web\ConsentController::class, 'storeForm'])->name('forms.store');
    Route::put('forms/{id}', [\App\Http\Controllers\Web\ConsentController::class, 'updateForm'])->name('forms.update');
    Route::delete('forms/{id}', [\App\Http\Controllers\Web\ConsentController::class, 'destroyForm'])->name('forms.destroy');
});

// ---------------------------------------------------------------
// Incident Reporting — safety/quality incidents + CAPA workflow
// ---------------------------------------------------------------
Route::middleware(['auth', 'module:incidents'])->prefix('incidents')->name('web.incidents.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\IncidentController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\Web\IncidentController::class, 'store'])->name('store');
    Route::post('{id}', [\App\Http\Controllers\Web\IncidentController::class, 'update'])->name('update');
});

// ---------------------------------------------------------------
// Housekeeping — location issue / non-compliance log + closure
// ---------------------------------------------------------------
Route::middleware(['auth', 'module:housekeeping'])->prefix('housekeeping')->name('web.housekeeping.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\HousekeepingController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\Web\HousekeepingController::class, 'store'])->name('store');
    Route::post('{id}', [\App\Http\Controllers\Web\HousekeepingController::class, 'update'])->name('update');
});

// ---------------------------------------------------------------
// Inventory — stock ledger + reorder alerts + batch/expiry
// ---------------------------------------------------------------
Route::middleware(['auth', 'module:inventory'])->prefix('inventory')->name('web.inventory.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\InventoryController::class, 'index'])->name('index');
    Route::post('move', [\App\Http\Controllers\Web\InventoryController::class, 'move'])->name('move');
    Route::post('items', [\App\Http\Controllers\Web\InventoryController::class, 'storeItem'])->name('items.store');
    Route::put('items/{id}', [\App\Http\Controllers\Web\InventoryController::class, 'updateItem'])->name('items.update');
    Route::delete('items/{id}', [\App\Http\Controllers\Web\InventoryController::class, 'destroyItem'])->name('items.destroy');
});

// ---------------------------------------------------------------
// Clinical Pathways — templates + patient enrollment + step tracking
// ---------------------------------------------------------------
Route::middleware(['auth', 'module:clinical_pathways'])->prefix('pathways')->name('web.pathways.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\ClinicalPathwayController::class, 'index'])->name('index');
    Route::post('enroll', [\App\Http\Controllers\Web\ClinicalPathwayController::class, 'enroll'])->name('enroll');
    Route::post('{id}/steps', [\App\Http\Controllers\Web\ClinicalPathwayController::class, 'toggleSteps'])->name('steps');
    Route::post('{id}/status', [\App\Http\Controllers\Web\ClinicalPathwayController::class, 'setStatus'])->name('status');
    Route::post('templates', [\App\Http\Controllers\Web\ClinicalPathwayController::class, 'storeTemplate'])->name('templates.store');
    Route::put('templates/{id}', [\App\Http\Controllers\Web\ClinicalPathwayController::class, 'updateTemplate'])->name('templates.update');
    Route::delete('templates/{id}', [\App\Http\Controllers\Web\ClinicalPathwayController::class, 'destroyTemplate'])->name('templates.destroy');
});

// ---------------------------------------------------------------
// Dental — tooth chart + treatment plan
// ---------------------------------------------------------------
Route::middleware(['auth', 'module:dental'])->prefix('dental')->name('web.dental.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\DentalController::class, 'index'])->name('index');
    Route::post('chart', [\App\Http\Controllers\Web\DentalController::class, 'saveChart'])->name('chart.save');
    Route::post('treatment', [\App\Http\Controllers\Web\DentalController::class, 'addTreatment'])->name('treatment.add');
    Route::post('treatment/{id}', [\App\Http\Controllers\Web\DentalController::class, 'updateTreatment'])->name('treatment.update');
    Route::post('treatment/{id}/delete', [\App\Http\Controllers\Web\DentalController::class, 'deleteTreatment'])->name('treatment.delete');
    Route::post('bill', [\App\Http\Controllers\Web\DentalController::class, 'billTreatments'])->name('bill');
    Route::post('visit', [\App\Http\Controllers\Web\DentalController::class, 'addVisit'])->name('visit.add');
    Route::post('procedure', [\App\Http\Controllers\Web\DentalController::class, 'storeProcedure'])->name('procedure.store');
    Route::post('procedure/{id}', [\App\Http\Controllers\Web\DentalController::class, 'updateProcedure'])->name('procedure.update');
});

// ---------------------------------------------------------------
// Voice AI Calls
// ---------------------------------------------------------------
Route::middleware(['auth', 'module:voice_calls'])->prefix('voice-calls')->name('web.voice-calls.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\VoiceCallController::class, 'dashboard'])->name('dashboard');
    Route::get('calls', [\App\Http\Controllers\Web\VoiceCallController::class, 'callLog'])->name('calls');
    Route::get('calls/{id}', [\App\Http\Controllers\Web\VoiceCallController::class, 'callDetail'])->name('calls.show');
    Route::get('settings', [\App\Http\Controllers\Web\VoiceCallController::class, 'settings'])->name('settings');
    Route::post('settings', [\App\Http\Controllers\Web\VoiceCallController::class, 'settings'])->name('settings.save');
    Route::post('toggle', [\App\Http\Controllers\Web\VoiceCallController::class, 'toggleEnabled'])->name('toggle');
    Route::get('live-calls', [\App\Http\Controllers\Web\VoiceCallController::class, 'liveCallsJson'])->name('live-calls');
    Route::get('calls/{id}/transcript', [\App\Http\Controllers\Web\VoiceCallController::class, 'callTranscriptJson'])->name('calls.transcript');
    Route::post('callbacks/{id}/initiate', [\App\Http\Controllers\Web\VoiceCallController::class, 'initiateCallback'])->name('callbacks.initiate');
    Route::get('analytics', [\App\Http\Controllers\Web\VoiceCallController::class, 'analyticsJson'])->name('analytics');
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

    Route::get('patient-upcoming', [AdminWebController::class, 'patientUpcoming']);
    Route::get('info-desk', [AdminWebController::class, 'infoDeskLookup']);
    Route::get('info-desk/patient/{id}', [AdminWebController::class, 'infoDeskPatient']);

    Route::get('patients', function (\Illuminate\Http\Request $request) {
        $hid = auth()->user()->hospital_id;
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        return \Illuminate\Support\Facades\DB::table('patients')
            ->where('hospital_id', $hid)
            ->whereNull('deleted_at')
            ->where(function ($x) use ($q) {
                $x->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%");
            })
            ->orderBy('name')->limit(15)
            ->get(['id', 'name', 'phone']);
    });

    Route::get('icd10', function (\Illuminate\Http\Request $request) {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        return \Illuminate\Support\Facades\DB::table('icd10_codes')
            ->where('code', 'like', "{$q}%")
            ->orWhere('title', 'like', "%{$q}%")
            ->orderByRaw('CASE WHEN code LIKE ? THEN 0 ELSE 1 END', ["{$q}%"])
            ->orderBy('code')
            ->limit(20)
            ->get(['code', 'title', 'category']);
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
// WhatsApp Bot Simulator
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
    abort_if($hospital && ! $hospital->isModuleEnabled('ai_receptionist'), 404);
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
    Route::post('hospitals/{id}/modules', [\App\Http\Controllers\Web\SuperAdminController::class, 'updateHospitalModules'])->name('hospitals.modules');
    Route::delete('hospitals/{id}', [\App\Http\Controllers\Web\SuperAdminController::class, 'deleteHospital'])->name('hospitals.delete');
    Route::delete('hospitals/{id}/force', [\App\Http\Controllers\Web\SuperAdminController::class, 'destroyHospital'])->name('hospitals.destroy');
    Route::post('hospitals/{id}/staff', [\App\Http\Controllers\Web\SuperAdminController::class, 'addStaffToHospital'])->name('hospitals.staff.add');
    Route::delete('hospitals/{hospitalId}/staff/{staffId}', [\App\Http\Controllers\Web\SuperAdminController::class, 'removeStaffFromHospital'])->name('hospitals.staff.remove');
    Route::post('hospitals/{id}/admin', [\App\Http\Controllers\Web\SuperAdminController::class, 'addAdminToHospital'])->name('hospitals.admin.add');
    Route::post('hospitals/{hospitalId}/users/{userId}/reset-password', [\App\Http\Controllers\Web\SuperAdminController::class, 'resetUserPassword'])->name('hospitals.users.reset');
    // IAM — all user accounts across hospitals
    Route::get('users', [\App\Http\Controllers\Web\SuperAdminController::class, 'users'])->name('users.index');
    Route::get('users/{userId}', [\App\Http\Controllers\Web\SuperAdminController::class, 'userDetail'])->name('users.show');
    Route::post('users/{userId}/toggle', [\App\Http\Controllers\Web\SuperAdminController::class, 'toggleUserActive'])->name('users.toggle');
    Route::delete('users/{userId}', [\App\Http\Controllers\Web\SuperAdminController::class, 'deleteUser'])->name('users.delete');
});

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
