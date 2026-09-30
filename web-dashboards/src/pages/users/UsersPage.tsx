import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { UserPlus, Copy } from 'lucide-react';
import toast from 'react-hot-toast';
import { adminService, hrService } from '../../services';
import { Button, Badge, Table, Pagination, Modal, Input, Select, PageHeader, Card, Spinner, ExportButton } from '../../components/ui';
import { STATE_NAMES, districtsForState } from '../../data/statesDistricts';
import { DocumentUploadFields } from '../hr/EmployeesPage';
import type { User, Role } from '../../types';

const ROLES: Role[] = [
  'super_admin', 'sales', 'state_manager', 'area_manager', 'district_manager',
  'warehouse_staff', 'warehouse_manager', 'qc_engineer', 'product_manager', 'packing_staff', 'placement_staff',
  'accounts', 'hr_manager', 'logistics',
];

// These log into the mobile app with Sales ID + password (both required) —
// so unlike other roles, a password is mandatory when creating one here.
const MOBILE_SALES_ROLES: Role[] = ['sales', 'state_manager', 'area_manager', 'district_manager'];

const EMPTY_FORM = {
  name: '', phone: '', email: '', password: '', role: 'sales' as Role,
  department: '', designation: '', employment_type: 'full_time', shift: 'morning',
  salary: '', joining_date: '',
  village_street: '', post_office: '', police_station: '', district: '', state: '', pincode: '',
  bank_account_number: '', confirm_account_number: '', account_holder_name: '', bank_name: '', ifsc_code: '',
};

