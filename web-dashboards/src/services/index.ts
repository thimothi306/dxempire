import { api } from '../lib/axios';
import {
  DEMO_MODE,
  DEMO_DASHBOARD, DEMO_REVENUE, DEMO_INVENTORY_ANALYTICS, DEMO_PARTNER_PERFORMANCE,
  DEMO_INVENTORY, DEMO_QC_QUEUE, DEMO_QC_STATS, DEMO_BINS,
  DEMO_ORDERS, DEMO_DEALERS, DEMO_LEADS,
  DEMO_SUPPLIERS, DEMO_PURCHASE_ORDERS,
  DEMO_INVOICES, DEMO_EXPENSES, DEMO_PL, DEMO_GST, DEMO_RECEIVABLES,
  DEMO_EMPLOYEES, DEMO_ATTENDANCE, DEMO_PAYROLL_RUNS, DEMO_PAYROLL_ITEMS,
  DEMO_USERS, DEMO_AUDIT_LOGS,
} from './demoData';

const mock = <T>(data: T): Promise<T> => Promise.resolve(data);

// ─── Auth ────────────────────────────────────────────────────────────────────
export const authService = {
  login: (email: string, password: string) =>
    api.post('/auth/admin/login', { email, password }).then((r) => r.data.data),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/me').then((r) => r.data.data),
};

// ─── Analytics ───────────────────────────────────────────────────────────────
export const analyticsService = {
  dashboard: () => DEMO_MODE ? mock(DEMO_DASHBOARD) : api.get('/analytics/dashboard').then((r) => r.data.data),
  revenue: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_REVENUE) : api.get('/analytics/revenue', { params }).then((r) => r.data.data),
  sales: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_REVENUE) : api.get('/analytics/sales', { params }).then((r) => r.data.data),
  inventory: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_INVENTORY_ANALYTICS) : api.get('/analytics/inventory', { params }).then((r) => r.data.data),
  partners: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_PARTNER_PERFORMANCE) : api.get('/analytics/partners', { params }).then((r) => r.data.data),
  partnerPerformance: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_PARTNER_PERFORMANCE) : api.get('/analytics/partners', { params }).then((r) => r.data),
  forecast: () => DEMO_MODE ? mock({}) : api.get('/analytics/forecast').then((r) => r.data.data),
  stockMovements: (params?: Record<string, string>) => DEMO_MODE ? mock({}) : api.get('/analytics/stock-movements', { params }).then((r) => r.data),
};

// ─── Inventory ───────────────────────────────────────────────────────────────
export const inventoryService = {
  list: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_INVENTORY) : api.get('/inventory', { params }).then((r) => r.data),
  byId: (id: number) => DEMO_MODE ? mock(DEMO_INVENTORY.data.find(p => p.id === id)) : api.get(`/inventory/${id}`).then((r) => r.data.data),
  byImei: (imei: string) => DEMO_MODE ? mock(DEMO_INVENTORY.data.find(p => p.imei === imei)) : api.get(`/inventory/imei/${imei}`).then((r) => r.data.data),
  availability: () => DEMO_MODE ? mock({}) : api.get('/inventory/availability').then((r) => r.data.data),
  export: () => api.get('/inventory/export', { responseType: 'blob' }).then((r) => r.data),
  moveBin: (productId: number, binId: number) => DEMO_MODE ? mock({}) : api.post('/bins/move', { product_id: productId, to_bin_id: binId }).then((r) => r.data),
};

// ─── Bins ────────────────────────────────────────────────────────────────────
export const binsService = {
  list: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_BINS) : api.get('/bins', { params }).then((r) => r.data),
  products: (id: number) => DEMO_MODE ? mock({ data: [] }) : api.get(`/bins/${id}/products`).then((r) => r.data),
  move: (product_id: number, to_bin_id: number, reason?: string) => DEMO_MODE ? mock({}) : api.post('/bins/move', { product_id, to_bin_id, reason }).then((r) => r.data),
  create: (data: { code: string; zone?: string; capacity?: number }) => DEMO_MODE ? mock({ id: Date.now(), ...data, current_count: 0, is_active: true }) : api.post('/bins', data).then((r) => r.data.data),
};

