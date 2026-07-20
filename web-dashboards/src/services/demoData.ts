// ── Demo mode flag — flip to false when backend is live ───────────────────────
export const DEMO_MODE = false;

const today = new Date().toISOString().slice(0, 10);
const d = (daysAgo: number) => { const x = new Date(); x.setDate(x.getDate() - daysAgo); return x.toISOString().slice(0, 10); };
const dt = (daysAgo: number) => { const x = new Date(); x.setDate(x.getDate() - daysAgo); return x.toISOString(); };

const meta = (total: number) => ({ total, current_page: 1, last_page: 1, per_page: 50 });

// ── Analytics / Dashboard ─────────────────────────────────────────────────────
export const DEMO_DASHBOARD = {
  today_revenue: 284000,
  week_revenue: 2895000,
  month_revenue: 4127000,
  active_orders: 47,
  total_in_stock: 732,
  pending_qc: 38,
  pending_dispatch: 24,
  in_refurbishment: 15,
};

export const DEMO_REVENUE = {
  time_series: [
    { period: d(13), revenue: 182000 }, { period: d(12), revenue: 245000 },
    { period: d(11), revenue: 198000 }, { period: d(10), revenue: 310000 },
    { period: d(9), revenue: 275000 },  { period: d(8), revenue: 422000 },
    { period: d(7), revenue: 389000 },  { period: d(6), revenue: 301000 },
    { period: d(5), revenue: 467000 },  { period: d(4), revenue: 512000 },
    { period: d(3), revenue: 398000 },  { period: d(2), revenue: 441000 },
    { period: d(1), revenue: 503000 },  { period: d(0), revenue: 284000 },
  ],
  top_products: [
    { brand: 'Apple', model: 'iPhone 14', grade: 'S2', revenue: 1840000, units_sold: 42 },
    { brand: 'Samsung', model: 'Galaxy S23', grade: 'S1', revenue: 1260000, units_sold: 35 },
    { brand: 'OnePlus', model: '11 5G', grade: 'S2', revenue: 980000, units_sold: 38 },
    { brand: 'Apple', model: 'iPhone 13', grade: 'S3', revenue: 875000, units_sold: 25 },
    { brand: 'Xiaomi', model: '13 Pro', grade: 'S2', revenue: 640000, units_sold: 29 },
  ],
  top_dealers: [
    { business_name: 'Sharma Mobile Hub', revenue: 1240000, order_count: 28 },
    { business_name: 'Galaxy Electronics', revenue: 980000, order_count: 21 },
    { business_name: 'Mumbai Phone Mart', revenue: 870000, order_count: 19 },
    { business_name: 'Delhi Trade Centre', revenue: 760000, order_count: 17 },
    { business_name: 'Pune Mobile World', revenue: 650000, order_count: 14 },
  ],
};

export const DEMO_INVENTORY_ANALYTICS = {
  category_breakdown: [
    { category: 'Mobiles', count: 512 },
    { category: 'Laptops', count: 148 },
    { category: 'Tablets', count: 72 },
  ],
  grade_breakdown: [
    { grade: 'S1', count: 124 },
    { grade: 'S2', count: 287 },
    { grade: 'S3', count: 198 },
    { grade: 'S4', count: 89 },
    { grade: 'S5', count: 34 },
  ],
};

export const DEMO_PARTNER_PERFORMANCE = {
  data: [
    { business_name: 'Sharma Mobile Hub', order_count: 28, revenue: 1240000, avg_order_value: 44285, return_rate: 1.2 },
    { business_name: 'Galaxy Electronics', order_count: 21, revenue: 980000, avg_order_value: 46666, return_rate: 0.8 },
    { business_name: 'Mumbai Phone Mart', order_count: 19, revenue: 870000, avg_order_value: 45789, return_rate: 2.1 },
    { business_name: 'Delhi Trade Centre', order_count: 17, revenue: 760000, avg_order_value: 44705, return_rate: 0.5 },
    { business_name: 'Pune Mobile World', order_count: 14, revenue: 650000, avg_order_value: 46428, return_rate: 1.5 },
    { business_name: 'Chennai Gadgets', order_count: 12, revenue: 540000, avg_order_value: 45000, return_rate: 0.9 },
  ],
};

