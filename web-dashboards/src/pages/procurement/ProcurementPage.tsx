import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2, Upload, Download } from 'lucide-react';
import toast from 'react-hot-toast';
import { procurementService } from '../../services';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Modal, Input, Select, fmtINR } from '../../components/ui';
import { ReceiveItemsForm, EMPTY_RECEIVE_ITEM, expandReceiveItems, type ReceiveItemRow } from '../../components/ReceiveItemsForm';

const PO_STATUS_COLORS: Record<string, string> = {
  draft: 'gray', sent: 'blue', received: 'green', partial: 'yellow', cancelled: 'red',
};

const EMPTY_SUPPLIER = { name: '', type: '', phone: '', email: '', gst_number: '', address: '' };
const EMPTY_PO = { supplier_id: '' };
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
      <Select
        label="Type *"
        value={form.type}
        onChange={(e) => setForm({ ...form, type: e.target.value })}
        options={[
          { value: '', label: 'Select type...' },
          { value: 'dealer', label: 'Dealer' },
          { value: 'importer', label: 'Importer' },
          { value: 'buyback_partner', label: 'Buyback Partner' },
        ]}
      />
      <Input label="Phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Input label="Email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
        <Input label="GST Number" value={form.gst_number} onChange={(e) => setForm({ ...form, gst_number: e.target.value })} />
      </div>
      <Input label="Address" value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
      <div className="flex gap-3 pt-2">
        <Button onClick={onSubmit} loading={loading} disabled={!form.name || !form.type} className="flex-1 justify-center">Save</Button>
        <Button variant="outline" onClick={onCancel} className="flex-1 justify-center">Cancel</Button>
      </div>
    </div>
  );
}