// ─── QC ──────────────────────────────────────────────────────────────────────
export const qcService = {
  queue: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_QC_QUEUE) : api.get('/qc/pending', { params }).then((r) => r.data),
  pending: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_QC_QUEUE) : api.get('/qc/pending', { params }).then((r) => r.data),
  stats: () => DEMO_MODE ? mock(DEMO_QC_STATS) : api.get('/qc/stats').then((r) => r.data.data),
  records: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_QC_QUEUE) : api.get('/qc/records', { params }).then((r) => r.data),
  refurbishment: () => DEMO_MODE ? mock({ data: [] }) : api.get('/qc/refurbishment').then((r) => r.data),
  sendToRefurbishment: (productId: number) => DEMO_MODE ? mock({}) : api.post(`/qc/refurbishment`, { product_id: productId }).then((r) => r.data),
  completeRefurb: (id: number) => DEMO_MODE ? mock({}) : api.put(`/qc/refurbishment/${id}`).then((r) => r.data),
  grade: (productId: number, data: { grade: string; selling_price?: number; issues?: string[] }) => DEMO_MODE ? mock({}) : api.post('/qc/grade', { product_id: productId, ...data }).then((r) => r.data.data),
};

// ─── Orders ──────────────────────────────────────────────────────────────────
export const ordersService = {
  list: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_ORDERS) : api.get('/orders', { params }).then((r) => r.data),
  get: (id: number) => DEMO_MODE ? mock({ data: DEMO_ORDERS.data.find(o => o.id === id) }) : api.get(`/orders/${id}`).then((r) => r.data),
  byId: (id: number) => DEMO_MODE ? mock(DEMO_ORDERS.data.find(o => o.id === id)) : api.get(`/orders/${id}`).then((r) => r.data.data),
  create: (data: { product_ids: number[]; dealer_id?: number; notes?: string }) => DEMO_MODE ? mock({}) : api.post('/orders', data).then((r) => r.data.data),
  approve: (id: number) => DEMO_MODE ? mock({}) : api.post(`/orders/${id}/approve`).then((r) => r.data),
  cancel: (id: number, data?: { reason?: string }) => DEMO_MODE ? mock({}) : api.post(`/orders/${id}/cancel`, data).then((r) => r.data),
  picking: (id: number) => DEMO_MODE ? mock({}) : api.post(`/orders/${id}/picking`).then((r) => r.data),
  packingComplete: (id: number) => DEMO_MODE ? mock({}) : api.post(`/orders/${id}/packing-complete`).then((r) => r.data),
  dispatch: (id: number, data: { tracking_number?: string; awb_number?: string; logistics_provider?: string }) => DEMO_MODE ? mock({}) : api.post(`/orders/${id}/dispatch`, data).then((r) => r.data),
  deliver: (id: number) => DEMO_MODE ? mock({}) : api.post(`/orders/${id}/deliver`).then((r) => r.data),
  return: (id: number) => DEMO_MODE ? mock({}) : api.post(`/orders/${id}/return`).then((r) => r.data),
  payments: (id: number) => DEMO_MODE ? mock([]) : api.get(`/orders/${id}/payments`).then((r) => r.data.data),
};