// ── Inventory ─────────────────────────────────────────────────────────────────
export const DEMO_INVENTORY = {
  data: [
    { id: 1, imei: '357812109876543', brand: 'Apple', model: 'iPhone 14', category: 'Mobiles', grade: 'S2', status: 'available', selling_price: 52000, bin: { code: 'A-01' } },
    { id: 2, imei: '352098765432109', brand: 'Samsung', model: 'Galaxy S23', category: 'Mobiles', grade: 'S1', status: 'available', selling_price: 38000, bin: { code: 'A-02' } },
    { id: 3, imei: '490123456789012', brand: 'OnePlus', model: '11 5G', category: 'Mobiles', grade: 'S2', status: 'available', selling_price: 28000, bin: { code: 'B-01' } },
    { id: 4, imei: '352765432198765', brand: 'Xiaomi', model: '13 Pro', category: 'Mobiles', grade: 'S3', status: 'available', selling_price: 22000, bin: { code: 'B-02' } },
    { id: 5, imei: '354321098765432', brand: 'Vivo', model: 'V29', category: 'Mobiles', grade: 'S2', status: 'available', selling_price: 18000, bin: { code: 'A-01' } },
    { id: 6, imei: '358901234567890', brand: 'Apple', model: 'MacBook Air M2', category: 'Laptops', grade: 'S1', status: 'available', selling_price: 88000, bin: { code: 'C-01' } },
    { id: 7, imei: '354567890123456', brand: 'Dell', model: 'XPS 15', category: 'Laptops', grade: 'S2', status: 'in_qc', selling_price: 72000, bin: null },
    { id: 8, imei: '352345678901234', brand: 'Samsung', model: 'Galaxy A54', category: 'Mobiles', grade: 'S3', status: 'available', selling_price: 16000, bin: { code: 'A-03' } },
    { id: 9, imei: '356789012345678', brand: 'Oppo', model: 'Reno 10', category: 'Mobiles', grade: 'S2', status: 'in_refurbishment', selling_price: 19000, bin: null },
    { id: 10, imei: '351234567890987', brand: 'Apple', model: 'iPad Pro', category: 'Tablets', grade: 'S1', status: 'available', selling_price: 64000, bin: { code: 'C-02' } },
  ],
  meta: meta(732),
};

// ── QC ────────────────────────────────────────────────────────────────────────
export const DEMO_QC_QUEUE = {
  data: [
    { id: 7, imei: '354567890123456', brand: 'Dell', model: 'XPS 15', category: 'Laptops', status: 'in_qc' },
    { id: 11, imei: '358765432109876', brand: 'Apple', model: 'iPhone 13 Pro', category: 'Mobiles', status: 'in_qc' },
    { id: 12, imei: '357654321098765', brand: 'Samsung', model: 'Galaxy S22', category: 'Mobiles', status: 'in_qc' },
    { id: 13, imei: '356543210987654', brand: 'OnePlus', model: '10T', category: 'Mobiles', status: 'in_qc' },
    { id: 14, imei: '355432109876543', brand: 'Xiaomi', model: '12 Pro', category: 'Mobiles', status: 'in_qc' },
    { id: 15, imei: '354321098765433', brand: 'Realme', model: 'GT 3', category: 'Mobiles', status: 'in_qc' },
  ],
  meta: meta(38),
};

export const DEMO_QC_STATS = {
  pending_qc: 38,
  graded_today: 14,
  in_refurbishment: 15,
  rejected_today: 2,
};

