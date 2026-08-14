import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import toast from 'react-hot-toast';
import { supportService } from '../../services';
import { Card, Table, Pagination, Select, Button, PageHeader, Spinner, Modal, Input, Badge, fmtDateTime } from '../../components/ui';

interface SupportTicketReply {
  id: number;
  message: string;
  created_at: string;
  author?: { id: number; name: string } | null;
}

interface SupportTicket {
  id: number;
  subject: string;
  description: string;
  status: 'open' | 'in_progress' | 'resolved' | 'closed';
  priority: 'low' | 'medium' | 'high';
  order_id?: number | null;
  order?: { id: number; order_number: string } | null;
  creator?: { id: number; name: string } | null;
  assignee?: { id: number; name: string } | null;
  resolved_at?: string | null;
  created_at: string;
  replies?: SupportTicketReply[];
}

const STATUS_COLOR: Record<string, string> = {
  open: 'yellow', in_progress: 'blue', resolved: 'green', closed: 'gray',
};
const PRIORITY_COLOR: Record<string, string> = {
  low: 'gray', medium: 'orange', high: 'red',
};

export default function SupportPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [selected, setSelected] = useState<SupportTicket | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState({ subject: '', description: '', priority: 'medium', order_id: '' });
  const [updateForm, setUpdateForm] = useState({ status: '', priority: '', assigned_to: '' });
  const [replyText, setReplyText] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['support-tickets', page, statusFilter, priorityFilter],
    queryFn: () => supportService.list({
      page: String(page),
      ...(statusFilter && { status: statusFilter }),
      ...(priorityFilter && { priority: priorityFilter }),
    }),
  });

  const createMut = useMutation({
    mutationFn: () => supportService.create({
      subject: createForm.subject,
      description: createForm.description,
      priority: createForm.priority,
      ...(createForm.order_id && { order_id: Number(createForm.order_id) }),
    }),
    onSuccess: () => {
      toast.success('Ticket created');
      qc.invalidateQueries({ queryKey: ['support-tickets'] });
      setCreateOpen(false);
      setCreateForm({ subject: '', description: '', priority: 'medium', order_id: '' });
    },
    onError: () => toast.error('Failed to create ticket'),
  });

  const updateMut = useMutation({
    mutationFn: (data: Record<string, unknown>) => supportService.update(selected!.id, data),
    onSuccess: () => {
      toast.success('Ticket updated');
      qc.invalidateQueries({ queryKey: ['support-tickets'] });
      qc.invalidateQueries({ queryKey: ['support-ticket', selected?.id] });
    },
    onError: () => toast.error('Failed to update'),
  });

  const { data: ticketDetail } = useQuery({
    queryKey: ['support-ticket', selected?.id],
    queryFn: () => supportService.show(selected!.id),
    enabled: !!selected,
  });

  const { data: staffData } = useQuery({
    queryKey: ['support-staff'],
    queryFn: supportService.staff,
    staleTime: 300_000,
  });
  const staff: { id: number; name: string }[] = Array.isArray(staffData) ? staffData : [];

  const replyMut = useMutation({
    mutationFn: () => supportService.reply(selected!.id, replyText.trim()),
    onSuccess: () => {
      setReplyText('');
      qc.invalidateQueries({ queryKey: ['support-ticket', selected?.id] });
      qc.invalidateQueries({ queryKey: ['support-tickets'] });
    },
    onError: () => toast.error('Failed to send reply'),
  });

  const tickets: SupportTicket[] = data?.data ?? [];
  const meta = data?.meta;
  const detail: SupportTicket | undefined = ticketDetail ?? (selected ?? undefined);

  useEffect(() => {
    if (ticketDetail) {
      setUpdateForm({
        status: ticketDetail.status,
        priority: ticketDetail.priority,
        assigned_to: ticketDetail.assignee?.id ? String(ticketDetail.assignee.id) : '',
      });
    }
  }, [ticketDetail]);

  const openTicket = (t: SupportTicket) => {
    setSelected(t);
    setReplyText('');
    setUpdateForm({ status: t.status, priority: t.priority, assigned_to: t.assignee?.id ? String(t.assignee.id) : '' });
  };

  return (
    <div>
      <PageHeader
        title="Support Tickets"
        subtitle={`${meta?.total ?? 0} total tickets`}
        action={
          <Button onClick={() => setCreateOpen(true)}>
            <Plus size={15} /> New Ticket
          </Button>
        }
      />

      <div className="flex gap-3 mb-5">
        <Select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
          options={[
            { value: '', label: 'All Statuses' },
            { value: 'open', label: 'Open' },
            { value: 'in_progress', label: 'In Progress' },
            { value: 'resolved', label: 'Resolved' },
            { value: 'closed', label: 'Closed' },
          ]}
        />
        <Select
          value={priorityFilter}
          onChange={(e) => { setPriorityFilter(e.target.value); setPage(1); }}
          options={[
            { value: '', label: 'All Priorities' },
            { value: 'high', label: 'High' },
            { value: 'medium', label: 'Medium' },
            { value: 'low', label: 'Low' },
          ]}
        />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'id', header: '#', render: (t) => <span className="text-xs text-gray-400">#{t.id}</span> },
                { key: 'subject', header: 'Subject', render: (t) => <span className="font-medium text-gray-800">{t.subject}</span> },
                { key: 'creator', header: 'Created By', render: (t) => t.creator?.name ?? '—' },
                { key: 'order', header: 'Order', render: (t) => t.order?.order_number ?? '—' },
                {
                  key: 'priority', header: 'Priority',
                  render: (t) => <Badge label={t.priority} color={PRIORITY_COLOR[t.priority] ?? 'gray'} />,
                },
                {
                  key: 'status', header: 'Status',
                  render: (t) => <Badge label={t.status.replace('_', ' ')} color={STATUS_COLOR[t.status] ?? 'gray'} />,
                },
                { key: 'created_at', header: 'Created', render: (t) => <span className="text-xs text-gray-400">{fmtDateTime(t.created_at)}</span> },
              ]}
              data={tickets}
              keyField="id"
              onRowClick={openTicket}
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Create Ticket Modal */}
      <Modal open={createOpen} onClose={() => setCreateOpen(false)} title="New Support Ticket">
        <div className="space-y-3">
          <Input
            label="Subject *"
            value={createForm.subject}
            onChange={(e) => setCreateForm({ ...createForm, subject: e.target.value })}
            placeholder="Brief description of the issue"
          />
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Description *</label>
            <textarea
              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 resize-none"
              rows={4}
              value={createForm.description}
              onChange={(e) => setCreateForm({ ...createForm, description: e.target.value })}
              placeholder="Detailed description..."
            />
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Select
              label="Priority"
              value={createForm.priority}
              onChange={(e) => setCreateForm({ ...createForm, priority: e.target.value })}
              options={[
                { value: 'low', label: 'Low' },
                { value: 'medium', label: 'Medium' },
                { value: 'high', label: 'High' },
              ]}
            />
            <Input
              label="Order ID (optional)"
              type="number"
              value={createForm.order_id}
              onChange={(e) => setCreateForm({ ...createForm, order_id: e.target.value })}
              placeholder="e.g. 42"
            />
          </div>
          <div className="flex gap-3 pt-2">
            <Button
              onClick={() => createMut.mutate()}
              loading={createMut.isPending}
              disabled={!createForm.subject || !createForm.description}
              className="flex-1 justify-center"
            >
              Create Ticket
            </Button>
            <Button variant="outline" onClick={() => setCreateOpen(false)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* Ticket Detail Modal */}
      <Modal open={!!selected} onClose={() => setSelected(null)} title={selected ? `Ticket #${selected.id}` : ''}>
        {detail && (
          <div className="space-y-4">
            <div>
              <h3 className="font-semibold text-gray-800">{detail.subject}</h3>
              <p className="text-sm text-gray-600 mt-2 whitespace-pre-line">{detail.description}</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div><span className="text-xs text-gray-500 block">Created by</span>{detail.creator?.name ?? '—'}</div>
              <div><span className="text-xs text-gray-500 block">Order</span>{detail.order?.order_number ?? '—'}</div>
              <div><span className="text-xs text-gray-500 block">Created</span>{fmtDateTime(detail.created_at)}</div>
              {detail.resolved_at && (
                <div><span className="text-xs text-gray-500 block">Resolved</span>{fmtDateTime(detail.resolved_at)}</div>
              )}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t">
              <Select
                label="Update Status"
                value={updateForm.status}
                onChange={(e) => setUpdateForm({ ...updateForm, status: e.target.value })}
                options={[
                  { value: 'open', label: 'Open' },
                  { value: 'in_progress', label: 'In Progress' },
                  { value: 'resolved', label: 'Resolved' },
                  { value: 'closed', label: 'Closed' },
                ]}
              />
              <Select
                label="Update Priority"
                value={updateForm.priority}
                onChange={(e) => setUpdateForm({ ...updateForm, priority: e.target.value })}
                options={[
                  { value: 'low', label: 'Low' },
                  { value: 'medium', label: 'Medium' },
                  { value: 'high', label: 'High' },
                ]}
              />
              <Select
                label="Assign To"
                value={updateForm.assigned_to}
                onChange={(e) => setUpdateForm({ ...updateForm, assigned_to: e.target.value })}
                options={[{ value: '', label: 'Unassigned' }, ...staff.map((s) => ({ value: String(s.id), label: s.name }))]}
              />
            </div>

            <div className="flex gap-3">
              <Button
                onClick={() => updateMut.mutate({
                  status: updateForm.status,
                  priority: updateForm.priority,
                  assigned_to: updateForm.assigned_to ? Number(updateForm.assigned_to) : null,
                })}
                loading={updateMut.isPending}
                className="flex-1 justify-center"
              >
                Save Changes
              </Button>
              <Button variant="outline" onClick={() => setSelected(null)} className="flex-1 justify-center">Close</Button>
            </div>

            {/* Reply thread */}
            <div className="pt-3 border-t">
              <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Conversation</h4>
              <div className="space-y-2 max-h-56 overflow-y-auto pr-1">
                {(detail.replies ?? []).length === 0 && (
                  <p className="text-xs text-gray-400">No replies yet</p>
                )}
                {(detail.replies ?? []).map((r) => (
                  <div key={r.id} className="bg-gray-50 rounded-lg px-3 py-2">
                    <div className="flex items-center justify-between mb-0.5">
                      <span className="text-xs font-semibold text-gray-700">{r.author?.name ?? '—'}</span>
                      <span className="text-[10px] text-gray-400">{fmtDateTime(r.created_at)}</span>
                    </div>
                    <p className="text-sm text-gray-600 whitespace-pre-line">{r.message}</p>
                  </div>
                ))}
              </div>
              <div className="flex gap-2 mt-3">
                <textarea
                  className="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 resize-none"
                  rows={2}
                  placeholder="Write a reply..."
                  value={replyText}
                  onChange={(e) => setReplyText(e.target.value)}
                />
                <Button
                  onClick={() => replyMut.mutate()}
                  loading={replyMut.isPending}
                  disabled={!replyText.trim()}
                >
                  Send
                </Button>
              </div>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
