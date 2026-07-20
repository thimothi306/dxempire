import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'react-hot-toast';
import { queryClient } from './lib/queryClient';
import { useAuthStore } from './stores/authStore';

import { AppLayout } from './components/layout/AppLayout';
import LoginPage from './pages/auth/LoginPage';
import DashboardPage from './pages/dashboard/DashboardPage';
import InventoryPage from './pages/inventory/InventoryPage';
import QCPage from './pages/qc/QCPage';
import BinsPage from './pages/bins/BinsPage';
import ProcurementPage from './pages/procurement/ProcurementPage';
import OrdersPage from './pages/orders/OrdersPage';
import DealersPage from './pages/dealers/DealersPage';
import LeadsPage from './pages/leads/LeadsPage';
import InvoicesPage from './pages/finance/InvoicesPage';
import ExpensesPage from './pages/finance/ExpensesPage';
import PLPage from './pages/finance/PLPage';
import GSTPage from './pages/finance/GSTPage';
import ReceivablesPage from './pages/finance/ReceivablesPage';
import EmployeesPage from './pages/hr/EmployeesPage';
import AttendancePage from './pages/hr/AttendancePage';
import PayrollPage from './pages/hr/PayrollPage';
import AnalyticsPage from './pages/analytics/AnalyticsPage';
import UsersPage from './pages/users/UsersPage';
import AuditPage from './pages/audit/AuditPage';
import SettingsPage from './pages/settings/SettingsPage';
import HierarchyPage from './pages/hierarchy/HierarchyPage';
import OffersPage from './pages/offers/OffersPage';
import PetiPage from './pages/peti/PetiPage';
import RetailCustomersPage from './pages/customers/RetailCustomersPage';
import SupportPage from './pages/support/SupportPage';

function RequireAuth({ children }: { children: React.ReactNode }) {
  const token = useAuthStore((s) => s.token);
  return token ? <>{children}</> : <Navigate to="/login" replace />;
}

const BASE_PATH = import.meta.env.VITE_BASE_URL?.replace(/\/$/, '') || '';

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter basename={BASE_PATH}>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route
            path="/"
            element={
              <RequireAuth>
                <AppLayout />
              </RequireAuth>
            }
          >
            <Route index element={<Navigate to="/dashboard" replace />} />
            <Route path="dashboard" element={<DashboardPage />} />

            {/* Warehouse */}
            <Route path="inventory"   element={<InventoryPage />} />
            <Route path="qc"          element={<QCPage />} />
            <Route path="bins"        element={<BinsPage />} />
            <Route path="procurement" element={<ProcurementPage />} />
            <Route path="peti"        element={<PetiPage />} />

            {/* Sales / CRM */}
            <Route path="orders"    element={<OrdersPage />} />
            <Route path="dealers"   element={<DealersPage />} />
            <Route path="leads"     element={<LeadsPage />} />
            <Route path="hierarchy"         element={<HierarchyPage />} />
            <Route path="offers"            element={<OffersPage />} />
            <Route path="retail-customers"  element={<RetailCustomersPage />} />
            <Route path="support"           element={<SupportPage />} />

            {/* Finance */}
            <Route path="invoices"    element={<InvoicesPage />} />
            <Route path="expenses"    element={<ExpensesPage />} />
            <Route path="pl"          element={<PLPage />} />
            <Route path="gst"         element={<GSTPage />} />
            <Route path="receivables" element={<ReceivablesPage />} />

            {/* HR */}
            <Route path="employees"  element={<EmployeesPage />} />
            <Route path="attendance" element={<AttendancePage />} />
            <Route path="payroll"    element={<PayrollPage />} />

            {/* Analytics */}
            <Route path="analytics" element={<AnalyticsPage />} />

            {/* Super Admin */}
            <Route path="users"    element={<UsersPage />} />
            <Route path="audit"    element={<AuditPage />} />
            <Route path="settings" element={<SettingsPage />} />

            <Route path="*" element={<Navigate to="/dashboard" replace />} />
          </Route>
        </Routes>
      </BrowserRouter>
      <Toaster position="top-right" toastOptions={{ duration: 3000, style: { fontSize: '14px' } }} />
    </QueryClientProvider>
  );
}
