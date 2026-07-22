import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, ChevronRight, Users, TrendingUp, Trash2, Pencil } from 'lucide-react';
import toast from 'react-hot-toast';
import { hierarchyService } from '../../services/newModules';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Modal, Input, Select, fmtINR } from '../../components/ui';

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

const INDIAN_STATES = [
  'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
  'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
  'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
  'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
  'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
  'Delhi', 'Jammu & Kashmir', 'Ladakh', 'Puducherry', 'Chandigarh',
];

const EMPTY_FORM = { name: '', phone: '', email: '', hierarchy_role: 'salesman', parent_unique_code: '', state: '', area: '', district: '' };
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
      <div className="grid grid-cols-2 gap-3">
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

      <Select label="State" value={form.state} onChange={e => setForm({ ...form, state: e.target.value })}
        options={[{ value: '', label: 'Select state...' }, ...INDIAN_STATES.map(s => ({ value: s, label: s }))]} />
      <div className="grid grid-cols-2 gap-3">
        <Input label="Area" value={form.area} onChange={e => setForm({ ...form, area: e.target.value })} placeholder="e.g. Bangalore Zone" />
        <Input label="District" value={form.district} onChange={e => setForm({ ...form, district: e.target.value })} placeholder="e.g. Jayanagar" />
      </div>
      <div className="flex gap-3 pt-2">
        <Button onClick={onSubmit} loading={loading} className="flex-1 justify-center">Save</Button>
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
  const [showCreate, setShowCreate] = useState(false);
  const [editTarget, setEditTarget] = useState<any>(null);
  const [selectedNode, setSelectedNode] = useState<any>(null);
  const [activeTab, setActiveTab] = useState<'details' | 'downline' | 'performance'>('details');
  const [form, setForm] = useState(EMPTY_FORM);

  const { data, isLoading } = useQuery({
    queryKey: ['hierarchy', page, roleFilter, stateFilter],
    queryFn: () => hierarchyService.list({
      page: String(page),
      ...(roleFilter && { role: roleFilter }),
      ...(stateFilter && { state: stateFilter }),
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
    mutationFn: () => hierarchyService.create({ ...form, parent_unique_code: form.parent_unique_code || null }),
    onSuccess: () => { toast.success('Member added'); qc.invalidateQueries({ queryKey: ['hierarchy'] }); qc.invalidateQueries({ queryKey: ['hierarchy-all'] }); setShowCreate(false); setForm(EMPTY_FORM); },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to add member'),
  });

  const updateMut = useMutation({
    mutationFn: () => hierarchyService.update(editTarget.id, { ...form, parent_unique_code: form.parent_unique_code || null }),
    onSuccess: () => { toast.success('Member updated'); qc.invalidateQueries({ queryKey: ['hierarchy'] }); setEditTarget(null); },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to update'),
  });

  const deleteMut = useMutation({
    mutationFn: (id: number) => hierarchyService.remove(id),
    onSuccess: () => { toast.success('Member deactivated'); qc.invalidateQueries({ queryKey: ['hierarchy'] }); },
    onError: () => toast.error('Failed'),
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
        action={<Button onClick={() => { setForm(EMPTY_FORM); setShowCreate(true); }}><Plus size={15} /> Add Member</Button>}
      />

      {/* Filters */}
      <div className="flex gap-3 mb-5">
        <Select value={roleFilter} onChange={e => { setRoleFilter(e.target.value); setPage(1); }}
          options={[{ value: '', label: 'All Roles' }, ...ROLES]} />
        <Select value={stateFilter} onChange={e => { setStateFilter(e.target.value); setPage(1); }}
          options={[{ value: '', label: 'All States' }, ...INDIAN_STATES.map(s => ({ value: s, label: s }))]} />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'unique_code', header: 'Unique Code', render: (n: any) => n.unique_code ? <code className="text-xs font-bold bg-blue-100 text-blue-800 px-2 py-1 rounded">{n.unique_code}</code> : '—' },
                { key: 'tree_id', header: 'Tree ID', render: n => <code className="text-xs font-bold text-primary">{n.tree_id}</code> },
                { key: 'name', header: 'Name', render: n => <span className="font-medium">{n.name}</span> },
                { key: 'hierarchy_role', header: 'Role', render: n => <Badge label={n.hierarchy_role.replace(/_/g, ' ')} color={ROLE_COLORS[n.hierarchy_role] ?? 'gray'} /> },
                { key: 'parent', header: 'Reports To', render: (n: any) => n.parent ? <span className="text-xs text-gray-500"><code>{n.parent.unique_code}</code> — {n.parent.name}</span> : '—' },
                { key: 'state', header: 'Territory', render: n => <span className="text-xs">{[n.state, n.area, n.district].filter(Boolean).join(' › ')}</span> },
                { key: 'phone', header: 'Phone', render: n => n.phone ?? '—' },
                {
                  key: 'actions', header: '', render: n => (
                    <div className="flex gap-2">
                      <Button size="sm" variant="outline" onClick={e => { e.stopPropagation(); setSelectedNode(n); setActiveTab('details'); }}>
                        <ChevronRight size={13} />
                      </Button>
                      <Button size="sm" variant="outline" onClick={e => { e.stopPropagation(); openEdit(n); }}>
                        <Pencil size={13} />
                      </Button>
                      <Button size="sm" variant="danger" onClick={e => { e.stopPropagation(); deleteMut.mutate(n.id); }}>
                        <Trash2 size={13} />
                      </Button>
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
              <div className="grid grid-cols-2 gap-3 text-sm">
                <div><span className="text-xs text-gray-500 block">Tree ID</span><code className="font-bold text-primary">{selectedNode.tree_id}</code></div>
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
                    <div className="grid grid-cols-2 gap-3">
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
                        <div className="text-xs font-medium text-gray-500 mb-2">Direct Reports</div>
                        <div className="space-y-1">
                          {downlineData.tree.map((child: any) => (
                            <div key={child.id} className="flex justify-between text-xs bg-gray-50 px-3 py-2 rounded">
                              <span><code className="text-primary font-bold">{child.tree_id}</code> — {child.name}</span>
                              <Badge label={child.hierarchy_role.replace(/_/g, ' ')} color={ROLE_COLORS[child.hierarchy_role]} />
                            </div>
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
                    <div className="grid grid-cols-2 gap-3">
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
      <Modal open={showCreate} onClose={() => { setShowCreate(false); setForm(EMPTY_FORM); }} title="Add Hierarchy Member">
        <MemberForm form={form} setForm={setForm} onSubmit={() => createMut.mutate()} onCancel={() => { setShowCreate(false); setForm(EMPTY_FORM); }} loading={createMut.isPending} />
      </Modal>

      {/* Edit Member Modal */}
      <Modal open={!!editTarget} onClose={() => setEditTarget(null)} title={`Edit — ${editTarget?.name}`}>
        <MemberForm form={form} setForm={setForm} onSubmit={() => updateMut.mutate()} onCancel={() => setEditTarget(null)} loading={updateMut.isPending} />
      </Modal>
    </div>
  );
}
