export type Role =
  | 'super_admin' | 'sales' | 'warehouse_staff'
  | 'qc_engineer' | 'accounts' | 'hr_manager' | 'b2b_partner' | 'logistics';

export interface User {
  id: number;
  name: string;
  phone: string;
  email: string | null;
  role: Role;
  partner_id: number | null;
  kyc_status: string | null;
  is_active: boolean;
  permissions: string[];
}

export interface PaginationMeta {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message: string;
}

export interface PaginatedResponse<T> {
  success: boolean;
  data: T[];
  meta: PaginationMeta;
}

// --- Inventory ---
export interface Product {
  id: number;
  imei: string;
  brand: string;
  model: string;
  category: 'phone' | 'laptop';
  grade: string;
  status: string;
  selling_price: number;
  retail_price?: number;
  cost_price?: number;
  return_count?: number;
  bin: { id: number; code: string } | null;
  supplier: { id: number; name: string } | null;
  created_at: string;
}

export interface Bin {
  id: number;
  code: string;
  name?: string;
  zone?: string;
  capacity?: number;
  occupied?: number;
  current_count?: number;
  is_active?: boolean;
}

// --- Orders ---
export type OrderStatus =
  | 'pending' | 'approved' | 'picking' | 'packed'
  | 'dispatched' | 'delivered' | 'cancelled' | 'returned'
  | 'return_requested';

export interface Order {
  id: number;
  order_number: string;
  status: OrderStatus;
  payment_status: 'unpaid' | 'partial' | 'paid' | 'refunded';
  subtotal: number;
  gst_amount: number;
  total_amount: number;
  notes: string | null;
  tracking_number?: string | null;
  dealer_id: number | null;
  dealer?: { id: number; business_name: string };
  items: OrderItem[];
  items_count?: number;
  created_at: string;
}

export interface OrderItem {
  id?: number;
  product_id: number;
  product?: { brand: string; model: string; imei: string };
  brand: string;
  model: string;
  grade: string;
  unit_price: number;
  gst_amount: number;
  line_total: number;
}

// --- Dealers ---
export type KycStatus = 'pending' | 'approved' | 'verified' | 'rejected';

export interface Dealer {
  id: number;
  business_name: string;
  owner_name?: string;
  gst_number?: string;
  state?: string;
  city?: string;
  pincode?: string;
  phone?: string;
  email?: string;
  kyc_status: KycStatus;
  price_tier?: string;
  credit_limit?: number;
  credit_used?: number;
  outstanding_balance?: number;
  available_credit?: number;
  orders_count?: number;
  created_at?: string;
  user?: { id: number; name: string; phone: string };
}

// --- Leads ---
export type LeadStage = 'new' | 'contacted' | 'qualified' | 'proposal' | 'negotiation' | 'won' | 'lost';

export interface Lead {
  id: number;
  name?: string;
  phone: string;
  business_name: string;
  contact_name?: string;
  email?: string;
  city?: string;
  source?: string;
  stage: LeadStage;
  notes: string | null;
  assigned_to?: string | null;
  created_at: string;
  updated_at: string;
}

// --- Analytics ---
export interface DashboardStats {
  today_revenue: number;
  week_revenue: number;
  month_revenue: number;
  active_orders: number;
  pending_qc: number;
  pending_dispatch: number;
  in_refurbishment: number;
  total_in_stock: number;
}

// --- Finance ---
export interface Invoice {
  id: number;
  invoice_number: string;
  order_id: number;
  order_number?: string;
  order?: { dealer?: { business_name: string } };
  dealer_name?: string;
  total?: number;
  total_amount?: number;
  subtotal?: number;
  gst_amount: number;
  cgst_amount?: number;
  sgst_amount?: number;
  igst_amount?: number;
  tax_type?: 'intra' | 'inter';
  billing_state?: string;
  status: string;
  due_date?: string;
  created_at: string;
}

export interface Expense {
  id: number;
  category: string;
  amount: number;
  description: string;
  date?: string;
  incurred_at?: string;
  vendor?: string;
  recorded_by?: { id: number; name: string };
  created_at: string;
}

// --- HR ---
export interface Employee {
  id: number;
  name?: string;
  phone?: string;
  email?: string;
  employee_code?: string;
  department: string;
  designation?: string;
  employment_type?: string;
  shift?: string;
  salary?: number;
  basic_salary?: number;
  joining_date?: string;
  is_active?: boolean;
  user?: { id: number; name: string; phone: string; email: string | null; role: Role };
}

export interface AttendanceRecord {
  id?: number;
  employee_id: number;
  name?: string;
  department?: string;
  shift?: string;
  date?: string;
  status?: 'present' | 'absent' | 'late' | 'half_day' | 'holiday' | 'leave';
  check_in_time?: string | null;
  check_out_time?: string | null;
  total_hours?: number;
  employee?: { id: number; name: string };
  attendance?: {
    status: 'present' | 'absent' | 'half_day' | 'leave';
    check_in: string | null;
    check_out: string | null;
  } | null;
}

export interface PayrollRun {
  id: number;
  month: string | number;
  year?: number;
  status: 'draft' | 'processing' | 'completed' | 'processed' | 'paid' | 'failed';
  total_payout?: number;
  total_gross?: number;
  total_deductions?: number;
  total_net?: number;
  employee_count?: number;
  processed_at?: string;
  created_at: string;
}

export interface PayrollItem {
  id: number;
  employee_id: number;
  employee?: { id: number; name: string };
  emp_code?: string;
  name?: string;
  department?: string;
  days_worked?: number;
  basic?: number;
  gross_salary?: number;
  deductions?: number;
  net_salary: number;
  status?: string;
}

// --- QC ---
export interface QcStats {
  total?: number;
  pass_count?: number;
  repair_count?: number;
  reject_count?: number;
  pass_rate?: number;
  today?: number;
  this_week?: number;
  pending_qc?: number;
  graded_today?: number;
  in_refurbishment?: number;
  rejected_today?: number;
}

// --- Notifications ---
export interface AppNotification {
  id: number;
  title: string;
  body: string;
  type: string;
  data?: Record<string, unknown>;
  is_read: boolean;
  created_at: string;
}

// --- Retail Customer ---
export interface RetailCustomer {
  id: number;
  name: string;
  phone: string;
  email?: string | null;
  address?: string | null;
  city?: string | null;
  state?: string | null;
  is_active: boolean;
  orders_count?: number;
  created_at: string;
}

// --- Audit ---
export interface AuditLog {
  id: number;
  user: { id: number; name: string; phone: string };
  action: string;
  model_type: string;
  model_id: number;
  old_values: Record<string, unknown>;
  new_values: Record<string, unknown>;
  created_at: string;
}
