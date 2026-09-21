import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Upload, Download, CheckCircle2 } from 'lucide-react';
import toast from 'react-hot-toast';
import { hrService } from '../../services';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Modal, Input, Select, fmtINR, fmtDate, ExportButton } from '../../components/ui';
import { STATE_NAMES, districtsForState } from '../../data/statesDistricts';
import type { Employee } from '../../types';

export const DEPARTMENTS = ['warehouse', 'sales', 'qc', 'accounts', 'hr', 'logistics', 'management'];
const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract'];
const EMPTY_FORM = {
  name: '', phone: '', email: '', department: 'warehouse', designation: '', employment_type: 'full_time',
  salary: '', joining_date: '', incentive_enabled: false, commission_rate: '',
  village_street: '', post_office: '', police_station: '', district: '', state: '', pincode: '',
  bank_account_number: '', confirm_account_number: '', account_holder_name: '', bank_name: '', ifsc_code: '',
};
type EmployeeFormState = typeof EMPTY_FORM;

const DOCUMENT_TYPES: { key: string; label: string }[] = [
  { key: 'aadhaar_document', label: 'Aadhaar Card' },
  { key: 'pan_document', label: 'PAN Card' },
  { key: 'passport_photo', label: 'Passport Size Photo' },
  { key: 'education_certificate', label: 'Educational Qualification Certificate' },
  { key: 'bank_passbook_document', label: 'Bank Passbook / Cancelled Cheque' },
  { key: 'signed_agreement_document', label: 'Signed Agreement Document' },
];

// Defined OUTSIDE the page component: an inline component definition would be
// recreated on every render, causing React to remount the inputs (and lose
// focus) after every keystroke.
function EmployeeForm({
  form, setForm, onSubmit, onCancel, loading,
}: {
  form: EmployeeFormState;
  setForm: (f: EmployeeFormState) => void;
  onSubmit: () => void;
  onCancel: () => void;
  loading: boolean;
}) {
  return (
    <div className="space-y-4">
      <Input label="Full Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
      <Input label="Mobile Number *" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="Enter 10-digit mobile number" />
      <Input label="Email ID *" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="Enter professional email address" />
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Select label="Department" value={form.department} onChange={(e) => setForm({ ...form, department: e.target.value })}
          options={DEPARTMENTS.map((d) => ({ value: d, label: d.charAt(0).toUpperCase() + d.slice(1) }))} />
        <Select label="Employment Type" value={form.employment_type} onChange={(e) => setForm({ ...form, employment_type: e.target.value })}
          options={EMPLOYMENT_TYPES.map((t) => ({ value: t, label: t.replace(/_/g, ' ') }))} />
      </div>
      <Input label="Designation" value={form.designation} onChange={(e) => setForm({ ...form, designation: e.target.value })} />
      <Input label="Monthly Salary (₹)" type="number" value={form.salary} onChange={(e) => setForm({ ...form, salary: e.target.value })} />
      <Input label="Joining Date" type="date" value={form.joining_date} onChange={(e) => setForm({ ...form, joining_date: e.target.value })} />

      <div className="border-t border-gray-100 pt-4">
        <h3 className="text-sm font-semibold text-gray-800 mb-3">Address Details</h3>
        <div className="space-y-3">
          <Input label="Village / Street" value={form.village_street} onChange={(e) => setForm({ ...form, village_street: e.target.value })} placeholder="Vill / Street name" />
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Input label="Post Office (P.O)" value={form.post_office} onChange={(e) => setForm({ ...form, post_office: e.target.value })} placeholder="P.O." />
            <Input label="Police Station (P.S)" value={form.police_station} onChange={(e) => setForm({ ...form, police_station: e.target.value })} placeholder="P.S." />
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Select
              label="State"
              value={form.state}
              onChange={(e) => setForm({ ...form, state: e.target.value, district: '' })}
              options={[{ value: '', label: 'Select state...' }, ...STATE_NAMES.map((s) => ({ value: s, label: s }))]}
            />
            <Select
              label="District"
              value={form.district}
              onChange={(e) => setForm({ ...form, district: e.target.value })}
              disabled={!form.state}
              options={[{ value: '', label: form.state ? 'Select district...' : 'Select a state first' }, ...districtsForState(form.state).map((d) => ({ value: d, label: d }))]}
            />
          </div>
          <Input label="Pin Code" value={form.pincode} onChange={(e) => setForm({ ...form, pincode: e.target.value })} placeholder="Pin" />
        </div>
      </div>

      <div className="border-t border-gray-100 pt-4">
        <h3 className="text-sm font-semibold text-gray-800 mb-3">Bank Account Details (Payout &amp; Settlement)</h3>
        <div className="space-y-3">
          <Input label="Bank Account Number *" value={form.bank_account_number} onChange={(e) => setForm({ ...form, bank_account_number: e.target.value })} placeholder="Enter bank account number" />
          <Input label="Confirm Account Number *" value={form.confirm_account_number} onChange={(e) => setForm({ ...form, confirm_account_number: e.target.value })} placeholder="Re-enter bank account number" />
          <Input label="Account Holder Name *" value={form.account_holder_name} onChange={(e) => setForm({ ...form, account_holder_name: e.target.value })} placeholder="Enter name as per bank passbook" />
          <Input label="Bank Name *" value={form.bank_name} onChange={(e) => setForm({ ...form, bank_name: e.target.value })} placeholder="Enter bank name" />
          <Input label="IFSC Code *" value={form.ifsc_code} onChange={(e) => setForm({ ...form, ifsc_code: e.target.value.toUpperCase() })} placeholder="Enter IFSC code" />
        </div>
      </div>

      <div className="border-t border-gray-100 pt-4">
        <label className="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
          <input
            type="checkbox"
            checked={form.incentive_enabled}
            onChange={(e) => setForm({ ...form, incentive_enabled: e.target.checked })}
            className="accent-primary"
          />
          Performance Incentive
        </label>
        <p className="text-xs text-gray-500 mt-1 mb-2">
          Pays a % commission on revenue from orders delivered that month, for dealers assigned to this employee (if linked to a sales hierarchy salesman).
        </p>
        {form.incentive_enabled && (
          <Input
            label="Commission Rate (%)"
            type="number"
            step="0.01"
            value={form.commission_rate}
            onChange={(e) => setForm({ ...form, commission_rate: e.target.value })}
            placeholder="e.g. 1.5"
          />
        )}
      </div>

      <div className="flex gap-3 pt-2">
        <Button
          onClick={() => {
            if (form.bank_account_number !== form.confirm_account_number) {
              toast.error('Account number and confirm account number do not match.');
              return;
            }
            onSubmit();
          }}
          loading={loading}
          className="flex-1 justify-center"
        >
          Save
        </Button>
        <Button variant="outline" onClick={onCancel} className="flex-1 justify-center">Cancel</Button>
      </div>
    </div>
  );
}

