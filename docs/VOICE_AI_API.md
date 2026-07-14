# MedOS — Voice-AI / Appointment Integration API

API for an external voice-AI (or chatbot / CRM) partner to look up patients, read
doctor availability, and **book / reschedule / cancel appointments and lab tests**
across **every hospital on MedOS** with a single set of credentials.

- **Base URL:** `https://medos.haztech.cloud/api/v1`
- **Format:** JSON in, JSON out. All responses are `{ "success": true|false, ... }`.
- **Auth:** Bearer token (Laravel Sanctum) on every call except `auth/login`.

---

## 1. Authentication

`POST /api/v1/auth/login`

```json
{ "email": "platform-bot@yourhospital.com", "password": "••••••••" }
```

**Response**
```json
{ "success": true, "data": { "token": "12|abcdEXAMPLETOKEN...", "user": { ... } } }
```

Send the token on every subsequent request:

```
Authorization: Bearer 12|abcdEXAMPLETOKEN...
Accept: application/json
Content-Type: application/json
```

> Use a **super-admin (platform) account** for the bot so one token can serve all
> hospitals (see §2). A hospital-specific account also works but is locked to that
> one hospital. Tokens are long-lived; log in once and reuse.

---

## 2. Targeting a hospital — `X-Hospital-ID` (multi-tenant)

MedOS hosts many hospitals. Every request runs against **one** hospital, chosen like this:

| Token type | How the hospital is chosen |
|---|---|
| **Super-admin (platform)** | The `X-Hospital-ID` header you send. Omit it and it defaults to the bot's home hospital. |
| **Hospital-specific** | Always its own hospital. Sending a *different* `X-Hospital-ID` is rejected (`422`). |

So the voice vendor should:
1. Call `GET /hospitals` once to get every hospital's `hospital_id` (map each inbound phone line / DID to a `hospital_id`).
2. On every call, send the header for the hospital that owns the phone line:

```
X-Hospital-ID: 22222222-2222-2222-2222-222222222222
```

### `GET /api/v1/hospitals`
Lists the hospitals your token may act for (super-admin → all; hospital token → its own). No `X-Hospital-ID` needed.

**Response**
```json
{ "success": true, "data": { "hospitals": [
  { "hospital_id": "11111111-...", "name": "City Care Hospital", "slug": "city-care", "city": "Panaji", "phone": "+91..." },
  { "hospital_id": "22222222-...", "name": "Gulf Medical Center", "slug": "gulf-medical", "city": "Dubai", "phone": "+971..." }
] } }
```

---

## 3. Common response & error format

Success: `{ "success": true, "data": { ... } }`
Failure: `{ "success": false, "message": "human-readable reason" }`

| HTTP | Meaning |
|---|---|
| `200` | OK |
| `201` | Created (a booking was made) |
| `404` | Patient / doctor / appointment / hospital not found |
| `409` | Slot was just taken by someone else (race) — offer another slot |
| `422` | Validation error, past slot, doctor not working, wrong hospital |
| `429` | Rate limit (30 requests / minute / token) |

**Enum values used below**

- **appointment `status`**: `scheduled` · `confirmed` · `checked_in` · `in_progress` · `completed` · `no_show` · `cancelled` · `rescheduled`
- **lab-test `priority`**: `routine` (default) · `urgent` · `stat`
- **lab-order `type`** (auto-detected from the test): `lab` · `imaging` · `procedure`
- **date** = `YYYY-MM-DD`, **time** = `HH:MM` (24-hour, hospital local time)

---

## 4. Endpoints

Every endpoint below needs `Authorization: Bearer …` and (for a platform token) `X-Hospital-ID: …`.

### 4.1 Look up a patient — `GET /customer`

| Field | In | Type | Req | Notes |
|---|---|---|---|---|
| `phone` | query | string | ✅ | Any format; last 10 digits are matched (e.g. `9876543210` or `+919876543210`) |

