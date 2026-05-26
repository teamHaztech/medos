# MedOS — Complete System Context for AI Prompts

**Use this file to give any AI full context about MedOS before asking it to write prompts or plan features.**

---

## What is MedOS?

AI-first Hospital Operating System that automates patient booking (WhatsApp), check-in (kiosk), queue management (voice announcements), doctor consultation (one-tap prescriptions), lab, pharmacy, billing, and follow-ups. Built by Haztech (AI Agency, Margao Goa India).

**Live:** https://medos.haztech.cloud
**GitHub:** https://github.com/teamHaztech/medos
**Hosting:** Hostinger shared (no SSH, no terminal — only File Manager + Git deploy)

---

## Tech Stack

| Component | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| Database | SQLite (file-based) |
| Frontend | Blade + Tailwind CSS v4 + Alpine.js |
| Auth | Laravel Sanctum |
| WhatsApp Bot | whatsapp-web.js (Node.js) |
| IDs | UUID everywhere |
| Multi-tenant | hospital_id on every table |
| PDF | Browser print (@media print CSS) — no server-side PDF |
| Deployment | Git push → hPanel Git pull → deploy.php runs migrations |

**Rules:** No React/Vue/jQuery. No SSH on server. Must commit vendor/ and public/build/. SQLite only (no MySQL). All enums: `is_object($x) ? $x->value : $x`.

---

## Database (23 tables)

### Core
- **hospitals** — id, name, slug, country (IN/AE), city, config (JSON), modules_enabled
- **users** — id, name, email, password, role (super_admin/hospital_admin/doctor/nurse/receptionist/pharmacist/lab_tech/billing_staff), hospital_id, staff_id
- **staff** — id, hospital_id, name, email, role, department, specialization, schedule (JSON), consultation_duration_default

### Patient Journey
- **patients** — id, hospital_id, name, phone, gender, date_of_birth, age_approximate, language_preference, allergies (array), current_medications (array), insurance_details (encrypted:array), abha_number, created_via
- **encounters** — id, hospital_id, patient_id, doctor_id, encounter_number, type, status, channel, intake_data (array with chief_complaint), soap_notes (encrypted:array), diagnosis_codes (array), abha_number, abha_linked, discharged_at
- **appointments** — id, hospital_id, encounter_id (nullable), patient_id, doctor_id, slot_start, slot_end, status, check_in_time, consultation_start_time, consultation_end_time, booking_source, notes (stores token like "PED-001")
- **conversations** — AI chat history
- **referrals** — from_doctor_id, to_doctor_id, urgency, reason, preferred_date/time, status

### Operations
- **orders** — id, encounter_id, patient_id, ordered_by, type (lab/pharmacy/imaging/procedure), status, items (JSON array), results (JSON), sample_collected_at, verified_by
- **bills** — id, encounter_id, patient_id, bill_number, line_items (JSON), subtotal, tax_amount, insurance_covered, patient_payable, payment_status, payment_method
- **queue_entries** — real-time queue positions
- **medicines** — 120+ medicines with dosage, frequency, timing defaults
- **available_tests** — 74 lab/imaging/procedure tests with prices
- **pharmacy_stock** — medicine_id, batch_number, expiry_date, quantity_available, selling_price

### ABHA (India health ID)
- **abha_consents** — consent lifecycle (request/grant/deny/revoke)
- **abha_health_records** — FHIR-compatible records
- **abha_audit_logs** — every ABHA action logged

### System
- **notifications_log** — WhatsApp/SMS/email notification tracking
- **audit_logs** — all data changes logged

---

## Modules & File Structure

```
app/Modules/
├── Core/           Models (Hospital, Staff, Order, AuditLog), Enums (8), Traits, Services (RegionService, WhatsAppNotifier, HospitalContext)
├── Auth/           Sanctum login, hospital setup, role-based abilities
├── AIReceptionist/ Conversation state machine, AI service, emergency detector
├── WhatsApp/       Meta Cloud API, webhook, message jobs, templates
├── Triage/         Rule engine (8 rules), clinical scorer (60+ complaints), specialty mapper
├── Appointment/    Smart scheduling, slot generator
├── Queue/          Priority scoring, real-time management
├── Insurance/      UAE DHA + India TPA providers
├── Billing/        Bill generation, payments
├── Patient/        Patient CRUD, search, timeline
├── DoctorAssist/   Pre-consultation briefings
├── Engagement/     Reminders, feedback, re-engagement
├── Multilingual/   Language detection, medical dictionary (EN/HI/AR)
├── Analytics/      KPIs, revenue, doctor performance
├── ABHA/           Ayushman Bharat health ID integration
├── Pharmacy/       PharmacyStock model
└── Lab/            (via LabController)

app/Http/Controllers/Web/
├── AdminWebController     — dashboard, patients CRUD, staff CRUD, appointments, settings, slots, tests, medicines, analytics
├── DoctorWebController    — queue, stats, my-patients, my-appointments, history, referrals, complete consultation, call next
├── KioskController        — register, check-in, room display, queue live view, ABHA verify, doctor matching
├── ChatController         — WhatsApp bot state machine (9 states, multilingual, session persistence by phone)
├── SuperAdminController   — hospital CRUD, region switching
├── BillingWebController   — bill list, create, show, payment, print receipt, print prescription, discharge summary
├── LabController          — dashboard, collect sample, enter results, verify
├── PharmacyController     — dashboard, dispense, stock management
└── WebAuthController      — login/logout
```