// KYC document uploads only make sense once an employee record exists (files
// attach to an ID) — shown inside the Edit modal, never at creation time.
// Fetches its own fresh copy of the employee (including kyc_documents, which
// the list endpoint doesn't return) rather than trusting the row passed in.
function EmployeeDocumentsPanel({ employee }: { employee: Employee }) {
  const qc = useQueryClient();
  const [aadhaarNumber, setAadhaarNumber] = useState('');
  const [panNumber, setPanNumber] = useState('');
  const [files, setFiles] = useState<Record<string, File | null>>({});

  const { data: detail } = useQuery({
    queryKey: ['employee-detail', employee.id],
    queryFn: () => hrService.employeeById(employee.id),
  });

  const checklist = detail?.kyc_documents ?? {};

  const saveMut = useMutation({
    mutationFn: () => {
      const fd = new FormData();
      if (aadhaarNumber) fd.append('aadhaar_number', aadhaarNumber);
      if (panNumber) fd.append('pan_number', panNumber);
      Object.entries(files).forEach(([key, file]) => { if (file) fd.append(key, file); });
      return hrService.uploadEmployeeDocuments(employee.id, fd);
    },
    onSuccess: () => {
      toast.success('Document(s) saved');
      setFiles({});
      setAadhaarNumber('');
      setPanNumber('');
      qc.invalidateQueries({ queryKey: ['employee-detail', employee.id] });
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to save documents'),
  });

  const download = async (type: string, label: string) => {
    const blob = await hrService.employeeDocument(employee.id, type);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${employee.name ?? 'employee'}_${label}`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const hasAnythingToSave = aadhaarNumber || panNumber || Object.values(files).some(Boolean);

  return (
    <div className="border-t border-gray-100 mt-4 pt-4">
      <h3 className="text-sm font-semibold text-gray-800 mb-1">Document Uploads (PDF / JPEG / PNG)</h3>
      <p className="text-xs text-gray-500 mb-3">Optional — can be added now or later. Aadhaar/PAN numbers are saved alongside their document.</p>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
        <Input label="Aadhaar Card No." value={aadhaarNumber} onChange={(e) => setAadhaarNumber(e.target.value)} placeholder={detail?.aadhaar_number ?? 'Enter Aadhaar number'} />
        <Input label="PAN Card No." value={panNumber} onChange={(e) => setPanNumber(e.target.value)} placeholder={detail?.pan_number ?? 'Enter PAN number'} />
      </div>

      <div className="space-y-2">
        {DOCUMENT_TYPES.map((doc) => (
          <div key={doc.key} className="flex items-center justify-between gap-3 bg-gray-50 rounded-lg px-3 py-2">
            <div className="flex items-center gap-2 min-w-0">
              {checklist[doc.key] && <CheckCircle2 size={14} className="text-green-600 shrink-0" />}
              <span className="text-xs font-medium text-gray-700 truncate">{doc.label}</span>
            </div>
            <div className="flex items-center gap-2 shrink-0">
              {checklist[doc.key] && (
                <button type="button" onClick={() => download(doc.key, doc.label)} className="text-primary hover:underline text-xs flex items-center gap-1">
                  <Download size={12} /> View
                </button>
              )}
              <label className="text-xs border border-gray-300 rounded-md px-2 py-1 bg-white hover:bg-gray-50 cursor-pointer flex items-center gap-1">
                <Upload size={12} />
                {files[doc.key] ? files[doc.key]!.name.slice(0, 14) : (checklist[doc.key] ? 'Replace' : 'Choose File')}
                <input
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png"
                  className="hidden"
                  onChange={(e) => setFiles({ ...files, [doc.key]: e.target.files?.[0] ?? null })}
                />
              </label>
            </div>
          </div>
        ))}
      </div>

      <Button
        variant="outline"
        className="mt-3 w-full justify-center"
        disabled={!hasAnythingToSave}
        loading={saveMut.isPending}
        onClick={() => saveMut.mutate()}
      >
        Save Document(s)
      </Button>
    </div>
  );
}

export default function EmployeesPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [departmentFilter, setDepartmentFilter] = useState('');
  const [showCreate, setShowCreate] = useState(false);
  const [editTarget, setEditTarget] = useState<Employee | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Employee | null>(null);
  const [form, setForm] = useState(EMPTY_FORM);

  const { data, isLoading } = useQuery({
    queryKey: ['employees', page, departmentFilter],
    queryFn: () => hrService.employees({ page: String(page), ...(departmentFilter && { department: departmentFilter }) }),
  });

  const createMut = useMutation({
    mutationFn: () => hrService.createEmployee({ ...form, salary: Number(form.salary), commission_rate: form.commission_rate ? Number(form.commission_rate) : null }),
    onSuccess: () => {
      toast.success('Employee added');
      qc.invalidateQueries({ queryKey: ['employees'] });
      setShowCreate(false);
      setForm(EMPTY_FORM);
    },
    onError: () => toast.error('Failed to add employee'),
  });

  const updateMut = useMutation({
    mutationFn: () => hrService.updateEmployee(editTarget!.id, { ...form, salary: Number(form.salary), commission_rate: form.commission_rate ? Number(form.commission_rate) : null }),
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
      incentive_enabled: emp.incentive_enabled ?? false,
      commission_rate: emp.commission_rate != null ? String(emp.commission_rate) : '',
      village_street: emp.village_street ?? '',
      post_office: emp.post_office ?? '',
      police_station: emp.police_station ?? '',
      district: emp.district ?? '',
      state: emp.state ?? '',
      pincode: emp.pincode ?? '',
      bank_account_number: emp.bank_account_number ?? '',
      confirm_account_number: emp.bank_account_number ?? '',
      account_holder_name: emp.account_holder_name ?? '',
      bank_name: emp.bank_name ?? '',
      ifsc_code: emp.ifsc_code ?? '',
    });
    setEditTarget(emp);
  };

  const employees: Employee[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };

  return (
    <div>
      <PageHeader
        title="Employees"
        subtitle={`${meta?.total ?? 0} staff members`}
        action={
          <div className="flex gap-2">
            <ExportButton filenameBase="employees" onExport={(format) => hrService.exportEmployees(format, { ...(departmentFilter && { department: departmentFilter }) })} />
            <Button onClick={() => { setForm(EMPTY_FORM); setShowCreate(true); }}><Plus size={15} /> Add Employee</Button>
          </div>
        }
      />

      <div className="mb-5 bg-blue-50 border border-blue-100 rounded-xl px-5 py-3 text-sm text-gray-700 space-y-1">
        <p>This is <span className="font-semibold">HR data</span> — salary, department, attendance, payroll. It has nothing to do with logging in.</p>
        <p>It's separate from <span className="font-semibold">Staff Users</span> (login access). Adding someone here doesn't give them a login, and vice versa.</p>
      </div>

      <div className="mb-5 max-w-xs">
        <Select
          value={departmentFilter}
          onChange={(e) => { setDepartmentFilter(e.target.value); setPage(1); }}
          options={[{ value: '', label: 'All Departments' }, ...DEPARTMENTS.map((d) => ({ value: d, label: d.charAt(0).toUpperCase() + d.slice(1) }))]}
        />
      </div>

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
                { key: 'incentive', header: 'Incentive', render: (e) => e.incentive_enabled ? <Badge label={`${e.commission_rate ?? 0}%`} color="green" /> : <span className="text-xs text-gray-400">—</span> },
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
        <EmployeeForm form={form} setForm={setForm} onSubmit={() => createMut.mutate()} onCancel={() => { setShowCreate(false); setForm(EMPTY_FORM); }} loading={createMut.isPending} />
      </Modal>

      {/* Edit Employee Modal */}
      <Modal open={!!editTarget} onClose={() => setEditTarget(null)} title={`Edit — ${editTarget?.name}`}>
        <EmployeeForm form={form} setForm={setForm} onSubmit={() => updateMut.mutate()} onCancel={() => setEditTarget(null)} loading={updateMut.isPending} />
        {editTarget && <EmployeeDocumentsPanel employee={editTarget} />}
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