**Example** `GET /customer?phone=9876543210`
```json
{ "success": true, "data": {
  "patient_id": "uuid", "name": "Arun Krishnamurthy", "email": "...", "phone": "+919876543210",
  "gender": "male", "date_of_birth": "1985-04-12", "blood_group": "B+",
  "upcoming_appointments": [
    { "appointment_id": "uuid", "doctor": "Dr. Priya Sharma", "department": "Pediatrics",
      "date": "2026-07-15", "time": "09:00", "token": "PED-001", "status": "scheduled" }
  ] } }
```
`404` if no patient has that phone in this hospital.

---

### 4.2 Doctor availability — `GET /doctor-schedule`

| Field | In | Type | Req | Notes |
|---|---|---|---|---|
| `name` | query | string | ✅ | Fuzzy match; handles typos and a leading `Dr.` (e.g. `priya`, `Dr Sharma`) |
| `days` | query | int (1–30) | — | Days ahead to return (default `7`) |

**Example** `GET /doctor-schedule?name=priya&days=7`
```json
{ "success": true, "data": {
  "doctor_id": "uuid", "name": "Dr. Priya Sharma", "specialty": "Pediatrics",
  "department": "Pediatrics", "consultation_duration": 15,
  "schedule": [
    { "date": "2026-07-15", "day": "Wednesday",
      "available_slots": ["09:00","09:15","09:30","10:00", "..."] }
  ] } }
```
Only **free, future** slots are returned. `404` if no doctor matches.

---

### 4.3 List a patient's appointments — `GET /my-appointments`

| Field | In | Type | Req | Notes |
|---|---|---|---|---|
| `phone` | query | string | ◑ | Patient's phone — **either** this **or** `patient_id` |
| `patient_id` | query | uuid | ◑ | **either** this **or** `phone` |
| `include_past` | query | bool | — | `false` (default) = upcoming & active only; `true` = full history |

**Example** `GET /my-appointments?phone=9876543210`
```json
{ "success": true, "data": {
  "patient_id": "uuid", "name": "Arun Krishnamurthy", "phone": "+919876543210",
  "appointments": [
    { "appointment_id": "uuid", "doctor": "Dr. Priya Sharma", "department": "Pediatrics",
      "specialty": "Pediatrics", "date": "2026-07-15", "time": "09:00",
      "token": "PED-001", "status": "scheduled" }
  ] } }
```

---

### 4.4 Book an appointment — `POST /book-appointment`

| Field | Type | Req | Notes |
|---|---|---|---|
| `doctor_id` | uuid | ✅ | From `/doctor-schedule` |
| `patient_id` | uuid | ◑ | **either** this **or** `phone` (patient must already exist) |
| `phone` | string | ◑ | **either** this **or** `patient_id` |
| `date` | date | ✅ | `YYYY-MM-DD`, must be in the future |
| `time` | time | ✅ | `HH:MM`, must be a free slot the doctor works |
| `notes` | string | — | e.g. chief complaint / reason (≤ 500 chars) |

**Request**
```json
{ "doctor_id": "uuid", "phone": "9876543210", "date": "2026-07-15", "time": "09:00", "notes": "fever and cough" }
```
**Response `201`**
```json
{ "success": true, "data": {
  "appointment_id": "uuid", "token": "PED-001", "doctor": "Dr. Priya Sharma",
  "patient": "Arun Krishnamurthy", "date": "2026-07-15", "time": "09:00", "status": "scheduled" } }
```
Errors: `404` patient not found · `422` doctor inactive / past slot / doctor not working / slot booked · `409` slot just taken.

---

### 4.5 Reschedule an appointment — `POST /reschedule-appointment`

Moves an existing appointment (same doctor) to a new slot and re-issues the token.

| Field | Type | Req | Notes |
|---|---|---|---|
| `appointment_id` | uuid | ✅ | From `/customer` or `/my-appointments` |
| `date` | date | ✅ | New date `YYYY-MM-DD` |
| `time` | time | ✅ | New time `HH:MM` |

