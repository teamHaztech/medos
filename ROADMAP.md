# MedOS — Billing / Revenue-Cycle Roadmap

Status of the billing module and what's left to reach "best-in-class Indian medical billing"
(NuvertOS / Marg / MediXcel tier). Read this before extending billing.

_Last updated: 2026-07-13 · current version: see `config/medos.php`._

---

## ✅ Done (shipped to `main`)

- **Charge-capture ledger (RCM core)** — `charge_items` table + `app/Modules/Billing/Services/ChargeCapture.php`.
  Every chargeable event posts a charge row; bills are *compiled from* unbilled charges, so revenue can't leak.
  `post()` (idempotent by `source,source_ref`), `pendingFor()`, `compileBill()` (Encounter **or** Admission),
  `priceFor()` (rate-card resolver).
- **OPD auto-capture** — consultation (rate card), lab-on-order (incl. add-ons after consult), pharmacy-on-dispense
  at `PharmacyStock.selling_price`. Hooks in `DoctorWebController::completeConsultation` / `referToLab` and
  `PharmacyController::dispense`.
- **IPD running bill** — ward tariffs (`wards.daily_rate` / `nursing_daily_rate`), rate snapshot on admit,
  on-demand room/nursing accrual (no cron), add-charge on the case sheet, final bill compiled at discharge
  (`bills.admission_id`). See `InpatientController` + `ChargeCapture::accrueRoom`.
- **Insurance claims (basic)** — `InsuranceWebController` (file / approve / deny), claim linked to the bill
  (`bills.insurance_transaction_id`), approval applies coverage via the ledger recompute. `InsuranceTransaction`
  model↔DB column mismatch fixed (real cols `insurer_code`/`type`/`external_reference_id`, back-compat aliases).
- **GST-compliant tax invoicing** — per-service GST rate + HSN/SAC (`service_charges`), per-line GST with
  CGST/SGST split on the bill (`bills.cgst_amount`/`sgst_amount`/`igst_amount`), hospital GSTIN in
  Settings → GST, "TAX INVOICE" print format (`resources/views/billing/print.blade.php`). Healthcare services are
  exempt (0%); pharmacy/consumables taxable.
- **Double-booking fix** — `IntegrationController::bookAppointment` (API used by AI Calls) is now atomic
  (transaction + write-lock-first + `lockForUpdate` re-check → 409 on clash). Chat booking also uses
  `lockForUpdate`; `PublicBookingController` was already transaction-guarded.

---

## ⏳ What's left (prioritised)

### 1. TPA / cashless claims depth (MediXcel level) — biggest remaining gap
Current claims are a simple file/approve/deny. To match real Indian cashless billing, add:
- **TPA / payer master** — per-hospital list of TPAs & insurers (mirror the `ServiceCharge` rate-card pattern;
  or `Hospital.config['tpas']`). Pick from it when filing a claim.
- **Pre-authorisation workflow** — request → approved amount / validity → link the pre-auth to the final claim.
  `InsuranceService::submitPreAuth` exists but is stubbed and API-only; give it a web UI.
- **Cashless vs reimbursement** flag on the claim; capture co-pay / deductible / sum-insured on the patient's
  `insurance_details` (schema is currently free-form — standardise it).
- **Claim lifecycle + query/denial management** — states: submitted → queried → resubmitted → approved /
  partially_approved → settled / denied, with a query/denial reason log and a resubmit action. The
  `insurance_transactions.status` enum already lists these values; the UI only handles approve/deny today.
- **Package / PMJAY-style claims** — package rate → claim amount; `IndiaTPAProvider` has a PMJAY branch (stubbed).
- Providers (`app/Modules/Insurance/Providers/*`) are **stubbed** — real DHA / TPA `Http::post` calls are commented
  out and go live only when payer credentials/keys are configured (pluggable adapter pattern, keep it).

### 2. RCM reporting dashboard
`BillingWebController::dashboard` has collections/outstanding basics. Add a proper RCM view:
- **Collection rate** (collected ÷ billed), **outstanding AR**, **AR aging** buckets (0–30 / 30–60 / 60–90 / 90+).
- **Denial rate** and **claim TAT** (submitted → responded) from `insurance_transactions`.
- **Payer-wise** and **department-wise** revenue. Data is all there (`bills`, `bill_payments`,
  `insurance_transactions`, `charge_items`); this is mostly aggregation + a Blade dashboard.

### 3. GST / invoice polish
- **GST summary by rate slab** on the invoice (HSN-wise: taxable value + CGST + SGST per 0/5/12/18%) — nice-to-have
  for strict compliance; per-line GST is already shown.
- **Amount in words** on the invoice.
- **Inter-state IGST** — currently intra-state only (CGST+SGST); to support IGST, capture the patient's state and
  compare with the hospital's `config['gst_state']` in `ChargeCapture::computeInvoice`.
- **Credit notes** — a GST credit note document for refunds/cancellations (refunds are negative `bill_payments`
  today; a formal credit-note number/print is missing).

---

## ⚠️ Known gaps / notes (not billing-specific)

- **AI receptionist booking** — the LLM path (`ConversationManager` + `AIService`) needs a working
  `ANTHROPIC_API_KEY` on prod, else it degrades to generic replies (this caused the "please log in to book"
  deflection). The `/api/v1/*` integration API correctly requires a Sanctum **token** (system-to-system) — patients
  never log in; that is by design.
- **Queue = `database` driver** — external ERP bill-sync (`BillObserver` → `SyncBillToExternal`), WhatsApp and
  notification jobs need the hPanel cron `php artisan queue:work --stop-when-empty` to drain.
- **Deploy** — prod is Hostinger (no SSH). After `git pull`, run `public/deploy.php?key=…` to apply migrations
  (billing added several: `charge_items`, IPD, insurance-claim links, GST). Delete `deploy.php` after.
- **SQLite in prod** — avoid MySQL-only SQL; the double-booking guard relies on transaction write-serialisation +
  `lockForUpdate` (a no-op on SQLite but the encounter-write-first ordering serialises writers).

---

## Reuse / conventions (so new billing code stays consistent)
- Compile charges **into** `Bill`; never set `amount_paid`/`balance_due`/`payment_status` directly —
  use the ledger recompute (`BillingWebController::recomputeBill` / `ChargeCapture::recomputePayments`).
- New money models: `HasUuid` + **manual `hospital_id` scoping** (avoid the null global-scope trap).
- Idempotent, guarded migrations (prod runs them via `deploy.php`).
- Tailwind is **not** rebuilt — verify any `[...]` bracket class ships in `public/build/assets/*.css` or use inline styles.
