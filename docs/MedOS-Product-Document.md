# MedOS — AI-First Hospital Operating System

## Product Document

**Built by Haztech Digital Innovation Agency**
**Version 1.0 | 2026**

---

## What is MedOS?

MedOS is a next-generation Hospital Operating System that **automates 80% of hospital reception and patient management** using AI — from the moment a patient sends a WhatsApp message to their follow-up after treatment.

It's not a typical Hospital Management System. It's an **intelligent operating system** that runs the hospital's patient-facing operations with minimal human intervention.

**Live Demo:** https://medos.haztech.cloud
**Login:** superadmin@haztech.in / password123

---

## The Problem We Solve

| Current Hospital Reality | With MedOS |
|---|---|
| 3-5 receptionists handling 100+ patients/day | AI handles 80% of interactions automatically |
| Patients wait 30-60 min with no updates | Real-time queue with wait time predictions |
| Phone calls go unanswered during rush hours | WhatsApp bot handles unlimited conversations 24/7 |
| Paper tokens, manual queue | Digital tokens, smart queue with priority scoring |
| Doctor spends 30% time on paperwork | AI pre-fills patient briefing, one-tap prescriptions |
| No follow-up after visit | Automated reminders, feedback, re-engagement |
| Insurance verified after consultation | Insurance pre-verified before booking |

---

## How It Works (Patient Journey)

### 1. Patient Sends WhatsApp Message
> "Hi, mujhe bukhar hai 2 din se"

- Bot detects language (Hindi) and responds in Hindi
- Asks for phone number → recognizes returning patients
- Asks about symptoms conversationally
- Finds the right doctor based on complaint
- Shows available doctors with wait times
- Patient picks doctor and confirms
- **Appointment booked with token number — all via WhatsApp**

### 2. Patient Arrives at Hospital (Kiosk)
- Walks to self-service kiosk (tablet at entrance)
- Enters phone number or ABHA number
- System recognizes them → shows token
- Directed to waiting area
- **No receptionist needed**

### 3. Waiting Room (TV Display)
- Large screen outside each doctor's room
- Shows: NOW SERVING → Token XYZ, Patient Name
- Queue list below with position numbers
- **Voice announcement in 5 languages** when next patient is called:
  - English, Hindi, Marathi, Konkani, Arabic
  - Three beeps → "Token number PED-003. Vikram Sharma. Please come in."

### 4. Doctor Consultation
- Doctor sees patient queue on their screen (auto-refreshes every 5 seconds)
- Clicks patient → sees AI-generated briefing:
  - Chief complaint, allergies, current medications
  - Previous visit history
  - Insurance status
  - Referral context (if referred from another doctor)
- **6-tab consultation panel:**
  - **Vitals** — BP, temp, pulse, SpO2, weight (quick entry)
  - **Diagnosis** — 18 common diagnoses as one-tap buttons + custom
  - **Tests** — 74 lab/imaging tests from database, one-tap to order
  - **Prescription** — Search from 120+ medicines database, auto-fills dosage/frequency/timing
  - **Refer** — Select doctor, see their calendar, pick available slot, set urgency
  - **Notes** — SOAP notes with quick templates + patient advice toggles
- Clicks "Complete" → **everything saves to database automatically**

### 5. After Consultation
- Bill auto-generated (consultation + tests + medicines)
- Insurance claim auto-submitted (if insured)
- Patient gets WhatsApp confirmation
- Follow-up reminder scheduled automatically
- Feedback request sent after 2 hours

### 6. Referral Flow
- Doctor A refers patient to Doctor B
- Doctor B sees referral in their dashboard with:
  - Patient details, complaint, previous diagnosis
  - Urgency level (Emergency / Priority / Normal)
  - Preferred slot from Doctor A
- Doctor B clicks "Accept" → patient appears in their queue with all data pre-filled
- **Zero re-entry of information**

---

## Key Features

### For Patients
- 📱 **Book via WhatsApp** — no app download needed
- 🔢 **Digital token** — know your position in real-time
- 🗣️ **Multilingual** — English, Hindi, Marathi, Konkani, Arabic
- ⏱️ **Wait time updates** — "You're #3, estimated 12 minutes"
- 🏥 **ABHA integration** — national health ID linked

### For Doctors
- 📋 **AI briefing** — patient history pre-loaded before consultation
- 💊 **One-tap prescriptions** — 120+ medicines with auto-fill
- 🧪 **One-tap tests** — 74 tests, just tap to order
- 🔄 **Smart referrals** — see other doctor's calendar, book slot directly
- 📊 **My Stats** — daily/weekly/monthly performance dashboard
- 🔴 **Live queue** — auto-refreshes every 5 seconds, no page reload

