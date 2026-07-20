import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  ShoppingCart, Package, Clock, Wrench, TrendingUp, BarChart3, IndianRupee,
  Boxes, Users, CalendarCheck, Wallet, ClipboardList, TrendingDown, AlertCircle,
  Archive, ClipboardCheck, Building2, UserPlus, FileText, Receipt,
  BadgeDollarSign, Landmark, Banknote,
} from 'lucide-react';
import { StatCard, Card, fmtINR, PageHeader, Spinner } from '../../components/ui';
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
  CartesianGrid, PieChart, Pie, Cell, Legend,
} from 'recharts';
import { useAuthStore } from '../../stores/authStore';
import {
  analyticsService, inventoryService, ordersService,
  qcService, hrService, financeService,
} from '../../services';
import type { Order } from '../../types';

// ── Constants ─────────────────────────────────────────────────────────────────
const GRADE_COLORS: Record<string, string> = {
  S1: '#22c55e', S2: '#3b82f6', S3: '#f59e0b', S4: '#ef4444', S5: '#6b7280',
};
const GRADES = ['S1', 'S2', 'S3', 'S4', 'S5'] as const;

const STATUS_COLOR: Record<string, string> = {
  approved:   'bg-blue-100 text-blue-700',
  dispatched: 'bg-orange-100 text-orange-700',
  pending:    'bg-yellow-100 text-yellow-700',
  delivered:  'bg-green-100 text-green-700',
  cancelled:  'bg-red-100 text-red-700',
  picking:    'bg-purple-100 text-purple-700',
  packed:     'bg-indigo-100 text-indigo-700',
};

function nDaysAgo(n: number) {
  const d = new Date(); d.setDate(d.getDate() - n);
  return d.toISOString().slice(0, 10);
}
function nMonthsAgo(n: number) {
  const d = new Date(); d.setMonth(d.getMonth() - n);
  return d.toISOString().slice(0, 10);
}
function today() { return new Date().toISOString().slice(0, 10); }
function startOfMonth() {
  const d = new Date(); d.setDate(1);
  return d.toISOString().slice(0, 10);
}
function periodLabel(p: string) {
  // '2026-05' → 'May' | '2026-05-28' → '05-28'
  if (/^\d{4}-\d{2}$/.test(p)) {
    return new Date(p + '-01').toLocaleString('default', { month: 'short' });
  }
  return p.slice(5);
}

// ── Shared hooks ──────────────────────────────────────────────────────────────
function useInventoryGrade() {
  const { data } = useQuery({
    queryKey: ['inventory-availability'],
    queryFn: inventoryService.availability,
    staleTime: 60_000,
  });
  if (!data) return [];
  return GRADES.map((g) => ({
    name: g,
    value:
      (data.phones?.[g] ?? 0) +
      (data.laptops?.[g] ?? 0) +
      (data.accessories?.[g] ?? 0),
    color: GRADE_COLORS[g],
  })).filter((g) => g.value > 0);
}

// ── Reusable helpers ──────────────────────────────────────────────────────────
function QuickLink({ to, icon, label }: { to: string; icon: React.ReactNode; label: string }) {
  return (
    <Link
      to={to}
      className="flex flex-col items-center gap-2 py-4 px-2 rounded-xl border border-gray-200 hover:border-primary hover:text-primary text-gray-600 text-sm font-medium transition-colors"
    >
      {icon}
      {label}
    </Link>
  );
}

function GradePieChart({ height = 160 }: { height?: number }) {
  const gradeData = useInventoryGrade();
  if (!gradeData.length) return <div className="flex items-center justify-center h-32 text-sm text-gray-400">No stock data</div>;
  return (
    <>
      <ResponsiveContainer width="100%" height={height}>
        <PieChart>
          <Pie
            data={gradeData}
            dataKey="value"
            nameKey="name"
            cx="50%"
            cy="50%"
            outerRadius={height / 2 - 10}
            label={({ name, value }) => `${name}: ${value}`}
            labelLine={false}
          >
            {gradeData.map((e, i) => <Cell key={i} fill={e.color} />)}
          </Pie>
          <Tooltip />
        </PieChart>
      </ResponsiveContainer>
      <div className="flex flex-wrap gap-2 mt-2 justify-center">
        {gradeData.map((g) => (
          <span key={g.name} className="flex items-center gap-1 text-xs text-gray-600">
            <span className="w-2 h-2 rounded-full inline-block" style={{ background: g.color }} />{g.name}
          </span>
        ))}
      </div>
    </>
  );
}

