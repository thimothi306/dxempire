import { Loader2 } from 'lucide-react';
import React from 'react';

// ─── Button ──────────────────────────────────────────────────────────────────
interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost' | 'outline';
  size?: 'sm' | 'md' | 'lg';
  loading?: boolean;
}

export const Button = ({ variant = 'primary', size = 'md', loading, children, className = '', disabled, ...props }: ButtonProps) => {
  const base = 'inline-flex items-center gap-2 font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed';
  const variants = {
    primary: 'bg-primary text-white hover:bg-primary-600 focus:ring-primary',
    secondary: 'bg-gray-100 text-gray-700 hover:bg-gray-200 focus:ring-gray-300',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
    ghost: 'text-gray-600 hover:bg-gray-100 focus:ring-gray-300',
    outline: 'border border-gray-300 text-gray-700 hover:bg-gray-50 focus:ring-gray-300',
  };
  const sizes = { sm: 'px-3 py-1.5 text-sm', md: 'px-4 py-2 text-sm', lg: 'px-5 py-2.5 text-base' };
  return (
    <button className={`${base} ${variants[variant]} ${sizes[size]} ${className}`} disabled={disabled || loading} {...props}>
      {loading && <Loader2 size={14} className="animate-spin" />}
      {children}
    </button>
  );
};