// ── Bins ──────────────────────────────────────────────────────────────────────
export const DEMO_BINS = {
  data: [
    { id: 1, code: 'A-01', zone: 'Zone A', capacity: 50, current_count: 45, is_active: true },
    { id: 2, code: 'A-02', zone: 'Zone A', capacity: 50, current_count: 32, is_active: true },
    { id: 3, code: 'A-03', zone: 'Zone A', capacity: 50, current_count: 28, is_active: true },
    { id: 4, code: 'B-01', zone: 'Zone B', capacity: 60, current_count: 48, is_active: true },
    { id: 5, code: 'B-02', zone: 'Zone B', capacity: 60, current_count: 21, is_active: true },
    { id: 6, code: 'C-01', zone: 'Zone C', capacity: 40, current_count: 15, is_active: true },
    { id: 7, code: 'C-02', zone: 'Zone C', capacity: 40, current_count: 38, is_active: true },
    { id: 8, code: 'D-01', zone: 'Zone D', capacity: 30, current_count: 0, is_active: false },
  ],
  meta: meta(8),
};

// ── Orders ────────────────────────────────────────────────────────────────────
export const DEMO_ORDERS = {
  data: [
    { id: 1, order_number: 'ORD-2481', status: 'confirmed', dealer: { business_name: 'Sharma Mobile Hub' }, total_amount: 124000, items_count: 4, created_at: dt(0), tracking_number: null,
      items: [{ id: 1, unit_price: 52000, product: { brand: 'Apple', model: 'iPhone 14', imei: '357812109876543' } }, { id: 2, unit_price: 38000, product: { brand: 'Samsung', model: 'Galaxy S23', imei: '352098765432109' } }] },
    { id: 2, order_number: 'ORD-2480', status: 'dispatched', dealer: { business_name: 'Galaxy Electronics' }, total_amount: 87500, items_count: 3, created_at: dt(1), tracking_number: 'BD789456123',
      items: [{ id: 3, unit_price: 28000, product: { brand: 'OnePlus', model: '11 5G', imei: '490123456789012' } }] },
    { id: 3, order_number: 'ORD-2479', status: 'pending', dealer: { business_name: 'Mumbai Phone Mart' }, total_amount: 210000, items_count: 7, created_at: dt(1), tracking_number: null,
      items: [{ id: 4, unit_price: 22000, product: { brand: 'Xiaomi', model: '13 Pro', imei: '352765432198765' } }] },
    { id: 4, order_number: 'ORD-2478', status: 'delivered', dealer: { business_name: 'Delhi Trade Centre' }, total_amount: 56000, items_count: 2, created_at: dt(2), tracking_number: 'DT987654321',
      items: [{ id: 5, unit_price: 18000, product: { brand: 'Vivo', model: 'V29', imei: '354321098765432' } }] },
    { id: 5, order_number: 'ORD-2477', status: 'confirmed', dealer: { business_name: 'Pune Mobile World' }, total_amount: 168000, items_count: 6, created_at: dt(2), tracking_number: null,
      items: [] },
    { id: 6, order_number: 'ORD-2476', status: 'cancelled', dealer: { business_name: 'Chennai Gadgets' }, total_amount: 45000, items_count: 1, created_at: dt(3), tracking_number: null,
      items: [] },
  ],
  meta: meta(47),
};

