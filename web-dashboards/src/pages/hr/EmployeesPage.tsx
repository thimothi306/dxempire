import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2 } from 'lucide-react';
import toast from 'react-hot-toast';
import { hrService } from '../../services';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Modal, Input, Select, fmtINR, fmtDate } from '../../components/ui';
import type { Employee } from '../../types';

const DEPARTMENTS = ['warehouse', 'sales', 'qc', 'accounts', 'hr', 'logistics', 'management'];
const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract'];
const EMPTY_FORM = { name: '', phone: '', email: '', department: 'warehouse', designation: '', employment_type: 'full_time', salary: '', joining_date: '' };

export default function EmployeesPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [showCreate, setShowCreate] = useState(false);
  const [editTarget, setEditTarget] = useState<Employee | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Employee | null>(null);
  const [form, setForm] = useState(EMPTY_FORM);

  const { data, isLoading } = useQuery({
    queryKey: ['employees', page],
    queryFn: () => hrService.employees({ page: String(page) }),
  });

  const createMut = useMutation({
    mutationFn: () => hrService.createEmployee({ ...form, salary: Number(form.salary) }),
    onSuccess: () => {
      toast.success('Employee added');
      qc.invalidateQueries({ queryKey: ['employees'] });
      setShowCreate(false);
      setForm(EMPTY_FORM);
    },
    onError: () => toast.error('Failed to add employee'),
  });

  const updateMut = useMutation({
    mutationFn: () => hrService.updateEmployee(editTarget!.id, { ...form, salary: Number(form.salary) }),
    onSuccess: () => {
      toast.success('Employee updated');
      qc.invalidateQueries({ queryKey: ['employees'] });
      setEditTarget(null);
    },
    onError: () => toast.error('Failed to update employee'),
  });

  const deleteMut = useMutation({
    mutationFn: (id: number) => hrService.deleteEmployee(id),
    onSuccess: () => {
      toast.success('Employee removed');
      qc.invalidateQueries({ queryKey: ['employees'] });
      setDeleteTarget(null);
    },
    onError: () => toast.error('Failed to remove employee'),
  });

  const openEdit = (emp: Employee) => {
    setForm({
      name: emp.name ?? '',
      phone: emp.phone ?? '',
      email: emp.email ?? '',
      department: emp.department ?? 'warehouse',
      designation: emp.designation ?? '',
      employment_type: emp.employment_type ?? 'full_time',
      salary: String(emp.salary ?? emp.basic_salary ?? ''),
      joining_date: emp.joining_date ?? '',
    });
    setEditTarget(emp);
  };

  const employees: Employee[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };

  const EmployeeForm = ({ onSubmit, loading }: { onSubmit: () => void; loading: boolean }) => (
    <div className="space-y-4">
      <Input label="Full Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
      <Input label="Phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
      <Input label="Email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
      <div className="grid grid-cols-2 gap-3">
        <Select label="Department" value={form.department} onChange={(e) => setForm({ ...form, department: e.target.value })}
          options={DEPARTMENTS.map((d) => ({ value: d, label: d.charAt(0).toUpperCase() + d.slice(1) }))} />
        <Select label="Employment Type" value={form.employment_type} onChange={(e) => setForm({ ...form, employment_type: e.target.value })}
          options={EMPLOYMENT_TYPES.map((t) => ({ value: t, label: t.replace(/_/g, ' ') }))} />
      </div>
      <Input label="Designation" value={form.designation} onChange={(e) => setForm({ ...form, designation: e.target.value })} />
      <Input label="Monthly Salary (₹)" type="number" value={form.salary} onChange={(e) => setForm({ ...form, salary: e.target.value })} />
      <Input label="Joining Date" type="date" value={form.joining_date} onChange={(e) => setForm({ ...form, joining_date: e.target.value })} />
      <div className="flex gap-3 pt-2">
        <Button onClick={onSubmit} loading={loading} className="flex-1 justify-center">Save</Button>
        <Button variant="outline" onClick={() => { setShowCreate(false); setEditTarget(null); }} className="flex-1 justify-center">Cancel</Button>
      </div>
    </div>
  );

  return (
    <div>
      <PageHeader
        title="Employees"
        subtitle={`${meta?.total ?? 0} staff members`}
        action={<Button onClick={() => { setForm(EMPTY_FORM); setShowCreate(true); }}><Plus size={15} /> Add Employee</Button>}
      />

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'name', header: 'Name', render: (e) => <span className="font-medium">{e.name}</span> },
                { key: 'employee_code', header: 'Code', render: (e) => <span className="font-mono text-xs">{e.employee_code ?? '—'}</span> },
                { key: 'department', header: 'Department', render: (e) => <Badge label={e.department} color="blue" /> },
                { key: 'designation', header: 'Designation', render: (e) => e.designation ?? '—' },
                { key: 'employment_type', header: 'Type', render: (e) => <Badge label={(e.employment_type ?? '').replace(/_/g, ' ')} color="gray" /> },
                { key: 'salary', header: 'Salary', render: (e) => fmtINR(e.salary ?? e.basic_salary ?? 0) },
                { key: 'joining_date', header: 'Joined', render: (e) => fmtDate(e.joining_date ?? '') },
                { key: 'is_active', header: 'Status', render: (e) => <Badge label={e.is_active ? 'Active' : 'Inactive'} color={e.is_active ? 'green' : 'red'} /> },
                {
                  key: 'actions', header: '', render: (e) => (
                    <div className="flex gap-2">
                      <Button size="sm" variant="outline" onClick={(ev) => { ev.stopPropagation(); openEdit(e); }}>
                        <Pencil size={13} />
                      </Button>
                      <Button size="sm" variant="danger" onClick={(ev) => { ev.stopPropagation(); setDeleteTarget(e); }}>
                        <Trash2 size={13} />
                      </Button>
                    </div>
                  ),
                },
              ]}
              data={employees}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Add Employee Modal */}
      <Modal open={showCreate} onClose={() => { setShowCreate(false); setForm(EMPTY_FORM); }} title="Add Employee">
        <EmployeeForm onSubmit={() => createMut.mutate()} loading={createMut.isPending} />
      </Modal>

      {/* Edit Employee Modal */}
      <Modal open={!!editTarget} onClose={() => setEditTarget(null)} title={`Edit — ${editTarget?.name}`}>
        <EmployeeForm onSubmit={() => updateMut.mutate()} loading={updateMut.isPending} />
      </Modal>

      {/* Delete Confirm Modal */}
      <Modal open={!!deleteTarget} onClose={() => setDeleteTarget(null)} title="Remove Employee">
        <div className="space-y-4">
          <p className="text-sm text-gray-600">
            Are you sure you want to remove <span className="font-semibold">{deleteTarget?.name}</span>?
            Their attendance and payroll history will be preserved.
          </p>
          <div className="flex gap-3">
            <Button variant="danger" onClick={() => deleteMut.mutate(deleteTarget!.id)} loading={deleteMut.isPending} className="flex-1 justify-center">Remove</Button>
            <Button variant="outline" onClick={() => setDeleteTarget(null)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