---

## Key Flows

### Patient Books via WhatsApp (/chat)
greeting → ask_phone → (recognize patient or ask_name) → main_menu → ask_complaint → show_doctors → confirm_booking → create Patient + Encounter + Appointment → completed

### Patient Arrives (Kiosk /kiosk)
Enter phone/ABHA → pick problem (16 icons) → system matches doctors by specialty → pick doctor → Token generated → Appointment + Encounter created → shows in doctor queue within 5 seconds

### Doctor Consultation (/doctor)
Queue (live 5s refresh) → click patient → AI briefing → Start Consultation → 6 tabs (Vitals, Diagnosis, Tests, Prescription, Refer, Notes) → Complete → saves prescriptions as pharmacy orders, tests as lab orders → WhatsApp notification sent

### Room Display (/kiosk/room/priya)
TV screen per doctor → shows Now Serving + queue → voice announcement in 5 languages (EN/HI/MR/KOK/AR) → QR code for patients to scan and see queue on phone (/kiosk/q/priya)

### Lab Flow (/lab)
Orders appear → collect sample → enter results → verify → WhatsApp notification to patient

### Pharmacy Flow (/pharmacy)
Prescription orders appear → dispense → stock management with batch/expiry alerts

### Billing Flow (/billing)
Generate from encounter → line items from orders → record payment → print receipt/prescription/discharge summary

---

## Region System (India vs UAE)

Config at `config/regions.php`. RegionService auto-detects from hospital.country.

| Feature | India (IN) | UAE (AE) |
|---|---|---|
| Currency | ₹ INR | AED |
| Health ID | ABHA 14-digit | Emirates ID 15-digit |
| Insurance | PMJAY + TPA | DHA / DoH |
| Languages | EN, HI, MR, KOK + 6 more | EN, AR |
| Tax | GST 18% | VAT 5% |
| Phone | +91 (10 digits) | +971 (9 digits) |
| Emergency | 108 | 999 |

---

## WhatsApp Notifications (automatic)

| Event | Message sent to patient |
|---|---|
| Appointment booked | ✅ Confirmed — doctor, date, time, token |
| Appointment cancelled | ❌ Cancelled — reason |
| Appointment rescheduled | 🔄 Old slot → new slot |
| Consultation complete | ✅ Done — visit pharmacy/lab, follow-up |
| Lab results ready | 🧪 Results ready — test names |
| Appointment reminder | ⏰ 24h or 2h before |

Messages are multilingual (EN/HI/AR) based on patient.language_preference. Currently queued in notifications_log table.

---

## Security (implemented)

- SecurityHeaders middleware (HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy)
- Chat bot wrapped in try-catch (never leaks stack traces)
- Input sanitization (strip_tags, length limits)
- Secure cookies (secure=true, same_site=strict)
- Role-based access (admin middleware on management pages)
- ABHA consent system (no data shared without patient consent)
- Audit logs for all data changes
- Double-booking prevention (DB transaction lock)

---

## Login Credentials

| Email | Password | Role |
|---|---|---|
| superadmin@haztech.in | password123 | Super Admin |
| admin@haztech.in | password123 | Hospital Admin |
| priya@haztech.in | password123 | Doctor (Pediatrics) |
| amit@haztech.in | password123 | Doctor (Cardiology) |

---

## All Routes (key ones)

```
/login                     Auth
/admin/*                   Dashboard, Patients, Appointments, Staff, Settings, Slots, Tests, Medicines, Analytics
/doctor/*                  Queue, Stats, My Patients, My Appointments, History, Referrals
/kiosk/*                   Home, Register, Check-in, Queue Display, Room Display, Patient Queue View
/kiosk/q/{name}            Patient scans QR to see live queue on phone
/chat                      WhatsApp bot simulator
/super-admin               Hospital management
/lab                       Lab dashboard, results, verify
/pharmacy                  Pharmacy dashboard, dispense, stock
/billing                   Bill list, create, show, payment, print
/billing/{id}/print        Print receipt (standalone A4)
/prescriptions/{id}/print  Print Rx slip (standalone)
/encounters/{id}/discharge Print discharge summary (standalone)
/ajax/medicines?q=         Medicine search
/ajax/tests                Test list
/ajax/doctor-slots/{id}    14-day slot calendar
```

---

## How to Write Good Prompts for MedOS Development

### Template:
```
I'm building a feature for MedOS (Laravel 13 Hospital OS).

**What I want:** [describe the feature]

**Where it fits:**
- Module: [which module — admin/doctor/kiosk/billing/etc.]
- Pages affected: [list URLs]
- Database: [new tables needed? existing tables modified?]

**Constraints:**
- Blade + Tailwind + Alpine.js only (no React)
- SQLite database (no MySQL-specific SQL)
- Shared hosting (no SSH, no terminal, no headless Chrome)
- Must commit vendor/ and public/build/
- All IDs are UUID, multi-tenant via hospital_id
- Enums: is_object($x) ? $x->value : $x
- Money: use RegionService::currency()
- Patient data: encrypted where needed

**Expected output:**
- Migration files
- Model updates
- Controller methods
- Blade views
- Route additions
- Sidebar link if needed
```

### Tips for token-efficient prompts:
1. Say exactly which files are affected — don't make AI search
2. Paste the exact error if reporting a bug
3. Batch related changes in one prompt
4. Reference existing patterns: "same as how pharmacy module works"
5. Say what should NOT change: "don't touch the doctor dashboard"
