import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2 } from 'lucide-react';
import toast from 'react-hot-toast';
import { procurementService } from '../../services';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Modal, Input, Select, fmtINR, fmtDate } from '../../components/ui';

const PO_STATUS_COLORS: Record<string, string> = {
  draft: 'gray', sent: 'blue', received: 'green', partial: 'yellow', cancelled: 'red',
};

const EMPTY_SUPPLIER = { name: '', contact_name: '', phone: '', email: '', gst_number: '', address: '' };
const EMPTY_PO = { supplier_id: '', expected_date: '', notes: '' };
const EMPTY_ITEM = { category: 'phone', brand: '', model: '', purchase_price: '', quantity: '1' };
type SupplierFormState = typeof EMPTY_SUPPLIER;

// Defined OUTSIDE the page component: an inline component definition would be
// recreated on every render, causing React to remount the inputs (and lose
// focus) after every keystroke.
function SupplierForm({
  form, setForm, onSubmit, onCancel, loading,
}: {
  form: SupplierFormState;
  setForm: (f: SupplierFormState) => void;
  onSubmit: () => void;
  onCancel: () => void;
  loading: boolean;
}) {
  return (
    <div className="space-y-4">
      <Input label="Supplier Name *" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
      <div className="grid grid-cols-2 gap-3">
        <Input label="Contact Person" value={form.contact_name} onChange={(e) => setForm({ ...form, contact_name: e.target.value })} />
        <Input label="Phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Input label="Email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
        <Input label="GST Number" value={form.gst_number} onChange={(e) => setForm({ ...form, gst_number: e.target.value })} />
      </div>
      <Input label="Address" value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
      <div className="flex gap-3 pt-2">
        <Button onClick={onSubmit} loading={loading} className="flex-1 justify-center">Save</Button>
        <Button variant="outline" onClick={onCancel} className="flex-1 justify-center">Cancel</Button>
      </div>
    </div>
  );
}

