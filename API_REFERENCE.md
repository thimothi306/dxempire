# DXEmpire — Complete API Reference

Full reference for every API in the system — the 3 apps (Staff, Partner, Warehouse), the admin
web dashboard's back-office endpoints, the not-yet-consumed B2C storefront, and Delhivery's direct
courier API — organized so any endpoint can be tested standalone in Postman.

**Parts 1–3** (mobile-facing) responses are **real samples captured from the live production API.**
**Parts 4–6** (admin back-office, retail storefront, Delhivery direct) are **derived directly from
the current controller/validation code** — accurate to the field names and shapes the code actually
produces, but not all individually re-captured live; where something *has* been confirmed against
a real request, that's called out explicitly (see Known Gaps at the end).

---

## Base URL

```
https://api.dxempire.in/api/v1
```

## Authentication

All protected endpoints use a **Bearer token** (Laravel Sanctum). Obtain it from the relevant `login`
endpoint, store it, and send it on every subsequent request:

```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

- **Staff app** tokens come from `POST /mobile/auth/login`
- **Partner app** tokens come from `POST /partner/auth/login`
- Tokens are valid for **30 days**. On `401 Unauthenticated`, send the user back to the login screen.

## Standard response envelope

Every response has this shape:

```json
{ "success": true, "message": "Success", "data": { ... } }
```

- `success` — boolean
- `message` — human-readable status
- `data` — the payload (object, array, or `null`)

Paginated lists add a `meta` object: `{ "current_page", "per_page", "total", "last_page" }`.

---
---

# 📱 PART 1 — STAFF (SALES) APP

The sales team logs in with their **unique Sales ID** (no password): `SM001`, `AM001`, `DM001`, `SG001`, etc.
The app shows a **role-specific dashboard** and the person's **team/hierarchy**.

Hierarchy levels (derived from the Sales ID prefix):

| Prefix | Level |
|--------|-------|
| `CEO`  | CEO |
| `SM`   | State Manager |
| `AM`   | Area Manager |
| `DM`   | District Manager |
| `SG`   | Salesman |

---

## 1.1 Login — `POST /mobile/auth/login`

Login with Sales ID only. **No auth header needed.**

**Request**
```json
{ "unique_code": "SM001" }
```

**Response `200`**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 2,
      "name": "Rajesh Kumar",
      "email": "rajesh@dxempire.com",
      "phone": "9111111102",
      "unique_code": "SM001",
      "role": "sales"
    },
    "token": "15|4wRByj0eBQkzi0uLoxdRZ24BTcJ6B2YSJEcQtCCY84649d96"
  }
}
```

**Error `401`** — invalid / inactive ID
```json
{ "success": false, "message": "Invalid Sales ID or account is inactive", "code": 401 }
```

---

## 1.2 My Profile — `GET /mobile/auth/me`

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 2,
    "name": "Rajesh Kumar",
    "email": "rajesh@dxempire.com",
    "phone": "9111111102",
    "unique_code": "SM001",
    "role": "sales",
    "parent": {
      "id": 1,
      "name": "Anil Sharma",
      "unique_code": "CEO001",
      "role": "super_admin"
    },
    "department": null
  }
}
```
`parent` is `null` for the top of the tree.

---

## 1.3 Logout — `POST /mobile/auth/logout`

Revokes the current token.

**Response `200`**
```json
{ "success": true, "message": "Logged out successfully", "data": null }
```

---

## 1.4 Dashboard — `GET /mobile/dashboard`

Returns a **different payload per hierarchy level**. Detect the shape from the caller's Sales ID prefix.

### 1.4.a State Manager (`SM*`)
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "user_info": {
      "name": "Rajesh Kumar",
      "unique_code": "SM001",
      "phone": "9111111102",
      "role": "State Manager",
      "state": null,
      "reports_to": "Anil Sharma"
    },
    "state_info": { "total_state_members": 7, "area_managers": 2 },
    "state_stats": {
      "total_orders": 0,
      "total_leads": 0,
      "state_revenue": "₹0",
      "state_conversion": "0%"
    },
    "quick_actions": [
      "view_state_structure", "view_state_orders", "view_state_leads",
      "view_state_performance", "manage_area_managers"
    ],
    "area_performance": [],
    "top_district_managers": []
  }
}
```

### 1.4.b Area Manager (`AM*`)
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "user_info": {
      "name": "Priya Singh", "unique_code": "AM001", "phone": "9111111103",
      "role": "Area Manager", "zone": null,
      "reports_to": "Rajesh Kumar", "reports_to_code": "SM001"
    },
    "zone_info": { "total_zone_members": 3, "district_managers": 1, "salesmen": 2 },
    "zone_stats": {
      "total_orders": 0, "total_leads": 0, "zone_revenue": "₹0", "zone_conversion": "0%"
    },
    "quick_actions": [
      "view_zone", "view_zone_orders", "view_zone_leads",
      "view_zone_performance", "manage_district_managers"
    ],
    "zone_performance": [],
    "top_salesmen": []
  }
}
```

### 1.4.c District Manager (`DM*`)
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "user_info": {
      "name": "Amit Patel", "unique_code": "DM001", "phone": "9111111105",
      "role": "District Manager", "territory": null,
      "reports_to": "Priya Singh", "reports_to_code": "AM001"
    },
    "team_info": {
      "total_team_members": 2,
      "direct_reports": 2,
      "team_members": [
        { "id": 7, "name": "Vikram Singh", "unique_code": "SG001", "role": "sales" },
        { "id": 8, "name": "Suresh Patel", "unique_code": "SG002", "role": "sales" }
      ]
    },
    "team_stats": {
      "total_orders": 0, "total_leads": 0, "team_revenue": "₹0", "average_conversion": "0%"
    },
    "my_stats": { "my_orders": 0, "my_leads": 0 },
    "quick_actions": [
      "view_team", "view_team_orders", "view_team_leads",
      "view_team_performance", "create_lead", "create_order"
    ],
    "team_performance": [],
    "recent_team_orders": []
  }
}
```

### 1.4.d Salesman (`SG*`)
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "user_info": {
      "name": "Vikram Singh", "unique_code": "SG001", "phone": "9111111107",
      "role": "Salesman", "reports_to": "Amit Patel", "reports_to_code": "DM001"
    },
    "my_stats": {
      "total_orders": 0, "total_leads": 0, "conversion_rate": "0%", "month_revenue": "₹0"
    },
    "quick_actions": [
      "create_lead", "create_order", "view_orders", "view_leads", "update_profile"
    ],
    "recent_orders": [],
    "recent_leads": []
  }
}
```

> **Note:** revenue/order/lead stats are currently `0`/`[]` placeholders — the Orders & Leads
> aggregation into the mobile dashboard is not wired up yet on the backend. Structure is final;
> only the numbers will populate later.

---

## 1.5 My Team (all levels below me) — `GET /mobile/hierarchy/subordinates`

Flat list of **everyone** under the logged-in user (recursive).

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "total_subordinates": 7,
    "subordinates": [
      { "id": 3, "name": "Priya Singh",  "unique_code": "AM001", "email": "priya@dxempire.com",  "phone": "9111111103", "role": "area_manager",     "is_active": true },
      { "id": 5, "name": "Amit Patel",   "unique_code": "DM001", "email": "amit@dxempire.com",   "phone": "9111111105", "role": "district_manager", "is_active": true },
      { "id": 7, "name": "Vikram Singh", "unique_code": "SG001", "email": "vikram@dxempire.com", "phone": "9111111107", "role": "sales",            "is_active": true }
    ]
  }
}
```

---

## 1.6 Org Tree — `GET /mobile/hierarchy/tree`

Nested tree structure under the logged-in user.

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 2, "name": "Rajesh Kumar", "unique_code": "SM001", "role": "sales",
    "subordinates": [
      {
        "id": 3, "name": "Priya Singh", "unique_code": "AM001", "role": "area_manager",
        "subordinates": [
          {
            "id": 5, "name": "Amit Patel", "unique_code": "DM001", "role": "district_manager",
            "subordinates": [
              { "id": 7, "name": "Vikram Singh", "unique_code": "SG001", "role": "sales", "subordinates": [] },
              { "id": 8, "name": "Suresh Patel", "unique_code": "SG002", "role": "sales", "subordinates": [] }
            ]
          }
        ]
      }
    ]
  }
}
```

---

## 1.7 Team Stats — `GET /mobile/hierarchy/team-stats`

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "total_team_size": 7,
    "by_role": { "area_manager": 2, "district_manager": 2, "sales": 3 },
    "direct_reports": 2,
    "total_orders": 0,
    "total_leads": 0
  }
}
```

---

## 1.8 Colleagues (same level) — `GET /mobile/hierarchy/colleagues`

Other people reporting to the **same parent**.

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": { "total_colleagues": 0, "colleagues": [] }
}
```
When there are colleagues, each entry looks like:
`{ "id", "name", "unique_code", "role" }`.

---

## 1.9 Attendance — Self Check-In / Check-Out (selfie + GPS)

Staff mark their **own** attendance from the app. The employee is resolved from the auth token —
**do not send an employee_id**. Requests are `multipart/form-data` (because of the selfie file).

> ⚠️ Base path is **`/mobile/attendance/...`** (not `/attendance/...`). Hitting
> `/mobile/attendance/check-in` without the `mobile` prefix, or `/hr/attendance/check-in`
> (which is HR-admin-only and takes a raw `employee_id`), is what was returning 404/403.

### 1.9.a Today's status — `GET /mobile/attendance/status`
Lets the app decide whether to show a Check-In or Check-Out button.
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "employee_id": 17,
    "name": "Suresh Patel",
    "date": "2026-07-27",
    "checked_in": false,
    "checked_out": false,
    "attendance": null
  }
}
```

### 1.9.b Check in — `POST /mobile/attendance/check-in`
**Content-Type:** `multipart/form-data`

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `selfie` | image file (jpg/jpeg/png/webp, ≤ 8 MB) | optional | Stored server-side, returned as a public URL |
| `latitude` | number (−90…90) | optional | e.g. `19.0760` |
| `longitude` | number (−180…180) | optional | e.g. `72.8777` |
| `timestamp` | ISO 8601 date string | optional | Used as the check-in time if sent; otherwise server time is used |

> The field names you're already sending — `selfie`, `latitude`, `longitude`, `timestamp` — are
> **correct**. No renaming needed.

