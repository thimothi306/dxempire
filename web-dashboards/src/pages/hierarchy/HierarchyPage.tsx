import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, ChevronRight, Users, TrendingUp, Trash2, Pencil, Ban } from 'lucide-react';
import toast from 'react-hot-toast';
import { hierarchyService } from '../../services/newModules';
import { hrService } from '../../services';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Modal, Input, Select, fmtINR, ExportButton } from '../../components/ui';
import { STATE_NAMES, districtsForState } from '../../data/statesDistricts';
import { DocumentUploadFields } from '../hr/EmployeesPage';

const ROLES = [
  { value: 'ceo',              label: 'CEO' },
  { value: 'state_manager',    label: 'State Manager' },
  { value: 'area_manager',     label: 'Area Manager' },
  { value: 'district_manager', label: 'District Manager' },
  { value: 'salesman',         label: 'Salesman' },
];

const ROLE_COLORS: Record<string, string> = {
  ceo: 'purple', state_manager: 'blue', area_manager: 'green',
  district_manager: 'yellow', salesman: 'orange',
};

const EMPTY_FORM = { name: '', phone: '', email: '', hierarchy_role: 'salesman', parent_unique_code: '', state: '', area: '', district: '' };

const EMPTY_ADD_FORM = {
  mode: 'existing' as 'existing' | 'new',
  existing_user_id: '',
  // Shared by both modes
  hierarchy_role: 'salesman',
  parent_unique_code: '',
  state: '', area: '', district: '',
  // "New person" only — Basic
  name: '', phone: '', email: '', password: '',
  // "New person" only — HR/Employee
  department: 'sales', designation: '', employment_type: 'full_time', shift: 'morning',
  salary: '', joining_date: '',
  village_street: '', post_office: '', police_station: '', pincode: '',
  // "New person" only — Bank (required)
  bank_account_number: '', confirm_account_number: '', account_holder_name: '', bank_name: '', ifsc_code: '',
};
type AddMemberFormState = typeof EMPTY_ADD_FORM;

// Recursive — the API loads 4 levels of children per node, so a manager's
// downline is a real tree, not a flat list of direct reports.
function DownlineNode({ node, depth }: { node: any; depth: number }) {
  const children = node.children ?? [];
  return (
    <div>
      <div className="flex justify-between text-xs bg-gray-50 px-3 py-2 rounded" style={{ marginLeft: depth * 16 }}>
        <span><code className="text-primary font-bold">{node.tree_id}</code> — {node.name}</span>
        <Badge label={node.hierarchy_role.replace(/_/g, ' ')} color={ROLE_COLORS[node.hierarchy_role]} />
      </div>
      {children.length > 0 && (
        <div className="mt-1 space-y-1">
          {children.map((child: any) => <DownlineNode key={child.id} node={child} depth={depth + 1} />)}
        </div>
      )}
    </div>
  );
}
type HierarchyFormState = typeof EMPTY_FORM;

