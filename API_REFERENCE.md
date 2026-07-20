# DXEMPIRE — Backend API Reference
> Base URL: `https://api.dxempire.com/api/v1`  
> All requests must include `Authorization: Bearer <token>` except the two OTP endpoints.  
> All responses follow: `{ success: bool, data: any, message: string }`  
> Paginated responses include: `{ data: [], meta: { total, per_page, current_page, last_page } }`

---

## Authentication

| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| POST | `/auth/send-otp` | ❌ | Send OTP to phone number |
| POST | `/auth/verify-otp` | ❌ | Verify OTP — returns token + user |
| GET | `/auth/me` | ✅ | Get logged-in user profile |
| POST | `/auth/refresh` | ✅ | Refresh token — revokes old, issues new 30-day token |
| POST | `/auth/logout` | ✅ | Revoke current token |

### POST `/auth/send-otp`
```json
// Request
{ "phone": "9876543210" }

// Response
{ "success": true, "message": "OTP sent successfully" }
```

### POST `/auth/verify-otp`
```json
// Request
{ "phone": "9876543210", "code": "123456" }

// Response
{
  "success": true,
  "data": {
    "token": "1|xxxxxxxxxxxxxxxx",
    "user": {
      "id": 1,
      "name": "Ravi Kumar",
      "phone": "9876543210",
      "role": "b2b_partner",
      "partner_id": 3,
      "kyc_status": "verified",
      "permissions": ["place-orders", "view-catalog"]
    }
  }
}
```
> `kyc_status` is `null` for internal staff roles. Only set for `b2b_partner`.  
> `partner_id` is `null` for internal staff roles.

### GET `/auth/me`
```json
// Response
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Ravi Kumar",
    "phone": "9876543210",
    "email": null,
    "role": "b2b_partner",
    "partner_id": 3,
    "kyc_status": "verified",
    "is_active": true,
    "permissions": ["place-orders"]
  }
}
```

---

## Push Tokens

> ⚠️ Guide says `/notifications/register` — actual URL is `/users/push-token`

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/users/push-token` | Register Expo push token for this device |
| DELETE | `/users/push-token` | Remove push token on logout |

### POST `/users/push-token`
```json
// Request
{ "token": "ExponentPushToken[xxxxxx]", "device_type": "android" }
// device_type: "android" | "ios" — defaults to "android"
```

### DELETE `/users/push-token`
```json
// Request (optional — omit to remove ALL tokens for user)
{ "token": "ExponentPushToken[xxxxxx]" }
```

---

## Notifications (In-App Inbox)

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/notifications` | List notifications for logged-in user (paginated) |
| PATCH | `/notifications/read-all` | Mark all as read |
| GET | `/notifications/unread-count` | Get unread count |
| PATCH | `/notifications/{id}` | Mark single notification as read |

### GET `/notifications`
```
Query params: per_page (default 20)
```
```json
// Response
{
  "data": [
    {
      "id": 1,
      "title": "Order Dispatched",
      "body": "Your order DX-2026-00001 is on the way.",
      "type": "order_update",
      "data": { "order_id": "42" },
      "is_read": false,
      "created_at": "2026-05-18T10:30:00"
    }
  ],
  "meta": { "total": 25, "per_page": 20, "current_page": 1 }
}
```

### GET `/notifications/unread-count`
```json
{ "success": true, "data": { "count": 5 } }
```

---

## Inventory / Catalog

> ⚠️ Partners (`b2b_partner` role) automatically see only `in_stock` items.  
> Staff roles see all statuses.

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/inventory` | Stock list (paginated) |
| GET | `/inventory/availability` | Stock count by category + grade |
| GET | `/inventory/imei/{imei}` | Look up product by IMEI (staff scanner) |
| GET | `/inventory/{id}` | Single product detail |
| GET | `/inventory/export` | Download Excel — staff/admin only |

### GET `/inventory`
```
Query params:
  category    → "phone" | "laptop" | "accessory"
  grade       → "S1" | "S2" | "S3" | "S4" | "S5"
  status      → "received" | "qc_pending" | "in_stock" | "sold" | "returned" | "rejected" | "refurbishment"
  brand       → string (partial match)
  bin_id      → integer
  search      → string (searches IMEI, serial number, model)
  sort        → column name (default: created_at)
  direction   → "asc" | "desc"
  per_page    → integer (max 100, default 50)