// ── Dealers ───────────────────────────────────────────────────────────────────
export const DEMO_DEALERS = {
  data: [
    { id: 1, business_name: 'Sharma Mobile Hub', owner_name: 'Rajesh Sharma', phone: '9876543210', email: 'rajesh@sharmamobile.com', city: 'Mumbai', kyc_status: 'approved', credit_limit: 500000, outstanding_balance: 124000, gst_number: '27AABCS1429B1ZB', created_at: dt(90) },
    { id: 2, business_name: 'Galaxy Electronics', owner_name: 'Suresh Patel', phone: '9765432109', email: 'suresh@galaxyelec.com', city: 'Ahmedabad', kyc_status: 'approved', credit_limit: 400000, outstanding_balance: 87500, gst_number: '24AADCS2180B1ZD', created_at: dt(75) },
    { id: 3, business_name: 'Mumbai Phone Mart', owner_name: 'Priya Mehta', phone: '9654321098', email: 'priya@mumbaiphone.com', city: 'Mumbai', kyc_status: 'approved', credit_limit: 600000, outstanding_balance: 210000, gst_number: '27AABCM4567C1ZA', created_at: dt(60) },
    { id: 4, business_name: 'Delhi Trade Centre', owner_name: 'Amit Kumar', phone: '9543210987', email: 'amit@delhitrade.com', city: 'Delhi', kyc_status: 'approved', credit_limit: 350000, outstanding_balance: 0, gst_number: '07AABCD3456E1ZB', created_at: dt(45) },
    { id: 5, business_name: 'Pune Mobile World', owner_name: 'Sneha Joshi', phone: '9432109876', email: 'sneha@punemobile.com', city: 'Pune', kyc_status: 'pending', credit_limit: 200000, outstanding_balance: 0, gst_number: null, created_at: dt(10) },
    { id: 6, business_name: 'Chennai Gadgets', owner_name: 'Vikram Reddy', phone: '9321098765', email: 'vikram@chennaigadgets.com', city: 'Chennai', kyc_status: 'pending', credit_limit: 0, outstanding_balance: 0, gst_number: null, created_at: dt(5) },
  ],
  meta: meta(6),
};

// ── Leads ─────────────────────────────────────────────────────────────────────
export const DEMO_LEADS = {
  data: [
    { id: 1, business_name: 'TechZone Mobiles', contact_name: 'Arjun Singh', phone: '9111222333', email: 'arjun@techzone.com', city: 'Bangalore', source: 'referral', stage: 'qualified', created_at: dt(3) },
    { id: 2, business_name: 'Star Electronics', contact_name: 'Pooja Nair', phone: '9222333444', email: 'pooja@starelectronics.com', city: 'Kochi', source: 'website', stage: 'new', created_at: dt(5) },
    { id: 3, business_name: 'Hyderabad Mobile Store', contact_name: 'Ravi Teja', phone: '9333444555', email: 'ravi@hydmobile.com', city: 'Hyderabad', source: 'cold_call', stage: 'proposal', created_at: dt(7) },
    { id: 4, business_name: 'Gadget Galaxy', contact_name: 'Meena Iyer', phone: '9444555666', email: 'meena@gadgetgalaxy.com', city: 'Chennai', source: 'trade_show', stage: 'negotiation', created_at: dt(10) },
    { id: 5, business_name: 'SmartPhone Hub', contact_name: 'Deepak Verma', phone: '9555666777', email: 'deepak@smarthub.com', city: 'Jaipur', source: 'website', stage: 'new', created_at: dt(12) },
    { id: 6, business_name: 'Mobile Point', contact_name: 'Kavya Rao', phone: '9666777888', email: 'kavya@mobilepoint.com', city: 'Lucknow', source: 'referral', stage: 'qualified', created_at: dt(14) },
  ],
  meta: meta(12),
};

// ── Procurement ───────────────────────────────────────────────────────────────
export const DEMO_SUPPLIERS = {
  data: [
    { id: 1, name: 'TechSource India', contact_name: 'Mahesh Gupta', phone: '9100200300', email: 'mahesh@techsource.in', gst_number: '29AABCT1234A1ZB' },
    { id: 2, name: 'Prime Electronics Wholesale', contact_name: 'Sanjay Jain', phone: '9200300400', email: 'sanjay@primeelec.com', gst_number: '27AADCP4567B1ZC' },
    { id: 3, name: 'Digital World Distributors', contact_name: 'Neha Sharma', phone: '9300400500', email: 'neha@digitalworld.com', gst_number: '07AABCD7890C1ZD' },
  ],
};