// Defined OUTSIDE the page component: an inline component definition would be
// recreated on every render, causing React to remount the inputs (and lose
// focus) after every keystroke.
function MemberForm({
  form, setForm, onSubmit, onCancel, loading,
}: {
  form: HierarchyFormState;
  setForm: (f: HierarchyFormState) => void;
  onSubmit: () => void;
  onCancel: () => void;
  loading: boolean;
}) {
  return (
    <div className="space-y-3">
      <Input label="Full Name *" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required />
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Input label="Phone" value={form.phone} onChange={e => setForm({ ...form, phone: e.target.value })} />
        <Input label="Email" type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} />
      </div>
      <Select label="Role *" value={form.hierarchy_role} onChange={e => setForm({ ...form, hierarchy_role: e.target.value })}
        options={ROLES} />

      <div className="bg-blue-50 border border-blue-200 p-3 rounded-lg">
        <Input
          label="Parent's Unique Code *"
          value={form.parent_unique_code}
          onChange={e => setForm({ ...form, parent_unique_code: e.target.value })}
          placeholder="e.g., SM001, DM001, AM001"
          required
        />
        <p className="text-xs text-blue-700 mt-2">👤 Enter the parent's unique code (e.g., SM001). Leave empty only for top-level members.</p>
      </div>

      {form.parent_unique_code && (
        <div className="bg-green-50 border border-green-200 p-2 rounded text-sm text-green-700">
          ✓ Parent code: <code className="font-bold">{form.parent_unique_code}</code>
        </div>
      )}

      <Select label="State" value={form.state} onChange={e => setForm({ ...form, state: e.target.value, district: '' })}
        options={[{ value: '', label: 'Select state...' }, ...STATE_NAMES.map(s => ({ value: s, label: s }))]} />
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Input label="Area" value={form.area} onChange={e => setForm({ ...form, area: e.target.value })} placeholder="e.g. Bangalore Zone" />
        <Select label="District" value={form.district} onChange={e => setForm({ ...form, district: e.target.value })}
          disabled={!form.state}
          options={[{ value: '', label: form.state ? 'Select district...' : 'Select a state first' }, ...districtsForState(form.state).map(d => ({ value: d, label: d }))]} />
      </div>
      <div className="flex gap-3 pt-2">
        <Button onClick={onSubmit} loading={loading} className="flex-1 justify-center">Save</Button>
        <Button variant="outline" onClick={onCancel} className="flex-1 justify-center">Cancel</Button>
      </div>
    </div>
  );
}

