# MedOS — AI-First Hospital Operating System

[![MedOS Status](https://img.shields.io/badge/MedOS-v1.0-blue.svg)](https://medos.haztech.cloud)
[![Laravel](https://img.shields.io/badge/Laravel-13-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-purple.svg)](https://php.net)
[![OpenAPI 3.0](https://img.shields.io/badge/OpenAPI-3.0-green.svg)](https://medos.haztech.cloud/docs/api)
[![Swagger UI](https://img.shields.io/badge/Swagger-UI-85ea2d.svg)](https://medos.haztech.cloud/docs/api)

Built by **Haztech Digital Innovation Agency**.

- **Live Application:** [https://medos.haztech.cloud](https://medos.haztech.cloud)
- **Interactive Swagger UI:** [https://medos.haztech.cloud/docs/api](https://medos.haztech.cloud/docs/api) (or `/swagger`)
- **OpenAPI 3.0 Specification:** [https://medos.haztech.cloud/api/v1/openapi.json](https://medos.haztech.cloud/api/v1/openapi.json)

---

## 📖 API Documentation & Swagger UI

MedOS includes a built-in, interactive **Swagger UI playground** for frontend developers, voice-AI partners, and external integrations:

- **Swagger UI Interactive Playground:** [`/docs/api`](https://medos.haztech.cloud/docs/api) or [`/swagger`](https://medos.haztech.cloud/swagger)
- **OpenAPI 3.0 JSON:** [`/api/v1/openapi.json`](https://medos.haztech.cloud/api/v1/openapi.json) (and versioned at [`public/swagger.json`](public/swagger.json) / [`docs/swagger.json`](docs/swagger.json))
- **Comprehensive Developer Guide:** See [docs/SWAGGER_API_GUIDE.md](docs/SWAGGER_API_GUIDE.md)

### 🔐 How to Authorize in Swagger UI

To test protected endpoints (booking, patient lookup, doctor schedules) directly inside Swagger UI:
1. Open the [Swagger UI Playground](https://medos.haztech.cloud/docs/api).
2. Under **Auth**, execute `POST /api/v1/auth/login` with demo credentials:
   - **Email:** `admin@haztech.in` | **Password:** `password123`
3. Copy the `token` string from the JSON response.
4. Click the green **"Authorize 🔓"** button at the top right of the Swagger UI.
5. Paste your token into the **BearerAuth** input field and click **Authorize**.
6. *(Optional)* Set `city-care` in **HospitalHeader** (`X-Hospital-ID`) for multi-tenant hospital routing.
7. Click **Close** — the lock icon updates to **Authorize 🔒** and all protected requests are authenticated automatically.

### Key API Capabilities

#### 1. Hospital Information (`GET /api/v1/hospital-info`)
Fetch real-time operational status and full profile of a hospital:
- **Open / Close Timings & Real-Time Status:** `open_time`, `close_time`, `is_open_now` (calculated in real time in hospital timezone), localized `current_time`.
- **Contact Info:** Reception `phone`, `email`, regional `emergency_phone` (`108` in India, `999` in UAE).
- **Regional Currency:** ISO `code` (e.g. `INR`, `AED`), symbol (`₹`, `AED`), and country name.
- **Communication Channels:** SMS gateway (`enabled`, `provider`, `sender_id`) and WhatsApp status (`enabled`, `number`, `provider`).
- **Departments & Doctors:** Full department breakdown with doctor count and doctor profiles including **qualifications and specifications**.

```bash
# Query by slug
curl -s "https://medos.haztech.cloud/api/v1/hospital-info?hospital=city-care"

# Direct URL lookup
curl -s "https://medos.haztech.cloud/api/v1/hospitals/city-care/info"
```

#### 2. Doctors with Qualifications & Specifications (`GET /api/v1/doctors`)
List all doctors or filter by department. Each doctor profile includes:
- `qualification` (e.g. `MBBS, MD`, `BDS, MDS`, `KA-MED-2012-00987`)
- `specialization` / `specification` (e.g. `Cardiology`, `Pediatrics`)
- `consultation_duration_minutes`
- `available` (bool) and `next_available` slot details

```bash
curl -s "https://medos.haztech.cloud/api/v1/doctors?department=Cardiology&hospital=city-care"
```

#### 3. Departments & Doctors (`GET /api/v1/departments`)
Lists all hospital departments grouped with their doctor rosters, qualifications, and consultation duration:

```bash
curl -s "https://medos.haztech.cloud/api/v1/departments?hospital=city-care"
```

#### 4. Appointment Booking & Doctor Schedule
- `GET /api/v1/doctor-schedule?name=Amit&hospital=city-care` — Doctor slot calendar.
- `POST /api/v1/book-appointment` — Atomic booking with lock to prevent double-booking.
- `POST /api/v1/reschedule-appointment` — Reschedule existing appointment.
- `POST /api/v1/cancel-appointment` — Cancel appointment with reason.

---

## 🏥 Architecture & Tech Stack

- **Backend:** Laravel 13, PHP 8.4, SQLite database
- **Frontend:** Blade + Tailwind CSS v4 + Alpine.js
- **Auth:** Laravel Sanctum (Bearer Token) + Session Auth
- **Multi-Tenant:** Every table is scoped by `hospital_id`. External requests use `X-Hospital-ID` header or `?hospital=<slug>`.
- **Primary Modules:** OPD, IPD, Billing & Charge-Capture ledger, Queue Management, Pharmacy, Lab, Radiology, Dental, Clinical Nutrition, WhatsApp Bot, Voice-AI.

---

## 💻 Local Development

Use the WinGet PHP 8.4 binary on Windows or system PHP 8.4:

```bash
# Serve application
php artisan serve --host=127.0.0.1 --port=8000

# Run migrations
php artisan migrate --force

# Clear caches after route/view/config changes
php artisan optimize:clear

# Open Swagger documentation locally
http://127.0.0.1:8000/docs/api
```

---

## 📚 Documentation Index

- [Swagger UI & API Developer Guide](docs/SWAGGER_API_GUIDE.md)
- [Voice-AI / Appointment Integration API Guide](docs/VOICE_AI_API.md)
- [MedOS Product Overview](docs/MedOS-Product-Document.md)
- [Architecture & Development Guidelines](CLAUDE.md)