```

### GET `/inventory/imei/{imei}`
```json
// Response — returns product with bin, supplier, qcRecords
// 404 if IMEI not found
{
  "success": true,
  "data": {
    "id": 12,
    "imei": "356789012345678",
    "brand": "Samsung",
    "model": "Galaxy S23",
    "grade": "S1",
    "status": "in_stock",
    "selling_price": "45000.00",
    "bin": { "id": 3, "name": "A-03" },
    "qc_records": [...]
  }
}
```

---

## Orders

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/orders` | List orders (paginated) |
| POST | `/orders` | Place new order |
| GET | `/orders/{id}` | Order detail |
| POST | `/orders/{id}/approve` | Approve order — super_admin only |
| POST | `/orders/{id}/cancel` | Cancel order — super_admin only |
| POST | `/orders/{id}/picking` | Start picking — warehouse_staff |
| POST | `/orders/{id}/packing-complete` | Mark packed — warehouse_staff |
| POST | `/orders/{id}/dispatch` | Mark dispatched — warehouse_staff |
| POST | `/orders/{id}/deliver` | Mark delivered — warehouse_staff |
| POST | `/orders/{id}/return` | Process return — warehouse_staff |
| GET | `/orders/{id}/payments` | Order payment history |
| GET | `/orders/{id}/invoice/download` | Invoice download info |

> ⚠️ Guide says `PUT /orders/:id/approve` — actual method is **POST**, not PUT.  
> ⚠️ Guide says `GET /dispatch/queue` — use `GET /orders?status=approved` instead.

### GET `/orders`
```
Query params:
  status          → "pending" | "approved" | "picking" | "packing" | "dispatched" | "delivered" | "cancelled" | "returned"
  dealer_id       → integer
  payment_status  → "unpaid" | "partial" | "paid" | "refunded"
  search          → order number
  per_page        → integer (default 20)
```

### POST `/orders` — Place order
```json
// Request
{
  "product_ids": [12, 15, 18],
  "dealer_id": 3,
  "notes": "Urgent delivery"
}
// dealer_id is optional — omit for retail/walk-in orders
// Duplicate product_ids are de-duped automatically
```
```json
// Response 201
{
  "data": {
    "id": 42,
    "order_number": "DX-2026-00042",
    "status": "pending",
    "subtotal": "45000.00",
    "gst_amount": "8100.00",
    "total_amount": "53100.00",
    "items": [...]
  }
}
// 422 if any product is out of stock
// 422 if dealer has insufficient credit
```

### POST `/orders/{id}/dispatch`
```json
// Request
{
  "logistics_provider": "shiprocket",
  "awb_number": "SHIP1234567890"
}
```

---

## Dealers / Partners

> ⚠️ Guide says `/partners` — actual URL is `/dealers`  
> ⚠️ Guide says `PUT /partners/:id/kyc` — actual URL is `PUT /dealers/:id/kyc`

| Method | URL | Roles | Description |
|--------|-----|-------|-------------|
| GET | `/dealers` | sales, super_admin | List all dealers |
| GET | `/dealers/{id}` | sales, super_admin | Dealer detail |
| POST | `/dealers` | super_admin | Create new dealer |
| PUT | `/dealers/{id}/kyc` | super_admin | Approve / reject KYC |
| PUT | `/dealers/{id}/credit` | super_admin | Update credit limit |
| GET | `/dealers/{id}/ledger` | sales, super_admin | Dealer order + credit ledger |

### PUT `/dealers/{id}/kyc`
```json
// Request
{ "kyc_status": "verified", "reason": "Documents verified" }
// kyc_status: "verified" | "rejected"
// Automatically pushes notification to partner on verification
```

### GET `/dealers/{id}/ledger`
```
Query params: from (date), to (date)
```
```json
// Response
{
  "data": {
    "summary": {
      "total_orders": 12,
      "total_billed": "500000.00",
      "total_paid": "450000.00",
      "credit_limit": "200000.00",
      "credit_used": "50000.00",
      "available_credit": "150000.00"
    },
    "transactions": { ...paginated orders... }
  }
}
```

---

## Leads (CRM)

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/leads` | List leads (paginated) |
| POST | `/leads` | Create lead |
| GET | `/leads/{id}` | Lead detail |
| PUT | `/leads/{id}` | Update lead |
| PUT | `/leads/{id}/stage` | Move lead to new pipeline stage |

### PUT `/leads/{id}/stage`
```json
// Request
{ "stage": "negotiating" }
// stages: new | contacted | quoted | negotiating | won | lost
```

---

## QC

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/qc/pending` | Items awaiting QC (paginated) |
| POST | `/qc/grade` | Submit QC result |
| GET | `/qc/records` | QC history log |
| GET | `/qc/stats` | QC throughput stats |
| GET | `/qc/refurbishment` | Items in refurbishment |
| PUT | `/qc/refurbishment/{product}` | Complete refurbishment |

