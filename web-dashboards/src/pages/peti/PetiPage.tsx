import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2, CheckCircle, XCircle } from 'lucide-react';
import toast from 'react-hot-toast';
import { petiService } from '../../services/newModules';
import { procurementService } from '../../services';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Modal, Input, Select, fmtINR, fmtDate } from '../../components/ui';

const STATUS_COLORS: Record<string, string> = { draft: 'gray', approved: 'blue', completed: 'green', cancelled: 'red' };
const EMPTY_ITEM = { category: 'phone', brand: '', model: '', grade: 'S1', quantity: '1', unit_price: '' };

export default function PetiPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [showCreate, setShowCreate] = useState(false);
  const [selected, setSelected] = useState<any>(null);
  const [transferType, setTransferType] = useState<'internal' | 'dealer'>('internal');
  const [fromLoc, setFromLoc] = useState('');
  const [toLoc, setToLoc] = useState('');
  const [toDealerId, setToDealerId] = useState('');
  const [notes, setNotes] = useState('');
  const [items, setItems] = useState([{ ...EMPTY_ITEM }]);

  const { data, isLoading } = useQuery({
    queryKey: ['peti-transfers', page, statusFilter],
    queryFn: () => petiService.list({ page: String(page), ...(statusFilter && { status: statusFilter }) }),
  });

  useQuery({
    queryKey: ['dealers-peti'],
    queryFn: () => procurementService.suppliers({}),
  });

  const { data: detailData } = useQuery({
    queryKey: ['peti-detail', selected?.id],
    queryFn: () => petiService.show(selected!.id),
    enabled: !!selected,
  });

  const createMut = useMutation({
    mutationFn: () => petiService.create({ type: transferType, from_location: fromLoc, to_location: toLoc || undefined, to_dealer_id: toDealerId ? Number(toDealerId) : undefined, items: items.map(i => ({ ...i, quantity: Number(i.quantity), unit_price: Number(i.unit_price) })), notes }),
    onSuccess: () => { toast.success('Transfer created'); qc.invalidateQueries({ queryKey: ['peti-transfers'] }); setShowCreate(false); resetForm(); },
    onError: () => toast.error('Failed to create transfer'),
  });

  const approveMut = useMutation({
    mutationFn: (id: number) => petiService.approve(id),
    onSuccess: () => { toast.success('Transfer approved'); qc.invalidateQueries({ queryKey: ['peti-transfers'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const completeMut = useMutation({
    mutationFn: (id: number) => petiService.complete(id),
    onSuccess: () => { toast.success('Transfer completed'); qc.invalidateQueries({ queryKey: ['peti-transfers'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const cancelMut = useMutation({
    mutationFn: (id: number) => petiService.cancel(id),
    onSuccess: () => { toast.success('Transfer cancelled'); qc.invalidateQueries({ queryKey: ['peti-transfers'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const resetForm = () => { setFromLoc(''); setToLoc(''); setToDealerId(''); setNotes(''); setItems([{ ...EMPTY_ITEM }]); setTransferType('internal'); };

  const addItem = () => setItems([...items, { ...EMPTY_ITEM }]);
  const removeItem = (idx: number) => setItems(items.filter((_, i) => i !== idx));
  const updateItem = (idx: number, field: string, value: string) => setItems(items.map((item, i) => i === idx ? { ...item, [field]: value } : item));

  const transfers: any[] = data?.data ?? [];
  const meta = data?.meta;
  const detail = detailData;

  return (
    <div>
      <PageHeader
        title="Peti to Peti"
        subtitle="Bulk box trading & internal stock transfers"
        action={<Button onClick={() => { resetForm(); setShowCreate(true); }}><Plus size={15} /> New Transfer</Button>}
      />

      <div className="mb-5">
        <Select value={statusFilter} onChange={e => { setStatusFilter(e.target.value); setPage(1); }}
          options={[{ value: '', label: 'All Statuses' }, ...Object.keys(STATUS_COLORS).map(s => ({ value: s, label: s.charAt(0).toUpperCase() + s.slice(1) }))]} />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'transfer_number', header: 'Transfer #', render: t => <code className="text-xs font-bold">{t.transfer_number}</code> },
                { key: 'type', header: 'Type', render: t => <Badge label={t.type} color={t.type === 'dealer' ? 'blue' : 'purple'} /> },
                { key: 'from_location', header: 'From', render: t => t.from_location ?? '—' },
                { key: 'to', header: 'To', render: t => t.to_dealer?.business_name ?? t.to_location ?? '—' },
                { key: 'total_units', header: 'Units', render: t => <span className="font-semibold">{t.total_units}</span> },
                { key: 'total_value', header: 'Value', render: t => fmtINR(t.total_value ?? 0) },
                { key: 'status', header: 'Status', render: t => <Badge label={t.status} color={STATUS_COLORS[t.status]} /> },
                { key: 'created_at', header: 'Date', render: t => <span className="text-xs text-gray-500">{fmtDate(t.created_at)}</span> },
                {
                  key: 'actions', header: '', render: t => (
                    <Button size="sm" variant="outline" onClick={e => { e.stopPropagation(); setSelected(t); }}>View</Button>
                  ),
                },
              ]}
              data={transfers}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Detail Modal */}
      <Modal open={!!selected} onClose={() => setSelected(null)} title={`Transfer ${selected?.transfer_number}`}>
        {selected && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div><span className="text-xs text-gray-500 block">Type</span><Badge label={detail?.type ?? selected.type} color="blue" /></div>
              <div><span className="text-xs text-gray-500 block">Status</span><Badge label={detail?.status ?? selected.status} color={STATUS_COLORS[detail?.status ?? selected.status]} /></div>
              <div><span className="text-xs text-gray-500 block">From</span>{detail?.from_location ?? selected.from_location ?? '—'}</div>
              <div><span className="text-xs text-gray-500 block">To</span>{detail?.to_dealer?.business_name ?? detail?.to_location ?? selected.to_location ?? '—'}</div>
              <div><span className="text-xs text-gray-500 block">Total Units</span><span className="font-bold">{detail?.total_units ?? selected.total_units}</span></div>
              <div><span className="text-xs text-gray-500 block">Total Value</span><span className="font-bold">{fmtINR(detail?.total_value ?? selected.total_value ?? 0)}</span></div>
            </div>

            {/* Items */}
            {(detail?.items ?? []).length > 0 && (
              <div>
                <div className="text-xs font-medium text-gray-500 mb-2">Items</div>
                <div className="space-y-1 max-h-40 overflow-y-auto">
                  {(detail?.items ?? []).map((item: any, i: number) => (
                    <div key={i} className="flex justify-between text-xs bg-gray-50 px-3 py-2 rounded">
                      <span>{item.brand} {item.model} — Grade {item.grade} × {item.quantity}</span>
                      <span className="font-semibold">{fmtINR(item.unit_price * item.quantity)}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Actions */}
            <div className="flex gap-2 pt-2 border-t">
              {(detail?.status ?? selected.status) === 'draft' && (
                <Button size="sm" onClick={() => approveMut.mutate(selected.id)} loading={approveMut.isPending}>
                  <CheckCircle size={13} /> Approve
                </Button>
              )}
              {(detail?.status ?? selected.status) === 'approved' && (
                <Button size="sm" onClick={() => completeMut.mutate(selected.id)} loading={completeMut.isPending}>
                  <CheckCircle size={13} /> Complete
                </Button>
              )}
              {!['completed', 'cancelled'].includes(detail?.status ?? selected.status) && (
                <Button size="sm" variant="danger" onClick={() => cancelMut.mutate(selected.id)} loading={cancelMut.isPending}>
                  <XCircle size={13} /> Cancel
                </Button>
              )}
            </div>
          </div>
        )}
      </Modal>

      {/* Create Transfer Modal */}
      <Modal open={showCreate} onClose={() => { setShowCreate(false); resetForm(); }} title="New Peti Transfer">
        <div className="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
          <div className="flex gap-2">
            {(['internal', 'dealer'] as const).map(t => (
              <button key={t} onClick={() => setTransferType(t)}
                className={`flex-1 py-2 rounded-lg text-sm font-medium border transition-colors ${transferType === t ? 'bg-primary text-white border-primary' : 'border-gray-200 text-gray-600 hover:border-primary'}`}>
                {t === 'internal' ? 'Internal Transfer' : 'Transfer to Dealer'}
              </button>
            ))}
          </div>

          <Input label="From Location" value={fromLoc} onChange={e => setFromLoc(e.target.value)} placeholder="e.g. Warehouse Zone A" />
          {transferType === 'internal' && (
            <Input label="To Location" value={toLoc} onChange={e => setToLoc(e.target.value)} placeholder="e.g. Warehouse Zone B" />
          )}

          <Input label="Notes" value={notes} onChange={e => setNotes(e.target.value)} placeholder="Optional" />

          {/* Items */}
          <div>
            <div className="flex justify-between items-center mb-2">
              <span className="text-xs font-medium text-gray-600">Box Items</span>
              <button onClick={addItem} className="text-xs text-primary hover:underline flex items-center gap-1"><Plus size={12} /> Add item</button>
            </div>
            <div className="space-y-2">
              {items.map((item, idx) => (
                <div key={idx} className="grid grid-cols-6 gap-2 items-end bg-gray-50 p-2 rounded-lg">
                  <Select label={idx === 0 ? 'Category' : ''} value={item.category} onChange={e => updateItem(idx, 'category', e.target.value)}
                    options={['phone', 'laptop', 'accessory'].map(c => ({ value: c, label: c }))} />
                  <Input label={idx === 0 ? 'Brand' : ''} value={item.brand} onChange={e => updateItem(idx, 'brand', e.target.value)} placeholder="Apple" />
                  <Input label={idx === 0 ? 'Model' : ''} value={item.model} onChange={e => updateItem(idx, 'model', e.target.value)} placeholder="iPhone 14" />
                  <Select label={idx === 0 ? 'Grade' : ''} value={item.grade} onChange={e => updateItem(idx, 'grade', e.target.value)}
                    options={['S1','S2','S3','S4','S5'].map(g => ({ value: g, label: g }))} />
                  <Input label={idx === 0 ? 'Qty' : ''} type="number" value={item.quantity} onChange={e => updateItem(idx, 'quantity', e.target.value)} />
                  <div className="flex items-end gap-1">
                    <Input label={idx === 0 ? 'Price ₹' : ''} type="number" value={item.unit_price} onChange={e => updateItem(idx, 'unit_price', e.target.value)} />
                    {items.length > 1 && <button onClick={() => removeItem(idx)} className="mb-0.5 text-red-400 hover:text-red-600"><Trash2 size={14} /></button>}
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="flex gap-3 pt-2">
            <Button onClick={() => createMut.mutate()} loading={createMut.isPending} className="flex-1 justify-center">Create Transfer</Button>
            <Button variant="outline" onClick={() => { setShowCreate(false); resetForm(); }} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
