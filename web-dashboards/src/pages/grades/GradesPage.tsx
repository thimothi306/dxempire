import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import toast from 'react-hot-toast';
import { gradeService } from '../../services';
import { Card, Table, Button, Badge, PageHeader, Spinner, Modal, Input } from '../../components/ui';

interface Grade {
  id: number;
  code: string;
  label: string;
  sort_order: number;
  is_active: boolean;
}

const EMPTY_FORM = { code: '', label: '', sort_order: '' };
type FormState = typeof EMPTY_FORM;

// Defined outside the page component so inputs don't lose focus on every keystroke.
function GradeForm({
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
      <Input
        label="Code *"
        value={form.code}
        onChange={(e) => setForm({ ...form, code: e.target.value })}
        placeholder="e.g. S6"
        disabled={editing}
      />
      <Input label="Label *" value={form.label} onChange={(e) => setForm({ ...form, label: e.target.value })} placeholder="e.g. S6 — Parts Only" />
      <Input label="Sort Order" type="number" value={form.sort_order} onChange={(e) => setForm({ ...form, sort_order: e.target.value })} placeholder="Lower shows first" />
      <div className="flex gap-3 pt-2">
        <Button onClick={onSubmit} loading={loading} className="flex-1 justify-center">{editing ? 'Save Changes' : 'Create Grade'}</Button>
        <Button variant="outline" onClick={onCancel} className="flex-1 justify-center">Cancel</Button>
      </div>
    </div>
  );
}

export default function GradesPage() {
  const qc = useQueryClient();
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Grade | null>(null);
  const [form, setForm] = useState(EMPTY_FORM);

  const { data, isLoading } = useQuery({ queryKey: ['grades'], queryFn: () => gradeService.list() });
  const grades: Grade[] = Array.isArray(data) ? data : [];

  const createMut = useMutation({
    mutationFn: () => gradeService.create({ code: form.code, label: form.label, sort_order: form.sort_order ? Number(form.sort_order) : undefined }),
    onSuccess: () => { toast.success('Grade created'); qc.invalidateQueries({ queryKey: ['grades'] }); closeModal(); },
    onError: (e: any) => toast.error(e?.response?.data?.message || 'Could not create grade'),
  });

  const updateMut = useMutation({
    mutationFn: () => gradeService.update(editing!.id, { label: form.label, sort_order: form.sort_order ? Number(form.sort_order) : undefined }),
    onSuccess: () => { toast.success('Grade updated'); qc.invalidateQueries({ queryKey: ['grades'] }); closeModal(); },
    onError: (e: any) => toast.error(e?.response?.data?.message || 'Could not update grade'),
  });

  const deactivateMut = useMutation({
    mutationFn: (id: number) => gradeService.deactivate(id),
    onSuccess: () => { toast.success('Grade deactivated'); qc.invalidateQueries({ queryKey: ['grades'] }); },
    onError: (e: any) => toast.error(e?.response?.data?.message || 'Could not deactivate — it may still be assigned to products'),
  });

  const openCreate = () => { setEditing(null); setForm(EMPTY_FORM); setModalOpen(true); };
  const openEdit = (g: Grade) => { setEditing(g); setForm({ code: g.code, label: g.label, sort_order: String(g.sort_order) }); setModalOpen(true); };
  const closeModal = () => { setModalOpen(false); setEditing(null); setForm(EMPTY_FORM); };

  if (isLoading) return <Spinner />;

  return (
    <div>
      <PageHeader
        title="Grades"
        subtitle="Quality grade tiers used across QC, Inventory, Offers, and Peti to Peti"
        action={<Button onClick={openCreate}><Plus size={15} /> Add Grade</Button>}
      />

      <Card>
        <Table
          columns={[
            { key: 'code', header: 'Code', render: (g: Grade) => <code className="text-xs bg-gray-100 px-2 py-1 rounded font-semibold">{g.code}</code> },
            { key: 'label', header: 'Label', render: (g: Grade) => g.label },
            { key: 'sort_order', header: 'Order', render: (g: Grade) => g.sort_order },
            { key: 'is_active', header: 'Status', render: (g: Grade) => <Badge label={g.is_active ? 'Active' : 'Inactive'} color={g.is_active ? 'green' : 'red'} /> },
            {
              key: 'actions', header: '', render: (g: Grade) => (
                <div className="flex gap-2">
                  <Button size="sm" variant="outline" onClick={() => openEdit(g)}>Edit</Button>
                  {g.is_active && (
                    <Button size="sm" variant="danger" onClick={() => deactivateMut.mutate(g.id)}>Deactivate</Button>
                  )}
                </div>
              ),
            },
          ]}
          data={grades}
          keyField="id"
          emptyText="No grades yet"
        />
      </Card>

      <Modal open={modalOpen} onClose={closeModal} title={editing ? `Edit ${editing.code}` : 'Add Grade'}>
        <GradeForm
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
