import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { LogIn, LogOut } from 'lucide-react';
import toast from 'react-hot-toast';
import { hrService } from '../../services';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Select, fmtDateTime, fmtDate } from '../../components/ui';
import type { AttendanceRecord } from '../../types';

const STATUS_COLORS: Record<string, string> = {
  present: 'green', absent: 'red', late: 'yellow', half_day: 'orange', holiday: 'blue',
};

export default function AttendancePage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [dateFilter, setDateFilter] = useState(new Date().toISOString().slice(0, 10));
  const [employeeFilter, setEmployeeFilter] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['attendance', page, dateFilter, employeeFilter],
    queryFn: () => hrService.attendance({ page: String(page), date: dateFilter, ...(employeeFilter && { employee_id: employeeFilter }) }),
  });

  const { data: empData } = useQuery({
    queryKey: ['employees-list'],
    queryFn: () => hrService.employees({ per_page: '200' }),
  });

  const checkInMut = useMutation({
    mutationFn: (employeeId: number) => hrService.checkIn(employeeId),
    onSuccess: () => { toast.success('Checked in'); qc.invalidateQueries({ queryKey: ['attendance'] }); },
    onError: () => toast.error('Failed'),
  });

  const checkOutMut = useMutation({
    mutationFn: (employeeId: number) => hrService.checkOut(employeeId),
    onSuccess: () => { toast.success('Checked out'); qc.invalidateQueries({ queryKey: ['attendance'] }); },
    onError: () => toast.error('Failed'),
  });

  const records: AttendanceRecord[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };
  const employees = Array.isArray(empData?.data) ? empData.data : [];

  return (
    <div>
      <PageHeader title="Attendance" subtitle="Daily check-in / check-out" />

      <div className="flex flex-wrap gap-3 mb-5">
        <input
          type="date"
          className="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30"
          value={dateFilter}
          onChange={(e) => { setDateFilter(e.target.value); setPage(1); }}
        />
        <Select
          value={employeeFilter}
          onChange={(e) => { setEmployeeFilter(e.target.value); setPage(1); }}
          options={[{ value: '', label: 'All Employees' }, ...employees.map((e: any) => ({ value: String(e.id), label: e.name }))]}
        />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'employee', header: 'Employee', render: (r) => <span className="font-medium">{r.employee?.name ?? '—'}</span> },
                { key: 'date', header: 'Date', render: (r) => fmtDate(r.date ?? '') },
                { key: 'status', header: 'Status', render: (r) => <Badge label={(r.status ?? '').replace('_', ' ')} color={STATUS_COLORS[r.status ?? ''] ?? 'gray'} /> },
                { key: 'check_in', header: 'Check In', render: (r) => r.check_in_time ? <span className="text-xs text-gray-600">{fmtDateTime(r.check_in_time)}</span> : '—' },
                { key: 'check_out', header: 'Check Out', render: (r) => r.check_out_time ? <span className="text-xs text-gray-600">{fmtDateTime(r.check_out_time)}</span> : '—' },
                { key: 'hours', header: 'Hours', render: (r) => r.total_hours ? `${r.total_hours}h` : '—' },
                {
                  key: 'actions', header: '', render: (r) => (
                    <div className="flex gap-1">
                      {!r.check_in_time && (
                        <Button size="sm" variant="secondary" onClick={(e) => { e.stopPropagation(); checkInMut.mutate(r.employee_id); }}>
                          <LogIn size={12} /> In
                        </Button>
                      )}
                      {r.check_in_time && !r.check_out_time && (
                        <Button size="sm" variant="outline" onClick={(e) => { e.stopPropagation(); checkOutMut.mutate(r.employee_id); }}>
                          <LogOut size={12} /> Out
                        </Button>
                      )}
                    </div>
                  ),
                },
              ]}
              data={records}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>
    </div>
  );
}
