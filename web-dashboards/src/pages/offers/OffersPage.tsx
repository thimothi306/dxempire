import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Tag, Trash2 } from 'lucide-react';
import toast from 'react-hot-toast';
import { offersService } from '../../services/newModules';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Modal, Input, Select, fmtDate } from '../../components/ui';

const DISCOUNT_TYPES = [{ value: 'percentage', label: 'Percentage (%)' }, { value: 'fixed', label: 'Fixed Amount (₹)' }];
const APPLICABLE_TO = [{ value: 'all', label: 'All Products' }, { value: 'phone', label: 'Phones' }, { value: 'laptop', label: 'Laptops' }, { value: 'accessory', label: 'Accessories' }];
const APPLICABLE_GRADE = [{ value: 'all', label: 'All Grades' }, ...['S1','S2','S3','S4','S5'].map(g => ({ value: g, label: g }))];
const CUSTOMER_TYPE = [{ value: 'all', label: 'All Customers' }, { value: 'b2b', label: 'B2B Only' }, { value: 'retail', label: 'Retail Only' }];

const EMPTY_FORM = { title: '', code: '', description: '', discount_type: 'percentage', discount_value: '', min_order_amount: '', max_discount_amount: '', applicable_to: 'all', applicable_grade: 'all', customer_type: 'all', valid_from: '', valid_to: '', max_usage: '' };

