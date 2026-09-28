import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2 } from 'lucide-react';
import toast from 'react-hot-toast';
import { binsService, warehouseService } from '../../services';
import { Card, Table, Pagination, Button, PageHeader, Spinner, Modal, Input, Select, ExportButton } from '../../components/ui';
import type { Bin } from '../../types';

const EMPTY_BIN_FORM = { code: '', zone: '', capacity: '', warehouse_id: '' };

export default function BinsPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [showCreate, setShowCreate] = useState(false);
  const [editTarget, setEditTarget] = useState<Bin | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Bin | null>(null);
  const [form, setForm] = useState(EMPTY_BIN_FORM);

  const { data, isLoading } = useQuery({
    queryKey: ['bins', page],
    queryFn: () => binsService.list({ page: String(page) }),
  });

  // Only fetched/shown when there's more than one warehouse — single-warehouse
  // installs never see this selector, bins auto-assign to the one warehouse.
  const { data: warehousesData } = useQuery({ queryKey: ['warehouses'], queryFn: () => warehouseService.list({ is_active: 'true' }) });
  const warehouses: { id: number; name: string; is_default: boolean }[] = Array.isArray(warehousesData) ? warehousesData : [];

  const createMut = useMutation({
    mutationFn: () => binsService.create({
      code: form.code, zone: form.zone, capacity: Number(form.capacity),
      ...(form.warehouse_id ? { warehouse_id: Number(form.warehouse_id) } : {}),
    }),
    onSuccess: () => { toast.success('Bin created'); qc.invalidateQueries({ queryKey: ['bins'] }); setShowCreate(false); setForm(EMPTY_BIN_FORM); },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to create bin'),
  });

  const updateMut = useMutation({
    mutationFn: () => binsService.update(editTarget!.id, {
      code: form.code, zone: form.zone, capacity: Number(form.capacity),
      ...(form.warehouse_id ? { warehouse_id: Number(form.warehouse_id) } : {}),
    }),
    onSuccess: () => { toast.success('Bin updated'); qc.invalidateQueries({ queryKey: ['bins'] }); setEditTarget(null); },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to update bin'),
  });

  const deleteMut = useMutation({
    mutationFn: (id: number) => binsService.destroy(id),
    onSuccess: () => { toast.success('Bin deleted'); qc.invalidateQueries({ queryKey: ['bins'] }); setDeleteTarget(null); },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to delete bin'),
  });

  const openEdit = (bin: Bin & { warehouse_id?: number }) => {
    setForm({
      code: bin.code,
      zone: bin.zone ?? '',
      capacity: String(bin.capacity ?? ''),
      warehouse_id: bin.warehouse_id ? String(bin.warehouse_id) : '',
    });
    setEditTarget(bin);
  };

  const bins: Bin[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };

  return (
    <div>
      <PageHeader
        title="Bin Management"
        subtitle="Warehouse storage locations"
        action={
          <div className="flex gap-2">
            <ExportButton filenameBase="bins" onExport={(format) => binsService.export(format)} />
            <Button onClick={() => setShowCreate(true)}><Plus size={15} /> New Bin</Button>
          </div>
        }
      />

      <div className="mb-5 bg-blue-50 border border-blue-100 rounded-xl px-5 py-4 text-sm text-gray-700 space-y-1.5">
        <p><span className="font-semibold">What a bin is:</span> a physical shelf/location slot inside a warehouse — this screen only creates the slot. Creating a bin here does not put any stock into it.</p>
        <p><span className="font-semibold">How stock actually gets into a bin:</span> go to <span className="font-medium">Inventory</span>, find the product, and use its <span className="font-medium">"Move to Bin"</span> button. That's the only action that changes a bin's occupancy count.</p>
        <p><span className="font-semibold">Occupancy</span> (e.g. <span className="font-mono">12/50</span>) is current units placed / maximum capacity you set when creating the bin. A new bin always starts at 0 and stays there until a product is moved into it from Inventory.</p>
        <p><span className="font-semibold">Zone</span> is just a label for grouping bins (e.g. "Zone A") — it doesn't affect capacity or behavior, purely for organizing the warehouse floor.</p>
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'code', header: 'Bin Code', render: (b) => <span className="font-mono font-semibold">{b.code}</span> },
                ...(warehouses.length > 1 ? [{ key: 'warehouse', header: 'Warehouse', render: (b: any) => b.warehouse?.name ?? '—' }] : []),
                { key: 'zone', header: 'Zone', render: (b) => b.zone ?? '—' },
                {
                  key: 'occupancy', header: 'Occupancy', render: (b) => {
                    const pct = b.capacity ? Math.round(((b.current_count ?? 0) / b.capacity) * 100) : 0;
                    const color = pct > 90 ? 'bg-red-500' : pct > 70 ? 'bg-yellow-400' : 'bg-green-500';
                    return (
                      <div className="flex items-center gap-2">
                        <div className="w-24 h-2 bg-gray-100 rounded-full overflow-hidden">
                          <div className={`h-full ${color} rounded-full`} style={{ width: `${pct}%` }} />
                        </div>
                        <span className="text-xs text-gray-500">{b.current_count ?? 0}/{b.capacity ?? '∞'}</span>
                      </div>
                    );
                  },
                },
                {
                  key: 'actions', header: '', render: (b: any) => (
                    <div className="flex gap-2">
                      <Button size="sm" variant="outline" onClick={(e) => { e.stopPropagation(); openEdit(b); }}>
                        <Pencil size={13} />
                      </Button>
                      <Button size="sm" variant="danger" onClick={(e) => { e.stopPropagation(); setDeleteTarget(b); }}>
                        <Trash2 size={13} />
                      </Button>
                    </div>
                  ),
                },
              ]}
              data={bins}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Create Bin Modal */}
      <Modal open={showCreate} onClose={() => { setShowCreate(false); setForm(EMPTY_BIN_FORM); }} title="Create Bin">
        <div className="space-y-4">
          {warehouses.length > 1 && (
            <Select
              label="Warehouse"
              value={form.warehouse_id}
              onChange={(e) => setForm({ ...form, warehouse_id: e.target.value })}
              options={[
                { value: '', label: 'Default warehouse' },
                ...warehouses.map((w) => ({ value: String(w.id), label: w.name + (w.is_default ? ' (Default)' : '') })),
              ]}
            />
          )}
          <Input label="Bin Code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} placeholder="e.g. A-01-03" />
          <Input label="Zone" value={form.zone} onChange={(e) => setForm({ ...form, zone: e.target.value })} placeholder="e.g. Zone A" />
          <Input label="Capacity" type="number" value={form.capacity} onChange={(e) => setForm({ ...form, capacity: e.target.value })} placeholder="e.g. 50" />
          <div className="flex gap-3 pt-2">
            <Button onClick={() => createMut.mutate()} loading={createMut.isPending} className="flex-1 justify-center">Create</Button>
            <Button variant="outline" onClick={() => { setShowCreate(false); setForm(EMPTY_BIN_FORM); }} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* Edit Bin Modal */}
      <Modal open={!!editTarget} onClose={() => setEditTarget(null)} title={`Edit — ${editTarget?.code}`}>
        <div className="space-y-4">
          {warehouses.length > 1 && (
            <Select
              label="Warehouse"
              value={form.warehouse_id}
              onChange={(e) => setForm({ ...form, warehouse_id: e.target.value })}
              options={[
                { value: '', label: 'Default warehouse' },
                ...warehouses.map((w) => ({ value: String(w.id), label: w.name + (w.is_default ? ' (Default)' : '') })),
              ]}
            />
          )}
          <Input label="Bin Code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} placeholder="e.g. A-01-03" />
          <Input label="Zone" value={form.zone} onChange={(e) => setForm({ ...form, zone: e.target.value })} placeholder="e.g. Zone A" />
          <Input
            label="Capacity"
            type="number"
            value={form.capacity}
            onChange={(e) => setForm({ ...form, capacity: e.target.value })}
            placeholder="e.g. 50"
          />
          {editTarget && (editTarget.current_count ?? 0) > 0 && (
            <p className="text-xs text-amber-600">
              This bin has {editTarget.current_count} product(s) in it — capacity can't be set below that.
            </p>
          )}
          <div className="flex gap-3 pt-2">
            <Button onClick={() => updateMut.mutate()} loading={updateMut.isPending} className="flex-1 justify-center">Save</Button>
            <Button variant="outline" onClick={() => setEditTarget(null)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* Delete Bin Modal */}
      <Modal open={!!deleteTarget} onClose={() => setDeleteTarget(null)} title="Delete Bin">
        <div className="space-y-4">
          <p className="text-sm text-gray-600">
            Are you sure you want to delete <span className="font-semibold">{deleteTarget?.code}</span>?
            {(deleteTarget?.current_count ?? 0) > 0 && (
              <span className="block text-amber-600 mt-1">
                This bin still has {deleteTarget?.current_count} product(s) in it — move them out first, deletion will be blocked otherwise.
              </span>
            )}
          </p>
          <div className="flex gap-3">
            <Button variant="danger" onClick={() => deleteMut.mutate(deleteTarget!.id)} loading={deleteMut.isPending} className="flex-1 justify-center">Delete</Button>
            <Button variant="outline" onClick={() => setDeleteTarget(null)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