**Response `200`**
```json
{
  "success": true,
  "message": "Checked in successfully.",
  "data": {
    "id": 402,
    "employee_id": 17,
    "date": "2026-07-27T00:00:00.000000Z",
    "status": "present",
    "check_in": "2026-07-27T09:15:00.000000Z",
    "check_in_selfie": "https://api.dxempire.in/uploads/attendance/att_17_20260727_091500_in_a1B2c3.png",
    "check_in_lat": "19.0760000",
    "check_in_lng": "72.8777000",
    "check_out": null,
    "check_out_selfie": null,
    "check_out_lat": null,
    "check_out_lng": null
  }
}
```

**Error `422`** — already checked in today: `{ "success": false, "message": "You have already checked in today." }`
**Error `404`** — the logged-in user has no linked employee record: `{ "success": false, "message": "No employee profile is linked to your account. Contact HR." }`

### 1.9.c Check out — `POST /mobile/attendance/check-out`
Same fields as check-in. Requires an existing check-in for today.

**Response `200`** — same shape, now with `check_out`, `check_out_selfie`, `check_out_lat/lng` populated.

**Error `422`** — `"No check-in found for today. Please check in first."` or `"You have already checked out today."`

---
---

# 🤝 PART 2 — PARTNER APP

Business partners (dealers) log in with **email or phone + password**. The app is **view-only**
for orders/invoices/dues, plus a **product catalog** to browse stock by brand and grade.

---

## 2.1 Login — `POST /partner/auth/login`

**No auth header needed.** `login` accepts **email OR phone**.

**Request**
```json
{ "login": "partner1@dxempire.com", "password": "password123" }
```

**Response `200`**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "17|xBNcLiJFIgAhQGSGsCFn00wN9Bz943DzsCKmIARH11d56c14",
    "partner": {
      "id": 14,
      "name": "Sharma Electronics (Owner)",
      "email": "partner1@dxempire.com",
      "phone": "9933000000",
      "business_name": "Sharma Electronics",
      "kyc_status": "verified",
      "gst_number": "27AABF6C6C8Z0",
      "state": "Maharashtra",
      "pincode": "400001",
      "price_tier": "T1",
      "has_dealer": true
    }
  }
}
```

**Error `401`**
```json
{ "success": false, "message": "Invalid login or password." }
```

**Error `403`** — deactivated account
```json
{ "success": false, "message": "Your account has been deactivated. Please contact your sales representative." }
```

---

## 2.2 My Profile — `GET /partner/auth/me`

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 14,
    "name": "Sharma Electronics (Owner)",
    "email": "partner1@dxempire.com",
    "phone": "9933000000",
    "business_name": "Sharma Electronics",
    "kyc_status": "verified",
    "gst_number": "27AABF6C6C8Z0",
    "state": "Maharashtra",
    "pincode": "400001",
    "price_tier": "T1",
    "has_dealer": true
  }
}
```

---

## 2.3 Logout — `POST /partner/auth/logout`

**Response `200`**
```json
{ "success": true, "message": "Logged out successfully", "data": null }
```

---

## 2.4 Dashboard — `GET /partner/dashboard`

Summary tiles + recent orders for the logged-in partner only.

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "business_name": "Sharma Electronics",
    "kyc_status": "verified",
    "total_orders": 1,
    "active_orders": 1,
    "delivered_orders": 0,
    "lifetime_purchases": 0,
    "credit_limit": 900000,
    "credit_used": 131269,
    "available_credit": 768731,
    "recent_orders": [
      {
        "id": 11,
        "order_number": "ORD-00011",
        "status": "packing",
        "total_amount": "305156.26",
        "created_at": "2026-07-20T01:41:59.000000Z"
      }
    ]
  }
}
```

---

## 2.5 My Orders — `GET /partner/orders`

Paginated. Optional filters: `?status=delivered` and `?per_page=15`.

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 11,
      "order_number": "ORD-00011",
      "dealer_id": 1,
      "status": "packing",
      "payment_status": "unpaid",
      "subtotal": "258607.00",
      "gst_amount": "46549.26",
      "total_amount": "305156.26",
      "awb_number": null,
      "logistics_provider": null,
      "dispatched_at": null,
      "delivered_at": null,
      "notes": "Bulk order of refurbished devices",
      "created_at": "2026-07-20T01:41:59.000000Z",
      "items_count": 3
    }
  ],
  "meta": { "current_page": 1, "per_page": 2, "total": 1, "last_page": 1 }
}
```

**Order status values:** `pending`, `approved`, `picking`, `packing`, `packed`, `dispatched`, `delivered`, `cancelled`, `returned`
**Payment status values:** `unpaid`, `partial`, `paid`, `refunded`

---

## 2.6 Place an Order — `POST /partner/orders`

**The end-to-end ordering endpoint.** Send `{ brand, model, grade, quantity }` lines straight from
the catalog response (2.10/2.11) — no need to know individual product/unit IDs. The backend finds
that many available units matching each line, locks them, and creates the order.

`dealer_id` is **always** taken from the authenticated partner's own account — it is never read from
the request body, so a partner can only ever order for themselves.

**Request**
```json
{
  "items": [
    { "brand": "Samsung", "model": "Galaxy S23 Ultra", "grade": "S3", "category": "phone", "quantity": 1 }
  ],
  "notes": "Please pack securely"
}
```
- `items` — required, 1–20 lines
- `items[].brand`, `items[].model` — required, must match a catalog entry
- `items[].grade` — required, one of `S1`–`S5`
- `items[].category` — optional (`phone`/`laptop`) — helps disambiguate if the same model name exists across categories
- `items[].quantity` — required, 1–50
- `notes` — optional, free text

**Response `201`**
```json
{
  "success": true,
  "message": "Order placed successfully.",
  "data": {
    "id": 15,
    "order_number": "DX-2026-00015",
    "dealer_id": 1,
    "status": "pending",
    "payment_status": "unpaid",
    "subtotal": "58381.00",
    "gst_amount": "10508.58",
    "total_amount": "68889.58",
    "credit_used": "68889.58",
    "billing_state": "Maharashtra",
    "shipping_state": "Maharashtra",
    "notes": "Please pack securely",
    "created_at": "2026-07-21T06:21:30.000000Z",
    "items": [
      {
        "id": 33,
        "product_id": 35,
        "quantity": 1,
        "unit_price": "58381.00",
        "gst_rate": "18.00",
        "gst_amount": "10508.58",
        "line_total": "68889.58",
        "product": { "id": 35, "brand": "Samsung", "model": "Galaxy S23 Ultra", "category": "phone", "grade": "S3" }
      }
    ]
  }
}
```

The order is created with **`status: "pending"`**. It moves to `approved` once your sales rep / admin
reviews it — track progress via `GET /partner/orders/{id}`. Note: the order appears immediately in
"My Orders", but `credit_used` on the dealer account only increases once the order is **approved**
(matches the existing admin order-approval flow).

**Error `422`** — not enough stock for a line
```json
{ "success": false, "message": "Only 1 unit(s) available for Samsung Galaxy S23 Ultra (Grade S3), requested 5." }
```

**Error `422`** — over the dealer's credit limit or KYC not verified
```json
{ "success": false, "message": "Insufficient credit or KYC not verified. Available: ₹768731.00" }
```

**Error `422`** — validation (e.g. quantity over the 50 cap)
```json
{ "message": "The items.0.quantity must not be greater than 50.", "errors": { "items.0.quantity": ["The items.0.quantity must not be greater than 50."] } }
```
> ⚠️ Validation errors like this one are only returned as JSON if the request sends
> `Accept: application/json`. Without it, Laravel redirects instead of returning JSON — always send
> this header (see **Authentication** at the top of this doc).

---

## 2.7 Order Detail — `GET /partner/orders/{id}`

Full order with line items (each item includes its product's brand/model/grade), payments, and invoice.
A partner can only open **their own** orders (others return `404`).

**Response `200`** (truncated to show item shape)
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 11,
    "order_number": "ORD-00011",
    "status": "packing",
    "payment_status": "unpaid",
    "subtotal": "258607.00",
    "gst_amount": "46549.26",
    "total_amount": "305156.26",
    "created_at": "2026-07-20T01:41:59.000000Z",
    "items": [
      {
        "id": 23,
        "product_id": 6,
        "quantity": 2,
        "unit_price": "62205.00",
        "gst_rate": "18.00",
        "gst_amount": "22393.80",
        "line_total": "146803.80",
        "product": { "id": 6, "brand": "Dell", "model": "XPS 13", "category": "laptop", "grade": "S3" }
      }
    ],
    "payments": [],
    "invoice": null
  }
}
```

**Error `404`** — not the partner's order / not found
```json
{ "success": false, "message": "Order not found.", "code": 404 }
```

---

## 2.8 My Invoices — `GET /partner/invoices`

Paginated. Each invoice includes its related order summary.

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "invoice_number": "INV-00003",
      "dealer_id": 1,
      "subtotal": "137900.00",
      "gst_amount": "24822.00",
      "total": "162722.00",
      "issued_at": "2026-07-03T13:14:48.000000Z",
      "order": { "id": 3, "order_number": "ORD-00003", "status": "delivered" }
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```
When a partner has no invoices yet, `data` is `[]` with `"total": 0`.

---

## 2.9 My Dues — `GET /partner/dues`

Outstanding balance + list of unpaid/partial orders.

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "credit_limit": 900000,
    "credit_used": 131269,
    "available_credit": 768731,
    "outstanding_amount": 131269,
    "unpaid_orders": [
      {
        "id": 11,
        "order_number": "ORD-00011",
        "status": "packing",
        "payment_status": "unpaid",
        "total_amount": "305156.26",
        "created_at": "2026-07-20T01:41:59.000000Z"
      }
    ],
    "note": "To make a payment, please use the DXEmpire mobile app or contact your sales representative."
  }
}
```

---

## 2.10 Catalog — Brands — `GET /partner/catalog/brands`

In-stock brands for the brand selector. Optional `?category=phone|laptop`.

> **Changed 2026-07-23:** the `accessory` category has been removed — this catalog now covers only
> `phone` and `laptop`.

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": [
    { "brand": "Apple",   "available_qty": 5, "image_url": "https://.../iphone-13.png" },
    { "brand": "Dell",    "available_qty": 2, "image_url": null },
    { "brand": "HP",      "available_qty": 3, "image_url": "https://.../hp-pavilion-15.png" },
    { "brand": "Samsung", "available_qty": 5, "image_url": "https://.../galaxy-s22.png" },
    { "brand": "Xiaomi",  "available_qty": 3, "image_url": null }
  ]
}
```

> **Added 2026-07-22:** `image_url` per brand. `CatalogImage` is stored per brand+model+category (not
> per brand alone), so this is a **representative** photo — the earliest-uploaded model image for that
> brand — used for the brand-selector tile. `null` if no model under that brand has a photo yet.

---

## 2.11 Catalog — Models by Brand — `GET /partner/catalog`

