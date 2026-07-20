import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { financeService } from '../../services';
import { Card, PageHeader, Spinner, fmtINR } from '../../components/ui';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Legend } from 'recharts';

export default function PLPage() {
  const [period, setPeriod] = useState('monthly');
  const [year, setYear] = useState(new Date().getFullYear());

  const { data, isLoading } = useQuery({
    queryKey: ['pl', period, year],
    queryFn: () => financeService.pl({ period, year: String(year) }),
  });

  const pl = data?.data ?? data ?? null;
  const timeSeries: any[] = pl?.time_series ?? [];

  return (
    <div>
      <PageHeader title="Profit & Loss" subtitle="Revenue vs expenses breakdown" />

      <div className="flex gap-3 mb-6">
        <div className="flex gap-1 bg-gray-100 p-1 rounded-lg">
          {['monthly', 'quarterly'].map((p) => (
            <button
              key={p}
              onClick={() => setPeriod(p)}
              className={`px-3 py-1 rounded-md text-sm font-medium transition-colors ${period === p ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'}`}
            >
              {p.charAt(0).toUpperCase() + p.slice(1)}
            </button>
          ))}
        </div>
        <select
          className="px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none"
          value={year}
          onChange={(e) => setYear(Number(e.target.value))}
        >
          {[2024, 2025, 2026].map((y) => <option key={y} value={y}>{y}</option>)}
        </select>
      </div>

      {isLoading ? <Spinner /> : pl && (
        <>
          {/* Summary cards */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            {[
              { label: 'Total Revenue', value: pl.total_revenue ?? 0, color: 'text-green-600' },
              { label: 'Total Expenses', value: pl.total_expenses ?? 0, color: 'text-red-600' },
              { label: 'Net Profit', value: (pl.total_revenue ?? 0) - (pl.total_expenses ?? 0), color: ((pl.total_revenue ?? 0) - (pl.total_expenses ?? 0)) >= 0 ? 'text-green-700' : 'text-red-700' },
            ].map((s) => (
              <Card key={s.label} className="p-5">
                <div className="text-xs text-gray-500 mb-1">{s.label}</div>
                <div className={`text-2xl font-bold ${s.color}`}>{fmtINR(s.value)}</div>
              </Card>
            ))}
          </div>

          {/* Margin */}
          {pl.total_revenue > 0 && (
            <Card className="p-5 mb-6">
              <div className="flex justify-between items-center">
                <span className="text-sm text-gray-600">Net Margin</span>
                <span className="text-xl font-bold text-primary">
                  {(((pl.total_revenue - pl.total_expenses) / pl.total_revenue) * 100).toFixed(1)}%
                </span>
              </div>
            </Card>
          )}

          {/* Chart */}
          {timeSeries.length > 0 && (
            <Card className="p-5">
              <h3 className="text-sm font-semibold text-gray-700 mb-4">Revenue vs Expenses</h3>
              <ResponsiveContainer width="100%" height={280}>
                <BarChart data={timeSeries}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="period" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} tickFormatter={(v) => `₹${(v / 1000).toFixed(0)}k`} />
                  <Tooltip formatter={(v: any) => fmtINR(Number(v))} />
                  <Legend />
                  <Bar dataKey="revenue" fill="#22c55e" radius={[3, 3, 0, 0]} name="Revenue" />
                  <Bar dataKey="expenses" fill="#ef4444" radius={[3, 3, 0, 0]} name="Expenses" />
                </BarChart>
              </ResponsiveContainer>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