// The "Add Member" flow has two modes:
// - "existing": place someone who's already a Staff User into the hierarchy —
//   just this one row, linked to their account. For reorganizations/promotions.
// - "new": onboard someone who exists nowhere yet — creates their Staff User
//   login, an Employee HR record (with bank details + optional documents),
//   and the hierarchy placement together, all sharing one generated code.
function AddMemberForm({
  form, setForm, availableUsers, onSubmit, onCancel, loading,
  docAadhaarNumber, docPanNumber, setDocAadhaarNumber, setDocPanNumber, docFiles, setDocFiles,
}: {
  form: AddMemberFormState;
  setForm: (f: AddMemberFormState) => void;
  availableUsers: { id: number; name: string; unique_code: string; role: string; phone?: string }[];
  onSubmit: () => void;
  onCancel: () => void;
  loading: boolean;
  docAadhaarNumber: string;
  docPanNumber: string;
  setDocAadhaarNumber: (v: string) => void;
  setDocPanNumber: (v: string) => void;
  docFiles: Record<string, File | null>;
  setDocFiles: (f: Record<string, File | null>) => void;
}) {
  return (
    <div className="space-y-3">
      <div className="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
        {([
          { key: 'existing', label: 'Existing Person' },
          { key: 'new', label: 'New Person' },
        ] as const).map(t => (
          <button
            key={t.key}
            onClick={() => setForm({ ...form, mode: t.key })}
            className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${form.mode === t.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {form.mode === 'existing' ? (
        <>
          <p className="text-xs text-gray-500">Pick someone who's already a Staff User — no new login is created, just their hierarchy placement. If they don't already have an HR/Employee record, a blank one is created automatically so they show up in Employees too.</p>
          <Select
            label="Staff User *"
            value={form.existing_user_id}
            onChange={e => setForm({ ...form, existing_user_id: e.target.value })}
            options={[{ value: '', label: 'Select a staff user...' }, ...availableUsers.map(u => ({ value: String(u.id), label: `${u.name} (${u.unique_code}) — ${u.role.replace(/_/g, ' ')}` }))]}
          />
        </>
      ) : (
        <>
          <p className="text-xs text-gray-500">A genuinely new hire — this creates their login, HR record, and hierarchy placement together.</p>
          <Input label="Full Name *" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required />
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Input label="Phone" value={form.phone} onChange={e => setForm({ ...form, phone: e.target.value })} />
            <Input label="Email" type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} />
          </div>
          <Input
            label="Password *"
            type="password"
            value={form.password}
            onChange={e => setForm({ ...form, password: e.target.value })}
            placeholder="Min 8 characters — used for Sales ID + Password login"
          />
        </>
      )}

      <Select label="Role *" value={form.hierarchy_role} onChange={e => setForm({ ...form, hierarchy_role: e.target.value })}
        options={ROLES} />

      <div className="bg-blue-50 border border-blue-200 p-3 rounded-lg">
        <Input
          label="Parent's Unique Code"
          value={form.parent_unique_code}
          onChange={e => setForm({ ...form, parent_unique_code: e.target.value })}
          placeholder="e.g., SM001, DM001, AM001"
        />
        <p className="text-xs text-blue-700 mt-2">👤 Enter the parent's unique code. Leave empty only for top-level members.</p>
      </div>

      <Select label="State" value={form.state} onChange={e => setForm({ ...form, state: e.target.value, district: '' })}
        options={[{ value: '', label: 'Select state...' }, ...STATE_NAMES.map(s => ({ value: s, label: s }))]} />
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Input label="Area" value={form.area} onChange={e => setForm({ ...form, area: e.target.value })} placeholder="e.g. Bangalore Zone" />
        <Select label="District" value={form.district} onChange={e => setForm({ ...form, district: e.target.value })}
          disabled={!form.state}
          options={[{ value: '', label: form.state ? 'Select district...' : 'Select a state first' }, ...districtsForState(form.state).map(d => ({ value: d, label: d }))]} />
      </div>

      {form.mode === 'new' && (
        <>
          <div className="border-t border-gray-100 pt-4">
            <h3 className="text-sm font-semibold text-gray-800 mb-3">Employment Details</h3>
            <div className="space-y-3">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Input label="Department" value={form.department} onChange={e => setForm({ ...form, department: e.target.value })} />
                <Input label="Designation" value={form.designation} onChange={e => setForm({ ...form, designation: e.target.value })} />
              </div>
              <Input label="Monthly Salary (₹) *" type="number" value={form.salary} onChange={e => setForm({ ...form, salary: e.target.value })} />
              <Input label="Joining Date *" type="date" value={form.joining_date} onChange={e => setForm({ ...form, joining_date: e.target.value })} />
            </div>
          </div>

          <div className="border-t border-gray-100 pt-4">
            <h3 className="text-sm font-semibold text-gray-800 mb-3">Address Details</h3>
            <div className="space-y-3">
              <Input label="Village / Street" value={form.village_street} onChange={e => setForm({ ...form, village_street: e.target.value })} />
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Input label="Post Office (P.O)" value={form.post_office} onChange={e => setForm({ ...form, post_office: e.target.value })} />
                <Input label="Police Station (P.S)" value={form.police_station} onChange={e => setForm({ ...form, police_station: e.target.value })} />
              </div>
              <Input label="Pin Code" value={form.pincode} onChange={e => setForm({ ...form, pincode: e.target.value })} />
            </div>
          </div>

          <div className="border-t border-gray-100 pt-4">
            <h3 className="text-sm font-semibold text-gray-800 mb-3">Bank Account Details (Payout &amp; Settlement)</h3>
            <div className="space-y-3">
              <Input label="Bank Account Number *" value={form.bank_account_number} onChange={e => setForm({ ...form, bank_account_number: e.target.value })} />
              <Input label="Confirm Account Number *" value={form.confirm_account_number} onChange={e => setForm({ ...form, confirm_account_number: e.target.value })} />
              <Input label="Account Holder Name *" value={form.account_holder_name} onChange={e => setForm({ ...form, account_holder_name: e.target.value })} />
              <Input label="Bank Name *" value={form.bank_name} onChange={e => setForm({ ...form, bank_name: e.target.value })} />
              <Input label="IFSC Code *" value={form.ifsc_code} onChange={e => setForm({ ...form, ifsc_code: e.target.value.toUpperCase() })} />
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
        </>
      )}

      <div className="flex gap-3 pt-2">
        <Button
          onClick={() => {
            if (form.mode === 'new' && form.password.length < 8) {
              toast.error('Password must be at least 8 characters.');
              return;
            }
            if (form.mode === 'new' && form.bank_account_number !== form.confirm_account_number) {
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

export default function HierarchyPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [roleFilter, setRoleFilter] = useState('');
  const [stateFilter, setStateFilter] = useState('');
  const [districtFilter, setDistrictFilter] = useState('');
  const [showCreate, setShowCreate] = useState(false);
  const [editTarget, setEditTarget] = useState<any>(null);
  const [selectedNode, setSelectedNode] = useState<any>(null);
  const [activeTab, setActiveTab] = useState<'details' | 'downline' | 'performance'>('details');
  const [form, setForm] = useState(EMPTY_FORM);
  const [addForm, setAddForm] = useState(EMPTY_ADD_FORM);
  const [addDocAadhaarNumber, setAddDocAadhaarNumber] = useState('');
  const [addDocPanNumber, setAddDocPanNumber] = useState('');
  const [addDocFiles, setAddDocFiles] = useState<Record<string, File | null>>({});

  const resetAddForm = () => {
    setAddForm(EMPTY_ADD_FORM);
    setAddDocAadhaarNumber('');
    setAddDocPanNumber('');
    setAddDocFiles({});
  };

  const { data: availableUsersData } = useQuery({
    queryKey: ['hierarchy-available-users'],
    queryFn: () => hierarchyService.availableUsers(),
    enabled: showCreate,
  });
  const availableUsers: any[] = Array.isArray(availableUsersData) ? availableUsersData : [];

  const { data, isLoading } = useQuery({
    queryKey: ['hierarchy', page, roleFilter, stateFilter, districtFilter],
    queryFn: () => hierarchyService.list({
      page: String(page),
      ...(roleFilter && { role: roleFilter }),
      ...(stateFilter && { state: stateFilter }),
      ...(districtFilter && { district: districtFilter }),
    }),
  });

  const { data: downlineData } = useQuery({
    queryKey: ['hierarchy-downline', selectedNode?.id],
    queryFn: () => hierarchyService.downline(selectedNode!.id),
    enabled: !!selectedNode && activeTab === 'downline',
  });

  const { data: perfData } = useQuery({
    queryKey: ['hierarchy-performance', selectedNode?.id],
    queryFn: () => hierarchyService.performance(selectedNode!.id),
    enabled: !!selectedNode && activeTab === 'performance',
  });

  const createMut = useMutation({
    mutationFn: async () => {
      const payload = addForm.mode === 'existing'
        ? {
            mode: 'existing',
            user_id: Number(addForm.existing_user_id),
            hierarchy_role: addForm.hierarchy_role,
            parent_unique_code: addForm.parent_unique_code || null,
            state: addForm.state || null,
            area: addForm.area || null,
            district: addForm.district || null,
          }
        : {
            mode: 'new',
            name: addForm.name, phone: addForm.phone || null, email: addForm.email || null,
            password: addForm.password,
            hierarchy_role: addForm.hierarchy_role,
            parent_unique_code: addForm.parent_unique_code || null,
            state: addForm.state || null, area: addForm.area || null, district: addForm.district || null,
            department: addForm.department || null, designation: addForm.designation || null,
            employment_type: addForm.employment_type, shift: addForm.shift,
            salary: Number(addForm.salary), joining_date: addForm.joining_date,
            village_street: addForm.village_street || null, post_office: addForm.post_office || null,
            police_station: addForm.police_station || null, pincode: addForm.pincode || null,
            bank_account_number: addForm.bank_account_number, confirm_account_number: addForm.confirm_account_number,
            account_holder_name: addForm.account_holder_name, bank_name: addForm.bank_name, ifsc_code: addForm.ifsc_code,
          };

      const result: any = await hierarchyService.create(payload);

      const hasDocs = addDocAadhaarNumber || addDocPanNumber || Object.values(addDocFiles).some(Boolean);
      if (addForm.mode === 'new' && hasDocs && result?.employee_id) {
        const fd = new FormData();
        if (addDocAadhaarNumber) fd.append('aadhaar_number', addDocAadhaarNumber);
        if (addDocPanNumber) fd.append('pan_number', addDocPanNumber);
        Object.entries(addDocFiles).forEach(([key, file]) => { if (file) fd.append(key, file); });
        await hrService.uploadEmployeeDocuments(result.employee_id, fd);
      }

      return result;
    },
    onSuccess: () => {
      toast.success('Member added');
      qc.invalidateQueries({ queryKey: ['hierarchy'] });
      qc.invalidateQueries({ queryKey: ['hierarchy-all'] });
      qc.invalidateQueries({ queryKey: ['hierarchy-available-users'] });
      setShowCreate(false);
      resetAddForm();
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to add member'),
  });

  const updateMut = useMutation({
    mutationFn: () => hierarchyService.update(editTarget.id, { ...form, parent_unique_code: form.parent_unique_code || null }),
    onSuccess: () => { toast.success('Member updated'); qc.invalidateQueries({ queryKey: ['hierarchy'] }); setEditTarget(null); },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to update'),
  });

  const deactivateMut = useMutation({
    mutationFn: async (node: any) => {
      const res: any = await hierarchyService.remove(node.id);
      if (res?.data?.needs_confirmation) {
        if (window.confirm(res.message)) {
          return hierarchyService.remove(node.id, true);
        }
        return res;
      }
      return res;
    },
    onSuccess: (res: any) => {
      if (res?.data?.needs_confirmation) return; // user cancelled the cascade prompt
      toast.success('Member deactivated');
      qc.invalidateQueries({ queryKey: ['hierarchy'] });
    },
    onError: () => toast.error('Failed'),
  });

  const forceDeleteMut = useMutation({
    mutationFn: (id: number) => hierarchyService.forceRemove(id),
    onSuccess: () => {
      toast.success('Member permanently deleted');
      qc.invalidateQueries({ queryKey: ['hierarchy'] });
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to delete'),
  });

  const activateMut = useMutation({
    mutationFn: (id: number) => hierarchyService.update(id, { is_active: true }),
    onSuccess: () => {
      toast.success('Member reactivated');
      qc.invalidateQueries({ queryKey: ['hierarchy'] });
    },
    onError: () => toast.error('Failed to reactivate'),
  });

  const openEdit = (node: any) => {
    setForm({ name: node.name, phone: node.phone ?? '', email: node.email ?? '', hierarchy_role: node.hierarchy_role, parent_unique_code: node.parent?.unique_code ?? '', state: node.state ?? '', area: node.area ?? '', district: node.district ?? '' });
    setEditTarget(node);
  };

  const nodes: any[] = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div>
      <PageHeader
        title="Sales Hierarchy"
        subtitle={`${meta?.total ?? 0} members`}
        action={
          <div className="flex gap-2">
            <ExportButton
              filenameBase="hierarchy"
              onExport={(format) => hierarchyService.export(format, {
                ...(roleFilter && { role: roleFilter }),
                ...(stateFilter && { state: stateFilter }),
                ...(districtFilter && { district: districtFilter }),
              })}
            />
            <Button onClick={() => { resetAddForm(); setShowCreate(true); }}><Plus size={15} /> Add Member</Button>
          </div>
        }
      />

      {/* Filters */}
      <div className="flex gap-3 mb-5">
        <Select value={roleFilter} onChange={e => { setRoleFilter(e.target.value); setPage(1); }}
          options={[{ value: '', label: 'All Roles' }, ...ROLES]} />
        <Select value={stateFilter} onChange={e => { setStateFilter(e.target.value); setDistrictFilter(''); setPage(1); }}
          options={[{ value: '', label: 'All States' }, ...STATE_NAMES.map(s => ({ value: s, label: s }))]} />
        <Select value={districtFilter} onChange={e => { setDistrictFilter(e.target.value); setPage(1); }}
          disabled={!stateFilter}
          options={[{ value: '', label: stateFilter ? 'All Districts' : 'Select a state first' }, ...districtsForState(stateFilter).map(d => ({ value: d, label: d }))]} />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'tree_id', header: 'Employee ID', render: n => <code className="text-xs font-bold text-primary">{n.tree_id}</code> },
                { key: 'name', header: 'Name', render: n => <span className="font-medium">{n.name}</span> },
                { key: 'hierarchy_role', header: 'Role', render: n => <Badge label={n.hierarchy_role.replace(/_/g, ' ')} color={ROLE_COLORS[n.hierarchy_role] ?? 'gray'} /> },
                { key: 'parent', header: 'Reports To', render: (n: any) => n.parent ? <span className="text-xs text-gray-500"><code>{n.parent.unique_code}</code> — {n.parent.name}</span> : '—' },
                { key: 'state', header: 'Territory', render: n => <span className="text-xs">{[n.state, n.area, n.district].filter(Boolean).join(' › ')}</span> },
                { key: 'phone', header: 'Phone', render: n => n.phone ?? '—' },
                { key: 'is_active', header: 'Status', render: n => <Badge label={n.is_active ? 'Active' : 'Deactivated'} color={n.is_active ? 'green' : 'gray'} /> },
                {
                  key: 'actions', header: '', render: n => (
                    <div className="flex gap-2">
                      <Button size="sm" variant="outline" onClick={e => { e.stopPropagation(); setSelectedNode(n); setActiveTab('details'); }}>
                        <ChevronRight size={13} />
                      </Button>
                      <Button size="sm" variant="outline" onClick={e => { e.stopPropagation(); openEdit(n); }}>
                        <Pencil size={13} />
                      </Button>
                      {n.is_active ? (
                        <Button
                          size="sm" variant="danger"
                          onClick={e => {
                            e.stopPropagation();
                            if (window.confirm(`Deactivate ${n.name}? They'll disappear from active lists but can be reactivated later.`)) {
                              deactivateMut.mutate(n);
                            }
                          }}
                        >
                          <Ban size={13} />
                        </Button>
                      ) : (
                        <>
                          <Button
                            size="sm" variant="secondary"
                            onClick={e => { e.stopPropagation(); activateMut.mutate(n.id); }}
                          >
                            Activate
                          </Button>
                          <Button
                            size="sm" variant="danger"
                            onClick={e => {
                              e.stopPropagation();
                              if (window.confirm(`Really delete ${n.name} permanently? This cannot be undone. Anyone reporting to them and any dealers assigned to them will be unlinked (not deleted).`)) {
                                forceDeleteMut.mutate(n.id);
                              }
                            }}
                          >
                            <Trash2 size={13} />
                          </Button>
                        </>
                      )}
                    </div>
                  ),
                },
              ]}
              data={nodes}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Node Detail Modal */}
      <Modal open={!!selectedNode} onClose={() => setSelectedNode(null)} title={`${selectedNode?.tree_id} — ${selectedNode?.name}`}>
        {selectedNode && (
          <div className="space-y-4">
            <div className="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
              {(['details', 'downline', 'performance'] as const).map(t => (
                <button key={t} onClick={() => setActiveTab(t)}
                  className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === t ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}>
                  {t.charAt(0).toUpperCase() + t.slice(1)}
                </button>
              ))}
            </div>

            {activeTab === 'details' && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div><span className="text-xs text-gray-500 block">Employee ID</span><code className="font-bold text-primary">{selectedNode.tree_id}</code></div>
                <div><span className="text-xs text-gray-500 block">Role</span><Badge label={selectedNode.hierarchy_role.replace(/_/g, ' ')} color={ROLE_COLORS[selectedNode.hierarchy_role]} /></div>
                <div><span className="text-xs text-gray-500 block">Phone</span>{selectedNode.phone ?? '—'}</div>
                <div><span className="text-xs text-gray-500 block">Email</span>{selectedNode.email ?? '—'}</div>
                <div><span className="text-xs text-gray-500 block">State</span>{selectedNode.state ?? '—'}</div>
                <div><span className="text-xs text-gray-500 block">Area</span>{selectedNode.area ?? '—'}</div>
                <div><span className="text-xs text-gray-500 block">District</span>{selectedNode.district ?? '—'}</div>
                <div><span className="text-xs text-gray-500 block">Reports To</span>{selectedNode.parent?.name ?? 'Top Level'}</div>
              </div>
            )}

            {activeTab === 'downline' && (
              <div>
                {!downlineData ? <Spinner /> : (
                  <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      {[
                        { label: 'Team Members', value: downlineData.total_members, icon: <Users size={16} /> },
                        { label: 'Total Dealers', value: downlineData.total_dealers, icon: <Users size={16} /> },
                        { label: 'Total Orders', value: downlineData.total_orders, icon: <TrendingUp size={16} /> },
                        { label: 'Total Revenue', value: fmtINR(downlineData.total_revenue ?? 0), icon: <TrendingUp size={16} /> },
                      ].map(s => (
                        <div key={s.label} className="bg-gray-50 rounded-lg p-3">
                          <div className="text-xs text-gray-500">{s.label}</div>
                          <div className="font-bold text-sm mt-1">{s.value}</div>
                        </div>
                      ))}
                    </div>
                    {(downlineData.tree ?? []).length > 0 && (
                      <div>
                        <div className="text-xs font-medium text-gray-500 mb-2">Full downline (every level below)</div>
                        <div className="space-y-1 max-h-72 overflow-y-auto">
                          {downlineData.tree.map((child: any) => (
                            <DownlineNode key={child.id} node={child} depth={0} />
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}

            {activeTab === 'performance' && (
              <div>
                {!perfData ? <Spinner /> : (
                  <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      {[
                        { label: 'Team Size', value: perfData.team_size },
                        { label: 'Total Dealers', value: perfData.total_dealers },
                        { label: 'Total Orders', value: perfData.total_orders },
                        { label: 'Total Revenue', value: fmtINR(perfData.total_revenue ?? 0) },
                      ].map(s => (
                        <div key={s.label} className="bg-gray-50 rounded-lg p-3">
                          <div className="text-xs text-gray-500">{s.label}</div>
                          <div className="font-bold text-sm mt-1">{s.value}</div>
                        </div>
                      ))}
                    </div>
                    {(perfData.dealer_performance ?? []).length > 0 && (
                      <div>
                        <div className="text-xs font-medium text-gray-500 mb-2">Dealer Performance</div>
                        <div className="space-y-1 max-h-48 overflow-y-auto">
                          {perfData.dealer_performance.map((d: any) => (
                            <div key={d.dealer_id} className="flex justify-between text-xs bg-gray-50 px-3 py-2 rounded">
                              <div><div className="font-medium">{d.business_name}</div><div className="text-gray-400">{d.order_count} orders</div></div>
                              <div className="text-right"><div className="font-semibold text-green-700">{fmtINR(d.revenue ?? 0)}</div><div className="text-gray-400">{d.kyc_status}</div></div>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>
        )}
      </Modal>

      {/* Add Member Modal */}
      <Modal open={showCreate} onClose={() => { setShowCreate(false); resetAddForm(); }} title="Add Hierarchy Member" width="max-w-2xl">
        <AddMemberForm
          form={addForm}
          setForm={setAddForm}
          availableUsers={availableUsers}
          onSubmit={() => createMut.mutate()}
          onCancel={() => { setShowCreate(false); resetAddForm(); }}
          loading={createMut.isPending}
          docAadhaarNumber={addDocAadhaarNumber}
          docPanNumber={addDocPanNumber}
          setDocAadhaarNumber={setAddDocAadhaarNumber}
          setDocPanNumber={setAddDocPanNumber}
          docFiles={addDocFiles}
          setDocFiles={setAddDocFiles}
        />
      </Modal>

      {/* Edit Member Modal */}
      <Modal open={!!editTarget} onClose={() => setEditTarget(null)} title={`Edit — ${editTarget?.name}`}>
        <MemberForm form={form} setForm={setForm} onSubmit={() => updateMut.mutate()} onCancel={() => setEditTarget(null)} loading={updateMut.isPending} />
      </Modal>
    </div>
  );
}