export default function ProcurementPage() {
  const qc = useQueryClient();
  const [tab, setTab] = useState<'orders' | 'suppliers'>('orders');
  const [page, setPage] = useState(1);

  // PO state
  const [showPO, setShowPO] = useState(false);
  const [receiveId, setReceiveId] = useState<number | null>(null);
  const [poForm, setPoForm] = useState(EMPTY_PO);
  const [poItems, setPoItems] = useState([{ ...EMPTY_ITEM }]);

  // Supplier state
  const [showSupplier, setShowSupplier] = useState(false);
  const [editSupplier, setEditSupplier] = useState<any | null>(null);
  const [deleteSupplier, setDeleteSupplier] = useState<any | null>(null);
  const [supplierForm, setSupplierForm] = useState(EMPTY_SUPPLIER);

  const { data: poData, isLoading: poLoading } = useQuery({
    queryKey: ['purchase-orders', page],
    queryFn: () => procurementService.purchaseOrders({ page: String(page) }),
    enabled: tab === 'orders',
  });

  const { data: suppData, isLoading: suppLoading } = useQuery({
    queryKey: ['suppliers'],
    queryFn: () => procurementService.suppliers({}),
  });

  const createPOMut = useMutation({
    mutationFn: () => procurementService.createPurchaseOrder({
      supplier_id: Number(poForm.supplier_id),
      expected_date: poForm.expected_date,
      notes: poForm.notes,
      items: poItems.map((i) => ({ ...i, purchase_price: Number(i.purchase_price), quantity: Number(i.quantity) })),
    }),
    onSuccess: () => {
      toast.success('Purchase order created');
      qc.invalidateQueries({ queryKey: ['purchase-orders'] });
      setShowPO(false);
      setPoForm(EMPTY_PO);
      setPoItems([{ ...EMPTY_ITEM }]);
    },
    onError: () => toast.error('Failed to create PO'),
  });

  const receiveMut = useMutation({
    mutationFn: () => procurementService.receivePO(receiveId!, {}),
    onSuccess: () => { toast.success('Stock received'); qc.invalidateQueries({ queryKey: ['purchase-orders'] }); setReceiveId(null); },
    onError: () => toast.error('Failed to record receipt'),
  });

  const createSupplierMut = useMutation({
    mutationFn: () => procurementService.createSupplier(supplierForm),
    onSuccess: () => {
      toast.success('Supplier added');
      qc.invalidateQueries({ queryKey: ['suppliers'] });
      setShowSupplier(false);
      setSupplierForm(EMPTY_SUPPLIER);
    },
    onError: () => toast.error('Failed to add supplier'),
  });

  const updateSupplierMut = useMutation({
    mutationFn: () => procurementService.updateSupplier(editSupplier.id, supplierForm),
    onSuccess: () => {
      toast.success('Supplier updated');
      qc.invalidateQueries({ queryKey: ['suppliers'] });
      setEditSupplier(null);
    },
    onError: () => toast.error('Failed to update supplier'),
  });

  const deleteSupplierMut = useMutation({
    mutationFn: (id: number) => procurementService.deleteSupplier(id),
    onSuccess: () => {
      toast.success('Supplier deleted');
      qc.invalidateQueries({ queryKey: ['suppliers'] });
      setDeleteSupplier(null);
    },
    onError: () => toast.error('Failed to delete supplier'),
  });

  const openEditSupplier = (s: any) => {
    setSupplierForm({ name: s.name ?? '', contact_name: s.contact_name ?? '', phone: s.phone ?? '', email: s.email ?? '', gst_number: s.gst_number ?? '', address: s.address ?? '' });
    setEditSupplier(s);
  };

  const addPoItem = () => setPoItems([...poItems, { ...EMPTY_ITEM }]);
  const removePoItem = (idx: number) => setPoItems(poItems.filter((_, i) => i !== idx));
  const updatePoItem = (idx: number, field: string, value: string) =>
    setPoItems(poItems.map((item, i) => i === idx ? { ...item, [field]: value } : item));

  const suppliers: any[] = suppData?.data ?? [];
  const pos: any[] = poData?.data ?? [];
  const meta = poData?.meta;

  return (
    <div>
      <PageHeader
        title="Procurement"
        subtitle="Purchase orders & suppliers"
        action={
          tab === 'orders'
            ? <Button onClick={() => setShowPO(true)}><Plus size={15} /> New PO</Button>
            : <Button onClick={() => { setSupplierForm(EMPTY_SUPPLIER); setShowSupplier(true); }}><Plus size={15} /> Add Supplier</Button>
        }
      />

      {/* Tabs */}
      <div className="flex gap-1 mb-5 bg-gray-100 p-1 rounded-lg w-fit">
        {(['orders', 'suppliers'] as const).map((t) => (
          <button
            key={t}
            onClick={() => { setTab(t); setPage(1); }}
            className={`px-4 py-1.5 rounded-md text-sm font-medium transition-colors ${tab === t ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
          >
            {t === 'orders' ? 'Purchase Orders' : 'Suppliers'}
          </button>
        ))}
      </div>

      {tab === 'orders' && (
        <Card>
          {poLoading ? <Spinner /> : (
            <>
              <Table
                columns={[
                  { key: 'po_number', header: 'PO #', render: (p) => <span className="font-mono text-xs">{p.po_number}</span> },
                  { key: 'supplier', header: 'Supplier', render: (p) => p.supplier?.name ?? '—' },
                  { key: 'status', header: 'Status', render: (p) => <Badge label={p.status} color={PO_STATUS_COLORS[p.status] ?? 'gray'} /> },
                  { key: 'expected_count', header: 'Items', render: (p) => `${p.received_count ?? 0}/${p.expected_count ?? '—'}` },
                  { key: 'total_amount', header: 'Amount', render: (p) => fmtINR(p.total_amount ?? 0) },
                  { key: 'expected_date', header: 'Expected', render: (p) => fmtDate(p.expected_date ?? '') },
                  {
                    key: 'actions', header: '', render: (p) => p.status === 'sent' || p.status === 'placed' ? (
                      <Button size="sm" onClick={(e) => { e.stopPropagation(); setReceiveId(p.id); }}>Receive</Button>
                    ) : null,
                  },
                ]}
                data={pos}
                keyField="id"
              />
              {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
            </>
          )}
        </Card>
      )}

      {tab === 'suppliers' && (
        <Card>
          {suppLoading ? <Spinner /> : (
            <Table
              columns={[
                { key: 'name', header: 'Supplier Name', render: (s) => <span className="font-medium">{s.name}</span> },
                { key: 'contact_name', header: 'Contact', render: (s) => s.contact_name ?? '—' },
                { key: 'phone', header: 'Phone', render: (s) => s.phone ?? '—' },
                { key: 'email', header: 'Email', render: (s) => s.email ?? '—' },
                { key: 'gst_number', header: 'GST', render: (s) => s.gst_number ?? '—' },
                {
                  key: 'actions', header: '', render: (s) => (
                    <div className="flex gap-2">
                      <Button size="sm" variant="outline" onClick={(e) => { e.stopPropagation(); openEditSupplier(s); }}>Edit</Button>
                      <Button size="sm" variant="danger" onClick={(e) => { e.stopPropagation(); setDeleteSupplier(s); }}><Trash2 size={13} /></Button>
                    </div>
                  ),
                },
              ]}
              data={suppliers}
              keyField="id"
            />
          )}
        </Card>
      )}

      {/* Create PO Modal */}
      <Modal open={showPO} onClose={() => setShowPO(false)} title="New Purchase Order">
        <div className="space-y-4">
          <Select
            label="Supplier *"
            value={poForm.supplier_id}
            onChange={(e) => setPoForm({ ...poForm, supplier_id: e.target.value })}
            options={[{ value: '', label: 'Select supplier...' }, ...suppliers.map((s) => ({ value: String(s.id), label: s.name }))]}
          />
          <Input label="Expected Delivery Date" type="date" value={poForm.expected_date} onChange={(e) => setPoForm({ ...poForm, expected_date: e.target.value })} />
          <Input label="Notes" value={poForm.notes} onChange={(e) => setPoForm({ ...poForm, notes: e.target.value })} placeholder="Optional" />

          {/* Line items */}
          <div>
            <div className="flex justify-between items-center mb-2">
              <span className="text-xs font-medium text-gray-600">Items</span>
              <button onClick={addPoItem} className="text-xs text-primary hover:underline flex items-center gap-1"><Plus size={12} /> Add item</button>
            </div>
            <div className="space-y-2">
              {poItems.map((item, idx) => (
                <div key={idx} className="grid grid-cols-5 gap-2 items-end bg-gray-50 p-2 rounded-lg">
                  <Select
                    label={idx === 0 ? 'Category' : ''}
                    value={item.category}
                    onChange={(e) => updatePoItem(idx, 'category', e.target.value)}
                    options={['phone', 'laptop', 'accessory'].map((c) => ({ value: c, label: c }))}
                  />
                  <Input label={idx === 0 ? 'Brand' : ''} value={item.brand} onChange={(e) => updatePoItem(idx, 'brand', e.target.value)} placeholder="Samsung" />
                  <Input label={idx === 0 ? 'Model' : ''} value={item.model} onChange={(e) => updatePoItem(idx, 'model', e.target.value)} placeholder="Galaxy S22" />
                  <Input label={idx === 0 ? 'Cost (₹)' : ''} type="number" value={item.purchase_price} onChange={(e) => updatePoItem(idx, 'purchase_price', e.target.value)} placeholder="8000" />
                  <div className="flex items-end gap-1">
                    <Input label={idx === 0 ? 'Qty' : ''} type="number" value={item.quantity} onChange={(e) => updatePoItem(idx, 'quantity', e.target.value)} />
                    {poItems.length > 1 && (
                      <button onClick={() => removePoItem(idx)} className="mb-0.5 text-red-400 hover:text-red-600"><Trash2 size={14} /></button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="flex gap-3 pt-2">
            <Button onClick={() => createPOMut.mutate()} loading={createPOMut.isPending} className="flex-1 justify-center">Create PO</Button>
            <Button variant="outline" onClick={() => setShowPO(false)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* Receive Stock Modal */}
      <Modal open={receiveId !== null} onClose={() => setReceiveId(null)} title="Receive Stock">
        <div className="space-y-4">
          <p className="text-sm text-gray-600">Confirm stock received for this purchase order. Products will enter the QC queue.</p>
          <div className="flex gap-3 pt-2">
            <Button onClick={() => receiveMut.mutate()} loading={receiveMut.isPending} className="flex-1 justify-center">Confirm Receipt</Button>
            <Button variant="outline" onClick={() => setReceiveId(null)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* Add Supplier Modal */}
      <Modal open={showSupplier} onClose={() => { setShowSupplier(false); setSupplierForm(EMPTY_SUPPLIER); }} title="Add Supplier">
        <SupplierForm form={supplierForm} setForm={setSupplierForm} onSubmit={() => createSupplierMut.mutate()} onCancel={() => { setShowSupplier(false); setSupplierForm(EMPTY_SUPPLIER); }} loading={createSupplierMut.isPending} />
      </Modal>

      {/* Edit Supplier Modal */}
      <Modal open={!!editSupplier} onClose={() => setEditSupplier(null)} title={`Edit — ${editSupplier?.name}`}>
        <SupplierForm form={supplierForm} setForm={setSupplierForm} onSubmit={() => updateSupplierMut.mutate()} onCancel={() => setEditSupplier(null)} loading={updateSupplierMut.isPending} />
      </Modal>

      {/* Delete Supplier Modal */}
      <Modal open={!!deleteSupplier} onClose={() => setDeleteSupplier(null)} title="Delete Supplier">
        <div className="space-y-4">
          <p className="text-sm text-gray-600">Are you sure you want to delete <span className="font-semibold">{deleteSupplier?.name}</span>?</p>
          <div className="flex gap-3">
            <Button variant="danger" onClick={() => deleteSupplierMut.mutate(deleteSupplier.id)} loading={deleteSupplierMut.isPending} className="flex-1 justify-center">Delete</Button>
            <Button variant="outline" onClick={() => setDeleteSupplier(null)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
