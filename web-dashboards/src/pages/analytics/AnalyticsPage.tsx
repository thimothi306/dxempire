import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { analyticsService } from '../../services';
import { Card, PageHeader, Spinner, fmtINR, Badge, Pagination } from '../../components/ui';
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
  CartesianGrid, LineChart, Line, PieChart, Pie, Cell,
} from 'recharts';

const COLORS = ['#E8593C', '#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899'];

function fmtISO(daysAgo: number) {
  const d = new Date(); d.setDate(d.getDate() - daysAgo); return d.toISOString().slice(0, 10);
}

type Tab = 'revenue' | 'sales' | 'inventory' | 'movements' | 'partners';

// ── Revenue Tab ───────────────────────────────────────────────────────────────
function RevenueTab() {
  const [period, setPeriod] = useState<'daily' | 'weekly' | 'monthly'>('daily');

  const { data: revenue, isLoading } = useQuery({
    queryKey: ['analytics-revenue', period],
    queryFn: () => analyticsService.revenue({ period, from: fmtISO(90), to: fmtISO(0) }),
    staleTime: 120_000,
  });

  const timeSeries = revenue?.time_series ?? [];
  const topProducts = revenue?.top_products?.slice(0, 5) ?? [];
  const topDealers = revenue?.top_dealers?.slice(0, 5) ?? [];
  const summary = revenue?.summary;

  return (
    <div className="space-y-5">
      {/* Period selector */}
      <div className="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
        {(['daily', 'weekly', 'monthly'] as const).map((p) => (
          <button
            key={p}
            onClick={() => setPeriod(p)}
            className={`px-4 py-1.5 rounded-md text-sm font-medium transition-colors ${period === p ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
          >
            {p.charAt(0).toUpperCase() + p.slice(1)}
          </button>
        ))}
      </div>

      {summary && (
        <div className="grid grid-cols-3 gap-4">
          {[
            { label: 'Total Revenue', value: fmtINR(Number(summary.total_revenue ?? 0)) },
            { label: 'Total Orders', value: summary.total_orders ?? 0 },
            { label: 'Avg Order Value', value: fmtINR(Number(summary.avg_order_value ?? 0)) },
          ].map((s) => (
            <Card key={s.label} className="p-4 text-center">
              <div className="text-lg font-bold text-gray-800">{s.value}</div>
              <div className="text-xs text-gray-500 mt-1">{s.label}</div>
            </Card>
          ))}
        </div>
      )}

      {isLoading ? <Spinner /> : timeSeries.length > 0 && (
        <Card className="p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Revenue Trend</h3>
          <ResponsiveContainer width="100%" height={240}>
            <LineChart data={timeSeries}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey="period" tick={{ fontSize: 11 }} tickFormatter={(v) => String(v).slice(5)} />
              <YAxis tick={{ fontSize: 11 }} tickFormatter={(v) => `₹${(v / 1000).toFixed(0)}k`} />
              <Tooltip formatter={(v: any) => fmtINR(Number(v))} />
              <Line type="monotone" dataKey="revenue" stroke="#E8593C" strokeWidth={2} dot={false} />
            </LineChart>
          </ResponsiveContainer>
        </Card>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {topProducts.length > 0 && (
          <Card className="p-5">
            <h3 className="text-sm font-semibold text-gray-700 mb-4">Top Products</h3>
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={topProducts} layout="vertical">
                <XAxis type="number" tick={{ fontSize: 10 }} tickFormatter={(v) => `₹${(v / 1000).toFixed(0)}k`} />
                <YAxis type="category" dataKey="model" tick={{ fontSize: 11 }} width={80} />
                <Tooltip formatter={(v: any) => fmtINR(Number(v))} />
                <Bar dataKey="revenue" fill="#E8593C" radius={[0, 3, 3, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </Card>
        )}
        {topDealers.length > 0 && (
          <Card className="p-5">
            <h3 className="text-sm font-semibold text-gray-700 mb-4">Top Dealers</h3>
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={topDealers} layout="vertical">
                <XAxis type="number" tick={{ fontSize: 10 }} tickFormatter={(v) => `₹${(v / 1000).toFixed(0)}k`} />
                <YAxis type="category" dataKey="business_name" tick={{ fontSize: 11 }} width={100} />
                <Tooltip formatter={(v: any) => fmtINR(Number(v))} />
                <Bar dataKey="revenue" fill="#3B82F6" radius={[0, 3, 3, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </Card>
        )}
      </div>
    </div>
  );
}

// ── Sales Tab ─────────────────────────────────────────────────────────────────
function SalesTab() {
  const [groupBy, setGroupBy] = useState<'category' | 'brand' | 'grade'>('category');

  const { data: sales, isLoading } = useQuery({
    queryKey: ['analytics-sales', groupBy],
    queryFn: () => analyticsService.sales({ group_by: groupBy, from: fmtISO(90), to: fmtISO(0) }),
    staleTime: 120_000,
  });

  const breakdown: any[] = sales?.breakdown ?? [];
  const channel = sales?.channel_split;

  const channelPieData = channel
    ? [
        { name: 'B2B', value: Number(channel.b2b_revenue ?? 0), color: '#E8593C' },
        { name: 'Retail', value: Number(channel.retail_revenue ?? 0), color: '#3B82F6' },
      ].filter((d) => d.value > 0)
    : [];

  return (
    <div className="space-y-5">
      <div className="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
        {(['category', 'brand', 'grade'] as const).map((g) => (
          <button
            key={g}
            onClick={() => setGroupBy(g)}
            className={`px-4 py-1.5 rounded-md text-sm font-medium transition-colors ${groupBy === g ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
          >
            By {g.charAt(0).toUpperCase() + g.slice(1)}
          </button>
        ))}
      </div>

      {isLoading ? <Spinner /> : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
          {channelPieData.length > 0 && (
            <Card className="p-5">
              <h3 className="text-sm font-semibold text-gray-700 mb-4">B2B vs Retail</h3>
              <ResponsiveContainer width="100%" height={160}>
                <PieChart>
                  <Pie data={channelPieData} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={65}
                    label={({ name, percent }: any) => `${name} ${((percent ?? 0) * 100).toFixed(0)}%`} labelLine={false}
                  >
                    {channelPieData.map((e, i) => <Cell key={i} fill={e.color} />)}
                  </Pie>
                  <Tooltip formatter={(v: any) => fmtINR(Number(v))} />
                </PieChart>
              </ResponsiveContainer>
              {channel && (
                <div className="mt-3 space-y-1 text-xs text-gray-600">
                  <div className="flex justify-between"><span>B2B Orders</span><span className="font-semibold">{channel.b2b_orders}</span></div>
                  <div className="flex justify-between"><span>Retail Orders</span><span className="font-semibold">{channel.retail_orders}</span></div>
                </div>
              )}
            </Card>
          )}

          {breakdown.length > 0 && (
            <Card className="p-5 lg:col-span-2">
              <h3 className="text-sm font-semibold text-gray-700 mb-4">Sales by {groupBy}</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-xs text-gray-500 border-b">
                      <th className="text-left pb-2">Segment</th>
                      <th className="text-right pb-2">Units</th>
                      <th className="text-right pb-2">Revenue</th>
                      <th className="text-right pb-2">Avg Price</th>
                      <th className="text-right pb-2">GST Collected</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {breakdown.map((r: any, i: number) => (
                      <tr key={i} className="hover:bg-gray-50">
                        <td className="py-2 font-medium capitalize">{r.segment}</td>
                        <td className="py-2 text-right">{r.units_sold}</td>
                        <td className="py-2 text-right font-semibold">{fmtINR(Number(r.revenue ?? 0))}</td>
                        <td className="py-2 text-right">{fmtINR(Number(r.avg_unit_price ?? 0))}</td>
                        <td className="py-2 text-right text-gray-500">{fmtINR(Number(r.gst_collected ?? 0))}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Card>
          )}

          {breakdown.length === 0 && !isLoading && (
            <Card className="p-8 text-center text-sm text-gray-400 lg:col-span-3">No sales data in the last 90 days</Card>
          )}
        </div>
      )}
    </div>
  );
}

// ── Inventory Tab ─────────────────────────────────────────────────────────────
function InventoryTab() {
  const { data: inv, isLoading } = useQuery({
    queryKey: ['analytics-inventory'],
    queryFn: () => analyticsService.inventory({}),
    staleTime: 300_000,
  });

  const stockMatrix: any[] = inv?.stock_matrix ?? [];
  const aging: any[] = inv?.aging_buckets ?? [];
  const slowMovers: any[] = inv?.slow_movers ?? [];
  const summary = inv?.summary;

  // Group stock matrix by category for chart
  const byCategory: Record<string, number> = {};
  stockMatrix.forEach((r: any) => {
    byCategory[r.category] = (byCategory[r.category] ?? 0) + Number(r.count ?? 0);
  });
  const categoryData = Object.entries(byCategory).map(([name, count], i) => ({ name, count, color: COLORS[i % COLORS.length] }));

  return (
    <div className="space-y-5">
      {isLoading ? <Spinner /> : (
        <>
          {summary && (
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
              {[
                { label: 'Total In Stock', value: summary.total_in_stock },
                { label: 'Stock Value', value: fmtINR(summary.total_stock_value ?? 0) },
                { label: 'Pending QC', value: summary.pending_qc },
                { label: 'In Refurbishment', value: summary.in_refurbishment },
              ].map((s) => (
                <Card key={s.label} className="p-4 text-center">
                  <div className="text-lg font-bold text-gray-800">{s.value}</div>
                  <div className="text-xs text-gray-500 mt-1">{s.label}</div>
                </Card>
              ))}
            </div>
          )}

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {categoryData.length > 0 && (
              <Card className="p-5">
                <h3 className="text-sm font-semibold text-gray-700 mb-4">Stock by Category</h3>
                <ResponsiveContainer width="100%" height={180}>
                  <PieChart>
                    <Pie data={categoryData} dataKey="count" nameKey="name" cx="50%" cy="50%" outerRadius={70}
                      label={({ name, percent }: any) => `${name} ${((percent ?? 0) * 100).toFixed(0)}%`} labelLine={false}
                    >
                      {categoryData.map((e, i) => <Cell key={i} fill={e.color} />)}
                    </Pie>
                    <Tooltip />
                  </PieChart>
                </ResponsiveContainer>
              </Card>
            )}

            {aging.length > 0 && (
              <Card className="p-5">
                <h3 className="text-sm font-semibold text-gray-700 mb-4">Stock Aging</h3>
                <ResponsiveContainer width="100%" height={180}>
                  <BarChart data={aging}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                    <XAxis dataKey="age_bucket" tick={{ fontSize: 11 }} />
                    <YAxis tick={{ fontSize: 11 }} />
                    <Tooltip />
                    <Bar dataKey="count" fill="#F59E0B" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </Card>
            )}
          </div>

          {slowMovers.length > 0 && (
            <Card className="p-5">
              <h3 className="text-sm font-semibold text-gray-700 mb-4">Slow Movers (60+ days in stock)</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-xs text-gray-500 border-b">
                      <th className="text-left pb-2">Brand / Model</th>
                      <th className="text-left pb-2">Category</th>
                      <th className="text-left pb-2">Grade</th>
                      <th className="text-right pb-2">Price</th>
                      <th className="text-right pb-2">Days in Stock</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {slowMovers.slice(0, 15).map((p: any) => (
                      <tr key={p.id} className="hover:bg-gray-50">
                        <td className="py-2 font-medium">{p.brand} {p.model}</td>
                        <td className="py-2"><Badge label={p.category} color="blue" /></td>
                        <td className="py-2"><Badge label={p.grade} color="purple" /></td>
                        <td className="py-2 text-right">{fmtINR(p.selling_price)}</td>
                        <td className="py-2 text-right">
                          <span className={p.days_in_stock > 90 ? 'text-red-600 font-semibold' : 'text-orange-600'}>
                            {p.days_in_stock}d
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Card>
          )}
        </>
      )}
    </div>
  );
}

// ── Stock Movements Tab ───────────────────────────────────────────────────────
function StockMovementsTab() {
  const [page, setPage] = useState(1);

  const { data, isLoading } = useQuery({
    queryKey: ['analytics-stock-movements', page],
    queryFn: () => analyticsService.stockMovements({ page: String(page), per_page: '30' }),
    staleTime: 60_000,
  });

  const movements: any[] = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div>
      <Card>
        {isLoading ? <Spinner /> : (
          <>
            {movements.length === 0 && (
              <div className="py-10 text-center text-sm text-gray-400">No stock movements recorded</div>
            )}
            {movements.length > 0 && (
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-xs text-gray-500 border-b">
                    <th className="text-left p-3">Product</th>
                    <th className="text-left p-3">IMEI</th>
                    <th className="text-left p-3">From Bin</th>
                    <th className="text-left p-3">To Bin</th>
                    <th className="text-left p-3">Moved By</th>
                    <th className="text-left p-3">Date</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {movements.map((m: any) => (
                    <tr key={m.id} className="hover:bg-gray-50">
                      <td className="p-3 font-medium">{m.product?.brand} {m.product?.model}</td>
                      <td className="p-3 font-mono text-xs text-gray-500">{m.product?.imei}</td>
                      <td className="p-3"><span className="text-gray-500">{m.from_bin?.code ?? '—'}</span></td>
                      <td className="p-3"><span className="font-medium">{m.to_bin?.code ?? '—'}</span></td>
                      <td className="p-3">{m.mover?.name ?? '—'}</td>
                      <td className="p-3 text-xs text-gray-400">{m.moved_at ? new Date(m.moved_at).toLocaleDateString() : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>
    </div>
  );
}

// ── Partners Tab ──────────────────────────────────────────────────────────────
function PartnersTab() {
  const { data: partners, isLoading } = useQuery({
    queryKey: ['analytics-partners'],
    queryFn: () => analyticsService.partnerPerformance({}),
    staleTime: 300_000,
  });

  const dealers: any[] = partners?.dealers ?? partners?.data?.dealers ?? [];

  return (
    <div>
      {isLoading ? <Spinner /> : (
        <Card className="p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Partner Performance</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-gray-500 border-b">
                  <th className="pb-2 font-medium">Partner</th>
                  <th className="pb-2 font-medium text-right">Orders</th>
                  <th className="pb-2 font-medium text-right">Revenue</th>
                  <th className="pb-2 font-medium text-right">Avg Order</th>
                  <th className="pb-2 font-medium text-right">Outstanding</th>
                  <th className="pb-2 font-medium text-right">Payment Rate</th>
                </tr>
              </thead>
              <tbody>
                {dealers.length === 0 && (
                  <tr><td colSpan={6} className="py-8 text-center text-gray-400">No partner data available</td></tr>
                )}
                {dealers.slice(0, 20).map((p: any, i: number) => (
                  <tr key={i} className="border-b last:border-0 hover:bg-gray-50">
                    <td className="py-2.5 font-medium">{p.business_name}</td>
                    <td className="py-2.5 text-right">{p.order_count ?? 0}</td>
                    <td className="py-2.5 text-right">{fmtINR(Number(p.total_revenue ?? 0))}</td>
                    <td className="py-2.5 text-right">{fmtINR(Number(p.avg_order_value ?? 0))}</td>
                    <td className="py-2.5 text-right text-red-600">{fmtINR(Number(p.amount_outstanding ?? 0))}</td>
                    <td className="py-2.5 text-right">
                      <span className={(p.payment_rate_pct ?? 0) >= 80 ? 'text-green-600 font-semibold' : 'text-orange-600 font-semibold'}>
                        {p.payment_rate_pct ?? 0}%
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  );
}

// ── Main ──────────────────────────────────────────────────────────────────────
const TABS: { key: Tab; label: string }[] = [
  { key: 'revenue', label: 'Revenue' },
  { key: 'sales', label: 'Sales' },
  { key: 'inventory', label: 'Inventory' },
  { key: 'movements', label: 'Stock Movements' },
  { key: 'partners', label: 'Partners' },
];

export default function AnalyticsPage() {
  const [tab, setTab] = useState<Tab>('revenue');

  return (
    <div>
      <PageHeader title="Analytics" subtitle="Sales, inventory & partner insights" />

      <div className="flex gap-1 mb-6 bg-gray-100 p-1 rounded-lg overflow-x-auto">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`px-4 py-1.5 rounded-md text-sm font-medium whitespace-nowrap transition-colors ${tab === t.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'revenue'    && <RevenueTab />}
      {tab === 'sales'      && <SalesTab />}
      {tab === 'inventory'  && <InventoryTab />}
      {tab === 'movements'  && <StockMovementsTab />}
      {tab === 'partners'   && <PartnersTab />}
    </div>
  );
}