### POST `/qc/grade`
```json
// Request
{
  "product_id": 12,
  "grade": "S2",
  "outcome": "pass",
  "notes": "Minor scratches on back"
}
// outcome: "pass" | "repair" | "reject"
// grade: "S1" | "S2" | "S3" | "S4" | "S5"
```

---

## Bins / Rack Management

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/bins` | List all bins with occupancy |
| POST | `/bins/move` | Move product to different bin |
| GET | `/bins/{id}/products` | Products in a specific bin |

### POST `/bins/move`
```json
// Request
{ "product_id": 12, "to_bin_id": 5 }
```

---

## Procurement

> ⚠️ Guide says `POST /inventory/receive` — actual URL is `POST /procurement/receive`

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/suppliers` | List suppliers |
| POST | `/suppliers` | Add supplier |
| GET | `/suppliers/{id}` | Supplier detail |
| PUT | `/suppliers/{id}` | Update supplier |
| GET | `/purchase-orders` | List purchase orders |
| POST | `/purchase-orders` | Create purchase order |
| GET | `/purchase-orders/{id}` | Purchase order detail |
| POST | `/procurement/receive` | Receive stock batch |
| GET | `/procurement/history` | Receiving history |

---

## Finance

> ⚠️ Guide says `GET /reports/pl` — actual URL is `GET /finance/profit-loss`

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/finance/invoices` | All GST invoices (paginated) |
| GET | `/finance/invoices/{id}` | Invoice detail |
| GET | `/finance/invoices/{id}/download` | Invoice PDF download |
| POST | `/finance/invoices/orders/{order}` | Generate invoice for order |
| GET | `/finance/expenses` | Expense list |
| POST | `/finance/expenses` | Add expense |
| GET | `/finance/expenses/categories` | Expense categories |
| GET | `/finance/expenses/{id}` | Expense detail |
| GET | `/finance/profit-loss` | P&L report |
| GET | `/finance/gst-summary` | GST 12-month summary |
| GET | `/finance/receivables` | Outstanding receivables |
| GET | `/finance/dealers/{id}/ledger` | Dealer ledger (finance view) |

### GET `/finance/profit-loss`
```
Query params: from (date), to (date)
```
```json
// Response
{
  "data": {
    "revenue": "5000000.00",
    "gst_collected": "900000.00",
    "cogs": "3500000.00",
    "gross_profit": "1500000.00",
    "expenses": "200000.00",
    "net_profit": "1300000.00",
    "gross_margin_pct": 30.0,
    "net_margin_pct": 26.0
  }
}
```

---

## HR

> ⚠️ Guide says `GET /attendance/:month` — actual URL is `GET /hr/attendance?month=YYYY-MM`  
> ⚠️ Guide says `POST /payroll/run` — actual URL is `POST /hr/payroll`

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/hr/employees` | Employee list |
| POST | `/hr/employees` | Add employee |
| GET | `/hr/employees/{id}` | Employee detail |
| PUT | `/hr/employees/{id}` | Update employee |
| DELETE | `/hr/employees/{id}` | Deactivate employee |
| GET | `/hr/employees/departments` | Department list |
| GET | `/hr/attendance` | Attendance records |
| POST | `/hr/attendance/bulk` | Bulk mark attendance |
| GET | `/hr/attendance/today` | Today's attendance status |
| GET | `/hr/attendance/{employee}/summary` | Employee attendance summary |
| GET | `/hr/payroll` | Payroll run list |
| POST | `/hr/payroll` | Create new payroll run |
| GET | `/hr/payroll/{id}` | Payroll run detail |
| GET | `/hr/payroll/{id}/items` | Payroll line items |
| POST | `/hr/payroll/{id}/process` | Process payroll (calculate salaries) |
| POST | `/hr/payroll/{id}/mark-paid` | Mark payroll as paid |
| GET | `/hr/payroll/{id}/slips/{itemId}` | Download salary slip PDF |

### GET `/hr/attendance`
```
Query params: month (YYYY-MM), employee_id
```

---

## Analytics