function RecentOrdersTable({ orders, showItems }: { orders: Order[]; showItems?: boolean }) {
  return (
    <table className="w-full text-sm">
      <thead>
        <tr className="text-xs text-gray-400 border-b border-gray-100">
          <th className="text-left pb-2">Order #</th>
          <th className="text-left pb-2">Dealer</th>
          {showItems && <th className="text-left pb-2">Items</th>}
          <th className="text-left pb-2">Amount</th>
          <th className="text-left pb-2">Status</th>
        </tr>
      </thead>
      <tbody className="divide-y divide-gray-50">
        {orders.map((o) => (
          <tr key={o.id}>
            <td className="py-2.5 font-medium">{o.order_number}</td>
            <td className="py-2.5 text-gray-600">{o.dealer?.business_name ?? '—'}</td>
            {showItems && <td className="py-2.5 text-gray-500">{o.items_count ?? o.items?.length ?? '—'} units</td>}
            <td className="py-2.5 font-semibold">{fmtINR(o.total_amount)}</td>
            <td className="py-2.5">
              <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLOR[o.status] ?? 'bg-gray-100 text-gray-700'}`}>
                {o.status}
              </span>
            </td>
          </tr>
        ))}
        {orders.length === 0 && (
          <tr><td colSpan={showItems ? 5 : 4} className="py-4 text-center text-gray-400 text-xs">No orders found</td></tr>
        )}
      </tbody>
    </table>
  );
}

// ── Super Admin Dashboard ─────────────────────────────────────────────────────
function AdminDashboard() {
  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['analytics-dashboard'],
    queryFn: analyticsService.dashboard,
    staleTime: 60_000,
  });

  const { data: dailyRevData, isLoading: dailyLoading } = useQuery({
    queryKey: ['analytics-revenue-daily'],
    queryFn: () => analyticsService.revenue({ period: 'daily', from: nDaysAgo(14), to: today() }),
    staleTime: 120_000,
  });

  const { data: monthlyRevData } = useQuery({
    queryKey: ['analytics-revenue-monthly'],
    queryFn: () => analyticsService.revenue({ period: 'monthly', from: nMonthsAgo(6), to: today() }),
    staleTime: 300_000,
  });

  const { data: ordersData } = useQuery({
    queryKey: ['orders-recent'],
    queryFn: () => ordersService.list({ per_page: '5' }),
    staleTime: 30_000,
  });

  const dailySeries = (dailyRevData?.time_series ?? []).map((r: any) => ({
    period: r.period,
    revenue: Number(r.revenue ?? 0),
  }));

  const monthlySeries = (monthlyRevData?.time_series ?? []).map((r: any) => ({
    month: periodLabel(String(r.period)),
    revenue: Number(r.revenue ?? 0),
  }));

  const topProducts: any[] = dailyRevData?.top_products ?? [];
  const topDealers: any[] = dailyRevData?.top_dealers ?? [];
  const recentOrders: Order[] = ordersData?.data ?? [];

  return (
    <div>
      {statsLoading ? <Spinner /> : (
        <>
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <StatCard label="Today Revenue"  value={fmtINR(stats?.today_revenue ?? 0)}  icon={<IndianRupee size={28} />} color="text-green-600" />
            <StatCard label="Week Revenue"   value={fmtINR(stats?.week_revenue ?? 0)}   icon={<TrendingUp size={28} />}  color="text-blue-600" />
            <StatCard label="Month Revenue"  value={fmtINR(stats?.month_revenue ?? 0)}  icon={<BarChart3 size={28} />}   color="text-purple-600" />
            <StatCard label="Active Orders"  value={stats?.active_orders ?? 0}           icon={<ShoppingCart size={28} />} color="text-primary" />
          </div>
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard label="In Stock"         value={stats?.total_in_stock ?? 0}    icon={<Package size={28} />}  color="text-green-600" />
            <StatCard label="Pending QC"       value={stats?.pending_qc ?? 0}        icon={<Clock size={28} />}    color="text-yellow-600" />
            <StatCard label="Pending Dispatch" value={stats?.pending_dispatch ?? 0}  icon={<Boxes size={28} />}    color="text-orange-600" />
            <StatCard label="Refurbishment"    value={stats?.in_refurbishment ?? 0}  icon={<Wrench size={28} />}   color="text-red-500" />
          </div>
        </>
      )}

      <Card className="p-5 mb-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-4">Daily Revenue — Last 14 Days</h3>
        {dailyLoading ? <Spinner /> : (
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={dailySeries}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey="period" tick={{ fontSize: 11 }} tickFormatter={(v) => v.slice(5)} />
              <YAxis tick={{ fontSize: 11 }} tickFormatter={(v) => `₹${(v / 1000).toFixed(0)}k`} />
              <Tooltip formatter={(v: any) => fmtINR(Number(v))} />
              <Bar dataKey="revenue" fill="#E8593C" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        )}
      </Card>

      <Card className="p-5 mb-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-4">Monthly Revenue — Last 6 Months</h3>
        <ResponsiveContainer width="100%" height={200}>
          <BarChart data={monthlySeries} barCategoryGap="30%">
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis dataKey="month" tick={{ fontSize: 11 }} />
            <YAxis tick={{ fontSize: 11 }} tickFormatter={(v) => `₹${(v / 100000).toFixed(1)}L`} />
            <Tooltip formatter={(v: any) => fmtINR(Number(v))} />
            <Bar dataKey="revenue" name="Revenue" fill="#E8593C" radius={[4, 4, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </Card>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        <Card className="p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Top Products</h3>
          <div className="space-y-3">
            {topProducts.length === 0 && <p className="text-xs text-gray-400">No data</p>}
            {topProducts.slice(0, 5).map((p, i) => (
              <div key={i} className="flex justify-between items-center text-sm">
                <div>
                  <div className="font-medium text-gray-800">{p.brand} {p.model}</div>
                  <div className="text-xs text-gray-400">Grade {p.grade}</div>
                </div>
                <div className="text-right">
                  <div className="font-semibold">{fmtINR(Number(p.revenue))}</div>
                  <div className="text-xs text-gray-400">{p.units_sold} units</div>
                </div>
              </div>
            ))}
          </div>
        </Card>

        <Card className="p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Top Dealers</h3>
          <div className="space-y-3">
            {topDealers.length === 0 && <p className="text-xs text-gray-400">No data</p>}
            {topDealers.slice(0, 5).map((d, i) => (
              <div key={i} className="flex justify-between items-center text-sm">
                <span className="font-medium text-gray-800">{d.business_name}</span>
                <div className="text-right">
                  <div className="font-semibold">{fmtINR(Number(d.revenue))}</div>
                  <div className="text-xs text-gray-400">{d.order_count} orders</div>
                </div>
              </div>
            ))}
          </div>
        </Card>

        <Card className="p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Inventory by Grade</h3>
          <GradePieChart height={160} />
        </Card>
      </div>

      <Card className="p-5 mb-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-4">Recent Orders</h3>
        <RecentOrdersTable orders={recentOrders} showItems />
      </Card>

      <Card className="p-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-3">Quick Links</h3>
        <div className="grid grid-cols-4 lg:grid-cols-8 gap-3">
          <QuickLink to="/orders"    icon={<ShoppingCart size={20} />}   label="Orders" />
          <QuickLink to="/inventory" icon={<Package size={20} />}        label="Inventory" />
          <QuickLink to="/qc"        icon={<ClipboardCheck size={20} />} label="QC" />
          <QuickLink to="/dealers"   icon={<Building2 size={20} />}      label="Business Partners" />
          <QuickLink to="/invoices"  icon={<FileText size={20} />}       label="Invoices" />
          <QuickLink to="/employees" icon={<Users size={20} />}          label="HR" />
          <QuickLink to="/analytics" icon={<BarChart3 size={20} />}      label="Analytics" />
          <QuickLink to="/users"     icon={<Users size={20} />}          label="Users" />
        </div>
      </Card>
    </div>
  );
}

// ── Warehouse Dashboard ───────────────────────────────────────────────────────
function WarehouseDashboard() {
  // warehouse_staff has no access to /analytics/dashboard; use qc stats + availability instead
  const { data: qcStats, isLoading } = useQuery({
    queryKey: ['qc-stats'],
    queryFn: qcService.stats,
    staleTime: 60_000,
  });

  const { data: avail } = useQuery({
    queryKey: ['inventory-availability'],
    queryFn: inventoryService.availability,
    staleTime: 60_000,
  });

  const totalInStock = avail
    ? (avail.phones?.total ?? 0) + (avail.laptops?.total ?? 0) + (avail.accessories?.total ?? 0)
    : 0;

  return (
    <div>
      {isLoading ? <Spinner /> : (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <StatCard label="In Stock"         value={totalInStock}                      icon={<Package size={28} />}  color="text-green-600" />
          <StatCard label="Pending QC"       value={qcStats?.pending_qc ?? 0}          icon={<Clock size={28} />}    color="text-yellow-600" />
          <StatCard label="Graded Today"     value={qcStats?.graded_today ?? 0}        icon={<Boxes size={28} />}    color="text-orange-600" />
          <StatCard label="Refurbishment"    value={qcStats?.in_refurbishment ?? 0}    icon={<Wrench size={28} />}   color="text-red-500" />
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <Card className="p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Inventory by Grade</h3>
          <GradePieChart height={180} />
        </Card>

        <Card className="p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Quick Links</h3>
          <div className="grid grid-cols-2 gap-3">
            <QuickLink to="/inventory"   icon={<Package size={20} />}        label="Inventory" />
            <QuickLink to="/qc"          icon={<ClipboardCheck size={20} />} label="QC Queue" />
            <QuickLink to="/bins"        icon={<Boxes size={20} />}           label="Bins" />
            <QuickLink to="/procurement" icon={<Archive size={20} />}         label="Procurement" />
          </div>
        </Card>
      </div>
    </div>
  );
}

// ── QC Engineer Dashboard ─────────────────────────────────────────────────────
function QCDashboard() {
  const { data: stats, isLoading } = useQuery({
    queryKey: ['qc-stats'],
    queryFn: qcService.stats,
    staleTime: 30_000,
  });

  const { data: avail } = useQuery({
    queryKey: ['inventory-availability'],
    queryFn: inventoryService.availability,
    staleTime: 60_000,
  });

  const totalInStock = avail
    ? (avail.phones?.total ?? 0) + (avail.laptops?.total ?? 0) + (avail.accessories?.total ?? 0)
    : 0;

  return (
    <div>
      {isLoading ? <Spinner /> : (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <StatCard label="In Stock"      value={totalInStock}                  icon={<Package size={28} />}        color="text-green-600" />
          <StatCard label="Pending QC"    value={stats?.pending_qc ?? 0}        icon={<Clock size={28} />}          color="text-yellow-600" />
          <StatCard label="Refurbishment" value={stats?.in_refurbishment ?? 0}  icon={<Wrench size={28} />}         color="text-red-500" />
          <StatCard label="Graded Today"  value={stats?.graded_today ?? 0}      icon={<ClipboardCheck size={28} />} color="text-blue-600" />
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <Card className="p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Inventory by Grade</h3>
          <GradePieChart height={180} />
        </Card>

        <Card className="p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Quick Links</h3>
          <div className="grid grid-cols-2 gap-3">
            <QuickLink to="/qc"        icon={<ClipboardCheck size={20} />} label="QC Queue" />
            <QuickLink to="/inventory" icon={<Package size={20} />}        label="Inventory" />
            <QuickLink to="/bins"      icon={<Boxes size={20} />}           label="Bins" />
            <QuickLink to="/procurement" icon={<Archive size={20} />}      label="Procurement" />
          </div>
        </Card>
      </div>
    </div>
  );
}

// ── Sales Dashboard ───────────────────────────────────────────────────────────
function SalesDashboard() {
  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['analytics-dashboard'],
    queryFn: analyticsService.dashboard,
    staleTime: 60_000,
  });

  const { data: ordersData } = useQuery({
    queryKey: ['orders-recent'],
    queryFn: () => ordersService.list({ per_page: '5' }),
    staleTime: 30_000,
  });

  const recentOrders: Order[] = ordersData?.data ?? [];

  return (
    <div>
      {statsLoading ? <Spinner /> : (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <StatCard label="Active Orders"    value={stats?.active_orders ?? 0}     icon={<ShoppingCart size={28} />} color="text-primary" />
          <StatCard label="Pending Dispatch" value={stats?.pending_dispatch ?? 0}  icon={<Boxes size={28} />}        color="text-orange-600" />
          <StatCard label="In Stock"         value={stats?.total_in_stock ?? 0}    icon={<Package size={28} />}       color="text-green-600" />
          <StatCard label="Month Revenue"    value={fmtINR(stats?.month_revenue ?? 0)} icon={<TrendingUp size={28} />} color="text-blue-600" />
        </div>
      )}

      <Card className="p-5 mb-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-4">Recent Orders</h3>
        <RecentOrdersTable orders={recentOrders} />
      </Card>

      <Card className="p-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-3">Quick Links</h3>
        <div className="grid grid-cols-3 gap-3">
          <QuickLink to="/orders"  icon={<ShoppingCart size={20} />} label="Orders" />
          <QuickLink to="/dealers" icon={<Building2 size={20} />}    label="Business Partners" />
          <QuickLink to="/leads"   icon={<UserPlus size={20} />}     label="Leads" />
        </div>
      </Card>
    </div>
  );
}

// ── Logistics Dashboard ───────────────────────────────────────────────────────
function LogisticsDashboard() {
  // logistics role has no access to /analytics; use orders list for counts
  const { data: allOrders, isLoading: statsLoading } = useQuery({
    queryKey: ['orders-all-counts'],
    queryFn: () => ordersService.list({ per_page: '1' }),
    staleTime: 60_000,
  });

  const { data: ordersData } = useQuery({
    queryKey: ['orders-dispatch'],
    queryFn: () => ordersService.list({ status: 'approved', per_page: '5' }),
    staleTime: 30_000,
  });

  const { data: packedData } = useQuery({
    queryKey: ['orders-packed'],
    queryFn: () => ordersService.list({ status: 'packed', per_page: '1' }),
    staleTime: 60_000,
  });

  const dispatchOrders: Order[] = ordersData?.data ?? [];
  const totalOrders = allOrders?.meta?.total ?? 0;
  const packedCount = packedData?.meta?.total ?? 0;

  return (
    <div>
      {statsLoading ? <Spinner /> : (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <StatCard label="Total Orders"     value={totalOrders}   icon={<ShoppingCart size={28} />} color="text-primary" />
          <StatCard label="Approved (Ready)" value={ordersData?.meta?.total ?? 0} icon={<Boxes size={28} />} color="text-orange-600" />
          <StatCard label="Packed"           value={packedCount}   icon={<Package size={28} />}      color="text-green-600" />
          <StatCard label="Pending QC"       value={0}             icon={<Clock size={28} />}         color="text-yellow-600" />
        </div>
      )}

      <Card className="p-5 mb-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-4">Orders Pending Dispatch</h3>
        <RecentOrdersTable orders={dispatchOrders} />
      </Card>

      <Card className="p-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-3">Quick Links</h3>
        <div className="grid grid-cols-1 gap-3 max-w-xs">
          <QuickLink to="/orders" icon={<ShoppingCart size={20} />} label="Orders" />
        </div>
      </Card>
    </div>
  );
}

// ── Accounts Dashboard ────────────────────────────────────────────────────────
function AccountsDashboard() {
  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['analytics-dashboard'],
    queryFn: analyticsService.dashboard,
    staleTime: 60_000,
  });

  const { data: pl } = useQuery({
    queryKey: ['finance-pl-month'],
    queryFn: () => financeService.pl({ from: startOfMonth(), to: today() }),
    staleTime: 120_000,
  });

  const { data: monthlyRevData } = useQuery({
    queryKey: ['analytics-revenue-monthly'],
    queryFn: () => analyticsService.revenue({ period: 'monthly', from: nMonthsAgo(6), to: today() }),
    staleTime: 300_000,
  });

  const monthlySeries = (monthlyRevData?.time_series ?? []).map((r: any) => ({
    month: periodLabel(String(r.period)),
    revenue: Number(r.revenue ?? 0),
  }));

  return (
    <div>
      {statsLoading ? <Spinner /> : (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <StatCard label="Month Revenue"  value={fmtINR(stats?.month_revenue ?? 0)}        icon={<IndianRupee size={28} />}  color="text-green-600" />
          <StatCard label="Month Expenses" value={fmtINR(pl?.operating_expenses ?? 0)}       icon={<TrendingDown size={28} />} color="text-red-500" />
          <StatCard label="Net Profit"     value={fmtINR(pl?.net_profit ?? 0)}               icon={<BarChart3 size={28} />}    color="text-blue-600" />
          <StatCard label="Active Orders"  value={stats?.active_orders ?? 0}                 icon={<AlertCircle size={28} />}  color="text-orange-600" />
        </div>
      )}

      <Card className="p-5 mb-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-4">Monthly Revenue — Last 6 Months</h3>
        <ResponsiveContainer width="100%" height={200}>
          <BarChart data={monthlySeries} barCategoryGap="30%">
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis dataKey="month" tick={{ fontSize: 11 }} />
            <YAxis tick={{ fontSize: 11 }} tickFormatter={(v) => `₹${(v / 100000).toFixed(1)}L`} />
            <Tooltip formatter={(v: any) => fmtINR(Number(v))} />
            <Legend />
            <Bar dataKey="revenue" name="Revenue" fill="#E8593C" radius={[4, 4, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </Card>

      <Card className="p-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-3">Quick Links</h3>
        <div className="grid grid-cols-3 gap-3 md:grid-cols-5">
          <QuickLink to="/invoices"    icon={<FileText size={20} />}         label="Invoices" />
          <QuickLink to="/expenses"    icon={<Receipt size={20} />}          label="Expenses" />
          <QuickLink to="/pl"          icon={<TrendingUp size={20} />}       label="P&L" />
          <QuickLink to="/gst"         icon={<BadgeDollarSign size={20} />}  label="GST" />
          <QuickLink to="/receivables" icon={<Landmark size={20} />}         label="Receivables" />
        </div>
      </Card>
    </div>
  );
}

// ── HR Dashboard ──────────────────────────────────────────────────────────────
function HRDashboard() {
  const { data: empData, isLoading: empLoading } = useQuery({
    queryKey: ['hr-employees-count'],
    queryFn: () => hrService.employees({ per_page: '200' }),
    staleTime: 300_000,
  });

  const { data: todayAttendance } = useQuery({
    queryKey: ['hr-attendance-today'],
    queryFn: hrService.attendanceToday,
    staleTime: 30_000,
  });

  const { data: payrollData } = useQuery({
    queryKey: ['hr-payroll-recent'],
    queryFn: () => hrService.payrollRuns({ per_page: '1' }),
    staleTime: 300_000,
  });

  const employees: import('../../types').Employee[] = empData?.data ?? [];
  const totalEmployees = empData?.meta?.total ?? employees.length;
  const presentToday = Array.isArray(todayAttendance)
    ? todayAttendance.filter((r: any) => r.status === 'present' || r.attendance?.status === 'present').length
    : 0;
  const lastPayroll = payrollData?.data?.[0] ?? payrollData?.[0];

  // Department headcount from employees list
  const deptCount: Record<string, number> = {};
  employees.forEach((e) => {
    if (e.department) deptCount[e.department] = (deptCount[e.department] ?? 0) + 1;
  });
  const deptList = Object.entries(deptCount).sort((a, b) => b[1] - a[1]);
  const maxDept = deptList[0]?.[1] ?? 1;
  const DEPT_COLORS = ['bg-blue-500', 'bg-yellow-500', 'bg-green-500', 'bg-purple-500', 'bg-orange-500', 'bg-red-500'];

  return (
    <div>
      {empLoading ? <Spinner /> : (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <StatCard label="Total Employees" value={totalEmployees}                                       icon={<Users size={28} />}         color="text-blue-600" />
          <StatCard label="Present Today"   value={presentToday}                                         icon={<CalendarCheck size={28} />}  color="text-green-600" />
          <StatCard label="Last Payroll"    value={fmtINR(lastPayroll?.total_payout ?? lastPayroll?.total_gross ?? 0)} icon={<Wallet size={28} />} color="text-purple-600" />
          <StatCard label="Payroll Status"  value={lastPayroll?.status ?? '—'}                            icon={<ClipboardList size={28} />}  color="text-orange-600" />
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <Card className="p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Department Headcount</h3>
          <div className="space-y-3 mt-2">
            {deptList.length === 0 && <p className="text-xs text-gray-400">No data</p>}
            {deptList.map(([dept, count], i) => (
              <div key={dept} className="flex items-center gap-3 text-sm">
                <span className="text-gray-600 w-28 truncate">{dept}</span>
                <div className="flex-1 bg-gray-100 rounded-full h-2">
                  <div className={`${DEPT_COLORS[i % DEPT_COLORS.length]} h-2 rounded-full`} style={{ width: `${(count / maxDept) * 100}%` }} />
                </div>
                <span className="font-semibold text-gray-700 w-5 text-right">{count}</span>
              </div>
            ))}
          </div>
        </Card>

        <Card className="p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-3">Quick Links</h3>
          <div className="grid grid-cols-3 gap-3">
            <QuickLink to="/employees"  icon={<Users size={20} />}          label="Employees" />
            <QuickLink to="/attendance" icon={<CalendarCheck size={20} />}   label="Attendance" />
            <QuickLink to="/payroll"    icon={<Banknote size={20} />}        label="Payroll" />
          </div>
        </Card>
      </div>
    </div>
  );
}

// ── Main Dashboard (role-aware) ───────────────────────────────────────────────
export default function DashboardPage() {
  const user = useAuthStore((s) => s.user);
  const role = user?.role;

  const subtitles: Record<string, string> = {
    super_admin:     'DXEMPIRE operations overview',
    sales:           'Sales & dealer overview',
    warehouse_staff: 'Warehouse operations',
    qc_engineer:     'QC & inventory overview',
    accounts:        'Finance overview',
    hr_manager:      'HR & payroll overview',
    logistics:       'Dispatch & logistics overview',
  };

  return (
    <div>
      <PageHeader
        title={`Welcome, ${user?.name?.split(' ')[0] ?? 'User'}`}
        subtitle={subtitles[role ?? ''] ?? 'Overview'}
      />
      {role === 'super_admin'     && <AdminDashboard />}
      {role === 'warehouse_staff' && <WarehouseDashboard />}
      {role === 'qc_engineer'     && <QCDashboard />}
      {role === 'sales'           && <SalesDashboard />}
      {role === 'logistics'       && <LogisticsDashboard />}
      {role === 'accounts'        && <AccountsDashboard />}
      {role === 'hr_manager'      && <HRDashboard />}
    </div>
  );
}
