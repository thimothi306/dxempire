import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { UserPlus, Copy } from 'lucide-react';
import toast from 'react-hot-toast';
import { adminService } from '../../services';
import { Button, Badge, Table, Pagination, Modal, Input, Select, PageHeader, Card, Spinner } from '../../components/ui';
import type { User, Role } from '../../types';

const ROLES: Role[] = ['super_admin', 'sales', 'warehouse_staff', 'qc_engineer', 'accounts', 'hr_manager', 'logistics'];

const EMPTY_FORM = { name: '', phone: '', email: '', password: '', role: 'sales' as Role };

export default function UsersPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [showCreate, setShowCreate] = useState(false);
  const [selected, setSelected] = useState<User | null>(null);
  const [newRole, setNewRole] = useState('');
  const [form, setForm] = useState(EMPTY_FORM);
  const [newUserCode, setNewUserCode] = useState<{ name: string; code: string } | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['admin-users', page],
    queryFn: () => adminService.users({ page: String(page) }),
  });

  const createMut = useMutation({
    mutationFn: () => adminService.createUser(form),
    onSuccess: (response: any) => {
      const uniqueCode = response?.unique_code || response?.data?.unique_code;
      setNewUserCode({ name: form.name, code: uniqueCode });
      if (uniqueCode) {
        navigator.clipboard.writeText(uniqueCode);
      }
      qc.invalidateQueries({ queryKey: ['admin-users'] });
      setShowCreate(false);
      setForm(EMPTY_FORM);
    },
    onError: () => toast.error('Failed to create user'),
  });

  const roleMut = useMutation({
    mutationFn: () => adminService.changeRole(selected!.id, newRole),
    onSuccess: () => { toast.success('Role updated'); qc.invalidateQueries({ queryKey: ['admin-users'] }); setSelected(null); },
    onError: () => toast.error('Failed to update role'),
  });

  const toggleMut = useMutation({
    mutationFn: (u: User) => u.is_active ? adminService.deactivate(u.id) : adminService.activate(u.id),
    onSuccess: () => { toast.success('User updated'); qc.invalidateQueries({ queryKey: ['admin-users'] }); },
    onError: () => toast.error('Failed'),
  });

  const users: User[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };

  return (
    <div>
      <PageHeader
        title="User Management"
        subtitle="All admin and staff users"
        action={<Button onClick={() => setShowCreate(true)}><UserPlus size={15} /> Add User</Button>}
      />

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'name', header: 'Name', render: (u) => <span className="font-medium">{u.name}</span> },
                { key: 'phone', header: 'Phone' },
                { key: 'email', header: 'Email', render: (u) => u.email || '—' },
                { key: 'unique_code', header: 'Unique Code', render: (u: any) => u.unique_code ? <code className="text-xs bg-gray-100 px-2 py-1 rounded font-semibold">{u.unique_code}</code> : '—' },
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
      <Modal open={showCreate} onClose={() => { setShowCreate(false); setForm(EMPTY_FORM); }} title="Add User">
        <div className="space-y-4">
          <Input label="Full Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <Input label="Phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          <Input label="Email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          <Input
            label="Password"
            type="password"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            placeholder="Min 6 characters — used to login to this dashboard"
          />
          <Select
            label="Role"
            value={form.role}
            onChange={(e) => setForm({ ...form, role: e.target.value as Role })}
            options={ROLES.map((r) => ({ value: r, label: r.replace(/_/g, ' ') }))}
          />
          <div className="flex gap-3 pt-2">
            <Button onClick={() => createMut.mutate()} loading={createMut.isPending} className="flex-1 justify-center">Create</Button>
            <Button variant="outline" onClick={() => { setShowCreate(false); setForm(EMPTY_FORM); }} className="flex-1 justify-center">Cancel</Button>
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
            options={ROLES.map((r) => ({ value: r, label: r.replace(/_/g, ' ') }))}
          />
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
              <p className="text-sm text-gray-600 mb-2">Unique Code (copied to clipboard):</p>
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
