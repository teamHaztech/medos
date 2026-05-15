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

## Logins
| Email | Password | Role |
|---|---|---|
| superadmin@haztech.in | password123 | Super Admin |
| admin@haztech.in | password123 | Hospital Admin |
| priya@haztech.in | password123 | Doctor (Pediatrics) |
| amit@haztech.in | password123 | Doctor (Cardiology) |

## Database (19 tables)
hospitals, users, staff, patients, encounters, appointments, conversations, insurance_transactions, orders, bills, queue_entries, notifications_log, audit_logs, referrals, medicines(120), available_tests(74), abha_consents, abha_health_records, abha_audit_logs, personal_access_tokens

### Key columns to remember
- **patients**: name (NOT first_name), phone, abha_number, language_preference (NOT preferred_language), insurance_details (encrypted:array), allergies/medical_history/current_medications (array cast)
- **encounters**: encounter_number, intake_data (array cast with chief_complaint key), soap_notes (encrypted:array), status/type/triage_classification are PHP enums — extract with `is_object($x) ? $x->value : $x`
- **appointments**: slot_start/slot_end (datetime), notes (stores token like "PED-001"), booking_source, status is AppointmentStatus enum
- **staff**: name, department, specialization, schedule (JSON), consultation_duration_default (NOT consultation_duration_minutes)
- **bills**: bill_number, total_amount, insurance_covered, patient_payable (NOT insurance_amount, NOT patient_amount)

## File Structure
```
app/Modules/          — 15 modules (Core, Auth, AIReceptionist, WhatsApp, Triage, Appointment, Queue, Insurance, Billing, Patient, DoctorAssist, Engagement, Multilingual, Analytics, ABHA)
app/Http/Controllers/Web/  — AdminWebController, DoctorWebController, KioskController, ChatController, SuperAdminController, WebAuthController
app/Http/Controllers/Api/  — WhatsAppWebhookController
config/medos.php      — central config (AI, WhatsApp, triage, queue, scheduling, insurance, languages)
config/regions.php    — India vs UAE region config (currency, languages, health ID, insurance, etc.)
routes/web.php        — all web routes
routes/api_v1.php     — all API routes
```

## Routes cheat sheet
```
/login                          — auth
/admin/*                        — dashboard, patients, appointments, staff, settings, slots, tests, medicines, analytics
/doctor/*                       — queue, stats, my-patients, my-appointments, history, referrals, complete/{id}, call-next/{id}, queue-json
/kiosk/*                        — index, register, checkin, check-phone, verify-abha, match-doctors, doctors, queue-display, room/{name}
/chat                           — WhatsApp bot simulator
/super-admin                    — hospital management
/ajax/medicines?q=              — medicine search
/ajax/tests?type=               — tests list
/ajax/doctor-slots/{id}         — 14-day slot calendar
/api/v1/auth/login              — API token
/api/v1/abha/*                  — ABHA endpoints
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
- Tailwind v4 uses @source directives for class scanning — if new classes don't compile, check resources/css/app.css
- `lg:!translate-x-0` on sidebar uses !important to override Alpine.js
- SQLite doesn't support TIMESTAMPDIFF — use Carbon diffInMinutes() in PHP
- Patient model has Auditable trait — if AuditLog insert fails, check column names (action NOT event, no user_name column)
- Appointment.encounter_id is nullable (walk-ins may not have encounter yet)
- Seeder uses SeedData.php constants for shared UUIDs across seeders