// ─── Dealers ─────────────────────────────────────────────────────────────────
export const dealersService = {
  list: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_DEALERS) : api.get('/dealers', { params }).then((r) => r.data),
  get: (id: number) => DEMO_MODE ? mock({ data: DEMO_DEALERS.data.find(d => d.id === id) }) : api.get(`/dealers/${id}`).then((r) => r.data),
  byId: (id: number) => DEMO_MODE ? mock(DEMO_DEALERS.data.find(d => d.id === id)) : api.get(`/dealers/${id}`).then((r) => r.data.data),
  create: (data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.post('/dealers', data).then((r) => r.data.data),
  updateKyc: (id: number, kyc_status: string, reason?: string) => DEMO_MODE ? mock({}) : api.put(`/dealers/${id}/kyc`, { kyc_status, reason }).then((r) => r.data),
  approveKyc: (id: number) => DEMO_MODE ? mock({}) : api.put(`/dealers/${id}/kyc`, { kyc_status: 'approved' }).then((r) => r.data),
  rejectKyc: (id: number, reason?: string) => DEMO_MODE ? mock({}) : api.put(`/dealers/${id}/kyc`, { kyc_status: 'rejected', reason }).then((r) => r.data),
  updateCredit: (id: number, data: { credit_limit: number }) => DEMO_MODE ? mock({}) : api.put(`/dealers/${id}/credit`, data).then((r) => r.data),
  ledger: (id: number, params?: Record<string, string>) => DEMO_MODE ? mock([]) : api.get(`/dealers/${id}/ledger`, { params }).then((r) => r.data.data),
};

// ─── Leads ───────────────────────────────────────────────────────────────────
export const leadsService = {
  list: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_LEADS) : api.get('/leads', { params }).then((r) => r.data),
  byId: (id: number) => DEMO_MODE ? mock(DEMO_LEADS.data.find(l => l.id === id)) : api.get(`/leads/${id}`).then((r) => r.data.data),
  create: (data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.post('/leads', data).then((r) => r.data.data),
  update: (id: number, data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.put(`/leads/${id}`, data).then((r) => r.data.data),
  updateStage: (id: number, data: { stage: string }) => DEMO_MODE ? mock({}) : api.put(`/leads/${id}/stage`, data).then((r) => r.data),
  convert: (id: number) => DEMO_MODE ? mock({}) : api.post(`/leads/${id}/convert`).then((r) => r.data),
};

// ─── Finance ─────────────────────────────────────────────────────────────────
export const financeService = {
  invoices: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_INVOICES) : api.get('/finance/invoices', { params }).then((r) => r.data),
  invoiceById: (id: number) => DEMO_MODE ? mock(DEMO_INVOICES.data.find(i => i.id === id)) : api.get(`/finance/invoices/${id}`).then((r) => r.data.data),
  generateInvoice: (orderId: number) => DEMO_MODE ? mock({}) : api.post(`/finance/invoices/orders/${orderId}`).then((r) => r.data.data),
  downloadInvoice: (id: number) => api.get(`/finance/invoices/${id}/download`, { responseType: 'blob' }).then((r) => r.data),
  expenses: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_EXPENSES) : api.get('/finance/expenses', { params }).then((r) => r.data),
  expenseCategories: () => DEMO_MODE ? mock(['Logistics', 'Office', 'Utilities', 'Marketing', 'Repairs', 'Other']) : api.get('/finance/expenses/categories').then((r) => r.data.data),
  createExpense: (data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.post('/finance/expenses', data).then((r) => r.data.data),
  deleteExpense: (id: number) => DEMO_MODE ? mock({}) : api.delete(`/finance/expenses/${id}`),
  pl: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_PL) : api.get('/finance/profit-loss', { params }).then((r) => r.data.data),
  profitLoss: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_PL) : api.get('/finance/profit-loss', { params }).then((r) => r.data.data),
  gstReport: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_GST) : api.get('/finance/gst-summary', { params }).then((r) => r.data.data),
  gstSummary: (year?: number) => DEMO_MODE ? mock(DEMO_GST) : api.get('/finance/gst-summary', { params: year ? { year: String(year) } : {} }).then((r) => r.data.data),
  exportGST: (params?: Record<string, string>) => api.get('/finance/gst-export', { params, responseType: 'blob' }).then((r) => r.data),
  receivables: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_RECEIVABLES) : api.get('/finance/receivables', { params }).then((r) => r.data),
  recordPayment: (invoiceId: number, data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.post(`/finance/invoices/${invoiceId}/payment`, data).then((r) => r.data),
  dealerLedger: (id: number, params?: Record<string, string>) => DEMO_MODE ? mock([]) : api.get(`/finance/dealers/${id}/ledger`, { params }).then((r) => r.data.data),
};