// ─── Badge ───────────────────────────────────────────────────────────────────
interface BadgeProps { label: string; color?: string; }
const badgeColors: Record<string, string> = {
  green: 'bg-green-100 text-green-700',
  red: 'bg-red-100 text-red-700',
  yellow: 'bg-yellow-100 text-yellow-700',
  blue: 'bg-blue-100 text-blue-700',
  orange: 'bg-orange-100 text-orange-700',
  purple: 'bg-purple-100 text-purple-700',
  gray: 'bg-gray-100 text-gray-600',
};
export const Badge = ({ label, color = 'gray' }: BadgeProps) => (
  <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${badgeColors[color] || badgeColors.gray}`}>{label}</span>
);

// ─── Status Badge helpers ─────────────────────────────────────────────────────
export const orderStatusBadge = (status: string) => {
  const map: Record<string, [string, string]> = {
    pending: ['Pending', 'yellow'], approved: ['Approved', 'blue'],
    picking: ['Picking', 'purple'], packing: ['Packing', 'purple'],
    dispatched: ['Dispatched', 'orange'], delivered: ['Delivered', 'green'],
    cancelled: ['Cancelled', 'red'], returned: ['Returned', 'red'],
  };
  const [label, color] = map[status] ?? [status, 'gray'];
  return <Badge label={label} color={color} />;
};

export const kycBadge = (status: string) => {
  const map: Record<string, [string, string]> = {
    pending: ['Pending', 'yellow'], verified: ['Verified', 'green'], rejected: ['Rejected', 'red'],
  };
  const [label, color] = map[status] ?? [status, 'gray'];
  return <Badge label={label} color={color} />;
};

export const leadStageBadge = (stage: string) => {
  const map: Record<string, [string, string]> = {
    new: ['New', 'blue'], contacted: ['Contacted', 'purple'], quoted: ['Quoted', 'yellow'],
    negotiating: ['Negotiating', 'orange'], won: ['Won', 'green'], lost: ['Lost', 'red'],
  };
  const [label, color] = map[stage] ?? [stage, 'gray'];
  return <Badge label={label} color={color} />;
};

// ─── Spinner ─────────────────────────────────────────────────────────────────
export const Spinner = ({ className = '' }: { className?: string }) => (
  <div className={`flex justify-center items-center py-12 ${className}`}>
    <Loader2 className="animate-spin text-primary" size={28} />
  </div>
);

// ─── Card ────────────────────────────────────────────────────────────────────
export const Card = ({ children, className = '' }: { children: React.ReactNode; className?: string }) => (
  <div className={`bg-white rounded-xl border border-gray-200 shadow-sm ${className}`}>{children}</div>
);

// ─── StatCard ────────────────────────────────────────────────────────────────
interface StatCardProps { label: string; value: string | number; icon: React.ReactNode; color?: string; sub?: string; }
export const StatCard = ({ label, value, icon, color = 'text-primary', sub }: StatCardProps) => (
  <Card className="p-5">
    <div className="flex items-center justify-between">
      <div>
        <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">{label}</p>
        <p className={`text-2xl font-bold mt-1 ${color}`}>{value}</p>
        {sub && <p className="text-xs text-gray-400 mt-1">{sub}</p>}
      </div>
      <div className="text-gray-300">{icon}</div>
    </div>
  </Card>
);

// ─── Table ───────────────────────────────────────────────────────────────────
interface Column<T> { key: string; header: string; render?: (row: T) => React.ReactNode; }
interface TableProps<T> { columns: Column<T>[]; data: T[]; keyField: keyof T; onRowClick?: (row: T) => void; emptyText?: string; }
export function Table<T>({ columns, data, keyField, onRowClick, emptyText = 'No data found' }: TableProps<T>) {
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full text-sm">
        <thead>
          <tr className="border-b border-gray-100">
            {columns.map((c) => (
              <th key={c.key} className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">{c.header}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.length === 0 ? (
            <tr><td colSpan={columns.length} className="py-12 text-center text-gray-400">{emptyText}</td></tr>
          ) : data.map((row) => (
            <tr
              key={String(row[keyField])}
              className={`border-b border-gray-50 hover:bg-gray-50 transition-colors ${onRowClick ? 'cursor-pointer' : ''}`}
              onClick={() => onRowClick?.(row)}
            >
              {columns.map((c) => (
                <td key={c.key} className="py-3 px-4 text-gray-700 whitespace-nowrap">
                  {c.render ? c.render(row) : String((row as Record<string, unknown>)[c.key] ?? '—')}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

// ─── Pagination ──────────────────────────────────────────────────────────────
interface PaginationProps { current: number; last: number; total: number; onChange: (page: number) => void; }
export const Pagination = ({ current, last, total, onChange }: PaginationProps) => (
  <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 text-sm text-gray-600">
    <span>{total} total records</span>
    <div className="flex gap-1">
      <button onClick={() => onChange(current - 1)} disabled={current === 1} className="px-3 py-1 rounded border border-gray-200 disabled:opacity-40 hover:bg-gray-50">Prev</button>
      <span className="px-3 py-1 bg-primary-50 text-primary rounded font-medium">{current} / {last}</span>
      <button onClick={() => onChange(current + 1)} disabled={current === last} className="px-3 py-1 rounded border border-gray-200 disabled:opacity-40 hover:bg-gray-50">Next</button>
    </div>
  </div>
);

// ─── Modal ───────────────────────────────────────────────────────────────────
interface ModalProps { open: boolean; onClose: () => void; title: string; children: React.ReactNode; width?: string; }
export const Modal = ({ open, onClose, title, children, width = 'max-w-lg' }: ModalProps) => {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div className={`relative bg-white rounded-2xl shadow-xl w-full ${width} mx-4 max-h-[90vh] overflow-y-auto`}>
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h2 className="text-base font-semibold text-gray-900">{title}</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div className="p-6">{children}</div>
      </div>
    </div>
  );
};

// ─── Input ───────────────────────────────────────────────────────────────────
interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> { label?: string; error?: string; }
export const Input = ({ label, error, className = '', ...props }: InputProps) => (
  <div className="flex flex-col gap-1">
    {label && <label className="text-xs font-medium text-gray-600">{label}</label>}
    <input className={`border ${error ? 'border-red-400' : 'border-gray-300'} rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary ${className}`} {...props} />
    {error && <p className="text-xs text-red-500">{error}</p>}
  </div>
);

// ─── Select ──────────────────────────────────────────────────────────────────
interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> { label?: string; options: { value: string; label: string }[]; }
export const Select = ({ label, options, className = '', ...props }: SelectProps) => (
  <div className="flex flex-col gap-1">
    {label && <label className="text-xs font-medium text-gray-600">{label}</label>}
    <select className={`border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary bg-white ${className}`} {...props}>
      {options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  </div>
);

// ─── Section Header ──────────────────────────────────────────────────────────
export const PageHeader = ({ title, subtitle, action }: { title: string; subtitle?: string; action?: React.ReactNode }) => (
  <div className="flex items-start justify-between mb-6">
    <div>
      <h1 className="text-xl font-bold text-gray-900">{title}</h1>
      {subtitle && <p className="text-sm text-gray-500 mt-0.5">{subtitle}</p>}
    </div>
    {action && <div>{action}</div>}
  </div>
);

// ─── Empty State ─────────────────────────────────────────────────────────────
export const EmptyState = ({ message = 'No data found' }: { message?: string }) => (
  <div className="flex flex-col items-center justify-center py-16 text-gray-400">
    <p className="text-sm">{message}</p>
  </div>
);

// ─── Format helpers ───────────────────────────────────────────────────────────
export const fmtINR = (n: number | string) =>
  '₹' + Number(n).toLocaleString('en-IN', { maximumFractionDigits: 0 });
export const fmtDate = (s: string) =>
  new Date(s).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
export const fmtDateTime = (s: string) =>
  new Date(s).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', hour12: true });