export default function OffersPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [showCreate, setShowCreate] = useState(false);
  const [editTarget, setEditTarget] = useState<any>(null);
  const [deleteTarget, setDeleteTarget] = useState<any>(null);
  const [form, setForm] = useState(EMPTY_FORM);

  const { data, isLoading } = useQuery({
    queryKey: ['offers', page],
    queryFn: () => offersService.list({ page: String(page) }),
  });

  const createMut = useMutation({
    mutationFn: () => offersService.create({ ...form, discount_value: Number(form.discount_value), min_order_amount: Number(form.min_order_amount || 0), max_discount_amount: form.max_discount_amount ? Number(form.max_discount_amount) : null, max_usage: form.max_usage ? Number(form.max_usage) : null, code: form.code.toUpperCase() }),
    onSuccess: () => { toast.success('Offer created'); qc.invalidateQueries({ queryKey: ['offers'] }); setShowCreate(false); setForm(EMPTY_FORM); },
    onError: () => toast.error('Failed to create offer'),
  });

  const updateMut = useMutation({
    mutationFn: () => offersService.update(editTarget.id, { ...form, discount_value: Number(form.discount_value), min_order_amount: Number(form.min_order_amount || 0), max_discount_amount: form.max_discount_amount ? Number(form.max_discount_amount) : null, max_usage: form.max_usage ? Number(form.max_usage) : null }),
    onSuccess: () => { toast.success('Offer updated'); qc.invalidateQueries({ queryKey: ['offers'] }); setEditTarget(null); },
    onError: () => toast.error('Failed to update'),
  });

  const deleteMut = useMutation({
    mutationFn: (id: number) => offersService.remove(id),
    onSuccess: () => { toast.success('Offer deactivated'); qc.invalidateQueries({ queryKey: ['offers'] }); setDeleteTarget(null); },
    onError: () => toast.error('Failed'),
  });

  const openEdit = (offer: any) => {
    setForm({ title: offer.title, code: offer.code, description: offer.description ?? '', discount_type: offer.discount_type, discount_value: String(offer.discount_value), min_order_amount: String(offer.min_order_amount ?? ''), max_discount_amount: String(offer.max_discount_amount ?? ''), applicable_to: offer.applicable_to, applicable_grade: offer.applicable_grade, customer_type: offer.customer_type, valid_from: offer.valid_from?.slice(0, 16) ?? '', valid_to: offer.valid_to?.slice(0, 16) ?? '', max_usage: String(offer.max_usage ?? '') });
    setEditTarget(offer);
  };

  const offers: any[] = data?.data ?? [];
  const meta = data?.meta;
  const now = new Date();
  const isOfferActive = (o: any) => o.is_active && new Date(o.valid_from) <= now && new Date(o.valid_to) >= now;

  const OfferForm = ({ onSubmit, loading }: { onSubmit: () => void; loading: boolean }) => (
    <div className="space-y-3 max-h-[70vh] overflow-y-auto pr-1">
      <div className="grid grid-cols-2 gap-3">
        <Input label="Offer Title *" value={form.title} onChange={e => setForm({ ...form, title: e.target.value })} />
        <Input label="Offer Code *" value={form.code} onChange={e => setForm({ ...form, code: e.target.value.toUpperCase() })} placeholder="e.g. DIWALI20" />
      </div>
      <Input label="Description" value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} placeholder="Optional" />
      <div className="grid grid-cols-2 gap-3">
        <Select label="Discount Type *" value={form.discount_type} onChange={e => setForm({ ...form, discount_type: e.target.value })} options={DISCOUNT_TYPES} />
        <Input label={form.discount_type === 'percentage' ? 'Discount % *' : 'Discount ₹ *'} type="number" value={form.discount_value} onChange={e => setForm({ ...form, discount_value: e.target.value })} />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Input label="Min Order Amount (₹)" type="number" value={form.min_order_amount} onChange={e => setForm({ ...form, min_order_amount: e.target.value })} placeholder="0" />
        <Input label="Max Discount Cap (₹)" type="number" value={form.max_discount_amount} onChange={e => setForm({ ...form, max_discount_amount: e.target.value })} placeholder="Optional" />
      </div>
      <div className="grid grid-cols-3 gap-3">
        <Select label="Applies To" value={form.applicable_to} onChange={e => setForm({ ...form, applicable_to: e.target.value })} options={APPLICABLE_TO} />
        <Select label="Grade" value={form.applicable_grade} onChange={e => setForm({ ...form, applicable_grade: e.target.value })} options={APPLICABLE_GRADE} />
        <Select label="Customer" value={form.customer_type} onChange={e => setForm({ ...form, customer_type: e.target.value })} options={CUSTOMER_TYPE} />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Input label="Valid From *" type="datetime-local" value={form.valid_from} onChange={e => setForm({ ...form, valid_from: e.target.value })} />
        <Input label="Valid To *" type="datetime-local" value={form.valid_to} onChange={e => setForm({ ...form, valid_to: e.target.value })} />
      </div>
      <Input label="Max Usage (leave blank for unlimited)" type="number" value={form.max_usage} onChange={e => setForm({ ...form, max_usage: e.target.value })} placeholder="Optional" />
      <div className="flex gap-3 pt-2">
        <Button onClick={onSubmit} loading={loading} className="flex-1 justify-center">Save</Button>
        <Button variant="outline" onClick={() => { setShowCreate(false); setEditTarget(null); }} className="flex-1 justify-center">Cancel</Button>
      </div>
    </div>
  );

  return (
    <div>
      <PageHeader
        title="Offer Engine"
        subtitle={`${meta?.total ?? 0} offers`}
        action={<Button onClick={() => { setForm(EMPTY_FORM); setShowCreate(true); }}><Plus size={15} /> Create Offer</Button>}
      />

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'code', header: 'Code', render: o => <code className="text-xs font-bold text-primary bg-primary/10 px-2 py-0.5 rounded">{o.code}</code> },
                { key: 'title', header: 'Title', render: o => <span className="font-medium">{o.title}</span> },
                { key: 'discount', header: 'Discount', render: o => <span className="font-semibold">{o.discount_type === 'percentage' ? `${o.discount_value}%` : `₹${o.discount_value}`}</span> },
                { key: 'applicable_to', header: 'Applies To', render: o => <div className="flex gap-1 flex-wrap"><Badge label={o.applicable_to} color="blue" /><Badge label={o.applicable_grade} color="purple" /></div> },
                { key: 'customer_type', header: 'Customer', render: o => <Badge label={o.customer_type} color="gray" /> },
                { key: 'usage', header: 'Usage', render: o => <span className="text-xs">{o.usage_count}/{o.max_usage ?? '∞'}</span> },
                { key: 'valid_to', header: 'Expires', render: o => <span className="text-xs">{fmtDate(o.valid_to)}</span> },
                { key: 'status', header: 'Status', render: o => <Badge label={isOfferActive(o) ? 'Active' : 'Inactive'} color={isOfferActive(o) ? 'green' : 'red'} /> },
                {
                  key: 'actions', header: '', render: o => (
                    <div className="flex gap-2">
                      <Button size="sm" variant="outline" onClick={e => { e.stopPropagation(); openEdit(o); }}><Tag size={13} /></Button>
                      <Button size="sm" variant="danger" onClick={e => { e.stopPropagation(); setDeleteTarget(o); }}><Trash2 size={13} /></Button>
                    </div>
                  ),
                },
              ]}
              data={offers}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      <Modal open={showCreate} onClose={() => { setShowCreate(false); setForm(EMPTY_FORM); }} title="Create Offer">
        <OfferForm onSubmit={() => createMut.mutate()} loading={createMut.isPending} />
      </Modal>

      <Modal open={!!editTarget} onClose={() => setEditTarget(null)} title={`Edit — ${editTarget?.title}`}>
        <OfferForm onSubmit={() => updateMut.mutate()} loading={updateMut.isPending} />
      </Modal>

      <Modal open={!!deleteTarget} onClose={() => setDeleteTarget(null)} title="Deactivate Offer">
        <div className="space-y-4">
          <p className="text-sm text-gray-600">Deactivate offer <span className="font-semibold">{deleteTarget?.code}</span>?</p>
          <div className="flex gap-3">
            <Button variant="danger" onClick={() => deleteMut.mutate(deleteTarget.id)} loading={deleteMut.isPending} className="flex-1 justify-center">Deactivate</Button>
            <Button variant="outline" onClick={() => setDeleteTarget(null)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