export default function UsersPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [showCreate, setShowCreate] = useState(false);
  const [selected, setSelected] = useState<User | null>(null);
  const [newRole, setNewRole] = useState('');
  const [form, setForm] = useState(EMPTY_FORM);
  const [newUserCode, setNewUserCode] = useState<{ name: string; code: string } | null>(null);
  const [roleFilter, setRoleFilter] = useState('');
  const [searchFilter, setSearchFilter] = useState('');
  const [docAadhaarNumber, setDocAadhaarNumber] = useState('');
  const [docPanNumber, setDocPanNumber] = useState('');
  const [docFiles, setDocFiles] = useState<Record<string, File | null>>({});

  const resetCreateForm = () => {
    setForm(EMPTY_FORM);
    setDocAadhaarNumber('');
    setDocPanNumber('');
    setDocFiles({});
  };

  const { data, isLoading } = useQuery({
    queryKey: ['admin-users', page, roleFilter, searchFilter],
    queryFn: () => adminService.users({
      page: String(page),
      ...(roleFilter && { role: roleFilter }),
      ...(searchFilter && { search: searchFilter }),
    }),
  });

  const createMut = useMutation({
    mutationFn: async () => {
      // A Staff User always gets a linked HR/Employee record — these details
      // are optional and can be filled in later from Employees if not known yet.
      const payload: Record<string, unknown> = {
        name: form.name, phone: form.phone, email: form.email || null, password: form.password || null, role: form.role,
        department: form.department || null, designation: form.designation || null,
        employment_type: form.employment_type, shift: form.shift,
        salary: form.salary ? Number(form.salary) : null, joining_date: form.joining_date || null,
        village_street: form.village_street || null, post_office: form.post_office || null,
        police_station: form.police_station || null, district: form.district || null,
        state: form.state || null, pincode: form.pincode || null,
        bank_account_number: form.bank_account_number || null, confirm_account_number: form.confirm_account_number || null,
        account_holder_name: form.account_holder_name || null, bank_name: form.bank_name || null, ifsc_code: form.ifsc_code || null,
      };

      const result: any = await adminService.createUser(payload);

      const hasDocs = docAadhaarNumber || docPanNumber || Object.values(docFiles).some(Boolean);
      if (hasDocs && result?.employee_id) {
        const fd = new FormData();
        if (docAadhaarNumber) fd.append('aadhaar_number', docAadhaarNumber);
        if (docPanNumber) fd.append('pan_number', docPanNumber);
        Object.entries(docFiles).forEach(([key, file]) => { if (file) fd.append(key, file); });
        await hrService.uploadEmployeeDocuments(result.employee_id, fd);
      }

      return result;
    },
    onSuccess: (response: any) => {
      const uniqueCode = response?.unique_code || response?.data?.unique_code;
      setNewUserCode({ name: form.name, code: uniqueCode });
      if (uniqueCode) {
        navigator.clipboard.writeText(uniqueCode);
      }
      qc.invalidateQueries({ queryKey: ['admin-users'] });
      setShowCreate(false);
      resetCreateForm();
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to create user'),
  });

  const roleMut = useMutation({
    mutationFn: () => adminService.changeRole(selected!.id, newRole),
    onSuccess: () => { toast.success('Role updated'); qc.invalidateQueries({ queryKey: ['admin-users'] }); setSelected(null); },
    onError: () => toast.error('Failed to update role'),
  });

  const toggleMut = useMutation({
    mutationFn: async (u: User) => {
      if (!u.is_active) {
        return adminService.activate(u.id);
      }
      const res: any = await adminService.deactivate(u.id);
      if (res?.data?.needs_confirmation) {
        if (window.confirm(res.message)) {
          return adminService.deactivate(u.id, true);
        }
        return res;
      }
      return res;
    },
    onSuccess: (res: any) => {
      if (res?.data?.needs_confirmation) return; // user cancelled the cascade prompt
      toast.success('User updated');
      qc.invalidateQueries({ queryKey: ['admin-users'] });
    },
    onError: () => toast.error('Failed'),
  });

  const users: User[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };

  return (
    <div>
      <PageHeader
        title="Staff Users"
        subtitle="All admin and staff users"
        action={
          <div className="flex gap-2">
            <ExportButton
              filenameBase="staff_users"
              onExport={(format) => adminService.exportUsers(format, { ...(roleFilter && { role: roleFilter }), ...(searchFilter && { search: searchFilter }) })}
            />
            <Button onClick={() => setShowCreate(true)}><UserPlus size={15} /> Add User</Button>
          </div>
        }
      />

      <div className="mb-5 bg-blue-50 border border-blue-100 rounded-xl px-5 py-3 text-sm text-gray-700 space-y-1">
        <p>This is <span className="font-semibold">login access</span> — who can sign in and what they're allowed to do (role, permissions).</p>
        <p>It's separate from <span className="font-semibold">Employees</span> (HR records — salary, department, attendance), though a linked HR record is created automatically whenever you add someone here.</p>
      </div>

      <div className="mb-5 flex flex-wrap gap-3">
        <Input
          placeholder="Search by name..."
          value={searchFilter}
          onChange={(e) => { setSearchFilter(e.target.value); setPage(1); }}
          className="max-w-[220px]"
        />
        <Select
          value={roleFilter}
          onChange={(e) => { setRoleFilter(e.target.value); setPage(1); }}
          options={[{ value: '', label: 'All Roles' }, ...ROLES.map((r) => ({ value: r, label: r.replace(/_/g, ' ') }))]}
        />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'name', header: 'Name', render: (u) => <span className="font-medium">{u.name}</span> },
                { key: 'phone', header: 'Phone' },
                { key: 'email', header: 'Email', render: (u) => u.email || '—' },
                { key: 'unique_code', header: 'Employee ID', render: (u: any) => u.unique_code ? <code className="text-xs bg-gray-100 px-2 py-1 rounded font-semibold">{u.unique_code}</code> : '—' },
                { key: 'role', header: 'Role', render: (u) => <Badge label={u.role.replace(/_/g, ' ')} color="blue" /> },
                { key: 'is_active', header: 'Status', render: (u) => <Badge label={u.is_active ? 'Active' : 'Inactive'} color={u.is_active ? 'green' : 'red'} /> },
                {
                  key: 'actions', header: '', render: (u) => (
                    <div className="flex gap-2">
                      <Button size="sm" variant="outline" onClick={(e) => { e.stopPropagation(); setSelected(u); setNewRole(u.role); }}>Role</Button>
                      <Button size="sm" variant={u.is_active ? 'danger' : 'secondary'} onClick={(e) => { e.stopPropagation(); toggleMut.mutate(u); }}>
                        {u.is_active ? 'Deactivate' : 'Activate'}
                      </Button>
                    </div>
                  ),
                },
              ]}
              data={users}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Create User Modal */}
      <Modal open={showCreate} onClose={() => { setShowCreate(false); resetCreateForm(); }} title="Add User" width="max-w-2xl">
        <div className="space-y-4">
          <Input label="Full Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <Input label="Phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          <Input label="Email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          <Select
            label="Role"
            value={form.role}
            onChange={(e) => setForm({ ...form, role: e.target.value as Role })}
            options={ROLES.map((r) => ({ value: r, label: r.replace(/_/g, ' ') }))}
          />
          <Input
            label={MOBILE_SALES_ROLES.includes(form.role) ? 'Password *' : 'Password'}
            type="password"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            placeholder={
              MOBILE_SALES_ROLES.includes(form.role)
                ? 'Min 8 characters — required for Sales ID + Password login'
                : 'Min 8 characters — used to login to this dashboard'
            }
          />

          <div className="bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 -mt-1">
            <p className="text-xs text-gray-600">
              Every Staff User also gets a linked HR/Employee record automatically. Everything below is optional —
              fill in what you know now, or leave it blank and complete it later from Employees.
            </p>
          </div>

          <div className="border-t border-gray-100 pt-4">
            <h3 className="text-sm font-semibold text-gray-800 mb-3">Employment Details</h3>
            <div className="space-y-3">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Input label="Department" value={form.department} onChange={(e) => setForm({ ...form, department: e.target.value })} />
                <Input label="Designation" value={form.designation} onChange={(e) => setForm({ ...form, designation: e.target.value })} />
              </div>
              <Input label="Monthly Salary (₹)" type="number" value={form.salary} onChange={(e) => setForm({ ...form, salary: e.target.value })} />
              <Input label="Joining Date" type="date" value={form.joining_date} onChange={(e) => setForm({ ...form, joining_date: e.target.value })} />
            </div>
          </div>

          <div className="border-t border-gray-100 pt-4">
            <h3 className="text-sm font-semibold text-gray-800 mb-3">Address Details</h3>
            <div className="space-y-3">
              <Input label="Village / Street" value={form.village_street} onChange={(e) => setForm({ ...form, village_street: e.target.value })} />
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Input label="Post Office (P.O)" value={form.post_office} onChange={(e) => setForm({ ...form, post_office: e.target.value })} />
                <Input label="Police Station (P.S)" value={form.police_station} onChange={(e) => setForm({ ...form, police_station: e.target.value })} />
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
              <Input label="Pin Code" value={form.pincode} onChange={(e) => setForm({ ...form, pincode: e.target.value })} />
            </div>
          </div>

          <div className="border-t border-gray-100 pt-4">
            <h3 className="text-sm font-semibold text-gray-800 mb-3">Bank Account Details (Payout &amp; Settlement)</h3>
            <div className="space-y-3">
              <Input label="Bank Account Number" value={form.bank_account_number} onChange={(e) => setForm({ ...form, bank_account_number: e.target.value })} />
              <Input label="Confirm Account Number" value={form.confirm_account_number} onChange={(e) => setForm({ ...form, confirm_account_number: e.target.value })} />
              <Input label="Account Holder Name" value={form.account_holder_name} onChange={(e) => setForm({ ...form, account_holder_name: e.target.value })} />
              <Input label="Bank Name" value={form.bank_name} onChange={(e) => setForm({ ...form, bank_name: e.target.value })} />
              <Input label="IFSC Code" value={form.ifsc_code} onChange={(e) => setForm({ ...form, ifsc_code: e.target.value.toUpperCase() })} />
            </div>
          </div>

          <div className="border-t border-gray-100 pt-4">
            <h3 className="text-sm font-semibold text-gray-800 mb-1">Document Uploads (PDF / JPEG / PNG)</h3>
            <p className="text-xs text-gray-500 mb-3">Optional — attach any that are ready now; the rest can be added later from Employees.</p>
            <DocumentUploadFields
              aadhaarNumber={docAadhaarNumber}
              panNumber={docPanNumber}
              onAadhaarNumberChange={setDocAadhaarNumber}
              onPanNumberChange={setDocPanNumber}
              files={docFiles}
              onFileChange={(key, file) => setDocFiles({ ...docFiles, [key]: file })}
            />
          </div>

          <div className="flex gap-3 pt-2">
            <Button
              onClick={() => {
                if (MOBILE_SALES_ROLES.includes(form.role) && form.password.length < 8) {
                  toast.error('Password must be at least 8 characters for this role.');
                  return;
                }
                if (form.bank_account_number && form.bank_account_number !== form.confirm_account_number) {
                  toast.error('Account number and confirm account number do not match.');
                  return;
                }
                createMut.mutate();
              }}
              loading={createMut.isPending}
              className="flex-1 justify-center"
            >
              Create
            </Button>
            <Button variant="outline" onClick={() => { setShowCreate(false); resetCreateForm(); }} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* Change Role Modal */}
      <Modal open={!!selected} onClose={() => setSelected(null)} title={`Change Role — ${selected?.name}`}>
        <div className="space-y-4">
          <Select
            label="New Role"
            value={newRole}
            onChange={(e) => setNewRole(e.target.value)}
            options={(selected?.role && !ROLES.includes(selected.role) ? [selected.role, ...ROLES] : ROLES)
              .map((r) => ({ value: r, label: r.replace(/_/g, ' ') }))}
          />
          {selected?.role && !ROLES.includes(selected.role) && (
            <p className="text-xs text-amber-600">
              This user's current role ("{selected.role.replace(/_/g, ' ')}") is a legacy hierarchy role no longer assignable here — pick a real role below to move them off it.
            </p>
          )}
          <div className="flex gap-3 pt-2">
            <Button onClick={() => roleMut.mutate()} loading={roleMut.isPending} className="flex-1 justify-center">Save</Button>
            <Button variant="outline" onClick={() => setSelected(null)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* New User Unique Code Modal */}
      <Modal open={!!newUserCode} onClose={() => setNewUserCode(null)} title="✅ User Created Successfully">
        {newUserCode && (
          <div className="space-y-4">
            <div className="bg-green-50 border border-green-200 p-4 rounded-lg">
              <p className="text-sm text-gray-600 mb-2">New user created:</p>
              <p className="text-lg font-bold text-green-700">{newUserCode.name}</p>
            </div>

            <div className="bg-blue-50 border border-blue-200 p-4 rounded-lg">
              <p className="text-sm text-gray-600 mb-2">Employee ID (copied to clipboard):</p>
              <div className="flex items-center justify-between bg-white p-3 rounded border border-blue-200">
                <code className="text-lg font-bold text-blue-600">{newUserCode.code}</code>
                <button
                  onClick={() => {
                    navigator.clipboard.writeText(newUserCode.code);
                    toast.success('Copied!');
                  }}
                  className="text-blue-600 hover:text-blue-800"
                >
                  <Copy size={18} />
                </button>
              </div>
            </div>

            <div className="bg-amber-50 border border-amber-200 p-3 rounded text-sm text-amber-800">
              ⚠️ <strong>Important:</strong> Share this code with the user. They will need it when creating subordinates in the sales hierarchy.
            </div>

            <Button onClick={() => setNewUserCode(null)} className="w-full justify-center">Done</Button>
          </div>
        )}
      </Modal>
    </div>
  );
}
