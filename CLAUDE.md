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
| dentist@haztech.in | Dentist (Dr. Neha Kapoor) | Dental module |
| dietitian@haztech.in | Dietitian (Dr. Anita Rao) | Clinical Nutrition module |

Demo accounts for reception/nurse/billing are seeded by `2026_07_08_000002_seed_department_accounts` and surfaced as quick-login buttons on `/login`.

## Database
Core: hospitals, users, staff, patients, encounters, appointments, conversations, insurance_transactions, orders, bills, queue_entries, notifications_log, audit_logs, referrals, medicines(120), available_tests(74), abha_consents, abha_health_records, abha_audit_logs, personal_access_tokens.
Added since: **billing** — service_charges (rate card: price + `gst_rate` + `hsn_sac`), patient_deposits (advance ledger), bill_payments (payment ledger; refunds = negative rows), billing_integration_logs (external-sync audit); **inpatient/ADT** — wards, beds, admissions, ip_vitals, ip_notes, ip_intake_outputs; icd10_codes.
- **Revenue-cycle** — **charge_items** (the charge-capture ledger — every chargeable event posts a row; see the Billing architecture note); bills gained `admission_id`, `cgst_amount`/`sgst_amount`/`igst_amount`, `insurance_transaction_id`, `insurer_name`/`policy_number`; wards/admissions gained room/nursing `daily_rate` + snapshot; `insurance_transactions` reconciled (real cols `insurer_code`/`type`/`external_reference_id`, with back-compat model aliases).
- **IAM / security** — **account_activity** (append-only audit trail: login/logout/failed-login rows from `WebAuthController` **plus** create/update/delete rows written by the `LogActivity` middleware — see the IAM architecture note; nullable hospital_id); users gained `last_login_at`/`last_login_ip`.
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
app/Http/Controllers/Api/  — WhatsAppWebhookController, IntegrationController (voice-AI/chatbot: customer, register-patient, doctor-schedule, my-appointments, book/reschedule/cancel-appointment, book-lab-test, hospitals — see the Voice-AI API note); ImportController (bulk CSV import hub)
app/Modules/Analytics/Services/RevenueInsights.php  — shared analytics engine behind every *-insights page (see Insights note)
docs/VOICE_AI_API.md  — hand-off API reference for the external voice-AI partner
config/medos.php      — central config (AI, WhatsApp, triage, queue, scheduling, insurance, languages)
config/regions.php    — India vs UAE region config (currency, languages, health ID, insurance, etc.)
routes/web.php        — all web routes
routes/api_v1.php     — all API routes
```

## Routes cheat sheet
```
/login                          — auth
/admin/*                        — dashboard, analytics, opd-insights, patients, appointments(+schedule), queue (Live Queue: doctor+lab, room-screen links), counter (Payment & Token), info-desk, inpatients(/ip), billing, staff, settings, api-keys, slots, tests, medicines, import (bulk CSV), assets (register + import/export), activity (Activity Log — this hospital)
*-insights                      — per-module analytics dashboards, all off RevenueInsights: web.{pharmacy,lab,billing,ip}.insights, web.admin.opd-insights, web.claims.insights (see Insights note)
/doctor/*                       — queue, stats, my-patients, my-appointments, history, referrals, complete/{id}, call-next/{id}, queue-json
/kiosk/*                        — index, register, checkin, check-phone, verify-abha, match-doctors, doctors, queue-display, room/{doctorId|name} (room/lab & room/imaging render the lab board; all honor ?hospital=slug), q/{id} (patient phone view)
/book                           — PUBLIC patient web/mobile booking (no auth): ?hospital=slug → pick doctor → future slot → book. /book/doctors, /book/slots/{id}, /book/confirmed/{token}
/billing/*                      — bills, dashboard, services (rate card + GST), new (itemized), export (CSV/Tally), {id} (payments/refund/cancel/deposit), compile/{encounter} (ledger→bill), print (TAX INVOICE)
/claims                         — insurance claims worklist; file/{bill}, {claim}/approve|deny (InsuranceWebController)
/ip/*                           — inpatient: dashboard (bed board), admissions, {id} (case sheet + running bill), {id}/charge|bill|discharge
/chat                           — WhatsApp bot simulator (scopes to ?hospital_id / session)
/super-admin                    — hospital management; /super-admin/users (IAM: accounts by hospital, orphan detection, {id} sign-in history); /super-admin/activity (platform-wide Activity Log, hospital-filterable)
/ajax/doctor-slots/{id}         — 14-day slot calendar (staff) · /ajax/patients?q= · /ajax/info-desk?q= (token|name auto-detect)
/api/v1/auth/login              — API token (send Accept: application/json + email/password, NOT "admin")
/api/v1/{customer,doctor-schedule,my-appointments,hospitals} (GET) · {register-patient,book-appointment,reschedule-appointment,cancel-appointment,book-lab-test} (POST) — voice-AI/chatbot integration (Sanctum, throttle:30,1; multi-hospital via X-Hospital-ID — see Voice-AI API note)
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
- **IAM / security** — Super-Admin user management: `SuperAdminController::users/userDetail/toggleUserActive/deleteUser` (`/super-admin/users`) lists accounts grouped by hospital, flags orphan (no/dangling hospital) + unknown-role accounts, and drills into per-account **sign-in history**. `WebAuthController` records login/logout/failed-login into `account_activity` via `AccountActivity::record()` and stamps `users.last_login_at`.
- **Super Admin is platform-level (no home hospital)** — `super_admin` users are seeded with `hospital_id = null` (see `StaffSeeder` + the `detach_super_admins_from_hospital` migration), so no hospital card shows an "operating here" marker and the top bar reads "All Hospitals". Because hospital-scoped admin screens (Settings, Analytics, Activity Log) still need a target, **`AdminWebController::effectiveHospitalId()`** resolves it: a normal admin's own `hospital_id`, or for a super admin the hospital they picked (via the Settings hospital-picker `?hospital_id=`, remembered in `session('sa_settings_hospital')`), else the first active hospital. Never hard-code `Auth::user()->hospital_id` on those shared screens — route it through this helper.
- **Account activity log (audit / incident trail)** — the `LogActivity` middleware (registered on the `web` group in `bootstrap/app.php`, runs in `terminate()` so it adds no latency) records every **successful** state-changing request (POST/PUT/PATCH/DELETE) to `account_activity` with a human-readable `description`, alongside the auth rows above. Read it at `/admin/activity` (Hospital Admin — scoped via `effectiveHospitalId`) and `/super-admin/activity` (all hospitals, hospital-filterable) — both render `admin/activity-log.blade.php` with filters (action/search/date). Not retroactive; it logs forward from deploy.
- **Backup & Restore** — two levels. (1) **Full DB**: `SuperAdminController::downloadFullBackup` streams the whole `database.sqlite` (the Hostinger DR net — restore by re-uploading the file in hPanel). (2) **Per-hospital**: **`HospitalBackup`** (`app/Modules/Core/Services`) `export()`/`import()` — a JSON envelope of one hospital's rows (hospital row + every `hospital_id` table). Restore is **additive + tenant-safe**: every row is forced to the TARGET `hospital_id` (never writes into another tenant) and uses `insertOrIgnore` (re-adds deleted rows, never overwrites); the `hospitals` table is never restored (restore INTO an existing hospital only). Super Admin backs up/restores any hospital (`hospitals/{id}/backup|restore`, Manage page card); a Hospital Admin backs up/restores **their own** (`admin/backup`, `admin/restore`, on the Import page) via `AdminWebController` scoped through `effectiveHospitalId()`. Prefer JSON over raw SQL here — never execute uploaded SQL against the live DB.
- **Per-hospital config** — free-form settings live on `Hospital.config` (JSON): `departments`, `areas` (waiting/lab area strings shown at kiosk check-in — `KioskController::hospitalArea()`), `gstin`/`gst_state`, plus `billing_integration`/`whatsapp`/etc. Mirror the `AdminWebController::saveDepartments`/`saveAreas`/`saveGstDetails` idiom (load with defaults in `settings()`, save-back to `config`).
- **Module gating** — `Hospital::isModuleEnabled($key)` (empty `modules_enabled` ⇒ everything on) + the `module:<key>` middleware (`EnsureModuleEnabled`) 404s disabled modules; `AppServiceProvider` shares a `$moduleOn('key')` closure to every view for hiding nav/UI. Catalog of toggleable keys: `App\Modules\Core\Support\ModuleCatalog`.
- **Shared UI** — every add/edit popup uses the `<x-modal show="alpineVar" title="…" max="lg">` component (`resources/views/components/modal.blade.php`); pass `body-class=""` when the slot brings its own padding. Don't hand-roll modal overlays. Dashboards use `<x-stat-card label value accent icon xtext>` (KPI tiles; `accent`/`icon` are lookup keys so the classes survive the no-rebuild pipeline — see the file) and `<x-dashboard-header subtitle>` — note the header's `title` prop is intentionally **not** rendered (the page title already shows in the top bar via `@yield('page-title')`); it emits only the subtitle + an optional `actions` slot, so don't re-add an in-page `<h2>` title.
- **Insights / reporting** — `RevenueInsights` (`app/Modules/Analytics/Services`) powers every period-over-period insights page (billing, lab, pharmacy, OPD, IPD, **claims**, plus the upgraded admin `analytics` + doctor `stats`). Add a page = 1 controller method calling `range()`/`totals()`/`trend()`/`items()`/`bySource()` + a Blade view using `<x-insights.{kpi,period-tabs,trend}>`. Its **generic `series($rows,$dateFn,…)`** buckets ANY row set (appointments/admissions/claims), not just the charge ledger. Two rules: revenue on hospital-wide finance pages (billing, admin analytics) comes from the **Bill** ledger (reconciles with payments; ledger-only revenue can diverge from bills on legacy data), while pharmacy/lab read `charge_items` directly; and chart/accent classes must be **compiled-CSS-safe** (see Tailwind gotcha — e.g. `text-indigo-600` is absent, so indigo is bar-only). Separately, **`/billing/audit`** (`BillingWebController::audit`) is the revenue-cycle audit hub: it rolls `charge_items` up by department/source with a GST-by-rate summary and CSV/Tally exports (`audit.charges`/`audit.gst`/`audit.payments`) — pure read over the charge ledger, no schema of its own. Note the two halves read **different** tables: the department/GST panels read `charge_items`, **Collections reads `bill_payments`** — so bills created without posting charges (seeded/imported/legacy) show zero departments but real collections. **`BillLedgerBackfill`** (`app/Modules/Billing/Services`) closes that gap: for any bill with no `charge_items`, it derives them from `line_items` (categorised via the medicine/test masters + keywords → consultation/lab/imaging/pharmacy/consumable/room/…; tagged `posted_by_name='Ledger backfill'`, non-taxable since historical per-line GST is unknown). Idempotent at bill granularity; runs on `audit()` view (covers future hospitals automatically) + migration `2026_07_14_000002` (all hospitals on deploy).
- **Idempotent migrations** — new migrations guard with `Schema::hasTable`/`Schema::hasColumn` and wrap sample-seed blocks in count checks, because prod runs them via `/public/deploy.php` and pages must not 500 if a table isn't there yet (controllers also guard with `Schema::hasTable`).
- **Pluggable adapter + registry pattern** — used for external integrations: `app/Modules/Billing/Integrations/*` (`BaseBillingConnector` abstract + `GenericWebhookConnector` + `BillingConnectorRegistry::make($code)`), mirroring `app/Modules/Insurance/Providers/*` + `InsuranceService::resolveProvider()`. To add a connector/gateway = one subclass + one registry line; per-hospital config + encrypted secrets live in `Hospital.config['<section>']` (save idiom = `AdminWebController::saveBillingIntegration` / `saveWhatsappSettings`, "•••• saved" secret pattern). The **Payment gateway** layer (Phase 2, `app/Modules/Billing/Payments/*`) follows the same shape.
- **External billing / ERP sync** — `BillObserver` (the app's one model observer, registered in `BillingServiceProvider`) fires on Bill create + `payment_status` change → dispatches the queued `SyncBillToExternal` job → HMAC-signed POST to the hospital's configured endpoint, logged to `billing_integration_logs`. So **any** created/paid/refunded bill auto-flows to the external ERP. Queue = `database` driver → prod needs an hPanel cron `queue:work --stop-when-empty` to drain it (same for WhatsApp/notification jobs).
- **Role-based access** — `WebAuthController::landingRoute($role)` routes each role to its work area on login; the sidebar (`_sidebar_nav.blade.php`) renders a per-role `@if($is<Role>)` block. **Dentist** and **Dietitian** (`UserRole::Dentist`/`Dietitian`, both `isClinical`) are first-class staff roles: they land on and are sidebar-scoped to their own module (Dental / Clinical Nutrition), are bookable from the chatbot (`SpecialtyMapper` tooth→dental, food/diet→nutrition), kiosk and admin dropdowns (all doctor-selection queries use `whereIn('role', ['doctor','hospital_admin','dentist','dietitian'])`), and — like doctors — see **only their own** appointments (`AdminWebController::appointments` scopes to `$user->staff->id` for practitioners). The `Consult →` button on a practitioner's appointment deep-links into their module for that patient via `?patient=<id>` (`$consultModule` map → module's `index(?patient=)` focus). Each clinical module has a chairside/toolkit tab (Dental anaesthetic-dose calculator + references; Clinical Nutrition calorie/protein/fluid calculator + advice tables). Hospital admin issues long-lived Sanctum API keys at `/admin/api-keys` (billing:read / billing:write abilities enforced by `ability:` middleware on `/api/v1/billing/*`); `resolve.hospital` pins non-super-admin tokens to their own hospital (rejects a mismatched `X-Hospital-ID`).
- **Public patient surfaces** — booking (`/book`, `PublicBookingController`) and kiosk (`/kiosk`) are the only patient-facing, no-auth web flows; both resolve the hospital by `?hospital=slug`/session and extend `layouts/public` (booking) or `layouts/kiosk`. Slot availability comes from the shared `DoctorSlotService::calendar()`. Guard new public routes with `throttle` and hospital-scope every query.
- **Bulk CSV import** — `ImportController` (admin-only, `/admin/import`) is a hub driven by a `COLUMNS`/`SAMPLE`/`import{Type}()` spec: types are patients, staff, medicines, pharmacy_stock, tests, services. Add a type = one `COLUMNS` entry + one `SAMPLE` row + one handler + a card in `admin/import.blade.php`. Native CSV parse (no PhpSpreadsheet); every row hospital-scoped + deduped; **staff import creates a login `user` per row** (insert staff → user → link `user_id`; passwords surfaced via `import_credentials` flash). Assets have their **own** import/export on the register toolbar (`AssetController::import`/`importTemplate`/`exportCsv`/`report`) — resolves vendor by name, `salvage_value` is NOT NULL so default it to 0. Templates download the exact headers.
- **Voice-AI / multi-hospital integration API** (`IntegrationController`, `docs/VOICE_AI_API.md`) — the endpoints an external voice-AI/chatbot partner uses: `customer`, `register-patient` (idempotent by phone), `doctor-schedule` (fuzzy name), `my-appointments`, `book-appointment`, `reschedule-appointment`, `cancel-appointment`, `book-lab-test` (resolves test names → lab/imaging/procedure `orders` + `ChargeCapture::captureOrder`), and `hospitals` (directory). **Multi-tenant is the whole point:** `hid()` reads the hospital resolved by the `resolve.hospital` middleware (`HospitalContext` → `X-Hospital-ID` header → subdomain → token's own hospital), NOT `Auth::user()->hospital_id`. So ONE super-admin (platform) token serves every hospital by sending `X-Hospital-ID: <id>`, while a hospital token stays pinned (mismatched header → 422). Booking is atomic (encounter insert → `lockForUpdate` slot re-check → 409 on clash). When adding endpoints here, use `$this->hid()` (never the auth user's hospital) and keep them `throttle:30,1`.

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
- **Never `json_encode()` a value into a model whose column is cast to `array`/`json`** (e.g. `Hospital.config`/`modules_enabled` — both cast to `array`). The cast encodes once; passing a pre-encoded string double-encodes, and on read the cast decodes one level → returns a **string**, which then fatals `in_array()`/`isModuleEnabled()` ("Argument #2 must be array, string given") — a 500 on every page for that hospital. Pass the raw PHP array to `Model::create/update`; only use `json_encode()` with `DB::table()->insert` (raw, no cast). Migration `2026_07_14_000001` repairs any already-double-encoded hospital rows.