**Select a brand → get its models, one row per model**, each listing which grades are in stock.
`data` is a **plain array** (not wrapped in an object).

**Query params (all optional):** `brand`, `category`, `grade` (only include models that have this grade in stock), `search`

**Example:** `GET /partner/catalog?brand=Apple`

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "brand": "Apple",
      "model": "iPhone 14 Pro",
      "category": "phone",
      "image_url": "https://placehold.co/600x600/1d1d1f/ffffff?text=iPhone+14+Pro",
      "total_available": 2,
      "price_from": 38495,
      "price_to": 63080,
      "grades_available": ["S2", "S3"],
      "grades": [
        { "grade": "S2", "available_qty": 1, "price_from": 38495, "price_to": 38495 },
        { "grade": "S3", "available_qty": 1, "price_from": 63080, "price_to": 63080 }
      ]
    }
  ]
}
```

- `grades_available` — flat array of grade codes in stock for this model (`S1`–`S5`)
- `grades` — same grades with per-grade `available_qty` and price range, for a "choose grade" screen
- `total_available` / `price_from` / `price_to` — totals across **all** grades of this model
- `image_url` — **model-level** stock photo (one photo per brand+model+category, not per physical unit). **`null`** if no photo has been uploaded yet — show a placeholder in the UI when null. Currently seeded with placeholder images for demo; real product photography needs to be uploaded via the admin panel (see note below).

> **Changed 2026-07-22:** this endpoint previously returned `{ total_variants, items: [...] }` with
> one flat row per (model, grade) pair. It now groups by model as shown above — if your app was built
> against the old shape, update the mapping to read `data` directly and use `grades_available` /
> `grades` instead of a flat per-grade list.

---

## 2.12 Catalog — Grades for a Model — `GET /partner/catalog/grades`

Grade breakdown for a specific brand + model (e.g. tap a phone → see its grades).

**Query params (required):** `brand`, `model`

**Example:** `GET /partner/catalog/grades?brand=Samsung&model=Galaxy S23 Ultra`

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "brand": "Apple",
    "model": "iPhone 13",
    "image_url": "https://placehold.co/600x600/1d1d1f/ffffff?text=iPhone+13",
    "grades": [
      { "grade": "S1", "available_qty": 1, "price_from": "52293.00", "price_to": "52293.00" },
      { "grade": "S5", "available_qty": 1, "price_from": "89999.00", "price_to": "89999.00" }
    ]
  }
}
```

---
---

# 🏭 PART 3 — WAREHOUSE APP

Same planned mobile app as Part 1/2, for warehouse staff. **Login is different** — warehouse staff use
the **general admin login** (email + password), not a Sales ID and not partner credentials, because
warehouse accounts live in the same system as other back-office staff.

---

## 3.1 Login — `POST /auth/admin/login`

**Request**
```json
{ "email": "mohan@dxempire.com", "password": "password123" }
```

**Response `200`**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "30|2fP9VRqU5Ogg7ipSfnSf35GFmiuxDfrqaHNYa3kid9383898",
    "user": {
      "id": 10,
      "name": "Mohan Kumar",
      "phone": "9111111110",
      "email": "mohan@dxempire.com",
      "role": "warehouse_staff",
      "permissions": ["inventory.view", "inventory.edit", "orders.view", "orders.dispatch", "procurement.view", "procurement.edit"]
    }
  }
}
```
Use the same Bearer token pattern as Parts 1 & 2. Logout: `POST /auth/logout`.

---

## 3.2 Inventory List — `GET /inventory`

Paginated, with filters. Warehouse staff see all statuses (not just in-stock).

**Query params (all optional):** `category`, `grade`, `status`, `bin_id`, `brand`, `search`, `sort`, `direction`, `per_page`

**Product status values:** `received`, `qc_pending`, `in_stock`, `reserved`, `sold`, `returned`, `rejected`, `refurbishment`

**Example:** `GET /inventory?category=phone&per_page=2`

**Response `200`** (truncated — each item includes full `bin` and `supplier` objects)
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 7,
      "imei": "0000004596609236",
      "serial_number": "SN946568",
      "category": "phone",
      "brand": "Samsung",
      "model": "Galaxy S23 Ultra",
      "grade": "S3",
      "status": "sold",
      "purchase_price": "48049.00",
      "selling_price": "68002.00",
      "bin_id": null,
      "supplier_id": 6,
      "qc_passed_at": "2026-07-04T09:41:59.000000Z",
      "sold_at": "2026-07-08T09:41:59.000000Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 2, "total": 25, "last_page": 13 }
}
```

---

## 3.3 IMEI Lookup — `GET /inventory/imei/{imei}`

For a barcode/IMEI scan screen. Returns the full unit with bin, supplier, and QC history.

**Example:** `GET /inventory/imei/0000004596609236`

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 7,
    "imei": "0000004596609236",
    "serial_number": "SN946568",
    "category": "phone",
    "brand": "Samsung",
    "model": "Galaxy S23 Ultra",
    "grade": "S3",
    "status": "sold",
    "purchase_price": "48049.00",
    "selling_price": "68002.00",
    "bin": null,
    "supplier": { "id": 6, "name": "ElectroHub Suppliers", "phone": "9822000005", "type": "buyback_partner" }
  }
}
```

**Error `404`**
```json
{ "success": false, "message": "No product found with IMEI: 0000000000000", "code": 404 }
```

---

## 3.4 Bins — `GET /bins`

Paginated list of storage bins with live product counts.

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": [
    { "id": 1, "code": "BIN-001", "zone": "Zone A", "row": "R1", "shelf": "S4", "capacity": 50, "current_count": 10, "products_count": 3 }
  ],
  "meta": { "current_page": 1, "per_page": 100, "total": 10, "last_page": 1 }
}
```

## 3.5 Move a Product to a Bin — `POST /bins/move`

**Request**
```json
{ "product_id": 40, "bin_id": 3 }
```

**Response `200`**
```json
{
  "success": true,
  "message": "Product moved to bin BIN-003.",
  "data": { "product_id": 40, "bin": { "id": 3, "code": "BIN-003", "current_count": 43, "capacity": 50 } }
}
```

**Error `422`** — bin full
```json
{ "success": false, "message": "Bin BIN-003 is full. Capacity: 50, Current: 50." }
```

## 3.6 Products in a Bin — `GET /bins/{id}/products`

Returns the array of products currently stored in that bin (same shape as inventory list items).

---

## 3.7 Receive Stock — `POST /procurement/receive`

Add new units into inventory (status starts as `received`, awaiting QC). Supports batch entry.

**Request**
```json
{
  "supplier_id": 1,
  "purchase_order_id": null,
  "items": [
    { "category": "phone", "brand": "Apple", "model": "iPhone 15", "purchase_price": 45000, "imei": "356789012345678", "serial_number": "APIPTEST01" }
  ]
}
```
- `items[].imei` — optional, but must be **exactly 15 digits** and globally unique (including soft-deleted units) when provided
- Use `POST /purchase-orders/{id}/receive` instead (same body, minus `purchase_order_id`) to receive against a specific PO — it auto-fills the PO link

**Response `200`**
```json
{ "success": true, "message": "1 item(s) received successfully.", "data": { "created_count": 1, "created_ids": [41], "failed": [] } }
```

**Error `422`** — duplicate IMEI (whole batch is rejected, nothing partially created)
```json
{ "success": false, "message": "Batch receive failed due to duplicate IMEI." }
```

## 3.8 Receiving History — `GET /procurement/history`

Paginated list of received products with their supplier and purchase-order context. Same shape as `GET /inventory`.

---

## 3.9 QC — Pending Queue — `GET /qc/pending`

Units awaiting grading (`status = received`). Same object shape as inventory list.

## 3.10 QC — Submit a Grade — `POST /qc/grade`

**Request**
```json
{ "product_id": 41, "grade": "S2", "condition_notes": "Minor scratches on back panel", "outcome": "pass" }
```
- `outcome` — one of `pass`, `repair`, `reject`
- `grade` — required **only if** `outcome` is `pass` (one of `S1`–`S5`)

**Response `200`**
```json
{
  "success": true,
  "message": "QC grade recorded.",
  "data": {
    "qc_record": { "id": 13, "product_id": 41, "engineer_id": 10, "grade": "S2", "condition_notes": "Minor scratches on back panel", "outcome": "pass", "graded_at": "2026-07-21T06:26:57.000000Z" },
    "product": { "id": 41, "status": "in_stock", "grade": "S2", "selling_price": "33750.00" }
  }
}
```
On `pass`, the product's status automatically becomes `in_stock` (ready to sell) and its `selling_price` is computed from the grade.

## 3.11 QC — Records & Stats — `GET /qc/records`, `GET /qc/stats`

`records` is a paginated audit trail of all grading decisions (same fields as the `qc_record` object
above). `stats` returns pass/repair/reject counts for a dashboard tile.

---

## 3.12 Peti to Peti — Bulk Stock Transfers

"Peti" (crate) transfers move a batch of units either between internal warehouse locations
(`type: internal`) or out to a dealer as a bulk consignment (`type: dealer`). Requires
`super_admin` or `warehouse_staff`. Lifecycle: `draft → approved → completed` (or `cancelled` from
either of the first two states).

> ⚠️ **Data-integrity note (found while verifying this doc against live production data):** some
> existing rows were seeded directly into the database before this endpoint existed, with a
> `transfer_number` of `PT-00001` (not the `PTR-`-prefixed format `PetiTransfer::generateTransferNumber()`
> actually produces) and a simplified `items` shape of `{ "grade": "S1", "qty": 18 }` — missing
> `category`/`brand`/`model`/`quantity`/`unit_price` that `POST /peti-transfers` requires and always
> writes going forward. **Anything created through this API from now on will match the documented
> shape below** (`PTR-00001` prefix, full `items` objects) — only pre-existing legacy rows look
> different. If the app renders `items`, handle both shapes defensively (check for `qty` vs `quantity`).