export const DEMO_PURCHASE_ORDERS = {
  data: [
    { id: 1, po_number: 'PO-0124', status: 'delivered', supplier: { name: 'TechSource India' }, total_amount: 840000, expected_date: d(5) },
    { id: 2, po_number: 'PO-0123', status: 'ordered', supplier: { name: 'Prime Electronics Wholesale' }, total_amount: 1200000, expected_date: d(-3) },
    { id: 3, po_number: 'PO-0122', status: 'partial', supplier: { name: 'Digital World Distributors' }, total_amount: 560000, expected_date: d(2) },
    { id: 4, po_number: 'PO-0121', status: 'delivered', supplier: { name: 'TechSource India' }, total_amount: 920000, expected_date: d(15) },
  ],
  meta: meta(4),
};

// ── Finance — Invoices ────────────────────────────────────────────────────────
export const DEMO_INVOICES = {
  data: [
    { id: 1, invoice_number: 'INV-2481', status: 'paid', order: { dealer: { business_name: 'Sharma Mobile Hub' } }, subtotal: 105085, gst_amount: 18915, total_amount: 124000, due_date: d(-5) },
    { id: 2, invoice_number: 'INV-2480', status: 'unpaid', order: { dealer: { business_name: 'Galaxy Electronics' } }, subtotal: 74152, gst_amount: 13348, total_amount: 87500, due_date: d(7) },
    { id: 3, invoice_number: 'INV-2479', status: 'unpaid', order: { dealer: { business_name: 'Mumbai Phone Mart' } }, subtotal: 178000, gst_amount: 32040, total_amount: 210000, due_date: d(10) },
    { id: 4, invoice_number: 'INV-2478', status: 'paid', order: { dealer: { business_name: 'Delhi Trade Centre' } }, subtotal: 47458, gst_amount: 8542, total_amount: 56000, due_date: d(-10) },
    { id: 5, invoice_number: 'INV-2477', status: 'overdue', order: { dealer: { business_name: 'Pune Mobile World' } }, subtotal: 142373, gst_amount: 25627, total_amount: 168000, due_date: d(-15) },
  ],
  meta: meta(5),
};

// ── Finance — Expenses ────────────────────────────────────────────────────────
export const DEMO_EXPENSES = {
  data: [
    { id: 1, category: 'Logistics', description: 'Bluedart courier charges — May batch', amount: 28500, date: d(2), vendor: 'Bluedart Express', recorded_by: { name: 'Anita Sharma' } },
    { id: 2, category: 'Office', description: 'Office supplies and stationery', amount: 4200, date: d(4), vendor: 'Staples India', recorded_by: { name: 'Anita Sharma' } },
    { id: 3, category: 'Utilities', description: 'Warehouse electricity bill', amount: 18700, date: d(6), vendor: 'MSEDCL', recorded_by: { name: 'Rahul Verma' } },
    { id: 4, category: 'Marketing', description: 'Google Ads — May campaign', amount: 15000, date: d(8), vendor: 'Google India', recorded_by: { name: 'Anita Sharma' } },
    { id: 5, category: 'Logistics', description: 'Delhivery bulk shipment charges', amount: 22000, date: d(10), vendor: 'Delhivery', recorded_by: { name: 'Rahul Verma' } },
    { id: 6, category: 'Repairs', description: 'Warehouse equipment maintenance', amount: 9800, date: d(12), vendor: 'TechFix Services', recorded_by: { name: 'Anita Sharma' } },
  ],
  meta: meta(6),
};

// ── Finance — P&L ─────────────────────────────────────────────────────────────
export const DEMO_PL = {
  total_revenue: 4127000,
  total_expenses: 2060000,
  time_series: [
    { period: 'Dec 2025', revenue: 2850000, expenses: 1420000 },
    { period: 'Jan 2026', revenue: 3120000, expenses: 1580000 },
    { period: 'Feb 2026', revenue: 2980000, expenses: 1490000 },
    { period: 'Mar 2026', revenue: 3450000, expenses: 1720000 },
    { period: 'Apr 2026', revenue: 3890000, expenses: 1950000 },
    { period: 'May 2026', revenue: 4127000, expenses: 2060000 },
  ],
};

