import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import toast from 'react-hot-toast';
import { leadsService } from '../../services';
import { Card, Table, Pagination, Select, Button, PageHeader, Spinner, Modal, Input, leadStageBadge, fmtDate } from '../../components/ui';
import type { Lead, LeadStage } from '../../types';

const STAGES: LeadStage[] = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];

export default function LeadsPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [stageFilter, setStageFilter] = useState('');
  const [selected, setSelected] = useState<Lead | null>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [stageForm, setStageForm] = useState({ stage: '' as LeadStage | '' });
  const [form, setForm] = useState({ business_name: '', contact_name: '', phone: '', email: '', city: '', source: '' });

  const { data, isLoading } = useQuery({
    queryKey: ['leads', page, stageFilter],
    queryFn: () => leadsService.list({ page: String(page), ...(stageFilter && { stage: stageFilter }) }),
  });

  const createMut = useMutation({
    mutationFn: () => leadsService.create(form),
    onSuccess: () => { toast.success('Lead created'); qc.invalidateQueries({ queryKey: ['leads'] }); setShowCreate(false); setForm({ business_name: '', contact_name: '', phone: '', email: '', city: '', source: '' }); },
    onError: () => toast.error('Failed to create lead'),
  });

  const stageMut = useMutation({
    mutationFn: () => leadsService.updateStage(selected!.id, { stage: stageForm.stage as LeadStage }),
    onSuccess: () => { toast.success('Stage updated'); qc.invalidateQueries({ queryKey: ['leads'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const convertMut = useMutation({
    mutationFn: (id: number) => leadsService.convert(id),
    onSuccess: () => { toast.success('Lead converted to dealer'); qc.invalidateQueries({ queryKey: ['leads'] }); setSelected(null); },
    onError: () => toast.error('Failed to convert lead'),
  });

  const leads: Lead[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };

  return (
    <div>
      <PageHeader
        title="Leads / CRM"
        subtitle="Potential dealer pipeline"
        action={<Button onClick={() => setShowCreate(true)}><Plus size={15} /> Add Lead</Button>}
      />

      {/* Pipeline view */}
      <div className="flex gap-2 overflow-x-auto pb-2 mb-5">
        {STAGES.map((s) => (
          <button
            key={s}
            onClick={() => setStageFilter(stageFilter === s ? '' : s)}
            className={`flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium border transition-colors ${stageFilter === s ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:border-primary'}`}
          >
            {s.charAt(0).toUpperCase() + s.slice(1)}
          </button>
        ))}
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'business_name', header: 'Business', render: (l) => <span className="font-medium">{l.business_name}</span> },
                { key: 'contact_name', header: 'Contact', render: (l) => l.contact_name ?? '—' },
                { key: 'phone', header: 'Phone' },
                { key: 'city', header: 'City', render: (l) => l.city ?? '—' },
                { key: 'source', header: 'Source', render: (l) => l.source ?? '—' },
                { key: 'stage', header: 'Stage', render: (l) => leadStageBadge(l.stage) },
                { key: 'created_at', header: 'Added', render: (l) => <span className="text-xs text-gray-400">{fmtDate(l.created_at)}</span> },
              ]}
              data={leads}
              keyField="id"
              onRowClick={(l) => { setSelected(l); setStageForm({ stage: l.stage }); }}
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Lead Detail / Edit Modal */}
      <Modal open={!!selected} onClose={() => setSelected(null)} title={selected?.business_name ?? 'Lead'}>
        {selected && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div><span className="text-gray-500 block text-xs">Contact</span>{selected.contact_name ?? '—'}</div>
              <div><span className="text-gray-500 block text-xs">Phone</span>{selected.phone}</div>
              <div><span className="text-gray-500 block text-xs">Email</span>{selected.email ?? '—'}</div>
              <div><span className="text-gray-500 block text-xs">City</span>{selected.city ?? '—'}</div>
              <div><span className="text-gray-500 block text-xs">Source</span>{selected.source ?? '—'}</div>
            </div>
            <div className="border-t pt-4">
              <div className="text-xs font-medium text-gray-500 mb-2">Update Stage</div>
              <div className="flex gap-2">
                <Select
                  value={stageForm.stage}
                  onChange={(e) => setStageForm({ stage: e.target.value as LeadStage })}
                  options={STAGES.map((s) => ({ value: s, label: s.charAt(0).toUpperCase() + s.slice(1) }))}
                />
                <Button size="sm" onClick={() => stageMut.mutate()} loading={stageMut.isPending}>Save</Button>
              </div>
            </div>
            {selected.stage === 'won' && (
              <Button onClick={() => convertMut.mutate(selected.id)} loading={convertMut.isPending} className="w-full justify-center">
                Convert to Dealer
              </Button>
            )}
          </div>
        )}
      </Modal>

      {/* Create Lead Modal */}
      <Modal open={showCreate} onClose={() => setShowCreate(false)} title="New Lead">
        <div className="space-y-4">
          <Input label="Business Name" value={form.business_name} onChange={(e) => setForm({ ...form, business_name: e.target.value })} />
          <Input label="Contact Name" value={form.contact_name} onChange={(e) => setForm({ ...form, contact_name: e.target.value })} />
          <Input label="Phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          <Input label="Email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          <Input label="City" value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} />
          <Input label="Source" placeholder="e.g. WhatsApp, Referral, LinkedIn" value={form.source} onChange={(e) => setForm({ ...form, source: e.target.value })} />
          <div className="flex gap-3 pt-2">
            <Button onClick={() => createMut.mutate()} loading={createMut.isPending} className="flex-1 justify-center">Create</Button>
            <Button variant="outline" onClick={() => setShowCreate(false)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
