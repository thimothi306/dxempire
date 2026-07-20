import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2 } from 'lucide-react';
import toast from 'react-hot-toast';
import { financeService } from '../../services';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Modal, Input, Select, fmtINR, fmtDate } from '../../components/ui';
import type { Expense } from '../../types';

const CATEGORIES = ['rent', 'salary', 'logistics', 'utilities', 'marketing', 'repairs', 'procurement', 'other'];
const EMPTY_FORM = { category: 'other', description: '', amount: '', date: '', vendor: '' };

export default function ExpensesPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [showCreate, setShowCreate] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Expense | null>(null);
  const [form, setForm] = useState(EMPTY_FORM);

  const { data, isLoading } = useQuery({
    queryKey: ['expenses', page],
    queryFn: () => financeService.expenses({ page: String(page) }),
  });

  const createMut = useMutation({
    mutationFn: () => financeService.createExpense({ ...form, amount: Number(form.amount), incurred_at: form.date }),
    onSuccess: () => {
      toast.success('Expense recorded');
      qc.invalidateQueries({ queryKey: ['expenses'] });
      setShowCreate(false);
      setForm(EMPTY_FORM);
    },
    onError: () => toast.error('Failed to record expense'),
  });

  const deleteMut = useMutation({
    mutationFn: (id: number) => financeService.deleteExpense(id),
    onSuccess: () => {
      toast.success('Expense deleted');
      qc.invalidateQueries({ queryKey: ['expenses'] });
      setDeleteTarget(null);
    },
    onError: () => toast.error('Failed to delete expense'),
  });

  const expenses: Expense[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };
  const totalShown = expenses.reduce((s, e) => s + e.amount, 0);

  return (
    <div>
      <PageHeader
        title="Expenses"
        subtitle={`${meta?.total ?? 0} expense records`}
        action={<Button onClick={() => setShowCreate(true)}><Plus size={15} /> Add Expense</Button>}
      />

      {expenses.length > 0 && (
        <div className="mb-5 bg-orange-50 border border-orange-100 rounded-xl px-5 py-3 flex justify-between items-center">
          <span className="text-sm text-gray-600">Shown total</span>
          <span className="text-lg font-bold text-orange-700">{fmtINR(totalShown)}</span>
        </div>
      )}

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'date', header: 'Date', render: (e) => fmtDate(e.date ?? e.incurred_at ?? '') },
                { key: 'category', header: 'Category', render: (e) => <Badge label={e.category} color="blue" /> },
                { key: 'description', header: 'Description', render: (e) => <span className="text-sm text-gray-700">{e.description}</span> },
                { key: 'vendor', header: 'Vendor', render: (e) => e.vendor ?? '—' },
                { key: 'amount', header: 'Amount', render: (e) => <span className="font-semibold">{fmtINR(e.amount)}</span> },
                { key: 'recorded_by', header: 'By', render: (e) => <span className="text-xs text-gray-500">{e.recorded_by?.name ?? '—'}</span> },
                {
                  key: 'actions', header: '', render: (e) => (
                    <Button size="sm" variant="danger" onClick={(ev) => { ev.stopPropagation(); setDeleteTarget(e); }}>
                      <Trash2 size={13} />
                    </Button>
                  ),
                },
              ]}
              data={expenses}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Add Expense Modal */}
      <Modal open={showCreate} onClose={() => { setShowCreate(false); setForm(EMPTY_FORM); }} title="Record Expense">
        <div className="space-y-4">
          <Select
            label="Category"
            value={form.category}
            onChange={(e) => setForm({ ...form, category: e.target.value })}
            options={CATEGORIES.map((c) => ({ value: c, label: c.charAt(0).toUpperCase() + c.slice(1) }))}
          />
          <Input label="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          <Input label="Amount (₹)" type="number" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
          <Input label="Date" type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} />
          <Input label="Vendor" value={form.vendor} onChange={(e) => setForm({ ...form, vendor: e.target.value })} placeholder="Optional" />
          <div className="flex gap-3 pt-2">
            <Button onClick={() => createMut.mutate()} loading={createMut.isPending} className="flex-1 justify-center">Save</Button>
            <Button variant="outline" onClick={() => { setShowCreate(false); setForm(EMPTY_FORM); }} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* Delete Confirm Modal */}
      <Modal open={!!deleteTarget} onClose={() => setDeleteTarget(null)} title="Delete Expense">
        <div className="space-y-4">
          <p className="text-sm text-gray-600">
            Delete <span className="font-semibold">{deleteTarget?.description}</span> of <span className="font-semibold">{fmtINR(deleteTarget?.amount ?? 0)}</span>?
            This cannot be undone.
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