**3.12.a List** — `GET /peti-transfers` — filters: `status`, `type`, `from`, `to` (date range on `created_at`). Paginated.
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 7, "transfer_number": "PTR-00007", "type": "dealer", "status": "draft",
      "from_location": null, "to_location": null, "to_dealer_id": 3,
      "total_units": 5, "total_value": "160000.00", "notes": "Bulk consignment for Diwali stock-up",
      "transferred_at": null,
      "created_at": "2026-07-29T09:10:00.000000Z", "updated_at": "2026-07-29T09:10:00.000000Z",
      "created_by": { "id": 2, "name": "Mohan Kumar" },
      "approved_by": null,
      "to_dealer": { "id": 3, "business_name": "Sharma Electronics" }
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1 }
}
```
- `transfer_number` format is `PTR-` + a 5-digit zero-padded sequence (e.g. `PTR-00007`), not date-based
- `items` (the line-item array) is **not** included in the list response, only in detail/create — fetch `GET /peti-transfers/{id}` for that

**3.12.b Create** — `POST /peti-transfers`
```json
{
  "type": "dealer",
  "to_dealer_id": 3,
  "items": [
    { "category": "phone", "brand": "Apple", "model": "iPhone 13", "grade": "S2", "quantity": 5, "unit_price": 32000 }
  ],
  "notes": "Bulk consignment for Diwali stock-up"
}
```
- `type: internal` requires `to_location` (free-text warehouse/bin area name) instead of `to_dealer_id`
- `items[].grade` — one of `S1`–`S5`
- Server computes `total_units` (sum of quantities) and `total_value` (sum of quantity × unit_price) — don't send these
- Starts at `status: "draft"`

**Response `201`**
```json
{
  "success": true,
  "message": "Peti transfer created.",
  "data": {
    "id": 7, "transfer_number": "PTR-00007", "type": "dealer", "status": "draft",
    "from_location": null, "to_location": null, "to_dealer_id": 3,
    "items": [ { "category": "phone", "brand": "Apple", "model": "iPhone 13", "grade": "S2", "quantity": 5, "unit_price": 32000 } ],
    "total_units": 5, "total_value": 160000, "notes": "Bulk consignment for Diwali stock-up",
    "created_by": { "id": 2, "name": "Mohan Kumar" },
    "to_dealer": { "id": 3, "business_name": "Sharma Electronics" },
    "created_at": "2026-07-29T09:10:00.000000Z"
  }
}
```

**3.12.c Detail** — `GET /peti-transfers/{id}`
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 7, "transfer_number": "PTR-00007", "type": "dealer", "status": "draft",
    "from_location": null, "to_location": null, "to_dealer_id": 3,
    "items": [ { "category": "phone", "brand": "Apple", "model": "iPhone 13", "grade": "S2", "quantity": 5, "unit_price": 32000 } ],
    "total_units": 5, "total_value": "160000.00", "notes": "Bulk consignment for Diwali stock-up",
    "created_by": { "id": 2, "name": "Mohan Kumar" },
    "approved_by": null,
    "to_dealer": { "id": 3, "business_name": "Sharma Electronics", "gst_number": "27AAAPL1234C1ZV" },
    "transferred_at": null,
    "created_at": "2026-07-29T09:10:00.000000Z", "updated_at": "2026-07-29T09:10:00.000000Z"
  }
}
```

**3.12.d Approve** — `POST /peti-transfers/{id}/approve` (no body) — `draft → approved` only
```json
{
  "success": true,
  "message": "Transfer approved.",
  "data": { "id": 7, "transfer_number": "PTR-00007", "status": "approved", "approved_by": 1, "...": "..." }
}
```

**3.12.e Complete** — `POST /peti-transfers/{id}/complete` (no body) — `approved → completed` only, stamps `transferred_at`
```json
{
  "success": true,
  "message": "Transfer completed.",
  "data": { "id": 7, "transfer_number": "PTR-00007", "status": "completed", "transferred_at": "2026-07-29T09:45:00.000000Z", "...": "..." }
}
```

**3.12.f Cancel** — `POST /peti-transfers/{id}/cancel` (no body) — allowed from `draft` or `approved`, blocked once `completed`
```json
{ "success": true, "message": "Transfer cancelled.", "data": null }
```

**Error `422`** — wrong-state transition, same pattern as order fulfillment:
```json
{ "success": false, "message": "Only draft transfers can be approved. Current status: completed." }
```
```json
{ "success": false, "message": "Completed transfers cannot be cancelled." }
```

---

## 3.13 Logistics — Pincode Check, Auto-Booking & Tracking

Two overlapping route groups exist here — use the right one for the job:

**Delhivery-specific pincode check** (no generic equivalent for other couriers yet):
`GET /orders/pincode-check/{pincode}` — call this **before** accepting an order to validate the
delivery address is serviceable.
```json
{ "success": true, "message": "Pincode checked.", "data": { "pincode": "110001", "serviceable": true, "cod": true, "prepaid": true } }
```

**Generic auto-booking** (works with whichever courier is set in Settings → `logistics_provider`,
defaulting to Shiprocket — Delhivery and DTDC also supported): `POST /logistics/orders/{order}/shipment`
```json
{ "weight_kg": 0.5, "length_cm": 30, "breadth_cm": 20, "height_cm": 10 }
```
All fields optional (sensible defaults applied). Order must be `approved` or `packed`. Builds the
shipping address from the order's dealer (`business_name`, `state`, `pincode`) — there is currently
no separate street-address field, so this is an approximation, not a full postal address. On success,
saves the returned AWB onto the order automatically (`awb_number`, `logistics_provider`).
```json
{ "success": true, "message": "Shipment created successfully.", "data": { "order_number": "DX-2026-00015", "awb": "1234567890", "tracking_url": "https://...", "label_url": null } }
```

**Generic tracking by AWB** — `GET /logistics/track/{awb}` — looks up whichever courier is
currently configured. Alternatively, `GET /orders/{order}/track` looks up the courier that was
actually used for *that* order (reads `logistics_provider` off the order row itself, so it still
works correctly even if the global Settings default has since changed).
```json
{ "success": true, "data": { "awb": "1234567890", "status": "Transit", "estimated_delivery": "2026-07-24", "events": [ { "date": "...", "activity": "Package received at origin" } ], "provider": "delhivery" } }
```

**Cancel a shipment** — `POST /logistics/shipment/{awb}` — cancels with the courier and clears `awb_number` off the matching order if one is found.

