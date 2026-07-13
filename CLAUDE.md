# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# MedOS — AI-First Hospital Operating System

## Project
- **Built by:** Haztech Digital Innovation Agency
- **Live:** https://medos.haztech.cloud
- **GitHub:** https://github.com/teamHaztech/medos
- **Hosting:** Hostinger shared hosting (hPanel, NO SSH, NO terminal, only File Manager + Git deploy)

## Stack
- Laravel 13, PHP 8.4, SQLite (production + dev)
- Frontend: Blade + Tailwind CSS v4 + Alpine.js (NO React, NO Vue, NO jQuery)
- Auth: Laravel Sanctum
- WhatsApp: whatsapp-web.js (Node.js bot at whatsapp-bot.js)
- All IDs: UUID
- Multi-tenant: hospital_id on every table

## Local development (Windows dev machine)
No `php`/`composer`/`npm` on PATH and **no Tailwind build step**. Use the WinGet PHP 8.4 binary directly:
- **PHP:** `"C:/Users/regan/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe"`
- **Run the app:** `php artisan serve --host=127.0.0.1 --port=8000` (run in background; then log in with an account above)
- **Migrations:** `php artisan migrate --force`
- **After editing any Blade:** `php artisan view:clear` (the running server serves compiled views). To compile-check ALL templates at once (catches Blade syntax errors everywhere): `php artisan view:cache && php artisan view:clear`
- **Inspect routes:** `php artisan route:list --path=<segment>`
- **Clear caches after route/config/middleware edits:** `php artisan optimize:clear`

### Verifying behaviour = throwaway kernel harness (there is no maintained PHPUnit suite)
Boot the kernel, authenticate, call controller methods / render views directly, assert, then **delete the script and any rows/config it touched**:
```php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{Auth, View, Session}; use Illuminate\Http\Request;
Session::start(); View::share('errors', new Illuminate\Support\ViewErrorBag());
Auth::setUser(App\Models\User::where('email','admin@haztech.in')->first());
$c = new App\Http\Controllers\Web\AdminWebController();
echo strlen($c->patients(Request::create('/','GET',[]))->render());   // render a page, or call an action
```
For Alpine/JS bugs you can extract a page's `<script>` and run it through `node --check` / `node` to catch syntax or runtime errors server-side.

## Logins
All `password123`. Each role lands on its own work area after login (see `WebAuthController::landingRoute`) and gets a role-specific sidebar block (`_sidebar_nav.blade.php`).
| Email | Role | Lands on |
|---|---|---|
| superadmin@haztech.in | Super Admin | admin dashboard |
| admin@haztech.in | Hospital Admin | admin dashboard |
| priya@haztech.in / amit@haztech.in | Doctor | doctor dashboard |
| reception@haztech.in | Receptionist | appointments |
| nurse@haztech.in | Nurse | inpatients |
| billing@haztech.in | Billing Staff | billing |
| lab@haztech.in / pharmacy@haztech.in | Lab / Pharmacy | lab / pharmacy dashboard |

Demo accounts for reception/nurse/billing are seeded by `2026_07_08_000002_seed_department_accounts` and surfaced as quick-login buttons on `/login`.

