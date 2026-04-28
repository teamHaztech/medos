# MedOS - Hospital Operating System
## Complete System Lifecycle Document

**Version:** 1.0
**Date:** March 2026
**Built by:** Haztech Digital Innovation Agency

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Complete Patient Journey](#2-complete-patient-journey)
3. [Module Architecture](#3-module-architecture)
4. [Detailed Lifecycle Flow](#4-detailed-lifecycle-flow)
5. [Background Processes](#5-background-processes)
6. [Data Flow Summary](#6-data-flow-summary)
7. [API Reference](#7-api-reference)
8. [Web Pages Reference](#8-web-pages-reference)
9. [Database Schema](#9-database-schema)
10. [Tech Stack](#10-tech-stack)

---

## 1. System Overview

MedOS is an AI-native Hospital Operating System that automates the entire patient journey from first WhatsApp message to long-term retention. It replaces 80% of reception interactions with AI while keeping doctors in full control.

**Key Numbers:**
- 14 backend modules (87 PHP files)
- 17 database tables
- 73 API + web routes
- 25 tested endpoints (all passing)
- 11 web pages (all working)
- 5 artisan commands + scheduled jobs
- 4 event-driven listeners

**Target Markets:** India (primary), UAE (secondary)
**Primary Interface:** WhatsApp (500M+ users in India, no app download needed)
**Languages:** English, Hindi/Hinglish, Arabic

---

## 2. Complete Patient Journey

The patient journey has 13 stages, each mapped to actual code modules:

### Stage 1: First Contact
Patient sends a message via WhatsApp, phone call, web, or walks into the kiosk.

**Code Path:** `WhatsApp Webhook` -> `ProcessIncomingMessage Job` -> `ConversationManager`

### Stage 2: AI Receptionist
The AI identifies/creates the patient, detects language, and begins conversational intake.

**Code Path:** `ConversationManager::handleIncomingMessage()` -> `LanguageDetector` -> `AIService`

### Stage 3: AI Intake
Conversational extraction of symptoms (not a form). AI asks follow-up questions naturally.

**Code Path:** `IntakeHandler::handle()` -> `AIService::extractIntakeData()` -> Structured JSON output

### Stage 4: Smart Triage
Three-layer safety-first scoring: Rules (always win) -> Clinical (ESI-adapted) -> ML (can only increase)

**Code Path:** `TriageService::assess()` -> `RuleEngine` + `ClinicalScorer` -> `SpecialtyMapper`

### Stage 5: Insurance Pre-Flight
Verifies eligibility, coverage, and co-pay BEFORE the appointment is booked.

**Code Path:** `InsuranceService::verifyEligibility()` -> `UAEDHAProvider` / `IndiaTPAProvider`

### Stage 6: Smart Scheduling
Constraint-optimization: matches patient to optimal doctor slot based on urgency, preference, load balance.

**Code Path:** `SchedulingService::findOptimalSlots()` -> `SlotGenerator::generate()` -> Score + rank

### Stage 7: Booking Confirmation
Creates appointment, generates token, sends WhatsApp confirmation with QR code.

**Code Path:** `BookingHandler::confirmBooking()` -> `SendWhatsAppMessage Job` -> `TemplateManager`

### Stage 8: Reminders
Automated reminders at 24h and 2h before appointment.

**Code Path:** `medos:send-reminders` (cron every 15 min) -> `SendAppointmentReminder Job`

### Stage 9: Arrival & Check-In
Patient checks in via WhatsApp geo-fence, kiosk QR scan, or token entry.

**Code Path:** `KioskController::processCheckin()` or `AppointmentController::checkIn()` -> `QueueService::addToQueue()`

### Stage 10: Queue Management
Real-time priority queue with weighted scoring and active reshuffling.

**Code Path:** `QueueService::reshuffleQueue()` -> `EvaluateQueue Job` (every 60s) -> WhatsApp notifications

**Priority Formula:**
```
score = urgency(0.40) + wait_time(0.25) + appointment(0.20) + age(0.10) + vip(0.05)
```
Starvation prevention: +0.01 per minute waited, auto-escalate at 45 minutes.

### Stage 11: Consultation
Doctor sees AI-generated briefing, manages queue, writes SOAP notes, orders labs/pharmacy.

**Code Path:** `BriefingService::generateBriefing()` -> `DoctorDashboardController` -> `completeConsultation()`

### Stage 12: Post-Consultation Cascade
Event-driven: auto-billing, insurance claim, lab/pharmacy routing, follow-up scheduling.

**Code Path:** `EncounterCompleted Event` -> `EncounterCompletedListener` triggers:
- `BillingService::generateBill()`
- `InsuranceService::submitClaim()`
- `EngagementService::scheduleFollowUpReminder()`
- `SendFeedbackRequest Job` (2h delay)

### Stage 13: Retention & Re-engagement
Medication reminders, follow-up scheduling, feedback collection, chronic patient re-engagement.

**Code Path:** `medos:re-engage` (weekly cron) -> `EngagementService::checkReEngagement()`

---

## 3. Module Architecture

```
medos/app/Modules/
|-- Core/              # Models, Enums, Traits, Events, Services
|-- Auth/              # Sanctum auth, login, hospital setup
|-- AIReceptionist/    # Conversation state machine, AI service, prompts
|-- WhatsApp/          # Meta/Gupshup API, webhooks, message jobs
|-- Triage/            # Rule engine, clinical scorer, specialty mapper
|-- Appointment/       # Smart scheduling, slot generator
|-- Queue/             # Priority queue, reshuffle, events
|-- Insurance/         # UAE DHA + India TPA, eligibility, claims
|-- Billing/           # Auto bill generation, payments
|-- Patient/           # Patient service, search, timeline
|-- DoctorAssist/      # Pre-consultation briefings
|-- Engagement/        # Reminders, feedback, re-engagement
|-- Multilingual/      # Language detection, medical dictionary
|-- Analytics/         # KPIs, revenue, doctor performance
|-- Admin/             # Dashboard, RBAC middleware
```

### Module Details

| Module | Files | Purpose |
|--------|-------|---------|
| Core | 15 | Hospital, Staff, AuditLog models; 8 enums; HasUuid, BelongsToHospital, Auditable traits |
| Auth | 3 | Sanctum token auth with role-based abilities |
| AIReceptionist | 11 | Conversation state machine (10 states), dual-provider AI (Claude/GPT), emergency detection |
| WhatsApp | 6 | Meta Cloud API + Gupshup, webhook handling, async message jobs, 7 templates |
| Triage | 5 | 8 hard-coded clinical rules, ESI-adapted scorer (60+ complaints), multilingual keywords |
| Appointment | 4 | Constraint-based scheduling, dynamic slot sizing, overbooking intelligence |
| Queue | 5 | Weighted priority scoring, starvation prevention, real-time reshuffling |
| Insurance | 7 | UAE DHA + India TPA providers, eligibility cache, pre-auth, claim lifecycle |
| Billing | 3 | Auto bill generation from encounters, insurance coverage, payment links |
| Patient | 3 | Find/create by phone, fuzzy search, merged timeline, patient merge |
| DoctorAssist | 2 | Pre-consultation briefing from patient graph |
| Engagement | 3 | Follow-up reminders, feedback (alert if < 3), chronic re-engagement |
| Multilingual | 3 | Script detection, Hinglish detection, 60+ medical terms in EN/HI/AR |
| Analytics | 2 | Dashboard KPIs, hourly flow, doctor metrics, AI metrics, revenue breakdown |

---

## 4. Detailed Lifecycle Flow

### Phase 1: Patient First Contact via WhatsApp

```
Patient WhatsApp Message
    |
    v
WhatsApp Webhook (POST /api/v1/whatsapp/webhook)
    |-- WebhookController@handle
    |-- Parse Meta payload (entry[].changes[].value.messages[])
    |-- Extract: phone, message_type, content, message_id
    |-- Return 200 immediately
    |
    v
ProcessIncomingMessage Job (queued)
    |-- Mark message as read (blue ticks)
    |-- Resolve hospital context
    |-- Download media if image/document
    |
    v
ConversationManager::handleIncomingMessage()
    |-- Find or create Patient (by phone number)
    |-- Find active Conversation or create new one
    |-- Run EmergencyDetector (fast, no AI, keyword-based)
    |       |-- English: "chest pain", "can't breathe", "unconscious"
    |       |-- Hindi: "seene mein dard", "saans nahi", "behosh"
    |       |-- Arabic: specific Arabic keywords
    |       |-- If emergency detected -> EMERGENCY FLOW
    |
    v
processState() - State Machine Router
    |
    |-- State: GREETING
    |       |-- LanguageDetector::detect() (script + keyword analysis)
    |       |-- AIService::extractIntent() -> BookAppointment/Cancel/Status/etc
    |       |-- SystemPrompts::greeting() -> AI response
    |       |-- Transition -> INTAKE
    |
    |-- State: INTAKE
    |       |-- IntakeHandler::handle()
    |       |-- AI extracts conversationally (not a form)
    |       |-- Output: {chief_complaint, duration, severity, who}
    |       |-- isIntakeComplete()? -> Transition -> TRIAGE
    |
    |-- State: TRIAGE
    |       |-- TriageService::assess()
    |       |-- Layer 1: RuleEngine (8 rules, hard-coded, safety-first)
    |       |-- Layer 2: ClinicalScorer (ESI-adapted, 60+ complaint map)
    |       |-- Layer 3: ML (optional, can only increase score)
    |       |-- Output: score (0-1), classification, specialty
    |       |-- Transition -> INSURANCE_CHECK
    |
    |-- State: INSURANCE_CHECK
    |       |-- InsuranceService::verifyEligibility()
    |       |-- Check Redis cache -> API call -> Log transaction
    |       |-- Output: eligible, copay, coverage
    |       |-- Transition -> BOOKING
    |
    |-- State: BOOKING
    |       |-- BookingHandler::handle()
    |       |-- SchedulingService::findOptimalSlots()
    |       |-- Present top 3 options to patient
    |       |-- Patient selects -> confirmBooking()
    |       |-- Create Encounter + Appointment + Token
    |       |-- Transition -> CONFIRMATION
    |
    |-- State: CONFIRMATION
    |       |-- Send WhatsApp confirmation (template message)
    |       |-- Schedule reminders (24h, 2h)
    |       |-- Submit pre-auth if needed
    |       |-- Transition -> COMPLETED
```

### Phase 2: Patient Arrival & Check-In

```
Patient Arrives at Hospital
    |
    |-- Option A: WhatsApp Geo-fence Check-in
    |       |-- Auto-prompt when within 200m
    |       |-- Patient taps "Check In"
    |
    |-- Option B: Kiosk Check-in
    |       |-- Scan QR from WhatsApp confirmation
    |       |-- Or enter token number / phone
    |       |-- KioskController::processCheckin()
    |
    |-- Option C: Walk-in (no prior booking)
    |       |-- Kiosk initiates full AI intake
    |       |-- Or scan QR to continue on WhatsApp
    |
    v
Appointment::checkIn()
    |-- Update status -> checked_in
    |-- Set check_in_time
    |
    v
QueueService::addToQueue()
    |-- Calculate priority score (weighted formula)
    |-- Generate token (e.g., "GEN-007")
    |-- Set initial position
    |-- Notify patient: position + estimated wait
```

### Phase 3: Queue & Consultation

```
Queue Engine (runs every 60 seconds)
    |
    v
EvaluateQueue Job
    |-- For each doctor with active queue:
    |       |-- QueueService::reshuffleQueue()
    |       |-- Recalculate all priorities
    |       |-- Detect no-shows (5 min timeout)
    |       |-- Update estimated wait times
    |
    |-- If positions changed:
    |       |-- QueueUpdated Event -> QueueUpdateNotifier
    |       |-- WhatsApp: "You're now #2, wait ~8 min"
    |
    |-- Doctor clicks "Call Next":
    |       |-- QueueService::callNext()
    |       |-- PatientCalled Event -> PatientCalledNotifier
    |       |-- WhatsApp: "Dr. Meera is ready. Room 204"

Doctor Consultation
    |
    v
Doctor Dashboard (/doctor)
    |-- Left: Queue panel (token, name, complaint, urgency)
    |-- Right: Patient briefing (BriefingService)
    |       |-- Demographics + language
    |       |-- Chief complaint + intake data
    |       |-- Triage score + classification
    |       |-- Previous 5 encounters
    |       |-- Allergies + medications (highlighted)
    |       |-- Insurance status + coverage
    |
    |-- During consultation:
    |       |-- SOAP notes form
    |       |-- Quick-add orders (Lab/Pharmacy/Imaging)
    |
    |-- "Complete Consultation" clicked:
    |       |-- Save SOAP notes (encrypted)
    |       |-- Create orders
    |       |-- Encounter status -> completed
    |       |-- Fire EncounterCompleted Event
```

### Phase 4: Post-Consultation Cascade

```
EncounterCompleted Event
    |
    v
EncounterCompletedListener (queued)
    |
    |-- 1. AUTO-BILLING
    |       |-- BillingService::generateBill()
    |       |-- Consultation fee (by department)
    |       |-- + Lab charges + Pharmacy charges
    |       |-- Apply insurance coverage
    |       |-- Calculate patient payable
    |       |-- Send bill via WhatsApp with payment link
    |
    |-- 2. INSURANCE CLAIM
    |       |-- InsuranceService::submitClaim()
    |       |-- Auto-assemble: ICD-10 + CPT codes + docs
    |       |-- Submit to DHA/TPA portal
    |       |-- Track: submitted -> adjudicated -> paid/denied
    |
    |-- 3. FOLLOW-UP
    |       |-- If follow_up_date set:
    |       |-- Schedule reminder (24h before)
    |       |-- medos:process-follow-ups handles daily
    |
    |-- 4. FEEDBACK
    |       |-- SendFeedbackRequest Job (delayed 2 hours)
    |       |-- WhatsApp: "Rate your visit 1-5"
    |       |-- If rating < 3: alert admin + callback offer
    |
    |-- 5. LAB/PHARMACY ROUTING
    |       |-- If lab ordered: "Go to Lab, Floor 3"
    |       |-- Results -> patient graph -> doctor review
    |       |-- If pharmacy: Rx sent, "Ready in 8 min"
```

### Phase 5: Long-term Engagement

```
Ongoing (Background Cron Jobs)
    |
    |-- Every 15 min: medos:send-reminders
    |       |-- Find appointments in 24h/2h window
    |       |-- Send WhatsApp reminders
    |
    |-- Daily 9 AM: medos:process-follow-ups
    |       |-- Find encounters with follow_up_date = today
    |       |-- Send follow-up reminders
    |
    |-- Weekly Monday 10 AM: medos:re-engage
    |       |-- Find chronic patients (diabetes, BP, etc.)
    |       |-- No visit in 6+ months
    |       |-- Send gentle re-engagement message
    |
    |-- Return Patient (next visit):
    |       |-- System recognizes by phone number
    |       |-- Loads full patient graph (history, meds, allergies)
    |       |-- "Welcome back! Book with Dr. Meera for diabetes follow-up?"
    |       |-- Skip intake for known conditions
```

---

## 5. Background Processes

### Scheduler (Laravel Cron)

| Schedule | Command | Purpose |
|----------|---------|---------|
| Every 1 min | `medos:evaluate-queues` | Reshuffle active queues, detect no-shows |
| Every 15 min | `medos:send-reminders` | 24h and 2h appointment reminders |
| Daily 9 AM | `medos:process-follow-ups` | Follow-up reminders for today |
| Weekly Mon 10 AM | `medos:re-engage` | Chronic patient re-engagement |
| Daily | `sanctum:prune-expired` | Clean expired API tokens |

### Event Listeners (Queued)

| Event | Listener | Actions |
|-------|----------|---------|
| EncounterCompleted | EncounterCompletedListener | Bill, claim, feedback, follow-up |
| EmergencyDetected | EmergencyAlertListener | SMS to ER, dashboard alert, logging |
| QueueUpdated | QueueUpdateNotifier | WhatsApp position updates |
| PatientCalled | PatientCalledNotifier | WhatsApp "Doctor is ready" |

### Queue Workers

| Queue Name | Purpose |
|------------|---------|
| default | General jobs |
| whatsapp | Message sending (rate-limited 80/sec) |
| ai | AI processing (may be slow) |
| notifications | Reminders, engagement |

---

## 6. Data Flow Summary

```
INBOUND:
Patient -> WhatsApp API -> Webhook -> Job Queue -> ConversationManager -> AI -> Response

CONSULTATION:
Doctor Dashboard -> Briefing Service -> SOAP Notes -> Complete -> Event Cascade

OUTBOUND:
Events -> Listeners -> Jobs -> WhatsApp API -> Patient

ADMIN:
Admin Dashboard -> Analytics Service -> Database Aggregations -> JSON/Charts
```

---

## 7. API Reference

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/v1/auth/login | Login, get Sanctum token |
| POST | /api/v1/auth/logout | Revoke token |
| GET | /api/v1/auth/me | Current user + hospital |
| POST | /api/v1/auth/register | Register staff (admin only) |
| POST | /api/v1/auth/hospital/setup | Create hospital + admin |

### Patients
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/patients | List (paginated, searchable) |
| POST | /api/v1/patients | Create patient |
| GET | /api/v1/patients/{id} | Show patient |
| PUT | /api/v1/patients/{id} | Update patient |
| GET | /api/v1/patients/{id}/encounters | Patient encounters |
| GET | /api/v1/patients/{id}/timeline | Merged timeline |
| GET | /api/v1/patients-search | Search by phone |

### Appointments
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/appointments | List (filterable) |
| POST | /api/v1/appointments | Create appointment |
| GET | /api/v1/appointments/{id} | Show appointment |
| POST | /api/v1/appointments/{id}/check-in | Check in patient |
| POST | /api/v1/appointments/{id}/cancel | Cancel appointment |

### Queue
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/queue/doctor/{id} | Doctor's queue |
| POST | /api/v1/queue/doctor/{id}/call-next | Call next patient |
| GET | /api/v1/queue/patient/{id}/status | Patient position + wait |

### Doctor
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/doctor/dashboard | Today's overview |
| GET | /api/v1/doctor/queue | My queue |
| GET | /api/v1/doctor/briefing/{id} | Patient briefing |
| POST | /api/v1/doctor/encounters/{id}/complete | Complete consultation |
| POST | /api/v1/doctor/encounters/{id}/orders | Add orders |

### Insurance
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/v1/insurance/verify | Verify eligibility |
| POST | /api/v1/insurance/pre-auth | Submit pre-authorization |
| POST | /api/v1/insurance/claim | Submit claim |
| GET | /api/v1/insurance/claim/{id}/status | Check claim status |
| GET | /api/v1/insurance/transactions | List transactions |

### Billing
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/billing/{id} | Show bill |
| POST | /api/v1/billing/generate | Generate bill for encounter |
| POST | /api/v1/billing/{id}/charge | Add line item |
| POST | /api/v1/billing/{id}/pay | Record payment |
| GET | /api/v1/billing/patient/{id} | Patient's bills |
| GET | /api/v1/billing/revenue/summary | Revenue analytics |

### Admin
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/admin/dashboard | KPIs + overview |
| GET | /api/v1/admin/patient-flow | Hourly patient counts |
| GET | /api/v1/admin/doctor-performance | Per-doctor metrics |
| GET | /api/v1/admin/ai-performance | AI automation metrics |
| GET | /api/v1/admin/configuration | Hospital config |
| PUT | /api/v1/admin/configuration | Update config |

### Analytics
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/analytics/dashboard | Combined analytics |
| GET | /api/v1/analytics/revenue | Revenue breakdown |
| GET | /api/v1/analytics/insurance | Insurance analytics |
| GET | /api/v1/analytics/export | Export as CSV |

### WhatsApp
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/whatsapp/webhook | Meta verification |
| POST | /api/v1/whatsapp/webhook | Incoming messages |

---

## 8. Web Pages Reference

| Page | URL | Auth Required | Description |
|------|-----|---------------|-------------|
| Login | /login | No | Email + password authentication |
| Admin Dashboard | /admin | Yes (admin) | KPI cards, queues, activity, quick stats |
| Patients | /admin/patients | Yes | Search, list, add patients |
| Patient Detail | /admin/patients/{id} | Yes | Profile, timeline, encounters, bills tabs |
| Appointments | /admin/appointments | Yes | Date filter, doctor filter, status badges |
| Staff | /admin/staff | Yes | Staff list, roles, add staff |
| Settings | /admin/settings | Yes | Hospital config, departments, modules |
| Analytics | /admin/analytics | Yes | Charts and reports |
| Doctor Dashboard | /doctor | Yes (doctor) | Queue panel + patient briefing + SOAP notes |
| Kiosk | /kiosk | No | Check-in home (QR scan / token entry) |
| Queue Display | /kiosk/queue-display | No | TV screen for waiting area (auto-refresh) |

---

## 9. Database Schema

### Tables (17 total)

| Table | Primary Key | Description |
|-------|-------------|-------------|
| users | UUID | Auth users with roles |
| hospitals | UUID | Multi-tenant base |
| staff | UUID | Doctors, nurses, admin |
| patients | UUID | Patient records (soft delete) |
| encounters | UUID | Clinical encounters |
| appointments | UUID | Scheduled appointments |
| conversations | UUID | AI conversation history |
| insurance_transactions | UUID | Eligibility, pre-auth, claims |
| orders | UUID | Lab, pharmacy, imaging |
| bills | UUID | Billing with line items |
| queue_entries | UUID | Real-time queue positions |
| notifications_log | UUID | All notification tracking |
| audit_logs | UUID | Full audit trail (7yr retention) |
| personal_access_tokens | ID | Sanctum API tokens |
| cache | key | Laravel cache |
| jobs | ID | Laravel queue |
| sessions | string | Laravel sessions |

### Key Relationships
- Hospital has many: Staff, Patients, Encounters, Appointments
- Patient has many: Encounters, Appointments, Conversations, Bills
- Encounter belongs to: Patient, Doctor (Staff), has one: Appointment, many: Orders, Bills
- Appointment belongs to: Patient, Doctor, Encounter (nullable)
- Conversation belongs to: Patient, Encounter (nullable)

---

## 10. Tech Stack

| Component | Technology |
|-----------|-----------|
| Framework | Laravel 13 (PHP 8.5) |
| Database | SQLite (dev), PostgreSQL (production) |
| Auth | Laravel Sanctum (API tokens + session) |
| Frontend | Blade + Tailwind CSS v4 + Alpine.js |
| Build | Vite |
| AI | Anthropic Claude / OpenAI GPT-4o |
| WhatsApp | Meta Cloud API / Gupshup |
| Queue | Laravel Queue (database/Redis) |
| Scheduler | Laravel Scheduler |
| OCR | Azure Document Intelligence (stubbed) |

---

*Document generated by MedOS v1.0 - Built by Haztech*