**Response**
```json
{ "success": true, "data": {
  "appointment_id": "uuid", "token": "PED-004", "doctor": "Dr. Priya Sharma",
  "date": "2026-07-15", "time": "09:45", "status": "scheduled" } }
```
Errors: `404` not found · `422` appointment already completed/cancelled or doctor not working at the new time · `409` new slot just taken.

---

### 4.6 Cancel an appointment — `POST /cancel-appointment`

| Field | Type | Req | Notes |
|---|---|---|---|
| `appointment_id` | uuid | ✅ | |
| `reason` | string | — | Optional (≤ 255 chars) |

**Response**
```json
{ "success": true, "data": { "appointment_id": "uuid", "status": "cancelled" } }
```
Re-cancelling is safe → `{ "status": "cancelled", "already": true }`. `422` if it's already `completed` / `in_progress`.

---

### 4.7 Book a lab test / scan — `POST /book-lab-test`

Creates lab / imaging / procedure orders for a patient. Each test is auto-routed to the right worklist (lab vs radiology vs procedure).

| Field | Type | Req | Notes |
|---|---|---|---|
| `patient_id` | uuid | ◑ | **either** this **or** `phone` |
| `phone` | string | ◑ | **either** this **or** `patient_id` |
| `tests` | string[] | ◑ | Up to 20 test names — **either** this **or** `test` |
| `test` | string | ◑ | A single test name |
| `date` | date | — | Preferred date `YYYY-MM-DD` (optional; walk-in if omitted) |
| `time` | time | — | Preferred time `HH:MM` (defaults to `09:00` if only a date is given) |
| `priority` | string | — | `routine` (default) · `urgent` · `stat` |
| `notes` | string | — | ≤ 500 chars |

**Request**
```json
{ "phone": "9876543210", "tests": ["Complete Blood Count", "Chest X-Ray"],
  "date": "2026-07-15", "priority": "urgent", "notes": "requested on call" }
```
**Response `201`**
```json
{ "success": true, "data": {
  "patient_id": "uuid", "patient": "Arun Krishnamurthy",
  "orders": [
    { "order_id": "uuid", "type": "lab", "tests": ["Complete Blood Count"], "priority": "urgent",
      "scheduled_for": "2026-07-15 09:00:00", "status": "ordered" },
    { "order_id": "uuid", "type": "imaging", "tests": ["Chest X-Ray"], "priority": "urgent",
      "scheduled_for": "2026-07-15 09:00:00", "status": "ordered" }
  ],
  "unmatched_tests": [] } }
```
`unmatched_tests` lists any names not in the hospital's catalogue — they're still booked as generic **lab** tests, so verify spelling. `404` if the patient isn't found.

---

## 5. Typical voice-call flow

1. Inbound call on a hospital's line → look up its `hospital_id` (from `GET /hospitals`) → set `X-Hospital-ID` for the rest of the call.
2. `GET /customer?phone=<caller>` → greet by name / know if a patient exists.
3. Caller asks for Dr. X → `GET /doctor-schedule?name=X` → read out `available_slots`.
4. Caller picks one → `POST /book-appointment` → confirm the `token` and time.
5. "Move my appointment" → `GET /my-appointments?phone=…` → `POST /reschedule-appointment`.
6. "Cancel it" → `POST /cancel-appointment`.
7. "I also need a blood test" → `POST /book-lab-test`.

## 6. Notes & limits

- **Rate limit:** 30 requests / minute per token (`429` if exceeded). Cache `/hospitals` and `/doctor-schedule`.
- **Patients must exist** before booking (match by `phone`). Registering new patients is a separate flow — ask us to expose it if the bot needs to create patients.
- All times are the **hospital's local time**.
- Bookings/reschedules are **atomic** — two simultaneous requests can't take the same slot (the loser gets `409`).