## Database
Core: hospitals, users, staff, patients, encounters, appointments, conversations, insurance_transactions, orders, bills, queue_entries, notifications_log, audit_logs, referrals, medicines(120), available_tests(74), abha_consents, abha_health_records, abha_audit_logs, personal_access_tokens.
Added since: **billing** — service_charges (rate card: price + `gst_rate` + `hsn_sac`), patient_deposits (advance ledger), bill_payments (payment ledger; refunds = negative rows), billing_integration_logs (external-sync audit); **inpatient/ADT** — wards, beds, admissions, ip_vitals, ip_notes, ip_intake_outputs; icd10_codes.
- **Revenue-cycle** — **charge_items** (the charge-capture ledger — every chargeable event posts a row; see the Billing architecture note); bills gained `admission_id`, `cgst_amount`/`sgst_amount`/`igst_amount`, `insurance_transaction_id`, `insurer_name`/`policy_number`; wards/admissions gained room/nursing `daily_rate` + snapshot; `insurance_transactions` reconciled (real cols `insurer_code`/`type`/`external_reference_id`, with back-compat model aliases).
- **IAM / security** — **account_activity** (append-only login/logout/failed-login log; nullable hospital_id); users gained `last_login_at`/`last_login_ip`.
- **Clinical modules** (each hospital-scoped, own tables) — Clinical Nutrition (therapeutic_diets, diet_orders, nutrition_assessments), Dental (dental_procedures, dental_treatments, dental_visits, dental_charts), Immunization (vaccines w/ `age_schedule`, patient_vaccinations), plus Consent/Incidents/Housekeeping/Inventory/Pathway/Quality and Voice-AI-Calls tables.

### Key columns to remember
- **patients**: name (NOT first_name), phone, abha_number, language_preference (NOT preferred_language), insurance_details (encrypted:array), allergies/medical_history/current_medications (array cast)
- **encounters**: encounter_number, intake_data (array cast with chief_complaint key), soap_notes (encrypted:array), status/type/triage_classification are PHP enums — extract with `is_object($x) ? $x->value : $x`
- **appointments**: slot_start/slot_end (datetime), notes (stores token like "PED-001"), booking_source, status is AppointmentStatus enum
- **staff**: name, department, specialization, schedule (JSON), consultation_duration_default (NOT consultation_duration_minutes)
- **bills**: bill_number, total_amount, insurance_covered, patient_payable (NOT insurance_amount, NOT patient_amount)

## File Structure
```
app/Modules/          — 15 modules (Core, Auth, AIReceptionist, WhatsApp, Triage, Appointment, Queue, Insurance, Billing, Patient, DoctorAssist, Engagement, Multilingual, Analytics, ABHA)
app/Http/Controllers/Web/  — AdminWebController (front office: patients, appts, queue, counter, info-desk, settings [departments/areas/GST], api-keys), BillingWebController, InsuranceWebController (claims), PublicBookingController (/book), InpatientController (IPD + running bill), WardController, DoctorWebController, KioskController, ChatController, SuperAdminController (hospitals + IAM), WebAuthController, LabController, PharmacyController, VoiceCallController; + clinical-module controllers (Dietary, Dental, Vaccination, Consent, Incident, Housekeeping, Inventory, ClinicalPathway, Ward, Asset)
app/Http/Controllers/Api/  — WhatsAppWebhookController, IntegrationController (customer / doctor-schedule / book-appointment)
config/medos.php      — central config (AI, WhatsApp, triage, queue, scheduling, insurance, languages)
config/regions.php    — India vs UAE region config (currency, languages, health ID, insurance, etc.)
routes/web.php        — all web routes
routes/api_v1.php     — all API routes
```

## Routes cheat sheet
```
/login                          — auth
/admin/*                        — dashboard, patients, appointments(+schedule), queue, counter (Payment & Token), info-desk, inpatients(/ip), billing, staff, settings, api-keys, slots, tests, analytics
/doctor/*                       — queue, stats, my-patients, my-appointments, history, referrals, complete/{id}, call-next/{id}, queue-json
/kiosk/*                        — index, register, checkin, check-phone, verify-abha, match-doctors, doctors, queue-display, room/{name}
/book                           — PUBLIC patient web/mobile booking (no auth): ?hospital=slug → pick doctor → future slot → book. /book/doctors, /book/slots/{id}, /book/confirmed/{token}
/billing/*                      — bills, dashboard, services (rate card + GST), new (itemized), export (CSV/Tally), {id} (payments/refund/cancel/deposit), compile/{encounter} (ledger→bill), print (TAX INVOICE)
/claims                         — insurance claims worklist; file/{bill}, {claim}/approve|deny (InsuranceWebController)
/ip/*                           — inpatient: dashboard (bed board), admissions, {id} (case sheet + running bill), {id}/charge|bill|discharge
/chat                           — WhatsApp bot simulator (scopes to ?hospital_id / session)
/super-admin                    — hospital management; /super-admin/users (IAM: accounts by hospital, orphan detection, {id} sign-in history)
/ajax/doctor-slots/{id}         — 14-day slot calendar (staff) · /ajax/patients?q= · /ajax/info-desk?q= (token|name auto-detect)
/api/v1/auth/login              — API token (send Accept: application/json + email/password, NOT "admin")
/api/v1/customer?phone= · /doctor-schedule?name= · POST /book-appointment   — external integration APIs (Sanctum)
/api/v1/billing/*               — bill read/write (ability:billing) · /api/v1/abha/* · /insurance/*
```

