# Password Reset / Change Password API

For the mobile app team. Covers forgot-password, reset-password, change-password, and the updated Sales login (password is now **required**, not optional — see the update note below if you built against an earlier version of this doc).

> **Update**: an earlier version of this doc said password was optional for Sales login. That's been reversed per a client decision — password is now **mandatory** for every Sales ID login, same as partner/warehouse. If you already built the optional version, the fix is small: just always show the password field and always send it (no more "send it only if the account has one" branching).

**Base URLs**
- Staging: `https://staging-api.dxempire.in/api/v1`
- Production: `https://api.dxempire.in/api/v1`

This reuses the exact SMS OTP system already used for partner login/registration — same `OtpCode` mechanism, same delivery. Right now the OTP SMS uses the existing "Partner login OTP" template wording (works everywhere, including warehouse/sales password resets); a dedicated "password reset" template has been submitted for DLT approval and will be swapped in later with no API changes on your side.

---

## Who has a password

| Login | Password required? |
|---|---|
| Partner (email/phone + password) | Yes — always required |
| Warehouse / office staff (email + password) | Yes — always required |
| Sales / hierarchy roles (Sales ID login) | **Yes — always required, as of this update** |

**Sales login screen needs a second field now**: `POST /mobile/auth/login` requires both `unique_code` and `password`.

```json
{ "unique_code": "SM001", "password": "TheirPassword123" }
```

**Errors (401)**
```json
// password field missing entirely from the request
{ "message": "The password field is required.", "errors": { "password": ["The password field is required."] } }

// account exists but has no password set (shouldn't happen for new accounts — every
// creation path now requires one — but could apply to an old account from before this change)
{ "success": false, "message": "No password set for this account yet. Use Forgot Password to set one." }

// wrong password
{ "success": false, "message": "Invalid Sales ID or password" }
```

If a real user ever hits that second error (no password set), point them at the Forgot Password flow below — entering their Sales ID there sends them an OTP and lets them set one, same as everyone else.

---

## 1. Forgot Password

`POST /auth/forgot-password`

No auth required. Works for **any** account that has a password (partner, warehouse, or a sales account that's set one).

**Body**
| Field | Type | Required | Notes |
|---|---|---|---|
| `identifier` | string | yes | Phone, email, **or** Sales ID/unique_code — any of the three. The OTP always goes by SMS to the phone number already on that account, regardless of which identifier was typed. |

```json
{ "identifier": "ramesh@example.com" }
```

**Success (200)** — always the same message, whether or not a match was found (so this can't be used to check which accounts exist):
```json
{ "success": true, "message": "If an account matches, an OTP has been sent to the mobile number on file." }
```

---

## 2. Reset Password

`POST /auth/reset-password`

No auth required. Completes the forgot-password flow.

**Body**
| Field | Type | Required | Notes |
|---|---|---|---|
| `identifier` | string | yes | Same value used in step 1 |
| `code` | string | yes | 6-digit OTP |
| `password` | string | yes | min 8 characters |
| `password_confirmation` | string | yes | must match `password` |

```json
{
  "identifier": "ramesh@example.com",
  "code": "482913",
  "password": "NewSecurePass123",
  "password_confirmation": "NewSecurePass123"
}
```

**Success (200)**
```json
{ "success": true, "message": "Password reset successfully. Please log in with your new password." }
```
Resetting a password **logs out every existing session** on that account (all tokens revoked) — the user will need to log in again with the new password wherever they were signed in.

**Errors**
- `401` — `"No OTP found for this account. Please request a new one."`
- `401` — `"OTP has expired. Please request a new one."`
- `401` — `"Invalid OTP. Please try again."`
- `422` — validation errors (e.g. passwords don't match, too short)

---

## 3. Change Password

`POST /auth/change-password`

Auth required (Bearer token). For someone already logged in who wants to set/update their password directly — no OTP needed here.

**Body**
| Field | Type | Required | Notes |
|---|---|---|---|
| `current_password` | string | **only if the account already has one** | Omit entirely if this account has never had a password (e.g. a Sales account setting one for the first time) |
| `password` | string | yes | min 8 characters |
| `password_confirmation` | string | yes | must match `password` |

```json
{
  "current_password": "OldPass123",
  "password": "NewPass456",
  "password_confirmation": "NewPass456"
}
```

**Success (200)**
```json
{ "success": true, "message": "Password changed successfully." }
```

**Errors**
- `422` — `"Current password is incorrect."`
- `422` — `"The current password field is required."` (account has a password, but none was sent)
- `422` — validation errors (passwords don't match, too short)

This is also the recovery path for the rare old Sales account that predates this change and has no password yet: call this with no `current_password` to set one directly (no OTP needed if they're already logged in some other way), or use Forgot Password if they're locked out entirely.