// ── Finance — GST ─────────────────────────────────────────────────────────────
export const DEMO_GST = {
  taxable_value: 3480508,
  cgst: 313246,
  sgst: 313246,
  invoices: [
    { id: 1, invoice_number: 'INV-2481', dealer_name: 'Sharma Mobile Hub', dealer_gstin: '27AABCS1429B1ZB', taxable_value: 105085, cgst: 9458, sgst: 9458, total_amount: 124000 },
    { id: 2, invoice_number: 'INV-2480', dealer_name: 'Galaxy Electronics', dealer_gstin: '24AADCS2180B1ZD', taxable_value: 74152, cgst: 6674, sgst: 6674, total_amount: 87500 },
    { id: 3, invoice_number: 'INV-2479', dealer_name: 'Mumbai Phone Mart', dealer_gstin: '27AABCM4567C1ZA', taxable_value: 178000, cgst: 16020, sgst: 16020, total_amount: 210000 },
    { id: 4, invoice_number: 'INV-2478', dealer_name: 'Delhi Trade Centre', dealer_gstin: '07AABCD3456E1ZB', taxable_value: 47458, cgst: 4271, sgst: 4271, total_amount: 56000 },
    { id: 5, invoice_number: 'INV-2477', dealer_name: 'Pune Mobile World', dealer_gstin: null, taxable_value: 142373, cgst: 12813, sgst: 12813, total_amount: 168000 },
  ],
};

// ── Finance — Receivables ─────────────────────────────────────────────────────
export const DEMO_RECEIVABLES = {
  data: [
    { id: 2, invoice_number: 'INV-2480', due_date: d(7), aging_bucket: 'current', total_amount: 87500, paid_amount: 0, dealer: { business_name: 'Galaxy Electronics' } },
    { id: 3, invoice_number: 'INV-2479', due_date: d(10), aging_bucket: 'current', total_amount: 210000, paid_amount: 0, dealer: { business_name: 'Mumbai Phone Mart' } },
    { id: 5, invoice_number: 'INV-2477', due_date: d(-15), aging_bucket: '1_30', total_amount: 168000, paid_amount: 0, dealer: { business_name: 'Pune Mobile World' } },
    { id: 7, invoice_number: 'INV-2465', due_date: d(-38), aging_bucket: '31_60', total_amount: 95000, paid_amount: 40000, dealer: { business_name: 'Chennai Gadgets' } },
  ],
  meta: meta(4),
};

// ── HR — Employees ────────────────────────────────────────────────────────────
export const DEMO_EMPLOYEES = {
  data: [
    { id: 1, name: 'Rahul Verma', phone: '9811122233', email: 'rahul@dxempire.com', employee_code: 'EMP001', department: 'Warehouse', designation: 'Warehouse Manager', employment_type: 'full_time', salary: 35000, joining_date: d(365), is_active: true },
    { id: 2, name: 'Sneha Kulkarni', phone: '9822233344', email: 'sneha@dxempire.com', employee_code: 'EMP002', department: 'QC', designation: 'QC Engineer', employment_type: 'full_time', salary: 28000, joining_date: d(300), is_active: true },
    { id: 3, name: 'Anita Sharma', phone: '9833344455', email: 'anita@dxempire.com', employee_code: 'EMP003', department: 'Accounts', designation: 'Accounts Manager', employment_type: 'full_time', salary: 40000, joining_date: d(400), is_active: true },
    { id: 4, name: 'Kiran Rao', phone: '9844455566', email: 'kiran@dxempire.com', employee_code: 'EMP004', department: 'Sales', designation: 'Sales Executive', employment_type: 'full_time', salary: 25000, joining_date: d(200), is_active: true },
    { id: 5, name: 'Deepak Nair', phone: '9855566677', email: 'deepak@dxempire.com', employee_code: 'EMP005', department: 'Warehouse', designation: 'Packing Staff', employment_type: 'full_time', salary: 18000, joining_date: d(150), is_active: true },
    { id: 6, name: 'Pooja Gupta', phone: '9866677788', email: 'pooja@dxempire.com', employee_code: 'EMP006', department: 'HR', designation: 'HR Manager', employment_type: 'full_time', salary: 38000, joining_date: d(500), is_active: true },
    { id: 7, name: 'Amit Tiwari', phone: '9877788899', email: 'amit@dxempire.com', employee_code: 'EMP007', department: 'QC', designation: 'QC Engineer', employment_type: 'full_time', salary: 27000, joining_date: d(100), is_active: true },
    { id: 8, name: 'Ritu Mishra', phone: '9888899900', email: 'ritu@dxempire.com', employee_code: 'EMP008', department: 'Sales', designation: 'Sales Executive', employment_type: 'contractual', salary: 22000, joining_date: d(60), is_active: false },
  ],
  meta: meta(20),
};

