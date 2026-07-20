import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Play, CheckCircle } from 'lucide-react';
import toast from 'react-hot-toast';
import { hrService } from '../../services';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, fmtINR, fmtDate } from '../../components/ui';
import type { PayrollRun } from '../../types';

const RUN_STATUS_COLORS: Record<string, string> = {
  draft: 'gray', processing: 'yellow', processed: 'blue', completed: 'green', paid: 'green', failed: 'red',
};

export default function PayrollPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [selectedRun, setSelectedRun] = useState<PayrollRun | null>(null);
  const [runMonth, setRunMonth] = useState(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  });

  const { data, isLoading } = useQuery({
    queryKey: ['payroll-runs', page],
    queryFn: () => hrService.payrollRuns({ page: String(page) }),
  });

  const { data: itemsData } = useQuery({
    queryKey: ['payroll-items', selectedRun?.id],
    queryFn: () => hrService.payrollItems(selectedRun!.id),
    enabled: !!selectedRun,
  });

  const processPayroll = useMutation({
    mutationFn: () => hrService.processPayroll({ month: runMonth }),
    onSuccess: () => { toast.success('Payroll processed'); qc.invalidateQueries({ queryKey: ['payroll-runs'] }); },
    onError: () => toast.error('Failed to run payroll'),
  });

  const markPaidMut = useMutation({
    mutationFn: (id: number) => hrService.markPaid(id),
    onSuccess: () => {
      toast.success('Payroll marked as paid');
      qc.invalidateQueries({ queryKey: ['payroll-runs'] });
      setSelectedRun(null);
    },
    onError: () => toast.error('Failed to mark as paid'),
  });

  const runs: PayrollRun[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };
  const items: any[] = itemsData?.data ?? [];

  return (
    <div>
      <PageHeader title="Payroll" subtitle="Monthly salary processing" />

      {/* Run payroll */}
      <Card className="p-5 mb-5">
        <div className="flex items-end gap-3">
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Run payroll for month</label>
            <input
              type="month"
              className="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30"
              value={runMonth}
              onChange={(e) => setRunMonth(e.target.value)}
            />
          </div>
          <Button onClick={() => processPayroll.mutate()} loading={processPayroll.isPending}>
            <Play size={15} /> Process Payroll
          </Button>
        </div>
      </Card>

      {/* Payroll runs */}
      <Card className="mb-5">
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'month', header: 'Month', render: (r) => <span className="font-semibold">{r.month}/{r.year ?? ''}</span> },
                { key: 'status', header: 'Status', render: (r) => <Badge label={r.status} color={RUN_STATUS_COLORS[r.status] ?? 'gray'} /> },
                { key: 'employee_count', header: 'Employees', render: (r) => r.employee_count ?? (r as any).items_count ?? '—' },
                { key: 'total_payout', header: 'Net Pay', render: (r) => <span className="font-semibold text-green-700">{fmtINR(r.total_payout ?? r.total_net ?? 0)}</span> },
                { key: 'processed_at', header: 'Processed', render: (r) => r.processed_at ? <span className="text-xs text-gray-400">{fmtDate(r.processed_at)}</span> : '—' },
                {
                  key: 'actions', header: '', render: (r) => (
                    <div className="flex gap-2">
                      <Button size="sm" variant="outline" onClick={(e) => { e.stopPropagation(); setSelectedRun(r); }}>
                        View
                      </Button>
                      {(r.status === 'processed' || r.status === 'completed') && (
                        <Button size="sm" onClick={(e) => { e.stopPropagation(); markPaidMut.mutate(r.id); }} loading={markPaidMut.isPending}>
                          <CheckCircle size={13} /> Mark Paid
                        </Button>
                      )}
                    </div>
                  ),
                },
              ]}
              data={runs}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Payroll items for selected run */}
      {selectedRun && (
        <Card>
          <div className="flex justify-between items-center px-5 py-3 border-b">
            <div>
              <h3 className="font-semibold text-gray-800">Payslips — {selectedRun.month}/{(selectedRun as any).year ?? ''}</h3>
              <p className="text-xs text-gray-400 mt-0.5">Status: <span className="font-medium">{selectedRun.status}</span></p>
            </div>
            <div className="flex gap-2 items-center">
              {(selectedRun.status === 'processed' || selectedRun.status === 'completed') && (
                <Button size="sm" onClick={() => markPaidMut.mutate(selectedRun.id)} loading={markPaidMut.isPending}>
                  <CheckCircle size={13} /> Mark All Paid
                </Button>
              )}
              <button onClick={() => setSelectedRun(null)} className="text-xs text-gray-400 hover:text-gray-600">Close</button>
            </div>
          </div>
          {items.length === 0
            ? <div className="p-8 text-center text-sm text-gray-400">No items found</div>
            : (
              <Table
                columns={[
                  { key: 'emp_code', header: 'Code', render: (i) => <span className="font-mono text-xs">{i.emp_code ?? '—'}</span> },
                  { key: 'employee', header: 'Employee', render: (i) => <span className="font-medium">{i.name ?? i.employee?.name ?? '—'}</span> },
                  { key: 'department', header: 'Dept', render: (i) => i.department ?? i.employee?.department ?? '—' },
                  { key: 'days_worked', header: 'Days', render: (i) => i.days_worked ?? '—' },
                  { key: 'basic', header: 'Basic', render: (i) => fmtINR(i.basic ?? 0) },
                  { key: 'deductions', header: 'Deductions', render: (i) => fmtINR(i.deductions ?? 0) },
                  { key: 'net_salary', header: 'Net', render: (i) => <span className="font-semibold text-green-700">{fmtINR(i.net_salary ?? 0)}</span> },
                  { key: 'status', header: 'Status', render: (i) => <Badge label={i.status ?? 'pending'} color={i.status === 'paid' ? 'green' : 'yellow'} /> },
                ]}
                data={items}
                keyField="id"
              />
            )}
        </Card>
      )}
    </div>
  );
}
