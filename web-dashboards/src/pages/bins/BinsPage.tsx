import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import toast from 'react-hot-toast';
import { binsService, inventoryService } from '../../services';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Modal, Input, Select } from '../../components/ui';
import type { Bin, Product } from '../../types';

export default function BinsPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [showCreate, setShowCreate] = useState(false);
  const [moveTarget, setMoveTarget] = useState<Product | null>(null);
  const [moveForm, setMoveForm] = useState({ bin_id: '' });
  const [form, setForm] = useState({ code: '', zone: '', capacity: '' });

  const { data, isLoading } = useQuery({
    queryKey: ['bins', page],
    queryFn: () => binsService.list({ page: String(page) }),
  });

  const { data: binsAll } = useQuery({ queryKey: ['bins-all'], queryFn: () => binsService.list({ per_page: '200' }) });

  const createMut = useMutation({
    mutationFn: () => binsService.create({ code: form.code, zone: form.zone, capacity: Number(form.capacity) }),
    onSuccess: () => { toast.success('Bin created'); qc.invalidateQueries({ queryKey: ['bins'] }); setShowCreate(false); setForm({ code: '', zone: '', capacity: '' }); },
    onError: () => toast.error('Failed to create bin'),
  });

  const moveMut = useMutation({
    mutationFn: () => inventoryService.moveBin(moveTarget!.id, Number(moveForm.bin_id)),
    onSuccess: () => { toast.success('Product moved'); qc.invalidateQueries({ queryKey: ['bins'] }); setMoveTarget(null); },
    onError: () => toast.error('Failed to move product'),
  });

  const bins: Bin[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };
  const allBins: Bin[] = Array.isArray(binsAll?.data) ? binsAll.data : [];

  return (
    <div>
      <PageHeader
        title="Bin Management"
        subtitle="Warehouse storage locations"
        action={<Button onClick={() => setShowCreate(true)}><Plus size={15} /> New Bin</Button>}
      />

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'code', header: 'Bin Code', render: (b) => <span className="font-mono font-semibold">{b.code}</span> },
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
                { key: 'is_active', header: 'Status', render: (b) => <Badge label={b.is_active ? 'Active' : 'Inactive'} color={b.is_active ? 'green' : 'gray'} /> },
              ]}
              data={bins}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Create Bin Modal */}
      <Modal open={showCreate} onClose={() => setShowCreate(false)} title="Create Bin">
        <div className="space-y-4">
          <Input label="Bin Code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} placeholder="e.g. A-01-03" />
          <Input label="Zone" value={form.zone} onChange={(e) => setForm({ ...form, zone: e.target.value })} placeholder="e.g. Zone A" />
          <Input label="Capacity" type="number" value={form.capacity} onChange={(e) => setForm({ ...form, capacity: e.target.value })} placeholder="e.g. 50" />
          <div className="flex gap-3 pt-2">
            <Button onClick={() => createMut.mutate()} loading={createMut.isPending} className="flex-1 justify-center">Create</Button>
            <Button variant="outline" onClick={() => setShowCreate(false)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* Move Product Modal */}
      <Modal open={!!moveTarget} onClose={() => setMoveTarget(null)} title={`Move ${moveTarget?.brand} ${moveTarget?.model}`}>
        <div className="space-y-4">
          <Select
            label="Move to Bin"
            value={moveForm.bin_id}
            onChange={(e) => setMoveForm({ bin_id: e.target.value })}
            options={[{ value: '', label: 'Select bin...' }, ...allBins.map((b) => ({ value: String(b.id), label: `${b.code}${b.zone ? ' — ' + b.zone : ''}` }))]}
          />
          <div className="flex gap-3 pt-2">
            <Button onClick={() => moveMut.mutate()} loading={moveMut.isPending} className="flex-1 justify-center">Move</Button>
            <Button variant="outline" onClick={() => setMoveTarget(null)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
