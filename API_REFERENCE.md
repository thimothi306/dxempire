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

## 3.12 Order Fulfillment Lifecycle

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

**3.12.a Start picking** — `POST /orders/{id}/picking` (no body)
```json
{ "success": true, "message": "Picking started.", "data": { "id": 15, "order_number": "DX-2026-00015", "status": "picking", "...": "..." } }
```

**3.12.b Complete packing** — `POST /orders/{id}/packing-complete` (no body)
```json
{ "success": true, "message": "Packing completed.", "data": { "id": 15, "status": "packed", "...": "..." } }
```

**3.12.c Dispatch** — `POST /orders/{id}/dispatch`
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

**3.12.d Mark delivered** — `POST /orders/{id}/deliver` (no body)
```json
{ "success": true, "message": "Order marked as delivered.", "data": { "id": 15, "status": "delivered", "delivered_at": "2026-07-21T06:25:38.000000Z" } }
```

**Error `422`** — wrong-state transition (e.g. calling `/picking` on a delivered order)
```json
{ "success": false, "message": "Order must be approved before picking. Current status: delivered." }
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
| `422` | Validation / business-rule error | `{ "success": false, "message": "...", "errors"?: { ... } }` |
| `500` | Server error | `{ "message": "Server Error" }` — should not happen; report it if you see one |

On any `401`, clear the stored token and route the user to the login screen.

> ⚠️ **Always send `Accept: application/json`** on every request. Without it, some validation
> failures return an HTML redirect instead of JSON (default Laravel behavior for "browser" requests).
> All three example flows below assume this header is always present.

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
placement/fulfillment triggers (3.12) do not yet push a notification for every step — only
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
6. `GET /orders?status=approved` → pick list → walk through 3.12 (picking → packed → dispatch → deliver)
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

---

_Last updated: 2026-07-21 • Base URL: `https://api.dxempire.in/api/v1`_
