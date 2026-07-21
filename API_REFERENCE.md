# DXEmpire — Mobile API Reference

Complete reference for the mobile app developer building the **Staff (Sales) App** and the **Partner App**.
All responses below are **real samples** captured from the live production API.

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

## 2.6 Order Detail — `GET /partner/orders/{id}`

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

## 2.7 My Invoices — `GET /partner/invoices`

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

## 2.8 My Dues — `GET /partner/dues`

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

## 2.9 Catalog — Brands — `GET /partner/catalog/brands`

In-stock brands for the brand selector. Optional `?category=phone|laptop|accessory`.

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": [
    { "brand": "Apple",   "available_qty": 5 },
    { "brand": "Dell",    "available_qty": 2 },
    { "brand": "HP",      "available_qty": 3 },
    { "brand": "Samsung", "available_qty": 5 },
    { "brand": "Xiaomi",  "available_qty": 3 }
  ]
}
```

---

## 2.10 Catalog — Products by Brand/Grade — `GET /partner/catalog`

**Select a brand → get all grades of that brand's mobiles.** Results are aggregated by model + grade
(available quantity + price range), so the app shows one row per variant instead of every physical unit.

**Query params (all optional):** `brand`, `category`, `grade`, `search`

**Example:** `GET /partner/catalog?brand=Samsung&category=phone`

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "total_variants": 3,
    "items": [
      { "brand": "Samsung", "model": "Galaxy S22",       "category": "phone", "grade": "S2", "available_qty": 1, "price_from": "80426.00", "price_to": "80426.00" },
      { "brand": "Samsung", "model": "Galaxy S22",       "category": "phone", "grade": "S5", "available_qty": 2, "price_from": "50340.00", "price_to": "74746.00" },
      { "brand": "Samsung", "model": "Galaxy S23 Ultra", "category": "phone", "grade": "S3", "available_qty": 2, "price_from": "27214.00", "price_to": "58381.00" }
    ]
  }
}
```

- `grade` is one of `S1`–`S5`
- `price_from` / `price_to` — price range (B2B `selling_price`) across available units of that model+grade

---

## 2.11 Catalog — Grades for a Model — `GET /partner/catalog/grades`

Grade breakdown for a specific brand + model (e.g. tap a phone → see its grades).

**Query params (required):** `brand`, `model`

**Example:** `GET /partner/catalog/grades?brand=Samsung&model=Galaxy S23 Ultra`

**Response `200`**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "brand": "Samsung",
    "model": "Galaxy S23 Ultra",
    "grades": [
      { "grade": "S3", "available_qty": 2, "price_from": "27214.00", "price_to": "58381.00" }
    ]
  }
}
```

---
---

# Common Errors

| Code | Meaning | Body |
|------|---------|------|
| `401` | Not logged in / token expired | `{ "success": false, "message": "Unauthenticated." }` |
| `401` | Bad credentials | `{ "success": false, "message": "Invalid login or password." }` |
| `403` | Account deactivated / no permission | `{ "success": false, "message": "..." }` |
| `404` | Resource not found / not yours | `{ "success": false, "message": "..." }` |
| `422` | Validation error | `{ "success": false, "message": "...", "errors": { ... } }` |

On any `401`, clear the stored token and route the user to the login screen.

---

# Suggested App Flows

**Staff (Sales) App**
1. `POST /mobile/auth/login` (Sales ID) → store token
2. `GET /mobile/dashboard` → render level-specific home screen
3. `GET /mobile/hierarchy/subordinates` or `/tree` → "My Team" screen
4. `GET /mobile/hierarchy/team-stats` → stats widget
5. `POST /mobile/auth/logout` on sign-out

**Partner App**
1. `POST /partner/auth/login` (email/phone + password) → store token
2. `GET /partner/dashboard` → home tiles + recent orders
3. `GET /partner/catalog/brands` → brand selector
4. `GET /partner/catalog?brand=X` → mobiles + grades → tap → `/catalog/grades`
5. `GET /partner/orders` → order history → `/orders/{id}` for detail
6. `GET /partner/invoices` and `GET /partner/dues` → billing screens
7. `POST /partner/auth/logout` on sign-out

---

# Test Credentials (production demo data)

**Staff app** (Sales ID, no password):
`SM001` · `AM001` / `AM002` · `DM001` / `DM002` · `SG001` / `SG002` / `SG003`

**Partner app** (email or phone + password `password123`):
`partner1@dxempire.com` … `partner10@dxempire.com`

---

_Last updated: 2026-07-21 • Base URL: `https://api.dxempire.in/api/v1`_