**Manual dispatch** (existing, courier-agnostic — use this if you're *not* using auto-booking and
already have an AWB from booking manually on the courier's own dashboard): `POST /orders/{id}/dispatch`
— see 3.14.c below.

---

## 3.14 Order Fulfillment Lifecycle

Warehouse staff move an order through these statuses, **in order**. Each endpoint validates the
current status server-side and rejects out-of-sequence calls with a `422`.

```
approved (by admin) → picking → packed → dispatched → delivered
```

| Step | Endpoint | Requires order status | New status |
|------|----------|------------------------|------------|
| Start picking | `POST /orders/{id}/picking` | `approved` | `picking` |
| Complete packing | `POST /orders/{id}/packing-complete` | `picking` | `packed` |
| Dispatch | `POST /orders/{id}/dispatch` | `packed` (or `approved`) | `dispatched` |
| Mark delivered | `POST /orders/{id}/deliver` | `dispatched` | `delivered` |
| Process a return | `POST /orders/{id}/return` | `delivered` | `returned` |

**3.14.a Start picking** — `POST /orders/{id}/picking` (no body)
```json
{ "success": true, "message": "Picking started.", "data": { "id": 15, "order_number": "DX-2026-00015", "status": "picking", "...": "..." } }
```

**3.14.b Complete packing** — `POST /orders/{id}/packing-complete` (no body)
```json
{ "success": true, "message": "Packing completed.", "data": { "id": 15, "status": "packed", "...": "..." } }
```

**3.14.c Dispatch (manual)** — `POST /orders/{id}/dispatch` — use this when you already have an AWB
(from booking manually on the courier's dashboard, or from a prior call to 3.13's auto-booking).
```json
{ "logistics_provider": "Shiprocket", "awb_number": "AWB12345678" }
```
```json
{
  "success": true,
  "message": "Order dispatched.",
  "data": {
    "id": 15, "status": "dispatched",
    "awb_number": "AWB12345678", "logistics_provider": "Shiprocket",
    "dispatched_at": "2026-07-21T06:25:38.000000Z"
  }
}
```

**3.14.d Mark delivered** — `POST /orders/{id}/deliver` (no body)
```json
{ "success": true, "message": "Order marked as delivered.", "data": { "id": 15, "status": "delivered", "delivered_at": "2026-07-21T06:25:38.000000Z" } }
```

**Error `422`** — wrong-state transition (e.g. calling `/picking` on a delivered order)
```json
{ "success": false, "message": "Order must be approved before picking. Current status: delivered." }
```

---
---

# 📋 PART 4 — ADMIN / BACK-OFFICE APIs (Web Dashboard only)

Everything below is consumed by `admin.dxempire.in` (the React admin dashboard), **not** by any of
the 3 apps in Parts 1–3. Auth is the same admin Bearer token from `POST /auth/admin/login` (3.1).
Roles noted per section are enforced server-side — calling with the wrong role returns `403`.

---

## 4.1 Users & Roles (`super_admin` only)

**List** — `GET /admin/users` — filters: `role`, `is_active`, `search` (name/phone). Paginated.
Excludes `b2b_partner` accounts (those live under Business Partners, 3.x Dealers section).

**Create** — `POST /admin/users`
```json
{ "name": "Priya Singh", "phone": "9876543210", "email": "priya@dxempire.com", "password": "secret123", "role": "warehouse_staff", "parent_unique_code": null, "is_active": true }
```
- `role` — one of `super_admin,warehouse_staff,qc_engineer,sales,accounts,hr_manager,logistics`
- `password` optional — if omitted, user has no password set yet (staff app login is Sales-ID-only anyway)

Response `201`:
```json
{ "success": true, "message": "User created with Unique Code: SG004", "data": { "user": { "id": 12, "name": "Priya Singh", "phone": "9876543210", "role": "warehouse_staff", "unique_code": "SG004", "roles": [{"name": "warehouse_staff"}] }, "unique_code": "SG004" } }
```

**Detail** — `GET /admin/users/{id}` — includes `roles`, `permissions`, `employee`, `dealer` relations if present.

**Update** — `PUT /admin/users/{id}`
```json
{ "name": "Priya S.", "email": "priya.s@dxempire.com", "is_active": true }
```

**Assign role** — `PUT /admin/users/{id}/role`
```json
{ "role": "accounts" }
```
Blocks removing the last remaining `super_admin` (`422` if attempted).

**Activate / Deactivate** — `POST /admin/users/{id}/activate`, `POST /admin/users/{id}/deactivate` (no body).
Deactivating also revokes all of that user's Sanctum tokens (forces logout everywhere). Cannot deactivate your own account (`422`).

**List all roles** — `GET /admin/roles`
```json
{ "success": true, "data": [ { "id": 1, "name": "super_admin", "users_count": 1 }, { "id": 2, "name": "sales", "users_count": 5 } ] }
```

---

## 4.2 Settings (`super_admin` only)

**List all** — `GET /admin/settings`
```json
{
  "success": true,
  "data": [
    { "key": "company_name", "value": "DXEMPIRE TECHBUZZ PRIVATE LIMITED", "raw": "...", "editable": true, "updated_at": "..." },
    { "key": "warehouse_pincode", "value": "799286", "raw": "799286", "editable": true, "updated_at": "..." }
  ]
}
```
`editable: false` marks settings that exist in the DB but aren't in the whitelist below — attempting
to update one of those returns `403`.

**Editable keys** (whitelist enforced server-side): `grade_price_rules`, `low_stock_threshold`,
`logistics_provider` (`shiprocket|delhivery|dtdc`), `whatsapp_provider` (`interakt|twilio`),
`company_name`, `company_address`, `company_gst`, `company_phone`, `company_email`,
`warehouse_name`, `warehouse_contact`, `warehouse_phone`, `warehouse_email`, `warehouse_address`,
`warehouse_city`, `warehouse_state`, `warehouse_pincode`.

**Get one** — `GET /admin/settings/{key}`

**Update one** — `PUT /admin/settings/{key}`
```json
{ "value": "delhivery" }
```

**Bulk update** — `PUT /admin/settings` (this is what the Settings page's "Save Changes" button calls)
```json
{ "settings": [ { "key": "warehouse_pincode", "value": "799286" }, { "key": "logistics_provider", "value": "delhivery" } ] }
```
```json
{ "success": true, "message": "2 setting(s) updated.", "data": null }
```
> ⚠️ Every `key` in the array must be in the editable whitelist above, or the **entire** batch is
> rejected with `422` — there is no partial success.

---

## 4.3 Catalog Images (`super_admin` only)

Already documented in the "Catalog Images — Admin Upload" appendix section further below — see that
section for the full upload/list/delete flow (`GET/POST /admin/catalog-images`, `POST /admin/catalog-images/upload`, `DELETE /admin/catalog-images/{id}`).

---

## 4.4 Audit Logs — `GET /admin/audit-logs` (`super_admin` only)

Filters: `user_id`, `action` (partial match), `model` (partial match on model class name), `from`, `to`. Paginated.
```json
{
  "success": true,
  "data": [
    { "id": 501, "user": { "id": 1, "name": "Anil Sharma", "phone": "9111111100" }, "action": "order.dispatched", "model_type": "App\\Models\\Order", "model_id": 15, "old_values": [], "new_values": { "awb": "AWB123", "provider": "Shiprocket" }, "created_at": "2026-07-21T06:25:38.000000Z" }
  ],
  "meta": { "current_page": 1, "per_page": 50, "total": 1, "last_page": 1 }
}
```

---

## 4.5 Analytics (`super_admin`, `sales`, `accounts`)

**Dashboard summary** — `GET /analytics/dashboard`
```json
{ "success": true, "data": { "today_revenue": 0, "week_revenue": 0, "month_revenue": 1355545, "active_orders": 5, "pending_qc": 7, "pending_dispatch": 2, "in_refurbishment": 5, "total_in_stock": 13 } }
```

**Revenue over time** — `GET /analytics/revenue?period=daily&from=2026-06-01&to=2026-07-01&channel=b2b`
`period`: `daily|weekly|monthly`. `channel`: `b2b|retail` (omit for both).
```json
{
  "success": true,
  "data": {
    "period": { "from": "2026-06-01", "to": "2026-07-01", "group_by": "daily" },
    "summary": { "total_orders": 42, "total_revenue": 1355545, "avg_order_value": 32275.0 },
    "time_series": [ { "period": "2026-06-15", "order_count": 3, "revenue": 96000, "avg_order_value": 32000 } ],
    "top_products": [ { "brand": "Apple", "model": "iPhone 13", "category": "phone", "grade": "S2", "units_sold": 12, "revenue": 384000 } ],
    "top_dealers": [ { "business_name": "Sharma Electronics", "gst_number": "27AAAPL...", "order_count": 8, "revenue": 256000 } ]
  }
}
```

**Sales breakdown** — `GET /analytics/sales?from=...&to=...&group_by=category` (`group_by`: `category|brand|grade`)
```json
{
  "success": true,
  "data": {
    "period": { "from": "2026-06-01", "to": "2026-07-01", "group_by": "category" },
    "breakdown": [ { "segment": "phone", "units_sold": 30, "revenue": 900000, "gst_collected": 162000, "avg_unit_price": 30000 } ],
    "channel_split": { "b2b_revenue": 1000000, "retail_revenue": 355545, "b2b_orders": 30, "retail_orders": 12 }
  }
}
```

**Inventory health** — `GET /analytics/inventory`
```json
{
  "success": true,
  "data": {
    "summary": { "total_in_stock": 13, "total_stock_value": 416000, "pending_qc": 7, "in_refurbishment": 5 },
    "stock_matrix": [ { "category": "phone", "grade": "S2", "count": 5 } ],
    "aging_buckets": [ { "age_bucket": "0-30 days", "count": 8, "stock_value": 256000 } ],
    "slow_movers": [ { "id": 22, "brand": "Samsung", "model": "Galaxy S21", "category": "phone", "grade": "S3", "selling_price": "18000.00", "created_at": "...", "days_in_stock": 65 } ]
  }
}
```

**Stock movement history** — `GET /analytics/stock-movements?product_id=&bin_id=&from=&to=` — paginated bin-transfer audit trail.

**Partner/dealer performance** — `GET /analytics/partners?from=&to=`
```json
{
  "success": true,
  "data": {
    "period": { "from": "2026-04-30", "to": "2026-07-29" },
    "dealers": [ { "id": 3, "business_name": "Sharma Electronics", "price_tier": "A", "credit_limit": "300000.00", "credit_used": "206423.00", "order_count": 8, "total_revenue": 256000, "avg_order_value": 32000, "amount_paid": 200000, "amount_outstanding": 56000, "last_order_at": "...", "payment_rate_pct": 78.1, "credit_utilisation_pct": 68.8 } ]
  }
}
```

**Demand forecast** — `GET /analytics/forecast` — 3-month rolling average per category, cached hourly.
```json
{
  "success": true,
  "data": {
    "generated_at": "2026-07-29 10:00:00",
    "forecast_month": "2026-08",
    "method": "3-month rolling average",
    "categories": [ { "category": "phone", "history": { "2026-05": 20, "2026-06": 25, "2026-07": 30 }, "forecast_month": "2026-08", "forecast_units": 25, "current_stock": 10 } ]
  }
}
```

---

## 4.6 Finance (`super_admin`, `accounts`)

**Dealer ledger** — `GET /finance/dealers/{id}/ledger?from=&to=`
```json
{
  "success": true,
  "data": {
    "dealer": { "id": 3, "business_name": "Sharma Electronics", "user": { "id": 10, "name": "...", "phone": "..." } },
    "summary": { "total_orders": 8, "total_billed": 256000, "total_paid": 200000, "total_refunded": 0, "outstanding": 56000, "credit_limit": "300000.00", "credit_used": "206423.00", "credit_available": 93577 },
    "transactions": [ { "order_number": "DX-2026-00015", "date": "2026-07-15", "status": "delivered", "payment_status": "paid", "total_amount": "32000.00", "invoice_number": "INV-2026-00015", "payments": [ { "amount": "32000.00", "status": "captured", "method": "razorpay", "paid_at": "..." } ] } ]
  }
}
```

**Invoices** — `GET /finance/invoices?dealer_id=&from=&to=` — paginated, each with `order`, `dealer.user` loaded.

**Generate invoice** — `POST /finance/invoices/orders/{orderId}/generate` (no body) — order must be `approved`, `dispatched`, or `delivered`.
```json
{ "success": true, "message": "Invoice generated.", "data": { "id": 15, "invoice_number": "INV-2026-00015", "order_id": 15, "subtotal": "27118.64", "gst_amount": "4881.36", "total": "32000.00", "status": "unpaid" } }
```

**Invoice detail** — `GET /finance/invoices/{id}` — includes `order.items.product`, `dealer.user`.

**Record a payment** — `POST /finance/invoices/{id}/payment`
```json
{ "amount": 32000, "method": "razorpay", "note": "Paid via UPI" }
```
`method`: `cash|bank_transfer|razorpay|cheque|upi`. Auto-flips the order's `payment_status` to `paid`/`partial` based on cumulative captured payments.

**Download invoice PDF** — `GET /finance/invoices/{id}/download` — binary PDF, `404` if not yet generated.

**Expenses** — `GET /finance/expenses?category=&from=&to=`, `POST /finance/expenses` (multipart if attaching a receipt):
```json
{ "category": "Rent", "amount": 25000, "vendor": "Landlord", "description": "July office rent", "incurred_at": "2026-07-01", "receipt": "<file, optional>" }
```
`GET /finance/expenses/{id}`, `POST /finance/expenses/{id}` (update — same fields, method-spoofed PUT via POST), `DELETE /finance/expenses/{id}`, `GET /finance/expenses/categories` (distinct category list).

**P&L report** — `GET /finance/profit-loss?period=monthly&year=2026` (`period`: `monthly|quarterly`)
```json
{
  "success": true,
  "data": {
    "year": 2026, "period": "monthly", "total_revenue": 1355545, "total_expenses": 125000,
    "net_profit": 1230545, "net_margin_pct": 90.78,
    "time_series": [ { "period": "Jan 2026", "revenue": 0, "expenses": 0 }, { "period": "Jul 2026", "revenue": 1355545, "expenses": 25000 } ]
  }
}
```

**GST summary** — `GET /finance/gst-summary?month=2026-07`
```json
{
  "success": true,
  "data": {
    "month": "2026-07", "taxable_value": 27118.64, "cgst": 2440.68, "sgst": 2440.68, "igst": 0,
    "invoices": [ { "id": 15, "invoice_number": "INV-2026-00015", "dealer_name": "Sharma Electronics", "dealer_gstin": "27AAAPL...", "taxable_value": 27118.64, "cgst": 2440.68, "sgst": 2440.68, "igst": 0, "total_amount": 32000 } ]
  }
}
```

**GST export (CSV)** — `GET /finance/gst-export?month=2026-07` — triggers a file download (`Content-Type: text/csv`), not a JSON envelope. In Postman, use "Send and Download."

**Receivables** — `GET /finance/receivables`
```json
{ "success": true, "data": { "total_outstanding": 206423, "dealers": [ { "dealer_id": 3, "business_name": "Sharma Electronics", "contact": "...", "phone": "...", "credit_limit": "300000.00", "credit_used": "206423.00", "credit_available": 93577, "utilisation_pct": 68.8 } ] } }
```

**Vendor payments** — `GET /finance/vendor-payments?supplier_id=&from=&to=`, `POST /finance/vendor-payments`:
```json
{ "supplier_id": 2, "amount": 50000, "method": "bank_transfer", "reference_number": "TXN12345", "note": "July stock payment", "paid_at": "2026-07-15" }
```
`method`: `cash|bank_transfer|cheque|upi`.

---

## 4.7 HR (`super_admin`, `hr_manager`)

**Employees** — `GET /hr/employees?department=&shift=&is_active=&search=` — paginated.

**Create** — `POST /hr/employees`
```json
{ "name": "Rahul Verma", "phone": "9123456780", "email": "rahul@dxempire.com", "department": "Warehouse", "designation": "Picker", "employment_type": "full_time", "shift": "morning", "salary": 18000, "joining_date": "2026-07-01" }
```
`employment_type`: `full_time|part_time|contract`. `shift`: `morning|evening`. Server auto-generates `employee_code`.

**Detail** — `GET /hr/employees/{id}` — includes `payrollItems.payrollRun`.
**Update** — `PUT /hr/employees/{id}` (same fields as create, all `sometimes`).
**Deactivate** — `DELETE /hr/employees/{id}` (soft — sets inactive, doesn't hard-delete).
**Departments list** — `GET /hr/employees/departments` — distinct department names.

**Attendance list** — `GET /hr/attendance?employee_id=&date=&month=&year=&status=` — paginated.

**Bulk mark** — `POST /hr/attendance/bulk`
```json
{ "records": [ { "employee_id": 5, "date": "2026-07-29", "status": "present", "check_in": "09:05", "check_out": "18:10" } ] }
```
`status`: `present|absent|late|half_day|holiday|leave`. Upserts by (employee_id, date).

**Check-in / check-out (admin-triggered, distinct from the mobile self check-in in Part 1.9)** —
`POST /hr/attendance/check-in` / `POST /hr/attendance/check-out`
```json
{ "employee_id": 5 }
```

**Today's status (all employees)** — `GET /hr/attendance/today`
```json
{ "success": true, "data": { "date": "2026-07-29", "total": 12, "marked": 9, "unmarked": 3, "records": [ { "employee_id": 5, "name": "Rahul Verma", "department": "Warehouse", "shift": "morning", "attendance": { "status": "present", "check_in": "...", "check_out": null } } ] } }
```

**Monthly summary for one employee** — `GET /hr/attendance/{employeeId}/summary?month=7&year=2026`
```json
{ "success": true, "data": { "employee": {...}, "month": 7, "year": 2026, "days_worked": 22.5, "present": 22, "half_day": 1, "absent": 2, "leave": 1, "records": [...] } }
```

**Payroll runs** — `GET /hr/payroll?year=2026` — paginated.

**Create draft run** — `POST /hr/payroll`
```json
{ "month": 7, "year": 2026 }
```
`422` if a run for that month/year already exists.

**Create + process in one call** — `POST /hr/payroll/process` (same body) — creates the run if it doesn't exist, then immediately processes it.

**Process an existing draft** — `POST /hr/payroll/{runId}/process` (no body) — only allowed while `status: draft`.

**Run detail** — `GET /hr/payroll/{runId}` — includes `items.employee.user`.

**Mark paid** — `POST /hr/payroll/{runId}/mark-paid` (no body) — only from `status: processed`.

**Run's line items** — `GET /hr/payroll/{runId}/items`
```json
{ "success": true, "data": { "run": { "id": 3, "month": 7, "year": 2026, "status": "processed", "total_payout": 180000 }, "employee_count": 10, "items": [ { "id": 30, "employee_id": 5, "emp_code": "EMP-0005", "name": "Rahul Verma", "phone": "9123456780", "department": "Warehouse", "days_worked": 22.5, "basic": 18000, "deductions": 500, "net_salary": 17500, "slip_path": "payslips/..." } ] } }
```

**Generate all pay slips for a run** — `POST /hr/payroll/{runId}/generate-slips` (no body)
```json
{ "success": true, "message": "10 pay slip(s) generated.", "data": { "generated": 10, "failed": [] } }
```

**Download one pay slip** — `GET /hr/payroll/{runId}/slips/{payrollItemId}` — binary PDF (auto-generates on first request if missing).

---

## 4.8 CRM extras — Dealers, Leads, Support Tickets

**Dealer admin actions** (beyond the customer-facing dealer list already in 3.x): `super_admin`, `sales`.

- `POST /dealers` — create dealer + linked user in one call:
  ```json
  { "name": "Owner Name", "phone": "9876543210", "email": "owner@biz.com", "business_name": "Sharma Electronics", "gst_number": "27AAAPL1234C1ZV", "state": "Maharashtra", "pincode": "400001", "credit_limit": 300000, "price_tier": "A" }
  ```
  `price_tier`: `A|B|C`. `422` if a dealer already exists for that phone.
- `GET /dealers/{id}` — includes `available_credit`, `orders_count`.
- `PUT /dealers/{id}/kyc` — `{ "kyc_status": "approved", "reason": "Docs verified" }` — `kyc_status` accepts `verified,approved,rejected` (`approved` is normalized to `verified` internally); sends a push notification to the dealer on approval.
- `POST /dealers/{id}/activate`, `POST /dealers/{id}/deactivate` — toggles the dealer's own login (not KYC/credit).
- `PUT /dealers/{id}/credit` — `{ "credit_limit": 350000 }`.
- `GET /dealers/{id}/ledger?from=&to=` — same shape as 4.6's finance ledger.

**Leads** (`super_admin`, `sales`):
- `GET /leads` — filters via query params handled by the Lead model's `filter()` scope (stage, source, assigned_to, search).
- `POST /leads` — `{ "source": "website", "contact_name": "Amit Patel", "phone": "9988776655", "business_name": "New Electronics Hub", "notes": "Interested in bulk phones", "assigned_to": 7 }` — `source`: `b2b_inquiry|website|referral|walk_in|marketplace`.
- `GET /leads/{id}`, `PUT /leads/{id}` (partial update — contact_name/phone/business_name/source/assigned_to/notes).
- `PUT /leads/{id}/stage` — `{ "stage": "qualified", "notes": "Follow-up call done" }` — `stage`: `new|contacted|qualified|proposal|negotiation|won|lost`.
- `POST /leads/{id}/convert` — no body, sets `stage: won`. `422` if already won.

**Support Tickets** (`super_admin`, `sales`, `accounts`, `warehouse_staff`):
- `GET /support/tickets?status=&priority=&assigned_to=` — paginated.
- `POST /support/tickets` — `{ "subject": "Damaged unit received", "description": "IMEI ... arrived cracked", "order_id": 15, "priority": "high" }` — `priority`: `low|medium|high` (defaults if omitted).
- `PUT /support/tickets/{id}` — `{ "status": "resolved", "assigned_to": 3, "priority": "high" }` — `status`: `open|in_progress|resolved|closed`; auto-stamps `resolved_at` on first transition to `resolved`.

**Retail Customers (B2B admin view of B2C customers)** (`super_admin`, `sales`, `accounts`):
- `GET /customers?search=&state=` — paginated, `orders_count` included.
- `GET /customers/{id}` — returns `customer`, `orders_count`, `total_spent`, full `orders` list.
- `PUT /customers/{id}` — `{ "name": "...", "email": "...", "is_active": false }`.

---

## 4.9 Sales Hierarchy — Admin View (`super_admin`, `sales`)

Distinct from the app's own read-only `/mobile/hierarchy/*` (Part 1.5–1.8) — this is full CRUD for
building/editing the org tree from the admin dashboard.

- `GET /sales/hierarchy?role=&state=&search=&parent_id=` — paginated flat list.
- `GET /sales/hierarchy/tree` — full nested tree from the root(s) down (4 levels deep).
- `POST /sales/hierarchy` — `{ "name": "Vikram Singh", "phone": "9111111107", "hierarchy_role": "salesman", "parent_unique_code": "DM001", "state": "Tripura", "district": "Dhalai" }` — `hierarchy_role`: `ceo|state_manager|area_manager|district_manager|salesman`. Auto-generates `tree_id` (this is the "unique_code" the mobile app logs in with, e.g. `SG004`).
- `GET /sales/hierarchy/{id}` — includes `parent`, nested `children`, `user`, `dealers`.
- `PUT /sales/hierarchy/{id}` — partial update, same fields as create.
- `DELETE /sales/hierarchy/{id}` — soft (sets `is_active: false`).
- `POST /sales/hierarchy/{id}/assign-dealer` — `{ "dealer_id": 3 }` — only valid when the target node's `hierarchy_role` is `salesman`.
- `GET /sales/hierarchy/{id}/downline` — total members/dealers/orders/revenue under this node.
- `GET /sales/hierarchy/{id}/performance` — per-dealer revenue breakdown for this node's whole downline.

---

## 4.10 Offers / Promo Codes (`super_admin`, `sales`)

- `GET /offers?is_active=&customer_type=` — paginated.
- `POST /offers` —
  ```json
  { "title": "Diwali Sale", "code": "DIWALI10", "discount_type": "percentage", "discount_value": 10, "min_order_amount": 5000, "max_discount_amount": 2000, "applicable_to": "phone", "applicable_grade": "all", "customer_type": "all", "valid_from": "2026-10-01", "valid_to": "2026-11-01", "max_usage": 500 }
  ```
  `discount_type`: `percentage|fixed`. `applicable_to`: `all|phone|laptop`. `applicable_grade`: `all|S1..S5`. `customer_type`: `all|b2b|retail`.
- `GET /offers/{id}`, `PUT /offers/{id}` (partial), `DELETE /offers/{id}` (soft — sets `is_active: false`).
- `GET /offers/active` — currently valid, non-expired, under-usage-limit offers (public listing).
- `POST /offers/validate` — `{ "code": "DIWALI10", "order_total": 32000 }` →
  ```json
  { "success": true, "message": "Offer applied.", "data": { "offer": { "id": 4, "title": "Diwali Sale", "code": "DIWALI10", "discount_type": "percentage", "discount_value": 10 }, "discount": 2000, "final_total": 30000 } }
  ```

---

## 4.11 Procurement — Suppliers & Purchase Orders (`super_admin`, `warehouse_staff`)

**Suppliers**: `GET /suppliers?type=&is_active=&search=`, `POST /suppliers` — `{ "name": "ABC Traders", "phone": "9876543210", "email": "abc@traders.com", "gst_number": "27AAAPL1234C1ZV", "address": "...", "type": "dealer" }` (`type`: `dealer|importer|buyback_partner`), `GET /suppliers/{id}` (includes `purchase_orders_count`, `products_count`), `PUT /suppliers/{id}`, `DELETE /suppliers/{id}` (soft).

**Purchase Orders**: `GET /purchase-orders?status=&supplier_id=`, `POST /purchase-orders` — `{ "supplier_id": 2, "total_amount": 450000, "expected_count": 10, "notes": "10 units iPhone 13" }` (starts as `status: draft`), `GET /purchase-orders/{id}` (includes `products` — units already received against it), `PUT /purchase-orders/{id}` — `{ "status": "placed" }` (`draft|placed|received`; `422` once `received`).

**Receive stock** — see 3.7/3.8 in Part 3 (same endpoints, shared with the warehouse app) — `POST /procurement/receive` and `POST /purchase-orders/{id}/receive` (auto-links the PO), `GET /procurement/history`.

---

## 4.12 QC — Refurbishment Queue (`super_admin`, `warehouse_staff`, `qc_engineer`)

Pending/grade/records/stats already covered in Part 3 (3.9–3.11, shared with the warehouse app).
Admin-only extras:

- `POST /qc/refurbishment` — `{ "product_id": 41, "condition_notes": "Screen replacement needed" }` — sends a unit straight to refurbishment (bypassing a full grade decision); allowed only from `received`/`in_stock`.
- `GET /qc/refurbishment` — paginated list of units currently `status: refurbishment`, with `qcRecords.engineer` history.
- `PUT /qc/refurbishment/{productId}` (no body) — marks refurbishment complete, sends the unit back into the QC queue (`status → received`).

---

## 4.13 Bins (`super_admin`, `warehouse_staff`)

Move/list/products-in-bin already covered in Part 3 (3.4–3.6). Admin-only:

- `POST /bins` — `{ "code": "A1-R2-S3", "zone": "A", "row": "2", "shelf": "3", "capacity": 50 }` — creates a new warehouse bin location.

---
---

# 🛍️ PART 5 — RETAIL (B2C) STOREFRONT API

Distinct customer type (`Customer` model, not `Dealer`/B2B) — a direct-to-consumer storefront.
**No live frontend/app consumes this today** as far as this codebase shows; documented here in full
in case a consumer-facing site/app is planned. Uses its own OTP auth, separate from every other
login in this document, and its own Bearer token (cached, not Sanctum).

## 5.1 Send OTP — `POST /retail/auth/send-otp`
```json
{ "phone": "9876543210" }
```
```json
{ "success": true, "message": "OTP sent successfully.", "data": { "otp": "482913" } }
```
> ⚠️ The OTP is currently returned **in the response body** (`data.otp`) — there is no SMS provider
> wired up yet (logged via `Log::info` instead). Fine for testing now; must be removed from the
> response before any real launch.

## 5.2 Verify OTP — `POST /retail/auth/verify-otp`
```json
{ "phone": "9876543210", "otp": "482913" }
```
```json
{ "success": true, "message": "Login successful.", "data": { "token": "aBc123...60charRandomString", "customer": { "id": 1, "name": "Customer 9876543210", "phone": "9876543210", "is_active": true } } }
```
First-ever OTP verify for a phone number auto-creates the `Customer` row. Token cached for 30 days.

## 5.3 My Profile — `GET /retail/auth/me`
## 5.4 Update Profile — `PUT /retail/auth/profile`
```json
{ "name": "Rohit Kumar", "email": "rohit@gmail.com", "address": "123 MG Road", "city": "Pune", "state": "Maharashtra", "pincode": "411001" }
```
## 5.5 Logout — `POST /retail/auth/logout` (no body)

## 5.6 Catalog — `GET /retail/catalog?category=&grade=&brand=&search=`
Only `status: in_stock` products, retail pricing.
```json
{ "success": true, "data": [ { "id": 41, "brand": "Apple", "model": "iPhone 13", "category": "phone", "grade": "S2", "retail_price": "33750.00", "color": "Blue", "storage": "128GB", "ram": null } ], "meta": {...} }
```

## 5.7 Product Detail — `GET /retail/catalog/{productId}` — `404` if not `in_stock`.

## 5.8 Cart — view / add / remove one / clear all
- `GET /retail/cart` → `{ "items": [...], "count": 2, "total": 79650.0 }` (total includes 18% GST, and auto-purges items whose product went out of stock)
- `POST /retail/cart` — `{ "product_id": 41 }`
- `DELETE /retail/cart/{productId}`
- `DELETE /retail/cart` (clears everything)

## 5.9 My Orders — `GET /retail/orders` (paginated, 10/page) · `GET /retail/orders/{id}`

## 5.10 Place Order — `POST /retail/orders`
```json
{ "product_ids": [41, 42], "shipping_state": "Maharashtra", "shipping_address": "123 MG Road, Pune - 411001" }
```
Locks stock on the selected products (`status → reserved`), computes GST-inclusive totals, clears those items from the cart.
```json
{ "success": true, "message": "Order placed successfully.", "data": { "id": 88, "order_number": "DX-2026-00088", "status": "pending", "total_amount": "63750.00", "items": [...] } }
```
`422` if any product went out of stock between cart-add and checkout.

---
---

# 🚚 PART 6 — DELHIVERY DIRECT API (for Postman — calling Delhivery itself, not our backend)

These are Delhivery's own endpoints, called directly (not through our `/logistics/*` or
`/orders/pincode-check` wrappers documented in 3.13 — that section explains how *our backend* uses
these; this section is the raw external contract, for testing straight against Delhivery in Postman).

**Auth for all of them**: header `Authorization: Token <your_delhivery_token>`.
**Base URL — Production**: `https://track.delhivery.com` · **Staging**: `https://staging-express.delhivery.com`
(a few endpoints live on a different subdomain — noted per-API below).

We are on the **B2C** product line (individual parcel shipments) — Delhivery's separate B2B API
family (LTL freight/trucking, "LR" documents) does not apply to this business and is intentionally
excluded.

## 6.1 Pincode Serviceability
`GET /c/api/pin-codes/json/?filter_codes={pincode}`
Empty `delivery_codes` array = not serviceable. A `remark: "Embargo"` on a code = temporarily non-serviceable.
```json
{ "delivery_codes": [ { "postal_code": { "pin": 110001, "cod": "Y", "pre_paid": "Y", "pickup": "Y", "district": "New Delhi", "state_code": "DL", "is_oda": "N" } } ] }
```

## 6.2 Fetch Waybill (bulk, up to 10,000 at once)
`GET /waybill/api/bulk/json/?count={n}` — response is a raw JSON array of waybill number strings (or a single string if `count=1`).

## 6.3 Fetch Single Waybill
`GET /waybill/api/fetch/json/` — returns one waybill number per call.

## 6.4 Shipment Manifestation (create)
`POST /api/cmu/create.json` — **body must be form-urlencoded**, not JSON:
```
format=json&data={"shipments":[{"name":"...","add":"...","pin":"...","city":"...","state":"...","country":"India","phone":"...","order":"ORDER123","payment_mode":"Prepaid","weight":"500","total_amount":"32000","shipment_height":"10","shipment_width":"20","shipment_length":"30","products_desc":"iPhone 13","seller_name":"DXEMPIRE"}],"pickup_location":{"name":"warehouse_name"}}
```
- `payment_mode`: `Prepaid` (forward) · `COD` (forward, cash on delivery) · `Pickup` (reverse/RVP) · `REPL` (replacement/exchange)
- `pickup_location.name` **must exactly match** (case/space-sensitive) the name registered via 6.10
```json
{ "success": true, "packages": [ { "waybill": "1234567890123", "status": "Success" } ] }
```

## 6.5 Shipment Updation/Edit
`POST /api/p/edit` — only while status is `Manifested`/`In Transit`/`Pending` (blocked once Dispatched/Delivered/terminal):
```json
{ "waybill": "1234567890123", "pt": "COD", "cod": 500, "add": "New address", "gm": 600, "shipment_height": 12 }
```

## 6.6 Shipment Cancellation
`POST /api/p/edit`
```json
{ "waybill": "1234567890123", "cancellation": "true" }
```
(Note: `cancellation` is the **string** `"true"`, not a boolean.)

## 6.7 E-way Bill Update (mandatory once shipment value > ₹50,000)
`PUT /api/rest/ewaybill/{waybill}/`
```json
{ "data": [ { "dcn": "INV-2026-00015", "ewbn": "141234567890" } ] }
```

## 6.8 Shipment Tracking
`GET /api/v1/packages/json/?waybill={waybill}&ref_ids={order_id}` — up to 50 comma-separated waybills per call.
```json
{ "ShipmentData": [ { "Shipment": { "Status": { "Status": "In Transit" }, "PromisedDeliveryDate": "2026-08-02", "Scans": [ { "ScanDetail": { "Scan": "In Transit", "ScanDateTime": "..." } } ] } } ] }
```

## 6.9 Calculate Shipping Cost
`GET /api/kinko/v1/invoice/charges/.json?md=E&ss=Delivered&o_pin={origin}&d_pin={dest}&cgm={grams}&pt=Pre-paid`
- `md`: `E` (Express) or `S` (Surface). `ss`: `Delivered|RTO|DTO`. `pt`: `Pre-paid|COD`.
```json
[ { "status": "Delivered", "zone": "C", "total_amount": 76.28, "gross_amount": 64.64, "tax_data": { "SGST": 5.82, "CGST": 5.82, "IGST": 0 }, "charged_weight": 500 } ]
```

## 6.10 Client Warehouse Creation (one-time per pickup location)
`POST /api/backend/clientwarehouse/create/`
```json
{ "name": "DXEMPIRE TECHBUZZ PRIVATE LIMITED", "phone": "8787635196", "email": "dxempire2610@gmail.com", "address": "C/O Subasish Das, Halhali, Halahali, Halahali", "city": "Dhalai", "pin": "799286", "country": "India", "registered_name": "DXEMPIRE TECHBUZZ PRIVATE LIMITED", "return_address": "C/O Subasish Das, Halhali, Halahali, Halahali", "return_pin": "799286", "return_city": "Dhalai", "return_state": "Tripura", "return_country": "India" }
```
> Rate-limited to **10 requests/minute** — far stricter than the other APIs. `name` becomes the
> exact string every future shipment must reference in `pickup_location.name` (6.4).

## 6.11 Client Warehouse Updation
`POST /api/backend/clientwarehouse/edit/` — `name` and `pin` required; warehouse name itself cannot be changed.
```json
{ "name": "DXEMPIRE TECHBUZZ PRIVATE LIMITED", "pin": "799286", "phone": "8787635196", "address": "Updated address line" }
```

## 6.12 Generate Shipping Label
`GET /api/p/packing_slip?wbns={waybill}&pdf=true&pdf_size=A4` (`pdf_size`: `A4` or `4R`)
Response shape isn't fully documented by Delhivery for `pdf=true` — expect an S3 PDF link in the
response somewhere near `pdf_download_link`; pass `pdf=false` instead to get a raw JSON packing-slip
payload you can render yourself.

## 6.13 Pickup Request Creation
`POST /fm/request/new/` — raised **per warehouse**, not per waybill (one request covers everything ready at that location today):
```json
{ "pickup_time": "18:00:00", "pickup_date": "2026-07-30", "pickup_location": "DXEMPIRE TECHBUZZ PRIVATE LIMITED", "expected_package_count": 3 }
```
Only one open pickup request per warehouse per day — a second attempt before the first closes will fail.

## 6.14 Download Document API
`GET /api/rest/fetch/pkg/document/?doc_type={type}&waybill={awb}`
`doc_type`: `SIGNATURE_URL | RVP_QC_IMAGE | EPOD | SELLER_RETURN_IMAGE` — only populated after the shipment reaches a terminal state (delivered/returned).

---

### Not implemented (need Delhivery BD/integration-team coordination first, not just code)
- **Webhook Functionality** — real-time push instead of polling Tracking; requires filling out and
  emailing Delhivery's Webhook Requirement Document (`lastmile-integration@delhivery.com`) before any
  code can receive it.
- **RVP QC 3.0** — doorstep quality-check questions for reverse pickups; requires a one-time question-ID
  mapping session with Delhivery's BD team before the `custom_qc` payload in 6.4 means anything.
- **Expected TAT API**, **MPS (multi-box) Manifestation**, **Heavy-product-type Pincode Check** — not
  relevant to current order patterns; straightforward to add later if needed.

---
# Common Errors

| Code | Meaning | Body |
|------|---------|------|
| `401` | Not logged in / token expired | `{ "success": false, "message": "Unauthenticated." }` |
| `401` | Bad credentials | `{ "success": false, "message": "Invalid login or password." }` |
| `403` | Account deactivated / no permission | `{ "success": false, "message": "..." }` |
| `404` | Resource not found / not yours | `{ "success": false, "message": "..." }` |
| `422` | Validation / business-rule error | `{ "success": false, "message": "...", "errors"?: { ... } }` |
| `500` | Server error | `{ "message": "Server Error" }` — should not happen; report it if you see one |

On any `401`, clear the stored token and route the user to the login screen.

> ⚠️ **Always send `Accept: application/json`** on every request. Without it, some validation
> failures return an HTML redirect instead of JSON (default Laravel behavior for "browser" requests).
> All three example flows below assume this header is always present.

---

# Notification Inbox

In-app notifications (separate from the Expo push alerts below — this is the persisted list a user
sees in a bell/inbox screen). Available on the general authenticated API — works with a staff,
warehouse, or partner token. All calls scoped to the logged-in user; you never see anyone else's.

**List** — `GET /notifications` — paginated, newest first
```json
{
  "success": true,
  "data": [
    { "id": 41, "title": "Order Dispatched", "body": "Your order DX-2026-00015 has been dispatched.", "type": "order_dispatched", "data": { "order_id": "15" }, "is_read": false, "created_at": "2026-07-29T10:15:00.000000Z" }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1 }
}
```

**Unread count** — `GET /notifications/unread-count`
```json
{ "success": true, "data": { "count": 3 } }
```

**Mark one read** — `PATCH /notifications/{id}` — returns the updated notification. Does **not** delete it.

**Mark all read** — `PATCH /notifications/read-all`
```json
{ "success": true, "message": "All notifications marked as read.", "data": null }
```

**Delete one** — `DELETE /notifications/{id}` — permanently removes it (404 if it isn't yours)
```json
{ "success": true, "message": "Notification deleted.", "data": null }
```

**Delete all** — `DELETE /notifications` — permanently removes every notification for the logged-in user
```json
{ "success": true, "message": "All notifications deleted.", "data": null }
```

> ⚠️ Deleting is permanent — unlike mark-read, deleted notifications do **not** come back on the
> next fetch. Use mark-read for a "seen" state and delete only when the user explicitly wants it gone.

---

# Push Notifications (Expo)

All three apps share the same push-token registration, under the general authenticated API
(works with a staff, warehouse, **or** partner token):

- Register on app open / after login: `POST /users/push-token` — body `{ "token": "<expo-push-token>", "device_type": "android"|"ios" }`
- Unregister on logout: `DELETE /users/push-token` — optional body `{ "token": "..." }` to remove just one device, omit to remove all of the user's tokens

The backend sends via **Expo's push API** (`exp.host`), which relays to FCM/APNs — so the app only
needs the **Expo SDK** (`expo-notifications`), no separate Firebase project required.

**Currently wired to fire on:** order approved (notifies partner + warehouse), order dispatched
(notifies dealer), stock added (notifies partners), product received (notifies QC team). Order
placement/fulfillment triggers (3.14) do not yet push a notification for every step — only
approval and dispatch do, today.

**⚠️ `EXPO_ACCESS_TOKEN` status: confirmed NOT configured in production `.env` as of this writing.**
The code path now supports it (optional `Authorization: Bearer <token>` header sent to Expo when
set — see `config/services.php` → `expo.access_token`), but no token is set on the server yet.
Expo's push API works *without* one — sending will still succeed — but Expo recommends setting one
for production to prevent anyone else from pushing to your project. **Action needed from us:**
generate an Expo access token (from the Expo dashboard, tied to the app's Expo project) and set
`EXPO_ACCESS_TOKEN=` in the production `.env`. Let us know if you want this prioritized before
launch, or if you're fine going live without it initially.

---

# Catalog Images — Admin Upload

`image_url` in the catalog (2.11/2.12) is **model-level** (one photo per brand+model+category — not
per physical IMEI). Managed from the admin dashboard, not any of the 3 mobile apps.

**Admin dashboard UI:** `admin.dxempire.in` → **Inventory → Catalog Images** (super_admin only).
Upload a photo file directly, pick Brand + Model + Category, done — the page handles hosting.
Uploading again for the same brand+model+category **replaces** the existing photo.

**API (used by that screen, also directly callable):**

```
POST /api/v1/admin/catalog-images/upload   (multipart/form-data)
  brand, model, category, image (file — jpg/png/webp, max 4MB)
```
Stores the file under `public/uploads/catalog-images/` on the API server (not S3 — no external
hosting needed) and upserts the `CatalogImage` row with the resulting public URL.

```
POST /api/v1/admin/catalog-images          (JSON — set a URL directly instead of uploading a file)
{ "brand": "Apple", "model": "iPhone 13", "category": "phone", "image_url": "https://..." }
```

`GET /api/v1/admin/catalog-images` lists all; `DELETE /api/v1/admin/catalog-images/{id}` removes one.

---

# Suggested App Flows

**Staff (Sales) App**
1. `POST /mobile/auth/login` (Sales ID) → store token
2. `GET /mobile/dashboard` → render level-specific home screen
3. `GET /mobile/hierarchy/subordinates` or `/tree` → "My Team" screen
4. `GET /mobile/hierarchy/team-stats` → stats widget
5. `POST /users/push-token` → register for push
6. `POST /mobile/auth/logout` on sign-out

**Partner App**
1. `POST /partner/auth/login` (email/phone + password) → store token
2. `GET /partner/dashboard` → home tiles + recent orders
3. `GET /partner/catalog/brands` → brand selector
4. `GET /partner/catalog?brand=X` → mobiles + grades (with `image_url`) → tap → `/catalog/grades`
5. Build a cart client-side → `POST /partner/orders` with `{ brand, model, grade, quantity }` lines
6. `GET /partner/orders` → order history → `/orders/{id}` for detail/tracking
7. `GET /partner/invoices` and `GET /partner/dues` → billing screens
8. `POST /users/push-token` → register for push
9. `POST /partner/auth/logout` on sign-out

**Warehouse App**
1. `POST /auth/admin/login` (email + password) → store token
2. `GET /inventory` or scan → `GET /inventory/imei/{imei}` → lookup screen
3. `GET /qc/pending` → `POST /qc/grade` → grading screen
4. `GET /bins` → `POST /bins/move` → put-away screen
5. `POST /procurement/receive` → receiving screen
6. `GET /orders?status=approved` → pick list → walk through 3.14 (picking → packed → dispatch → deliver)
7. `POST /users/push-token` → register for push
8. `POST /auth/logout` on sign-out

---

# Test Credentials (production demo data)

**Staff app** (Sales ID, no password):
`SM001` · `AM001` / `AM002` · `DM001` / `DM002` · `SG001` / `SG002` / `SG003`

**Partner app** (email or phone + password `password123`):
`partner1@dxempire.com` … `partner10@dxempire.com`

**Warehouse app** (email + password `password123`):
`mohan@dxempire.com` (warehouse_staff) · `deepak@dxempire.com` (qc_engineer, can also access `/qc/*`)

---

# Known Gaps / Follow-ups

Flagging these now so nothing is a surprise later:

1. **Staff dashboard stats are placeholders.** `total_orders`, `total_leads`, revenue figures in
   Part 1.4 are hardcoded `0`/`[]` — Orders/Leads aren't yet aggregated into the staff dashboard.
2. **`EXPO_ACCESS_TOKEN`** — not set in production yet (see Push Notifications above). Push will
   still work without it.
3. **Catalog images** — upload is live (admin dashboard → Inventory → Catalog Images, or the API
   directly). Any model still showing a placeholder simply hasn't had a real photo uploaded yet.
4. **Order-lifecycle push notifications** — only "approved" and "dispatched" currently notify;
   picking/packed/delivered do not yet.
5. **`/retail/*` (Part 5) has no live frontend/app consuming it yet** — fully built and documented,
   but nothing in this codebase calls it today. Its OTP endpoint also returns the OTP directly in
   the response body for testing (no SMS provider wired up) — remove that before any real launch.
6. **Delhivery Warehouse Creation (6.10) has not actually been executed against the live account
   yet** — real business details are saved in Settings (4.2) and the code is ready, but nothing
   auto-fires it. Until a warehouse is registered, Shipment Manifestation (6.4), Pickup Request
   (6.13), and therefore Tracking/Cancellation/Labels on a *real* AWB cannot be exercised end-to-end.
7. **Delhivery Webhooks and RVP QC 3.0 need one-time coordination with Delhivery's BD/integration
   team** (question-ID mapping, signed webhook agreement) before any code against them means
   anything — see the note at the end of Part 6.
8. **Most Delhivery Part 6 response samples are transcribed from Delhivery's own documentation, not
   captured live** — only Pincode Serviceability (6.1), Fetch Waybill (6.2), and Calculate Shipping
   Cost (6.9) have been confirmed against the real production API as of this writing. Treat the rest
   as "should be right" until independently verified in Postman.

---

_Last updated: 2026-07-30 • Base URL: `https://api.dxempire.in/api/v1`_