// ─── HR ──────────────────────────────────────────────────────────────────────
export const hrService = {
  employees: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_EMPLOYEES) : api.get('/hr/employees', { params }).then((r) => r.data),
  departments: () => DEMO_MODE ? mock(['Warehouse', 'QC', 'Sales', 'Accounts', 'HR']) : api.get('/hr/employees/departments').then((r) => r.data.data),
  employeeById: (id: number) => DEMO_MODE ? mock(DEMO_EMPLOYEES.data.find(e => e.id === id)) : api.get(`/hr/employees/${id}`).then((r) => r.data.data),
  createEmployee: (data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.post('/hr/employees', data).then((r) => r.data.data),
  updateEmployee: (id: number, data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.put(`/hr/employees/${id}`, data).then((r) => r.data.data),
  deleteEmployee: (id: number) => DEMO_MODE ? mock({}) : api.delete(`/hr/employees/${id}`),
  attendance: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_ATTENDANCE) : api.get('/hr/attendance', { params }).then((r) => r.data),
  attendanceToday: () => DEMO_MODE ? mock(DEMO_ATTENDANCE.data) : api.get('/hr/attendance/today').then((r) => r.data.data),
  attendanceBulk: (records: unknown[]) => DEMO_MODE ? mock({}) : api.post('/hr/attendance/bulk', { records }).then((r) => r.data),
  checkIn: (employeeId: number) => DEMO_MODE ? mock({}) : api.post('/hr/attendance/check-in', { employee_id: employeeId }).then((r) => r.data),
  checkOut: (employeeId: number) => DEMO_MODE ? mock({}) : api.post('/hr/attendance/check-out', { employee_id: employeeId }).then((r) => r.data),
  payrollRuns: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_PAYROLL_RUNS) : api.get('/hr/payroll', { params }).then((r) => r.data),
  payrollList: (year?: number) => DEMO_MODE ? mock(DEMO_PAYROLL_RUNS.data) : api.get('/hr/payroll', { params: year ? { year: String(year) } : {} }).then((r) => r.data.data),
  createPayroll: (month: number, year: number) => DEMO_MODE ? mock({}) : api.post('/hr/payroll', { month, year }).then((r) => r.data.data),
  processPayroll: (data: { month: string }) => DEMO_MODE ? mock({}) : api.post('/hr/payroll/process', data).then((r) => r.data.data),
  payrollById: (id: number) => DEMO_MODE ? mock(DEMO_PAYROLL_RUNS.data.find(r => r.id === id)) : api.get(`/hr/payroll/${id}`).then((r) => r.data.data),
  payrollItems: (id: number) => DEMO_MODE ? mock(DEMO_PAYROLL_ITEMS) : api.get(`/hr/payroll/${id}/items`).then((r) => r.data),
  markPaid: (id: number) => DEMO_MODE ? mock({}) : api.post(`/hr/payroll/${id}/mark-paid`).then((r) => r.data),
};

