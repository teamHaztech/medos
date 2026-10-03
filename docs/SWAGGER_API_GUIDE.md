# MedOS API & Swagger UI — Developer Guide

This guide explains how to use, test, and integrate with the MedOS API using the interactive **Swagger UI** and OpenAPI 3.0 specification.

---

## 1. Quick Links

| Environment | Swagger UI (Interactive Playground) | Raw OpenAPI 3.0 JSON |
|---|---|---|
| **Production** | [`https://medos.haztech.cloud/docs/api`](https://medos.haztech.cloud/docs/api) | [`https://medos.haztech.cloud/api/v1/openapi.json`](https://medos.haztech.cloud/api/v1/openapi.json) |
| **Local Dev** | [`http://127.0.0.1:8000/docs/api`](http://127.0.0.1:8000/docs/api) | [`http://127.0.0.1:8000/api/v1/openapi.json`](http://127.0.0.1:8000/api/v1/openapi.json) |
| **Short URL** | `/swagger` redirects directly to `/docs/api` | `/swagger.json` |

Inside the Admin dashboard, you can also click the **"Swagger API Docs →"** button on the **API Keys** page (`/admin/api-keys`).

---

## 2. How to Use Swagger UI

### Step 1: Open the Swagger UI
Visit `https://medos.haztech.cloud/docs/api` (or `http://127.0.0.1:8000/docs/api` locally). You will see all grouped endpoints with full schema definitions, parameter requirements, and example responses.

### Step 2: "Try It Out" Interactive Testing
1. Click on any endpoint block (e.g. `GET /api/v1/hospital-info`).
2. Click the white **"Try it out"** button on the right.
3. Fill in query parameters:
   - For `hospital`: enter a hospital slug like `city-care` or `gulf-medical`.
   - For `X-Hospital-ID` header: enter `city-care` or leave blank.
4. Click the blue **"Execute"** button.
5. Review the live JSON response, status code, response headers, and ready-to-copy cURL command.

---

## 3. 🔐 The "Authorize" Button — Complete Authentication Guide

On the top right of the Swagger UI (above the endpoints list), you will see the green **"Authorize 🔓"** button. This button manages API authentication across the playground so you can test protected endpoints without manually passing headers on every request.

```
+-------------------------------------------------------------+
| Available authorizations                                    |
+-------------------------------------------------------------+
|                                                             |
| BearerAuth (http, Bearer)                                   |
| Value: [ 1|abcdef123456789...                             ] |
|                                                             |
| HospitalHeader (apiKey in header: X-Hospital-ID)            |
| Value: [ city-care                                        ] |
|                                                             |
|                      [ Authorize ]   [ Close ]              |
+-------------------------------------------------------------+
```

### 1. What Each Field Means:
- **`BearerAuth` (HTTP Bearer / Laravel Sanctum):**
  - Enter your API token string (e.g. `1|AbCdEf123...`).
  - Swagger UI automatically prepends `Bearer ` when making HTTP requests to endpoints requiring authentication.
- **`HospitalHeader` (`X-Hospital-ID`):**
  - Enter the hospital slug (e.g. `city-care`, `gulf-medical`) or hospital UUID.
  - Used for multi-tenant hospital routing when using a platform or super-admin token.

### 2. How to Authenticate Step-by-Step in 60 Seconds:
1. **Get a Token right from Swagger:**
   - Scroll down to the **Auth** section and expand `POST /api/v1/auth/login`.
   - Click **Try it out**.
   - Use the test credentials:
     ```json
     {
       "email": "admin@haztech.in",
       "password": "password123"
     }
     ```
   - Click **Execute**.
   - In the JSON response, copy the `token` string inside `data.token`:
     ```json
     {
       "success": true,
       "data": {
         "token": "1|N4GZfUj5B..."
       }
     }
     ```
2. **Authorize the Playground:**
   - Scroll back up and click the green **"Authorize 🔓"** button.
   - Paste the token into the **`BearerAuth`** text box.
   - (Optional) Enter `city-care` in **`HospitalHeader`**.
   - Click **Authorize**, then click **Close**.
3. **Verify:**
   - Notice the green button now shows **"Authorize 🔒"** (locked padlock).
   - All protected endpoints (such as `POST /api/v1/book-appointment`, `GET /api/v1/customer`, `GET /api/v1/doctor-schedule`) will now execute with full authentication!

### 3. Available Test Accounts

All demo accounts use password `password123`:

| Email | Role | Pinned Hospital | Notes |
|---|---|---|---|
| `superadmin@haztech.in` | Super Admin | Platform-wide | Can target any hospital with `X-Hospital-ID` |
| `admin@haztech.in` | Hospital Admin | City Care (`city-care`) | Pinned to City Care Hospital |
| `priya@haztech.in` | Doctor (Pediatrics) | City Care (`city-care`) | Clinical doctor access |
| `amit@haztech.in` | Doctor (Cardiology) | City Care (`city-care`) | Clinical doctor access |

---

## 4. Multi-Tenant Targeting (`X-Hospital-ID`)

MedOS is a multi-tenant platform hosting multiple hospitals. Requests are routed to a hospital in one of the following ways:

1. **Query Parameter:** `?hospital=city-care` or `?hospital=gulf-medical` (ideal for Swagger UI and browser testing).
2. **HTTP Header:** `X-Hospital-ID: city-care` (or hospital UUID).
3. **Path Parameter:** `GET /api/v1/hospitals/{hospital}/info` (e.g. `/api/v1/hospitals/city-care/info`).
4. **Hospital API Token:** If using a token issued by a specific hospital, it is pinned automatically and requires no header.

---

## 4. Key Endpoints Reference

### 1. Hospital Information (`GET /api/v1/hospital-info`)
Returns complete hospital operations info:
- **Operating Hours:** `open_time`, `close_time`, `is_open_now` (calculated in real-time according to hospital timezone), `status` (`open` or `closed`), `current_time`.
- **Contact:** `phone`, `email`, regional `emergency_phone` (`108` in India, `999` in UAE).
- **Currency:** `code` (e.g. `INR`, `AED`), `symbol` (`₹`, `AED`), country name.
- **SMS & WhatsApp:** Status and provider details (`sms.enabled`, `sms.provider`, `sms.sender_id`, `whatsapp.enabled`, `whatsapp.number`, `whatsapp.provider`).
- **Departments & Doctors:** Full list of departments, doctor count, and doctor profiles with **qualifications and specifications**.

#### Example Request:
```bash
curl -s "https://medos.haztech.cloud/api/v1/hospital-info?hospital=city-care"
```

#### Example Response:
```json
{
  "success": true,
  "data": {
    "hospital": {
      "id": "11111111-1111-1111-1111-111111111111",
      "name": "City Care Hospital",
      "slug": "city-care",
      "address": "42, MG Road, Koramangala",
      "city": "Bangalore",
      "state": "Karnataka",
      "country": "IN",
      "timezone": "Asia/Kolkata",
      "is_active": true
    },
    "open_close": {
      "open_time": "08:00",
      "close_time": "21:00",
      "is_open_now": true,
      "status": "open",
      "current_time": "08:30",
      "timezone": "Asia/Kolkata"
    },
    "contact": {
      "phone": "+918041234567",
      "email": "admin@citycare.medos.local",
      "emergency_phone": "108"
    },
    "currency": {
      "code": "INR",
      "symbol": "₹",
      "name": "India"
    },
    "sms": {
      "enabled": true,
      "status": "active",
      "provider": "msg91",
      "sender_id": "MEDOS"
    },
    "whatsapp": {
      "enabled": true,
      "status": "active",
      "provider": "meta",
      "number": "+918041234567"
    },
    "department_count": 10,
    "departments": [
      {
        "department": "Cardiology",
        "doctor_count": 1,
        "doctors": [
          {
            "doctor_id": "a0000001-0000-0000-0000-000000000003",
            "name": "Dr. Amit Patel",
            "role": "doctor",
            "department": "Cardiology",
            "specialty": "Cardiology",
            "specialization": "Cardiology",
            "specification": "Cardiology",
            "qualification": "KA-MED-2012-00987",
            "phone": "+919845212345",
            "email": "amit@haztech.in",
            "consultation_duration_minutes": 20
          }
        ]
      }
    ]
  }
}
```

---

### 2. Doctors with Qualifications & Specifications (`GET /api/v1/doctors`)
Lists all active doctors with:
- `qualification` (e.g. `MBBS, MD`, `BDS, MDS`, `KA-MED-2012-00987`)
- `specialization` / `specification` (e.g. `Cardiology`, `Pediatrics`)
- `consultation_duration_minutes`
- `available` (boolean) & `next_available` slot details

#### Example Request:
```bash
curl -s "https://medos.haztech.cloud/api/v1/doctors?department=Cardiology&hospital=city-care"
```

#### Example Response:
```json
{
  "success": true,
  "data": {
    "count": 1,
    "doctors": [
      {
        "doctor_id": "a0000001-0000-0000-0000-000000000003",
        "name": "Dr. Amit Patel",
        "role": "doctor",
        "department": "Cardiology",
        "specialty": "Cardiology",
        "specialization": "Cardiology",
        "specification": "Cardiology",
        "qualification": "KA-MED-2012-00987",
        "phone": "+919845212345",
        "email": "amit@haztech.in",
        "consultation_duration_minutes": 20,
        "available": true,
        "next_available": {
          "date": "2026-10-05",
          "day": "Monday",
          "time": "09:00"
        }
      }
    ]
  }
}
```

---

### 3. Departments & Doctors (`GET /api/v1/departments`)
Lists all hospital departments grouped with their doctor rosters, qualifications, and consultation duration.

```bash
curl -s "https://medos.haztech.cloud/api/v1/departments?hospital=city-care"
```

---

### 4. Doctor Schedule & Slots (`GET /api/v1/doctor-schedule`)
Fuzzy name match for doctor availability across the next 14 days.

```bash
curl -s "https://medos.haztech.cloud/api/v1/doctor-schedule?name=Amit&hospital=city-care"
```

---

### 5. Appointment Booking (`POST /api/v1/book-appointment`)
Atomic slot booking with concurrency lock.

```bash
curl -X POST "https://medos.haztech.cloud/api/v1/book-appointment" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "X-Hospital-ID: city-care" \
  -d '{
    "doctor_id": "a0000001-0000-0000-0000-000000000003",
    "name": "Rahul Sharma",
    "phone": "9876543210",
    "date": "2026-10-05",
    "time": "09:00",
    "reason": "Routine Consultation"
  }'
```

---

## 5. Client Code Examples

### JavaScript (Fetch)
```javascript
// Fetch hospital open/close hours, currency, and departments
async function getHospitalInfo(hospitalSlug = 'city-care') {
  const response = await fetch(`https://medos.haztech.cloud/api/v1/hospital-info?hospital=${hospitalSlug}`);
  const result = await response.json();
  
  if (result.success) {
    const { hospital, open_close, contact, currency, departments } = result.data;
    console.log(`Hospital: ${hospital.name} (${open_close.status.toUpperCase()})`);
    console.log(`Operating Hours: ${open_close.open_time} - ${open_close.close_time}`);
    console.log(`Currency: ${currency.symbol} (${currency.code})`);
    console.log(`Departments: ${departments.map(d => d.department).join(', ')}`);
  }
}

getHospitalInfo('city-care');
```

### Python (Requests)
```python
import requests

def get_doctors_by_department(hospital_slug='city-care', department='Cardiology'):
    url = f"https://medos.haztech.cloud/api/v1/doctors"
    params = {'hospital': hospital_slug, 'department': department}
    res = requests.get(url, params=params).json()
    
    if res.get('success'):
        for doc in res['data']['doctors']:
            print(f"{doc['name']} | Qual: {doc['qualification']} | Spec: {doc['specification']}")

get_doctors_by_department()
```

---

## 6. Maintaining the OpenAPI Specification

The OpenAPI specification is stored in two locations:
1. `public/swagger.json` — directly served by the web server to the Swagger UI frontend.
2. `docs/swagger.json` — versioned specification in the repository.

When adding or modifying an endpoint:
1. Update `public/swagger.json` and sync to `docs/swagger.json`.
2. Clear route and view caches:
   ```bash
   php artisan optimize:clear
   ```
3. Test locally at `http://127.0.0.1:8000/docs/api`.