## Key services
- **RegionService** (app/Modules/Core/Services/RegionService.php) — `RegionService::currency()`, `::isIndia()`, `::isUAE()`, `::healthIdLabel()`, etc.
- **ABHAService** (app/Modules/ABHA/Services/ABHAService.php) — ABHA create/link/verify
- **TriageService** → RuleEngine + ClinicalScorer + SpecialtyMapper
- **ConversationManager** — AI receptionist brain
- **EmergencyDetector** — fast keyword-based, no AI needed

## Common patterns
- Controllers return `{success, data, message}` for API, Blade views for web
- Enum values in views: always `is_object($x) ? $x->value : ($x ?? 'default')` — NEVER cast enum directly to string
- intake_data: pass as PHP array to Eloquent (model casts it) — NEVER json_encode() manually
- New tables: always UUID primary key, hospital_id FK, created_at/updated_at
- Alpine.js for all interactivity — x-data, x-show, @click, fetch() for AJAX
- Doctor queue auto-refreshes from /doctor/queue-json every 5 seconds
- Room display at /kiosk/room/{name} — voice in 5 languages (EN, HI, MR, KOK, AR)

## Architecture notes (spans multiple files — read before touching these areas)
- **Tenant scoping** — the `BelongsToHospital` trait auto-fills `hospital_id` on create and adds a global scope `where hospital_id = config('medos.current_hospital_id')`. That config is **NULL** in ordinary web/CLI requests, so controllers either set `config(['medos.current_hospital_id' => Auth::user()->hospital_id])` at the top of the method (e.g. `InpatientController::hid()`, `AssetController`) OR scope every query by hand with `->where('hospital_id', Auth::user()->hospital_id)` (e.g. `BillingWebController`, `AdminWebController`). Newer models that scope by hand (`ServiceCharge`, `PatientDeposit`) deliberately use only `HasUuid` to avoid the null-scope trap. Know which pattern a file uses before adding queries.
- **Tokens & the doctor queue** — the live doctor "queue" is NOT `queue_entries`; it is today's `appointments` with status `checked_in`/`in_progress` (see `DoctorWebController::queueJson`, `KioskController::queueDisplay`). Tokens like `PED-001` come from `Appointment::generateToken($doctorId, $department, $slotStart)`. Walk-in booking, Add-to-Queue, and the Payment & Token counter all create a `checked_in` appointment to put the patient in the queue.
- **Billing** — a `Bill` holds JSON `line_items` plus a `BillPayment` ledger; partial payments are extra rows and **refunds are negative-amount rows**. `BillingWebController::recomputeBill()` derives `amount_paid`/`balance_due`/`payment_status` from the ledger — never set those columns directly. Rate card = `ServiceCharge` (service master); patient advances = `PatientDeposit` (signed ledger, balance via `PatientDeposit::balanceFor()`, paid via a `deposit` method). Every bill needs an `encounter_id`, so standalone/counter bills create a lightweight `Encounter` (`type consultation`, `channel walk_in`).
- **Revenue cycle / charge-capture ledger** (READ THIS before touching billing/charges) — the anti-leakage spine is `charge_items` + **`ChargeCapture`** (`app/Modules/Billing/Services/ChargeCapture.php`). Every chargeable event auto-posts a charge via `ChargeCapture::post([...])` — **idempotent by (hospital_id, source, source_ref)**; a billed charge is never mutated (except room/nursing accrual, which stays live until discharge). Auto-capture hooks: consultation on `DoctorWebController::completeConsultation` (rate-card priced, not the old hardcoded fee), lab on order creation/`referToLab`, **pharmacy on `PharmacyController::dispense`** at `PharmacyStock.selling_price`, IPD room/nursing via `ChargeCapture::accrueRoom` (on-demand, no cron), ad-hoc IPD charges from the case sheet. **`ChargeCapture::compileBill($scope)`** turns pending charges into a `Bill` (create/refresh) — `$scope` is an **Encounter (OPD)** or an **Admission (IPD, keyed by `bills.admission_id`)** — reusing Bill + BillPayment + the ERP `BillObserver` untouched. The old one-shot `BillingService::generateBill` is superseded by this path. Money math lives in `ChargeCapture::computeInvoice` (per-line GST → CGST/SGST split; discount/insurance preserved) + `recomputePayments` (public, mirrors `recomputeBill`). Avoid `Bill::calculateTotals()` (double-subtracts insurance).
- **GST (India)** — per-service `gst_rate` + `hsn_sac` on `ServiceCharge`; GST is computed **per line** (clinical services exempt at 0%, pharmacy/consumables 5/12/18/28%), split into CGST+SGST (intra-state) on the bill. Hospital GSTIN/state live in `Hospital.config['gstin']`/`['gst_state']` (Settings → GST). `billing/print.blade.php` renders a "TAX INVOICE" (GSTIN + HSN/SAC + GST% per line) when GST applies.
- **Insurance claims** — `InsuranceTransaction` (real cols `insurer_code`/`type`/`external_reference_id`; the model aliases the older `provider_name`/`transaction_type`/`authorization_number` — don't reintroduce those as columns). Bill-centric flow in `InsuranceWebController`: file a claim against a bill → approve (sets `insurance_covered`, recomputes `patient_payable` via the ledger) / deny; worklist at `/claims`. Provider adapters (`app/Modules/Insurance/Providers/*`) remain stubbed until payer keys.
- **IAM / security** — Super-Admin user management: `SuperAdminController::users/userDetail/toggleUserActive/deleteUser` (`/super-admin/users`) lists accounts grouped by hospital, flags orphan (no/dangling hospital) + unknown-role accounts, and drills into per-account **sign-in history**. `WebAuthController` records login/logout/failed-login into `account_activity` via `AccountActivity::record()` and stamps `users.last_login_at`. "Switch to This" (super-admin) mutates the acting user's `hospital_id` = the current tenant context (drives currency, `/chat?hospital_id=`, all scoped screens).
- **Per-hospital config** — free-form settings live on `Hospital.config` (JSON): `departments`, `areas` (waiting/lab area strings shown at kiosk check-in — `KioskController::hospitalArea()`), `gstin`/`gst_state`, plus `billing_integration`/`whatsapp`/etc. Mirror the `AdminWebController::saveDepartments`/`saveAreas`/`saveGstDetails` idiom (load with defaults in `settings()`, save-back to `config`).
- **Module gating** — `Hospital::isModuleEnabled($key)` (empty `modules_enabled` ⇒ everything on) + the `module:<key>` middleware (`EnsureModuleEnabled`) 404s disabled modules; `AppServiceProvider` shares a `$moduleOn('key')` closure to every view for hiding nav/UI. Catalog of toggleable keys: `App\Modules\Core\Support\ModuleCatalog`.
- **Shared UI** — every add/edit popup uses the `<x-modal show="alpineVar" title="…" max="lg">` component (`resources/views/components/modal.blade.php`); pass `body-class=""` when the slot brings its own padding. Don't hand-roll modal overlays.
- **Idempotent migrations** — new migrations guard with `Schema::hasTable`/`Schema::hasColumn` and wrap sample-seed blocks in count checks, because prod runs them via `/public/deploy.php` and pages must not 500 if a table isn't there yet (controllers also guard with `Schema::hasTable`).
- **Pluggable adapter + registry pattern** — used for external integrations: `app/Modules/Billing/Integrations/*` (`BaseBillingConnector` abstract + `GenericWebhookConnector` + `BillingConnectorRegistry::make($code)`), mirroring `app/Modules/Insurance/Providers/*` + `InsuranceService::resolveProvider()`. To add a connector/gateway = one subclass + one registry line; per-hospital config + encrypted secrets live in `Hospital.config['<section>']` (save idiom = `AdminWebController::saveBillingIntegration` / `saveWhatsappSettings`, "•••• saved" secret pattern). The **Payment gateway** layer (Phase 2, `app/Modules/Billing/Payments/*`) follows the same shape.
- **External billing / ERP sync** — `BillObserver` (the app's one model observer, registered in `BillingServiceProvider`) fires on Bill create + `payment_status` change → dispatches the queued `SyncBillToExternal` job → HMAC-signed POST to the hospital's configured endpoint, logged to `billing_integration_logs`. So **any** created/paid/refunded bill auto-flows to the external ERP. Queue = `database` driver → prod needs an hPanel cron `queue:work --stop-when-empty` to drain it (same for WhatsApp/notification jobs).
- **Role-based access** — `WebAuthController::landingRoute($role)` routes each role to its work area on login; the sidebar (`_sidebar_nav.blade.php`) renders a per-role `@if($is<Role>)` block. Hospital admin issues long-lived Sanctum API keys at `/admin/api-keys` (billing:read / billing:write abilities enforced by `ability:` middleware on `/api/v1/billing/*`); `resolve.hospital` pins non-super-admin tokens to their own hospital (rejects a mismatched `X-Hospital-ID`).
- **Public patient surfaces** — booking (`/book`, `PublicBookingController`) and kiosk (`/kiosk`) are the only patient-facing, no-auth web flows; both resolve the hospital by `?hospital=slug`/session and extend `layouts/public` (booking) or `layouts/kiosk`. Slot availability comes from the shared `DoctorSlotService::calendar()`. Guard new public routes with `throttle` and hospital-scope every query.

## Deployment rules
- MUST commit vendor/ and public/build/ (no composer/npm on server)
- After push: user must Pull in hPanel → Git (or auto-deploy if enabled)
- deploy.php at /public/deploy.php runs migrations+seeding via browser
- .env is on server only (not in git), .env.production is template
- SQLite DB path on server: /home/u705434801/domains/haztech.cloud/public_html/medos/database/database.sqlite

## Work rules
- Don't ask permission — just build
- Don't rewrite entire files — use targeted Edit
- Don't add React/Vue/jQuery — Blade + Alpine only
- Don't use MySQL-specific SQL (TIMESTAMPDIFF, HOUR()) — use PHP Carbon or SQLite strftime
- Test locally first, then push
- After pushing, say "Pull in hPanel" if needed
- When creating new pages: add route, controller method, blade view, sidebar link
- Money: use RegionService::currency() not hardcoded ₹ or AED

## Known gotchas
- **Tailwind is NOT rebuilt** locally or on prod — the compiled `public/build/` is committed as-is. Any arbitrary `[...]` bracket class (`text-[10px]`, `bg-black/50`, `max-h-[90vh]`, `print:hidden`) that isn't already in the built CSS silently no-ops in production. Prefer inline `style="…"` or standard utilities; verify a bracket class ships with `grep -F 'text-[10px]' public/build/assets/*.css`.
- `lg:!translate-x-0` on sidebar uses !important to override Alpine.js
- SQLite doesn't support TIMESTAMPDIFF — use Carbon diffInMinutes() in PHP
- Patient model has Auditable trait — if AuditLog insert fails, check column names (action NOT event, no user_name column)
- Appointment.encounter_id is nullable (walk-ins may not have encounter yet)
- Seeder uses SeedData.php constants for shared UUIDs across seeders