// ─── Admin ───────────────────────────────────────────────────────────────────
export const adminService = {
  users: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_USERS) : api.get('/admin/users', { params }).then((r) => r.data),
  userById: (id: number) => DEMO_MODE ? mock(DEMO_USERS.data.find(u => u.id === id)) : api.get(`/admin/users/${id}`).then((r) => r.data.data),
  createUser: (data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.post('/admin/users', data).then((r) => r.data.data),
  updateUser: (id: number, data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.put(`/admin/users/${id}`, data).then((r) => r.data.data),
  changeRole: (id: number, role: string) => DEMO_MODE ? mock({}) : api.put(`/admin/users/${id}/role`, { role }).then((r) => r.data),
  deactivate: (id: number) => DEMO_MODE ? mock({}) : api.post(`/admin/users/${id}/deactivate`).then((r) => r.data),
  activate: (id: number) => DEMO_MODE ? mock({}) : api.post(`/admin/users/${id}/activate`).then((r) => r.data),
  roles: () => DEMO_MODE ? mock(['super_admin', 'warehouse_staff', 'qc_engineer', 'sales', 'accounts', 'hr_manager', 'logistics']) : api.get('/admin/roles').then((r) => r.data.data),
  auditLogs: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_AUDIT_LOGS) : api.get('/admin/audit-logs', { params }).then((r) => r.data),
  settings: () => DEMO_MODE ? mock({ company_name: 'DXEMPIRE', gst_number: '27AABCD1234E1ZB', address: 'Mumbai, Maharashtra', phone: '9000000000', email: 'admin@dxempire.com' }) : api.get('/admin/settings').then((r) => r.data.data),
  updateSettings: (data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.put('/admin/settings', data).then((r) => r.data),
  updateSetting: (key: string, value: string) => DEMO_MODE ? mock({}) : api.put(`/admin/settings/${key}`, { value }).then((r) => r.data),
};

// ─── Support Tickets ─────────────────────────────────────────────────────────
export const supportService = {
  list: (params?: Record<string, string>) => api.get('/support/tickets', { params }).then((r) => r.data),
  create: (data: Record<string, unknown>) => api.post('/support/tickets', data).then((r) => r.data.data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/support/tickets/${id}`, data).then((r) => r.data),
};

// ─── Notifications ───────────────────────────────────────────────────────────
export const notificationService = {
  list: (params?: Record<string, string>) => api.get('/notifications', { params }).then((r) => r.data),
  unreadCount: () => api.get('/notifications/unread-count').then((r) => r.data.data),
  markRead: (id: number) => api.patch(`/notifications/${id}`).then((r) => r.data),
  markAllRead: () => api.patch('/notifications/read-all').then((r) => r.data),
};

// ─── Logistics ────────────────────────────────────────────────────────────────
export const logisticsService = {
  track: (awb: string) => api.get(`/logistics/track/${awb}`).then((r) => r.data.data),
};

// ─── Retail Customers (Admin view) ───────────────────────────────────────────
export const retailCustomerService = {
  list: (params?: Record<string, string>) => api.get('/customers', { params }).then((r) => r.data),
  show: (id: number) => api.get(`/customers/${id}`).then((r) => r.data.data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/customers/${id}`, data).then((r) => r.data),
};

// ─── Procurement ─────────────────────────────────────────────────────────────
export const procurementService = {
  suppliers: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_SUPPLIERS) : api.get('/suppliers', { params }).then((r) => r.data),
  createSupplier: (data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.post('/suppliers', data).then((r) => r.data.data),
  updateSupplier: (id: number, data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.put(`/suppliers/${id}`, data).then((r) => r.data.data),
  deleteSupplier: (id: number) => DEMO_MODE ? mock({}) : api.delete(`/suppliers/${id}`),
  purchaseOrders: (params?: Record<string, string>) => DEMO_MODE ? mock(DEMO_PURCHASE_ORDERS) : api.get('/purchase-orders', { params }).then((r) => r.data),
  createPurchaseOrder: (data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.post('/purchase-orders', data).then((r) => r.data.data),
  purchaseOrderById: (id: number) => DEMO_MODE ? mock(DEMO_PURCHASE_ORDERS.data.find(p => p.id === id)) : api.get(`/purchase-orders/${id}`).then((r) => r.data.data),
  receivePO: (id: number, data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.post(`/purchase-orders/${id}/receive`, data).then((r) => r.data),
  receive: (data: Record<string, unknown>) => DEMO_MODE ? mock({}) : api.post('/procurement/receive', data).then((r) => r.data),
  history: () => DEMO_MODE ? mock({ data: [] }) : api.get('/procurement/history').then((r) => r.data),
};

// ─── Catalog Images (partner catalog photos) ─────────────────────────────────
export const catalogImageService = {
  list: () => api.get('/admin/catalog-images').then((r) => r.data.data),
  upload: (formData: FormData) =>
    api.post('/admin/catalog-images/upload', formData, { headers: { 'Content-Type': 'multipart/form-data' } }).then((r) => r.data.data),
  remove: (id: number) => api.delete(`/admin/catalog-images/${id}`),
};