// ── HR — Attendance ───────────────────────────────────────────────────────────
export const DEMO_ATTENDANCE = {
  data: [
    { id: 1, employee_id: 1, date: today, status: 'present', check_in_time: `${today}T09:02:00`, check_out_time: null, total_hours: null, employee: { name: 'Rahul Verma' } },
    { id: 2, employee_id: 2, date: today, status: 'present', check_in_time: `${today}T09:15:00`, check_out_time: `${today}T18:10:00`, total_hours: 8.9, employee: { name: 'Sneha Kulkarni' } },
    { id: 3, employee_id: 3, date: today, status: 'present', check_in_time: `${today}T08:55:00`, check_out_time: `${today}T18:00:00`, total_hours: 9.1, employee: { name: 'Anita Sharma' } },
    { id: 4, employee_id: 4, date: today, status: 'absent', check_in_time: null, check_out_time: null, total_hours: null, employee: { name: 'Kiran Rao' } },
    { id: 5, employee_id: 5, date: today, status: 'present', check_in_time: `${today}T09:30:00`, check_out_time: null, total_hours: null, employee: { name: 'Deepak Nair' } },
    { id: 6, employee_id: 6, date: today, status: 'present', check_in_time: `${today}T09:05:00`, check_out_time: `${today}T17:55:00`, total_hours: 8.8, employee: { name: 'Pooja Gupta' } },
    { id: 7, employee_id: 7, date: today, status: 'half_day', check_in_time: `${today}T09:10:00`, check_out_time: `${today}T13:00:00`, total_hours: 3.8, employee: { name: 'Amit Tiwari' } },
  ],
  meta: meta(7),
};

// ── HR — Payroll ──────────────────────────────────────────────────────────────
export const DEMO_PAYROLL_RUNS = {
  data: [
    { id: 1, month: 'May 2026', status: 'draft', employee_count: 20, total_gross: 580000, total_deductions: 58000, total_net: 522000, processed_at: null },
    { id: 2, month: 'April 2026', status: 'paid', employee_count: 20, total_gross: 575000, total_deductions: 57500, total_net: 517500, processed_at: dt(28) },
    { id: 3, month: 'March 2026', status: 'paid', employee_count: 19, total_gross: 548000, total_deductions: 54800, total_net: 493200, processed_at: dt(58) },
  ],
  meta: meta(3),
};

