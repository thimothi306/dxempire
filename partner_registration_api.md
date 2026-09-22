# Business Partner Registration API

For the mobile app team. Covers the full partner signup flow: OTP login → complete registration → (optional, any time after) KYC document uploads.

**Base URLs**
- Staging: `https://staging-api.dxempire.in/api/v1`
- Production: `https://api.dxempire.in/api/v1`

**Auth header** (all endpoints except `send-otp`/`verify-otp`):
```
Authorization: Bearer <token>
Accept: application/json
```
Always send `Accept: application/json` — without it, validation errors come back as an HTML redirect instead of JSON.

---

## 1. Send OTP

`POST /auth/send-otp`

No auth required.

**Body**
| Field | Type | Required | Notes |
|---|---|---|---|
| `phone` | string | yes | 10 digits, must start with 6–9 (Indian mobile format) |

```json
{ "phone": "9876543210" }
```

**Success (200)**
```json
{ "success": true, "message": "OTP sent successfully", "data": null }
```

**Errors**
- `422` — invalid phone format (`errors.phone`)

---

## 2. Verify OTP

`POST /auth/verify-otp`

No auth required. This is also the login call — it creates the user record on first use.

**Body**
| Field | Type | Required | Notes |
|---|---|---|---|
| `phone` | string | yes | same number OTP was sent to |
| `code` | string | yes | 6 digits |
| `expo_push_token` | string | no | registers the device for push notifications |
| `device_type` | string | no | `android` or `ios` |

```json
{ "phone": "9876543210", "code": "482913", "expo_push_token": "ExponentPushToken[xxx]", "device_type": "android" }
```