### For Hospital Admin
- 📈 **Dashboard** — patients today, revenue, AI automation rate, queue depths
- 👥 **Staff management** — add/edit doctors, nurses, receptionists
- 🕐 **Slot management** — set doctor schedules with calendar preview
- 🧪 **Test management** — add/remove available lab tests and imaging
- 💊 **Medicine database** — manage 120+ medicines
- ⚙️ **Settings** — hospital name, region (India/UAE), configuration

### For Hospital Owner (Super Admin)
- 🏥 **Multi-hospital** — manage multiple hospitals from one panel
- 🇮🇳🇦🇪 **Region switching** — India mode vs UAE mode (different currencies, languages, health IDs, insurance systems)
- 📊 **Cross-hospital analytics**

---

## India vs UAE — One System, Two Modes

| Feature | 🇮🇳 India Mode | 🇦🇪 UAE Mode |
|---|---|---|
| Currency | ₹ (INR) | AED |
| Health ID | ABHA (Ayushman Bharat) | Emirates ID |
| Insurance | PMJAY + TPA | DHA / DoH (mandatory) |
| Languages | EN, HI, MR, KOK + 6 more | EN, AR |
| Voice Announcements | English, Hindi, Marathi, Konkani | English, Arabic |
| Payment | UPI, Cash, Card | Card, Apple Pay, Insurance |
| Tax | GST 18% | VAT 5% |
| Emergency | 108 | 999 |
| Compliance | DPDP Act, ABDM | PDPL, NABIDH |

Switch between modes in Settings → entire system adapts instantly.

---

## Technical Highlights

- **Built with:** Laravel 13 (PHP 8.4), Tailwind CSS, Alpine.js
- **Database:** SQLite (dev) / PostgreSQL (production)
- **WhatsApp Bot:** whatsapp-web.js — just scan QR code, bot is live
- **No app download needed** — patients use WhatsApp, staff use web browser
- **AI-powered:** language detection, triage scoring, specialty mapping
- **Real-time:** queue updates every 5 seconds, room display every 10 seconds
- **Secure:** role-based access, audit logs, encrypted health data, ABHA consent management
- **19 database tables**, 130+ PHP files, 25+ blade views

---

## Revenue Model (SaaS)

### India Pricing

| Plan | Target | Monthly |
|---|---|---|
| Starter | 1-3 doctors | ₹15,000/mo |
| Professional | 4-15 doctors | ₹45,000/mo |
| Enterprise | 15+ doctors | ₹1,20,000+/mo |

### UAE Pricing

| Plan | Target | Monthly |
|---|---|---|
| Professional | 1-5 doctors | AED 3,500/mo |
| Enterprise | 5+ doctors | AED 8,000+/mo |

### Setup Fees
- Starter: ₹25,000 one-time
- Professional: ₹75,000 one-time
- Enterprise: ₹2,00,000+ one-time

---

## Demo Credentials

| Role | Email | Password |
|---|---|---|
| Super Admin | superadmin@haztech.in | password123 |
| Hospital Admin | admin@haztech.in | password123 |
| Doctor (Pediatrics) | priya@haztech.in | password123 |
| Doctor (Cardiology) | amit@haztech.in | password123 |

### Demo Pages

| Page | URL |
|---|---|
| Login | /login |
| Admin Dashboard | /admin |
| Doctor Queue | /doctor |
| Kiosk | /kiosk |
| WhatsApp Bot Simulator | /chat |
| Room Display | /kiosk/room/priya |
| Queue Display (TV) | /kiosk/queue-display |
| Super Admin | /super-admin |

---

## What Makes MedOS Different

1. **AI-native** — AI is the system, not a chatbot bolted on top
2. **WhatsApp-first** — 500M+ Indian users, zero friction
3. **No app download** — patients use WhatsApp, staff use browser
4. **Works offline** — kiosk and queue display work without internet
5. **5-language voice** — announcements in English, Hindi, Marathi, Konkani, Arabic
6. **India + UAE ready** — one system, two regulatory frameworks
7. **ABHA integrated** — national health ID with consent management
8. **Doctor-friendly** — one-tap prescriptions, not forms to fill

---

## Target Market

### Primary: India
- 70,000+ hospitals with 5-50 doctors
- Rising insurance penetration (PMJAY covering 500M people)
- WhatsApp penetration: 500M+ users
- Government push for digital health (ABDM)

### Secondary: UAE
- Mandatory health insurance for all residents
- High smartphone penetration
- DHA pushing for digital-first healthcare
- Expat population needs multilingual support

---

## Built by Haztech

Haztech is a digital innovation agency building next-generation systems that automate businesses.

**Products:**
- **MedOS** — Hospital Operating System
- **PropOS** — Real Estate Management System

**Contact:** hello@haztech.cloud
**Website:** https://haztech.cloud