export const DEMO_PAYROLL_ITEMS = {
  data: [
    { id: 1, employee: { name: 'Rahul Verma' }, gross_salary: 35000, deductions: 3500, net_salary: 31500, status: 'pending' },
    { id: 2, employee: { name: 'Sneha Kulkarni' }, gross_salary: 28000, deductions: 2800, net_salary: 25200, status: 'pending' },
    { id: 3, employee: { name: 'Anita Sharma' }, gross_salary: 40000, deductions: 4000, net_salary: 36000, status: 'pending' },
    { id: 4, employee: { name: 'Kiran Rao' }, gross_salary: 25000, deductions: 2500, net_salary: 22500, status: 'pending' },
    { id: 5, employee: { name: 'Deepak Nair' }, gross_salary: 18000, deductions: 1800, net_salary: 16200, status: 'pending' },
    { id: 6, employee: { name: 'Pooja Gupta' }, gross_salary: 38000, deductions: 3800, net_salary: 34200, status: 'pending' },
    { id: 7, employee: { name: 'Amit Tiwari' }, gross_salary: 27000, deductions: 2700, net_salary: 24300, status: 'pending' },
  ],
};

// ── Admin — Users ─────────────────────────────────────────────────────────────
export const DEMO_USERS = {
  data: [
    { id: 1, name: 'Super Admin', phone: '9000000001', email: 'admin@dxempire.com', role: 'super_admin', is_active: true },
    { id: 2, name: 'Rahul Verma', phone: '9811122233', email: 'rahul@dxempire.com', role: 'warehouse_staff', is_active: true },
    { id: 3, name: 'Sneha Kulkarni', phone: '9822233344', email: 'sneha@dxempire.com', role: 'qc_engineer', is_active: true },
    { id: 4, name: 'Anita Sharma', phone: '9833344455', email: 'anita@dxempire.com', role: 'accounts', is_active: true },
    { id: 5, name: 'Kiran Rao', phone: '9844455566', email: 'kiran@dxempire.com', role: 'sales', is_active: true },
    { id: 6, name: 'Pooja Gupta', phone: '9866677788', email: 'pooja@dxempire.com', role: 'hr_manager', is_active: true },
    { id: 7, name: 'Deepak Nair', phone: '9855566677', email: 'deepak@dxempire.com', role: 'logistics', is_active: true },
    { id: 8, name: 'Ritu Mishra', phone: '9888899900', email: 'ritu@dxempire.com', role: 'sales', is_active: false },
  ],
  meta: meta(8),
};

// ── Admin — Audit Logs ────────────────────────────────────────────────────────
export const DEMO_AUDIT_LOGS = {
  data: [
    { id: 1, user: { name: 'Super Admin' }, action: 'order.approve', model_type: 'Order', model_id: 2481, new_values: { status: 'confirmed' }, created_at: dt(0) },
    { id: 2, user: { name: 'Anita Sharma' }, action: 'invoice.created', model_type: 'Invoice', model_id: 2481, new_values: { total_amount: 124000 }, created_at: dt(0) },
    { id: 3, user: { name: 'Rahul Verma' }, action: 'inventory.bin_move', model_type: 'Product', model_id: 5, new_values: { from_bin: 'A-03', to_bin: 'A-01' }, created_at: dt(1) },
    { id: 4, user: { name: 'Sneha Kulkarni' }, action: 'qc.graded', model_type: 'Product', model_id: 11, new_values: { grade: 'S2', selling_price: 42000 }, created_at: dt(1) },
    { id: 5, user: { name: 'Super Admin' }, action: 'dealer.kyc_approved', model_type: 'Dealer', model_id: 3, new_values: { kyc_status: 'approved' }, created_at: dt(2) },
    { id: 6, user: { name: 'Kiran Rao' }, action: 'lead.stage_updated', model_type: 'Lead', model_id: 4, new_values: { stage: 'negotiation' }, created_at: dt(2) },
    { id: 7, user: { name: 'Super Admin' }, action: 'user.created', model_type: 'User', model_id: 8, new_values: { name: 'Ritu Mishra', role: 'sales' }, created_at: dt(3) },
    { id: 8, user: { name: 'Pooja Gupta' }, action: 'payroll.processed', model_type: 'PayrollRun', model_id: 2, new_values: { month: 'April 2026', total_net: 517500 }, created_at: dt(28) },
  ],
  meta: meta(8),
};