> ⚠️ Guide says `GET /admin/stats` — actual URL is `GET /analytics/dashboard`

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/analytics/dashboard` | KPI summary (revenue, orders, stock counts) |
| GET | `/analytics/revenue` | Revenue by period |
| GET | `/analytics/sales` | Sales breakdown by brand/grade/channel |
| GET | `/analytics/inventory` | Inventory matrix + aging buckets |
| GET | `/analytics/stock-movements` | Bin movement history (paginated) |
| GET | `/analytics/partners` | Partner performance table |
| GET | `/analytics/forecast` | 3-month demand forecast |

### GET `/analytics/dashboard`
```json
// Response
{
  "data": {
    "today_revenue": "85000.00",
    "week_revenue": "520000.00",
    "month_revenue": "2100000.00",
    "active_orders": 14,
    "pending_qc": 8,
    "pending_dispatch": 3,
    "in_refurbishment": 5,
    "total_in_stock": 142
  }
}
```

### GET `/analytics/revenue`
```
Query params: period (daily | weekly | monthly), from, to
```

### GET `/analytics/sales`
```
Query params: group_by (category | brand | grade), from, to
```

---

## Admin (Super Admin only)

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/admin/users` | All users list |
| POST | `/admin/users` | Create user |
| GET | `/admin/users/{id}` | User detail |
| PUT | `/admin/users/{id}` | Update user |
| PUT | `/admin/users/{id}/role` | Change user role |
| POST | `/admin/users/{id}/deactivate` | Deactivate user + revoke tokens |
| POST | `/admin/users/{id}/activate` | Re-activate user |
| GET | `/admin/roles` | All roles with user counts |
| GET | `/admin/settings` | All settings |
| PUT | `/admin/settings` | Bulk update settings |
| GET | `/admin/settings/{key}` | Single setting |
| PUT | `/admin/settings/{key}` | Update single setting |
| GET | `/admin/audit-logs` | Full audit log (paginated) |

### PUT `/admin/users/{id}/role`
```json
// Request
{ "role": "accounts" }
// roles: super_admin | warehouse_staff | qc_engineer | sales | accounts | hr_manager | b2b_partner | logistics
```

### GET `/admin/audit-logs`
```
Query params: user_id, action, model, from (date), to (date), per_page
```

### PUT `/admin/settings/{key}`
```json
// Request
{ "value": "shiprocket" }
```

**Editable setting keys:**
| Key | Description |
|-----|-------------|
| `logistics_provider` | `shiprocket` \| `delhivery` \| `dtdc` |
| `whatsapp_provider` | `interakt` \| `twilio` |
| `company_name` | Company display name |
| `company_address` | Registered address |
| `company_gst` | GST number |
| `company_phone` | Contact phone |
| `company_email` | Contact email |
| `grade_price_rules` | JSON grade pricing rules |
| `low_stock_threshold` | Integer — triggers low stock alert |

---

## Support Tickets

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/support/tickets` | Create ticket (any logged-in user) |
| GET | `/support/tickets` | List tickets — sales, super_admin |
| PUT | `/support/tickets/{id}` | Update ticket status |

---

## Logistics

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/logistics/orders/{order}/shipment` | Create shipment with provider |
| GET | `/logistics/track/{awb}` | Track shipment by AWB |
| DELETE | `/logistics/shipment/{awb}` | Cancel shipment |

---

## Role Permission Matrix

| Endpoint group | super_admin | sales | warehouse_staff | qc_engineer | accounts | hr_manager | b2b_partner |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `/admin/*` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `/dealers` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `/leads` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `/inventory` (view) | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ |
| `/inventory` (export) | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/qc/*` | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `/bins/*` | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/orders` (place) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| `/orders` (approve/cancel) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `/orders` (fulfill) | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/finance/*` | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| `/hr/*` | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| `/analytics/*` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| `/procurement/*` | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/notifications` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## Common HTTP Status Codes

| Code | Meaning | Frontend action |
|------|---------|-----------------|
| 200 | Success | Show data |
| 201 | Created | Show success toast, refresh list |
| 401 | Unauthenticated | Clear token → redirect to login |
| 403 | Forbidden | Show "No permission" toast, stay on page |
| 404 | Not found | Show empty state or error message |
| 422 | Validation failed | Map `errors` object to form fields |
| 500 | Server error | Show "Something went wrong" toast |

### 422 error shape
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "phone": ["The phone field is required."],
    "product_ids.0": ["The selected product is out of stock."]
  }
}
```

---

## Quick URL Correction Table
*The frontend development guide uses some URLs that differ from the actual backend.*

| Guide says | Actual backend URL | Note |
|---|---|---|
| `GET /admin/stats` | `GET /analytics/dashboard` | Same data, different path |
| `GET /partners` | `GET /dealers` | Partners = Dealers in backend |
| `PUT /partners/:id/kyc` | `PUT /dealers/:id/kyc` | Same |
| `GET /dispatch/queue` | `GET /orders?status=approved` | Filter by status |
| `POST /notifications/register` | `POST /users/push-token` | Register Expo token |
| `GET /reports/pl` | `GET /finance/profit-loss` | P&L report |
| `POST /inventory/receive` | `POST /procurement/receive` | Receive new stock |
| `PUT /orders/:id/approve` | `POST /orders/:id/approve` | Method is POST not PUT |
| `GET /attendance/:month` | `GET /hr/attendance?month=YYYY-MM` | Query param not path param |
| `POST /payroll/run` | `POST /hr/payroll` | Under /hr prefix |
