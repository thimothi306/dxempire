import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Star, Boxes } from 'lucide-react';
import toast from 'react-hot-toast';
import { warehouseService } from '../../services';
import { Card, Table, Button, Badge, PageHeader, Spinner, Modal, Input } from '../../components/ui';

interface Warehouse {
  id: number;
  name: string;
  code: string;
  phone: string | null;
  email: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  pincode: string | null;
  is_default: boolean;
  is_active: boolean;
  bins_count?: number;
}

const EMPTY_FORM = { name: '', code: '', phone: '', email: '', address: '', city: '', state: '', pincode: '' };
type FormState = typeof EMPTY_FORM;

// Defined outside the page component so inputs don't lose focus on every keystroke.
function WarehouseForm({
  form, setForm, onSubmit, onCancel, loading, editing,
}: {
  form: FormState;
  setForm: (f: FormState) => void;
  onSubmit: () => void;
  onCancel: () => void;
  loading: boolean;
  editing: boolean;
}) {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Input label="Warehouse Name *" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="e.g. Mumbai Warehouse" />
        <Input label="Code *" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} placeholder="e.g. WH-02" disabled={editing} />
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Input label="Phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
        <Input label="Email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
      </div>
      <Input label="Address" value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <Input label="City" value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} />
        <Input label="State" value={form.state} onChange={(e) => setForm({ ...form, state: e.target.value })} />
        <Input label="Pincode" value={form.pincode} onChange={(e) => setForm({ ...form, pincode: e.target.value })} />
      </div>
      <div className="flex gap-3 pt-2">
        <Button onClick={onSubmit} loading={loading} className="flex-1 justify-center">{editing ? 'Save Changes' : 'Create Warehouse'}</Button>
        <Button variant="outline" onClick={onCancel} className="flex-1 justify-center">Cancel</Button>
      </div>
    </div>
  );
}

export default function WarehousesPage() {
  const qc = useQueryClient();
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Warehouse | null>(null);
  const [form, setForm] = useState(EMPTY_FORM);

  const { data, isLoading } = useQuery({ queryKey: ['warehouses'], queryFn: () => warehouseService.list() });
  const warehouses: Warehouse[] = Array.isArray(data) ? data : [];

  const createMut = useMutation({
    mutationFn: () => warehouseService.create(form),
    onSuccess: () => {
      toast.success('Warehouse created');
      qc.invalidateQueries({ queryKey: ['warehouses'] });
      closeModal();
    },
    onError: (e: any) => toast.error(e?.response?.data?.message || 'Could not create warehouse'),
  });

  const updateMut = useMutation({
    mutationFn: () => warehouseService.update(editing!.id, form),
    onSuccess: () => {
      toast.success('Warehouse updated');
      qc.invalidateQueries({ queryKey: ['warehouses'] });
      closeModal();
    },
    onError: (e: any) => toast.error(e?.response?.data?.message || 'Could not update warehouse'),
  });

  const defaultMut = useMutation({
    mutationFn: (id: number) => warehouseService.makeDefault(id),
    onSuccess: () => {
      toast.success('Default warehouse updated');
      qc.invalidateQueries({ queryKey: ['warehouses'] });
    },
    onError: (e: any) => toast.error(e?.response?.data?.message || 'Could not set default'),
  });

  const deactivateMut = useMutation({
    mutationFn: (id: number) => warehouseService.deactivate(id),
    onSuccess: () => {
      toast.success('Warehouse deactivated');
      qc.invalidateQueries({ queryKey: ['warehouses'] });
    },
    onError: (e: any) => toast.error(e?.response?.data?.message || 'Could not deactivate warehouse'),
  });

  const openCreate = () => { setEditing(null); setForm(EMPTY_FORM); setModalOpen(true); };
  const openEdit = (w: Warehouse) => {
    setEditing(w);
    setForm({
      name: w.name, code: w.code, phone: w.phone ?? '', email: w.email ?? '',
      address: w.address ?? '', city: w.city ?? '', state: w.state ?? '', pincode: w.pincode ?? '',
    });
    setModalOpen(true);
  };
  const closeModal = () => { setModalOpen(false); setEditing(null); setForm(EMPTY_FORM); };

  if (isLoading) return <Spinner />;

  return (
    <div>
      <PageHeader
        title="Warehouses"
        subtitle={`${warehouses.length} warehouse${warehouses.length === 1 ? '' : 's'} — bins are assigned to whichever warehouse you pick here`}
        action={<Button onClick={openCreate}><Plus size={15} /> Add Warehouse</Button>}
      />

      <Card>
        <Table
          columns={[
            {
              key: 'name', header: 'Warehouse', render: (w: Warehouse) => (
                <div className="flex items-center gap-2">
                  <span className="font-medium">{w.name}</span>
                  {w.is_default && <Badge label="Default" color="blue" />}
                </div>
              ),
            },
            { key: 'code', header: 'Code', render: (w: Warehouse) => <code className="text-xs bg-gray-100 px-2 py-1 rounded font-semibold">{w.code}</code> },
            { key: 'location', header: 'Location', render: (w: Warehouse) => [w.city, w.state].filter(Boolean).join(', ') || '—' },
            { key: 'bins_count', header: 'Bins', render: (w: Warehouse) => <span className="inline-flex items-center gap-1"><Boxes size={13} className="text-gray-400" /> {w.bins_count ?? 0}</span> },
            { key: 'is_active', header: 'Status', render: (w: Warehouse) => <Badge label={w.is_active ? 'Active' : 'Inactive'} color={w.is_active ? 'green' : 'red'} /> },
            {
              key: 'actions', header: '', render: (w: Warehouse) => (
                <div className="flex gap-2">
                  <Button size="sm" variant="outline" onClick={() => openEdit(w)}>Edit</Button>
                  {!w.is_default && (
                    <Button size="sm" variant="outline" onClick={() => defaultMut.mutate(w.id)} title="Set as default pickup warehouse">
                      <Star size={13} />
                    </Button>
                  )}
                  {!w.is_default && w.is_active && (
                    <Button size="sm" variant="danger" onClick={() => deactivateMut.mutate(w.id)}>Deactivate</Button>
                  )}
                </div>
              ),
            },
          ]}
          data={warehouses}
          keyField="id"
          emptyText="No warehouses yet"
        />
      </Card>

      <Modal open={modalOpen} onClose={closeModal} title={editing ? `Edit ${editing.name}` : 'Add Warehouse'}>
        <WarehouseForm
          form={form} setForm={setForm}
          onSubmit={() => (editing ? updateMut.mutate() : createMut.mutate())}
          onCancel={closeModal}
          loading={createMut.isPending || updateMut.isPending}
          editing={!!editing}
        />
      </Modal>
    </div>
  );
}