export default function ProcurementPage() {
  const qc = useQueryClient();
  const [tab, setTab] = useState<'orders' | 'suppliers'>('orders');
  const [page, setPage] = useState(1);

  // PO state — a PO is just a commitment (supplier + expected totals); the
  // actual per-unit detail (brand/model/IMEI) is recorded at Receive time.
  const [showPO, setShowPO] = useState(false);
  const [receivePO, setReceivePO] = useState<any | null>(null);
  const [poForm, setPoForm] = useState(EMPTY_PO);
  const [poItems, setPoItems] = useState<ReceiveItemRow[]>([{ ...EMPTY_RECEIVE_ITEM }]);
  const [receiveItems, setReceiveItems] = useState<ReceiveItemRow[]>([{ ...EMPTY_RECEIVE_ITEM }]);

  // Supplier state
  const [showSupplier, setShowSupplier] = useState(false);
  const [editSupplier, setEditSupplier] = useState<any | null>(null);
  const [deleteSupplier, setDeleteSupplier] = useState<any | null>(null);
  const [supplierForm, setSupplierForm] = useState(EMPTY_SUPPLIER);

  // Bulk import state
  const [showImport, setShowImport] = useState(false);
  const [importSupplierId, setImportSupplierId] = useState('');
  const [importFile, setImportFile] = useState<File | null>(null);
  const [importResult, setImportResult] = useState<any | null>(null);

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
    mutationFn: () => {
      const units = poItems.reduce((sum, i) => sum + (Number(i.quantity) || 1), 0);
      const total = poItems.reduce((sum, i) => sum + (Number(i.purchase_price) || 0) * (Number(i.quantity) || 1), 0);
      return procurementService.createPurchaseOrder({
        supplier_id: Number(poForm.supplier_id),
        expected_count: units,
        total_amount: total,
      });
    },
    onSuccess: () => {
      toast.success('Purchase order created');
      qc.invalidateQueries({ queryKey: ['purchase-orders'] });
      setShowPO(false);
      setPoForm(EMPTY_PO);
      setPoItems([{ ...EMPTY_RECEIVE_ITEM }]);
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to create PO'),
  });

  const receiveMut = useMutation({
    mutationFn: () => procurementService.receivePO(receivePO!.id, {
      supplier_id: receivePO!.supplier_id ?? receivePO!.supplier?.id,
      items: expandReceiveItems(receiveItems),
    }),
    onSuccess: () => {
      toast.success('Stock received — products entered the QC queue');
      qc.invalidateQueries({ queryKey: ['purchase-orders'] });
      qc.invalidateQueries({ queryKey: ['inventory'] });
      setReceivePO(null);
      setReceiveItems([{ ...EMPTY_RECEIVE_ITEM }]);
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to record receipt'),
  });

  const importMut = useMutation({
    mutationFn: () => procurementService.importReceive(importFile!, importSupplierId),
    onSuccess: (res: any) => {
      const result = res.data ?? res;
      setImportResult(result);
      qc.invalidateQueries({ queryKey: ['inventory'] });
      if (result.created_count > 0) toast.success(`${result.created_count} item(s) imported`);
      if (result.failed_count > 0) toast.error(`${result.failed_count} row(s) skipped — see details below`);
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Import failed'),
  });

  const downloadTemplate = async () => {
    const blob = await procurementService.importTemplate();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'receiving_template.csv';
    a.click();
    URL.revokeObjectURL(url);
  };

  const createSupplierMut = useMutation({
    mutationFn: () => procurementService.createSupplier(supplierForm),
    onSuccess: () => {
      toast.success('Supplier added');
      qc.invalidateQueries({ queryKey: ['suppliers'] });
      setShowSupplier(false);
      setSupplierForm(EMPTY_SUPPLIER);
    },
    onError: (e: any) => toast.error(e?.response?.data?.message ?? 'Failed to add supplier'),
  });

  const updateSupplierMut = useMutation({
    mutationFn: () => procurementService.updateSupplier(editSupplier.id, supplierForm),
    onSuccess: () => {
      toast.success('Supplier updated');
      qc.invalidateQueries({ queryKey: ['suppliers'] });
      setEditSupplier(null);
    },
    onError: (e: any) => toast.error(e?.response?.data?.message ?? 'Failed to update supplier'),
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
    setSupplierForm({ name: s.name ?? '', type: s.type ?? '', phone: s.phone ?? '', email: s.email ?? '', gst_number: s.gst_number ?? '', address: s.address ?? '' });
    setEditSupplier(s);
  };

  const openReceive = (po: any) => {
    setReceiveItems([{ ...EMPTY_RECEIVE_ITEM }]);
    setReceivePO(po);
  };

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
            ? (
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => { setImportSupplierId(''); setImportFile(null); setImportResult(null); setShowImport(true); }}>
                  <Upload size={15} /> Bulk Import
                </Button>
                <Button onClick={() => setShowPO(true)}><Plus size={15} /> New PO</Button>
              </div>
            )
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
                  { key: 'id', header: 'PO #', render: (p) => <span className="font-mono text-xs">PO-{String(p.id).padStart(5, '0')}</span> },
                  { key: 'supplier', header: 'Supplier', render: (p) => p.supplier?.name ?? '—' },
                  { key: 'status', header: 'Status', render: (p) => <Badge label={p.status} color={PO_STATUS_COLORS[p.status] ?? 'gray'} /> },
                  { key: 'expected_count', header: 'Units', render: (p) => `${p.received_count ?? 0}/${p.expected_count ?? '—'} received` },
                  { key: 'total_amount', header: 'Amount', render: (p) => fmtINR(p.total_amount ?? 0) },
                  {
                    key: 'actions', header: '', render: (p) => p.status !== 'received' ? (
                      <Button size="sm" onClick={(e) => { e.stopPropagation(); openReceive(p); }}>Receive Stock</Button>
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
                { key: 'type', header: 'Type', render: (s) => <Badge label={s.type ?? '—'} color="blue" /> },
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

      {/* Create PO Modal — a PO is a commitment (supplier + expected totals);
          items entered here just compute those totals for you. Exact
          brand/model/IMEI is recorded when you Receive against this PO. */}
      <Modal open={showPO} onClose={() => setShowPO(false)} title="New Purchase Order" width="max-w-2xl">
        <div className="space-y-4">
          <Select
            label="Supplier *"
            value={poForm.supplier_id}
            onChange={(e) => setPoForm({ ...poForm, supplier_id: e.target.value })}
            options={[{ value: '', label: 'Select supplier...' }, ...suppliers.map((s) => ({ value: String(s.id), label: s.name }))]}
          />

          <ReceiveItemsForm items={poItems} setItems={setPoItems} />
          <p className="text-xs text-gray-500">
            These items are used to calculate the expected unit count and total order value — exact
            brand/model/IMEI per unit gets recorded when you receive the stock.
          </p>

          <div className="flex gap-3 pt-2">
            <Button onClick={() => createPOMut.mutate()} loading={createPOMut.isPending} disabled={!poForm.supplier_id} className="flex-1 justify-center">Create PO</Button>
            <Button variant="outline" onClick={() => setShowPO(false)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* Receive Stock Modal — THIS is where real products get created. */}
      <Modal open={!!receivePO} onClose={() => setReceivePO(null)} title={`Receive Stock — Supplier: ${receivePO?.supplier?.name ?? ''}`} width="max-w-2xl">
        <div className="space-y-4">
          <p className="text-sm text-gray-600">Enter exactly what arrived. Each item becomes a real product and enters the QC queue.</p>
          <ReceiveItemsForm items={receiveItems} setItems={setReceiveItems} />
          <div className="flex gap-3 pt-2">
            <Button onClick={() => receiveMut.mutate()} loading={receiveMut.isPending} className="flex-1 justify-center">Confirm Receipt</Button>
            <Button variant="outline" onClick={() => setReceivePO(null)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* Bulk Import Modal */}
      <Modal open={showImport} onClose={() => setShowImport(false)} title="Bulk Import Stock" width="max-w-lg">
        <div className="space-y-4">
          <p className="text-sm text-gray-600">
            For receiving many units at once instead of typing each one in. Download the template,
            fill in one row per product (or per batch of identical units — use the quantity column
            instead of repeating rows), and upload it back.
          </p>

          <button onClick={downloadTemplate} className="text-sm text-primary hover:underline flex items-center gap-1">
            <Download size={14} /> Download CSV template
          </button>

          <Select
            label="Supplier *"
            value={importSupplierId}
            onChange={(e) => setImportSupplierId(e.target.value)}
            options={[{ value: '', label: 'Select supplier...' }, ...suppliers.map((s: any) => ({ value: String(s.id), label: s.name }))]}
          />

          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-gray-600">Filled-in CSV *</label>
            <input
              type="file"
              accept=".csv,text/csv"
              onChange={(e) => setImportFile(e.target.files?.[0] ?? null)}
              className="text-sm border border-gray-300 rounded-lg px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-primary/10 file:text-primary file:text-xs file:font-medium"
            />
          </div>

          {importResult && (
            <div className="bg-gray-50 rounded-lg p-3 text-sm space-y-2">
              <div className="flex gap-4">
                <span className="text-green-700 font-medium">{importResult.created_count} created</span>
                {importResult.failed_count > 0 && <span className="text-red-600 font-medium">{importResult.failed_count} skipped</span>}
              </div>
              {importResult.failed?.length > 0 && (
                <div className="space-y-1 max-h-40 overflow-y-auto">
                  {importResult.failed.map((f: any, i: number) => (
                    <div key={i} className="text-xs text-red-600">Row {f.row}: {f.reason}</div>
                  ))}
                </div>
              )}
            </div>
          )}

          <div className="flex gap-3 pt-2">
            <Button
              onClick={() => importMut.mutate()}
              loading={importMut.isPending}
              disabled={!importSupplierId || !importFile}
              className="flex-1 justify-center"
            >
              Import
            </Button>
            <Button variant="outline" onClick={() => setShowImport(false)} className="flex-1 justify-center">Close</Button>
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
