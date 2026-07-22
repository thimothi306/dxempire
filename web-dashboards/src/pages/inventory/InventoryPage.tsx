import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Download, Plus } from 'lucide-react';
import toast from 'react-hot-toast';
import { inventoryService, procurementService } from '../../services';
import { Card, Table, Pagination, Input, Select, Badge, Button, PageHeader, Spinner, Modal, fmtINR } from '../../components/ui';
import { ReceiveItemsForm, EMPTY_RECEIVE_ITEM, expandReceiveItems, type ReceiveItemRow } from '../../components/ReceiveItemsForm';
import type { Product } from '../../types';

const STATUS_COLORS: Record<string, string> = {
  in_stock: 'green', qc_pending: 'yellow', received: 'blue', reserved: 'orange',
  sold: 'gray', returned: 'purple', rejected: 'red', refurbishment: 'purple',
};

const GRADE_COLORS: Record<string, string> = { S1: 'green', S2: 'blue', S3: 'yellow', S4: 'orange', S5: 'red' };

function StockAvailabilityWidget() {
  const { data, isLoading } = useQuery({
    queryKey: ['inventory-availability'],
    queryFn: () => inventoryService.availability(),
    staleTime: 60_000,
  });

  if (isLoading) return <Spinner />;
  if (!data) return null;

  const sections = [
    { key: 'phones', label: 'Phones' },
    { key: 'laptops', label: 'Laptops' },
    { key: 'accessories', label: 'Accessories' },
  ] as const;

  return (
    <div className="grid grid-cols-3 gap-4 mb-6">
      {sections.map(({ key, label }) => {
        const section = data[key] ?? {};
        const grades = ['S1', 'S2', 'S3', 'S4', 'S5'].filter(g => section[g] !== undefined);
        return (
          <Card key={key}>
            <div className="flex items-center justify-between mb-3">
              <span className="text-sm font-semibold text-gray-700">{label}</span>
              <span className="text-lg font-bold text-primary">{section.total ?? 0}</span>
            </div>
            <div className="flex flex-wrap gap-2">
              {grades.map(g => (
                <div key={g} className="flex items-center gap-1.5">
                  <Badge label={g} color={GRADE_COLORS[g]} />
                  <span className="text-sm font-semibold">{section[g]}</span>
                </div>
              ))}
              {grades.length === 0 && <span className="text-xs text-gray-400">No stock</span>}
            </div>
          </Card>
        );
      })}
    </div>
  );
}

export default function InventoryPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState({ search: '', category: '', grade: '', status: '' });
  const [showAdd, setShowAdd] = useState(false);
  const [addSupplierId, setAddSupplierId] = useState('');
  const [addItems, setAddItems] = useState<ReceiveItemRow[]>([{ ...EMPTY_RECEIVE_ITEM }]);

  const { data, isLoading } = useQuery({
    queryKey: ['inventory', page, filters],
    queryFn: () => inventoryService.list({ page: String(page), per_page: '50', ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v)) }),
  });

  const { data: suppData } = useQuery({
    queryKey: ['suppliers'],
    queryFn: () => procurementService.suppliers({}),
    enabled: showAdd,
  });
  const suppliers: any[] = suppData?.data ?? [];

  const addProductMut = useMutation({
    mutationFn: () => procurementService.receive({
      supplier_id: Number(addSupplierId),
      items: expandReceiveItems(addItems),
    }),
    onSuccess: () => {
      toast.success('Product(s) added — sent to QC queue');
      qc.invalidateQueries({ queryKey: ['inventory'] });
      qc.invalidateQueries({ queryKey: ['inventory-availability'] });
      setShowAdd(false);
      setAddSupplierId('');
      setAddItems([{ ...EMPTY_RECEIVE_ITEM }]);
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to add product'),
  });

  const products: Product[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };

  const handleExport = async () => {
    const blob = await inventoryService.export();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'inventory.xlsx'; a.click();
  };

  return (
    <div>
      <PageHeader
        title="Inventory"
        subtitle={`${meta?.total ?? 0} total items`}
        action={
          <div className="flex gap-2">
            <Button onClick={() => setShowAdd(true)}><Plus size={15} /> Add Product</Button>
            <Button variant="outline" onClick={handleExport}><Download size={15} /> Export</Button>
          </div>
        }
      />

      <StockAvailabilityWidget />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <Input placeholder="Search IMEI / model..." value={filters.search} onChange={(e) => setFilters({ ...filters, search: e.target.value })} />
        <Select value={filters.category} onChange={(e) => setFilters({ ...filters, category: e.target.value })}
          options={[{ value: '', label: 'All Categories' }, { value: 'phone', label: 'Phone' }, { value: 'laptop', label: 'Laptop' }, { value: 'accessory', label: 'Accessory' }]} />
        <Select value={filters.grade} onChange={(e) => setFilters({ ...filters, grade: e.target.value })}
          options={[{ value: '', label: 'All Grades' }, ...['S1','S2','S3','S4','S5'].map((g) => ({ value: g, label: g }))]} />
        <Select value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })}
          options={[{ value: '', label: 'All Statuses' }, ...Object.keys(STATUS_COLORS).map((s) => ({ value: s, label: s.replace('_', ' ') }))]} />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'imei', header: 'IMEI', render: (p) => <code className="text-xs">{p.imei}</code> },
                { key: 'brand', header: 'Brand' },
                { key: 'model', header: 'Model' },
                { key: 'category', header: 'Category', render: (p) => <Badge label={p.category} color="blue" /> },
                { key: 'grade', header: 'Grade', render: (p) => <Badge label={p.grade} color="purple" /> },
                { key: 'status', header: 'Status', render: (p) => <Badge label={p.status.replace('_', ' ')} color={STATUS_COLORS[p.status] ?? 'gray'} /> },
                { key: 'selling_price', header: 'Price', render: (p) => fmtINR(p.selling_price) },
                { key: 'bin', header: 'Bin', render: (p) => p.bin?.code ?? '—' },
              ]}
              data={products}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Add Product Modal — creates real product records (status: received),
          which then go through QC before appearing as in_stock / sellable. */}
      <Modal open={showAdd} onClose={() => setShowAdd(false)} title="Add Product" width="max-w-2xl">
        <div className="space-y-4">
          <Select
            label="Supplier *"
            value={addSupplierId}
            onChange={(e) => setAddSupplierId(e.target.value)}
            options={[{ value: '', label: 'Select supplier...' }, ...suppliers.map((s: any) => ({ value: String(s.id), label: s.name }))]}
          />
          <ReceiveItemsForm items={addItems} setItems={setAddItems} />
          <p className="text-xs text-gray-500">New products enter as "received" and must pass QC grading before they show as in-stock.</p>
          <div className="flex gap-3 pt-2">
            <Button onClick={() => addProductMut.mutate()} loading={addProductMut.isPending} disabled={!addSupplierId} className="flex-1 justify-center">Add Product</Button>
            <Button variant="outline" onClick={() => setShowAdd(false)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