**Success (200)**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "1|abcdef123456...",
    "user": {
      "id": 42,
      "name": "User 9876543210",
      "phone": "9876543210",
      "role": "b2b_partner",
      "partner_id": null,
      "kyc_status": null,
      "permissions": []
    }
  }
}
```

- `kyc_status: null` and `partner_id: null` means this phone has **no Dealer profile yet** → app should route to the registration form (step 3 below).
- If `kyc_status` is `"pending"`, `"verified"`, or `"rejected"`, registration is already complete → skip straight to the main app / KYC-pending screen.
- Save `token` — every subsequent call needs it as `Authorization: Bearer <token>`.

**Errors**
- `401` — no OTP found / expired / wrong code
- `403` — account deactivated

---

## 3. Complete Registration

`POST /auth/complete-registration`

Auth required (the token from step 2). Call this once, right after OTP verification, when `kyc_status` came back `null`. Fails with 422 if the account is already registered.

This is the screen the client specified — all fields below map directly to it.

### Section 1 — Employee *(client's label; maps to the partner's own account)*
| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | **yes** | Full Name |
| `business_name` | string | **yes** | not on the client's mockup, but required — the partner's shop/company name |
| `email` | string | **yes** | matches "Email ID *" on the form. Must be a valid, unused email. |
| `password` | string | **yes** | min 8 characters |
| `unique_code` | string | **yes** | "Employ Code (Mandatory)" on the form — this is the **staff member's (salesman's) own login code** (e.g. `SM001`, `DM001`, `SG001` — from Staff Users, *not* a business partner's code). Entering it assigns this partner to that salesman for downline/commission tracking. Registration fails with "Invalid unique code" if it doesn't match any active staff user, or with a separate message if that staff account isn't linked to an active hierarchy node. The new partner separately gets **their own** fresh partner code back in the response (a different kind of code, `DX`-prefixed — used elsewhere, not for this field). |
| `gst_number` | string | no | optional GST number |

*(Mobile Number isn't a field on this call — it's the phone number already verified via OTP in step 2, tied to the authenticated session.)*

### Section 2 — Address Details *(all optional, exactly matching the client's form — no `*` on any of these, State and Pin Code included)*
| Field | Type | Required | Notes |
|---|---|---|---|
| `village_street` | string | no | max 150 chars |
| `post_office` | string | no | max 100 chars |
| `police_station` | string | no | max 100 chars |
| `district` | string | no | max 100 chars |
| `state` | string | no | max 100 chars |
| `pincode` | string | no | max 10 chars |

### Section 3 — Bank Account Details *(REMOVED from this call — client no longer collects bank details at registration. Do not send these.)*
| Field | Type | Required | Notes |
|---|---|---|---|
| `bank_account_number` | string | no | max 30 chars — accepted if sent, but the app should not send it |
| `confirm_account_number` | string | no | must match `bank_account_number` only if both are present |
| `account_holder_name` | string | no | max 150 chars |
| `bank_name` | string | no | max 150 chars |
| `ifsc_code` | string | no | max 15 chars |

These fields still exist on the endpoint (kept nullable, not deleted) in case a future "complete KYC" step ever needs them — but as of now the client does not want bank details collected at registration, and the app should stop sending all five fields. Registration succeeds fully without them.

### Section 4 — Document Uploads
**Not part of this call.** Submit the form above first; documents go through the separate endpoint in step 4, any time after registration (can be same screen flow, just a second network call, or a completely separate "Complete your KYC" screen later — your call).

**Example request**
```json
{
  "name": "Ramesh Kumar",
  "business_name": "Ramesh Electronics",
  "email": "ramesh@example.com",
  "password": "SecurePass123",
  "unique_code": "SM001",
  "village_street": "12 Gandhi Road",
  "post_office": "Shivaji Nagar PO",
  "police_station": "Shivaji Nagar PS",
  "district": "Pune",
  "state": "Maharashtra",
  "pincode": "411001"
}
```
Note `unique_code` here is `SM001` — a **staff member's own code**, not a partner code. This is the field the client refers to as "Employee Code" in the app.

**Success (200)**
```json
{
  "success": true,
  "message": "Registration complete. Your account is pending KYC approval.",
  "data": {
    "business_name": "Ramesh Electronics",
    "kyc_status": "pending",
    "unique_code": "DXFH3H",
    "assigned_salesman": "Vikram Singh"
  }
}
```
`unique_code` in the response is a **different, new code** — the partner's own `DX`-prefixed code, unrelated to the `SM001` they entered. `assigned_salesman` confirms who they were assigned to.

**Errors**
- `422` — "This account is already registered." (already has a Dealer)
- `422` — "Invalid unique code." (no staff user has that code)
- `422` — "This code is not linked to an active salesman. Contact your admin." (the staff code is real, but that staff member has no Hierarchy node — an admin needs to set one up first)
- `422` — field validation errors (`errors.<field>`)

---

## 4. Upload KYC Documents

`POST /auth/kyc-documents`

Auth required. `multipart/form-data`, not JSON. Call any time after registration — once, for all documents together, or repeatedly as the partner gets each one ready. Every field is optional and independent; sending just one file doesn't require the others. Re-uploading a field replaces the previous file.

| Field | Type | Required | Notes |
|---|---|---|---|
| `aadhaar_number` | string | no | max 20 chars |
| `pan_number` | string | no | max 15 chars |
| `aadhaar_document` | file | no | pdf/jpg/jpeg/png, max 5MB |
| `pan_document` | file | no | pdf/jpg/jpeg/png, max 5MB |
| `passport_photo` | file | no | jpg/jpeg/png only, max 5MB |
| `education_certificate` | file | no | pdf/jpg/jpeg/png, max 5MB |
| `bank_passbook_document` | file | no | pdf/jpg/jpeg/png, max 5MB |
| `signed_agreement_document` | file | no | pdf/jpg/jpeg/png, max 5MB |

At least one field must be present or the call returns a 422.

**Success (200)**
```json
{
  "success": true,
  "message": "Document(s) saved.",
  "data": {
    "kyc_documents": {
      "aadhaar_number": true,
      "pan_number": false,
      "aadhaar_document": true,
      "pan_document": false,
      "passport_photo": false,
      "education_certificate": false,
      "bank_passbook_document": false,
      "signed_agreement_document": false
    }
  }
}
```
The `kyc_documents` object is a checklist — `true` means that item is on file. Use it to show upload progress / what's still missing.

**Errors**
- `422` — "Complete your registration before uploading documents." (no Dealer yet)
- `422` — "No document or detail provided to save." (empty request)
- `422` — file validation (wrong type / too large)

---

## 5. Check Status / Get Profile

`GET /auth/me`

Auth required. Call this on app resume to get the partner's current, authoritative status — including live KYC document checklist.

**Success (200)** — for a `b2b_partner` role:
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 42,
    "name": "Ramesh Kumar",
    "phone": "9876543210",
    "email": null,
    "role": "b2b_partner",
    "partner_id": null,
    "is_active": true,
    "permissions": [],
    "kyc_status": "pending",
    "business_name": "Ramesh Electronics",
    "gst_number": null,
    "state": "Maharashtra",
    "district": "Pune",
    "pincode": "411001",
    "village_street": "12 Gandhi Road",
    "post_office": "Shivaji Nagar PO",
    "police_station": "Shivaji Nagar PS",
    "account_holder_name": null,
    "bank_name": null,
    "ifsc_code": null,
    "bank_account_last4": null,
    "price_tier": null,
    "unique_code": "DXFH3H",
    "assigned_salesman": "Vikram Singh",
    "has_dealer": true,
    "kyc_documents": {
      "aadhaar_number": true,
      "pan_number": false,
      "aadhaar_document": true,
      "pan_document": false,
      "passport_photo": false,
      "education_certificate": false,
      "bank_passbook_document": false,
      "signed_agreement_document": false
    }
  }
}
```

Notes:
- `account_holder_name`/`bank_name`/`ifsc_code`/`bank_account_last4` will normally be `null` now, since registration no longer collects bank details.
- `bank_account_last4` — only the last 4 digits are returned here (not the full number), by design, for the rare case these do get set later.
- `has_dealer: false` means registration was never completed → same routing as `kyc_status: null` from step 2.
- `kyc_status` moves from `pending` → `verified` (or `rejected`) only after admin review in the back office — there's no partner-facing action for that.

---

## Flow Summary

```
send-otp → verify-otp
              │
              ├─ kyc_status present (has_dealer=true) → go to main app
              │
              └─ kyc_status null (has_dealer=false)
                     │
                     ▼
              complete-registration  (name, business_name, email, password,
                                       unique_code = STAFF/salesman code (assigns partner
                                       to that salesman) — address fields optional,
                                       no bank fields sent)
                     │
                     ▼
              kyc-documents  (optional, any time — call once or repeatedly)
                     │
                     ▼
              me  (poll/refresh to show current kyc_status + document checklist)
```
