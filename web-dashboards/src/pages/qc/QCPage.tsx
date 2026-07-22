import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { qcService } from '../../services';
import { Card, Table, Pagination, Select, Badge, Button, PageHeader, Spinner, Modal } from '../../components/ui';
import type { Product } from '../../types';

const GRADES = ['S1', 'S2', 'S3', 'S4', 'S5'];

const ISSUE_OPTIONS = [
  'Screen crack', 'Battery issue', 'Back cover damage', 'Button malfunction',
  'Speaker issue', 'Camera issue', 'Charging port issue', 'Software issue',
];

const EMPTY_GRADE_FORM = { outcome: 'pass', grade: 'S1', issues: '' };

export default function QCPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<Product | null>(null);
  const [gradeForm, setGradeForm] = useState(EMPTY_GRADE_FORM);

  const { data: queueData, isLoading } = useQuery({
    queryKey: ['qc-queue', page],
    queryFn: () => qcService.queue({ page: String(page) }),
  });

  const { data: stats } = useQuery({ queryKey: ['qc-stats'], queryFn: qcService.stats });

  const gradeMut = useMutation({
    mutationFn: () => qcService.grade(selected!.id, {
      outcome: gradeForm.outcome as 'pass' | 'reject',
      grade: gradeForm.outcome === 'pass' ? gradeForm.grade : undefined,
      condition_notes: gradeForm.issues || undefined,
    }),
    onSuccess: () => {
      toast.success(gradeForm.outcome === 'pass' ? 'Product passed QC — now in stock' : 'Product rejected');
      qc.invalidateQueries({ queryKey: ['qc-queue'] });
      qc.invalidateQueries({ queryKey: ['qc-stats'] });
      qc.invalidateQueries({ queryKey: ['inventory'] });
      setSelected(null);
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to grade product'),
  });

  const refurbishMut = useMutation({
    mutationFn: (product: Product) => qcService.sendToRefurbishment(product.id),
    onSuccess: () => { toast.success('Sent to refurbishment'); qc.invalidateQueries({ queryKey: ['qc-queue'] }); },
    onError: () => toast.error('Failed'),
  });

  const products: Product[] = queueData?.data ?? [];
  const meta = queueData?.meta;

  return (
    <div>
      <PageHeader title="Quality Control" subtitle="Grade incoming products" />

      {/* Stats row */}
      {stats && (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          {[
            { label: 'Pending QC', value: stats.pending_qc ?? 0, color: 'text-yellow-600' },
            { label: 'Graded Today', value: stats.graded_today ?? 0, color: 'text-green-600' },
            { label: 'Refurbishment', value: stats.in_refurbishment ?? 0, color: 'text-purple-600' },
            { label: 'Rejected Today', value: stats.rejected_today ?? 0, color: 'text-red-600' },
          ].map((s) => (
            <Card key={s.label} className="p-4">
              <div className={`text-2xl font-bold ${s.color}`}>{s.value}</div>
              <div className="text-xs text-gray-500 mt-1">{s.label}</div>
            </Card>
          ))}
        </div>
      )}

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'imei', header: 'IMEI', render: (p) => <code className="text-xs">{p.imei}</code> },
                { key: 'brand', header: 'Brand' },
                { key: 'model', header: 'Model' },
                { key: 'category', header: 'Category', render: (p) => <Badge label={p.category} color="blue" /> },
                { key: 'status', header: 'Status', render: (p) => (
                  <div className="flex items-center gap-1">
                    <Badge label={p.status === 'qc_pending' ? 'Re-QC' : 'New Stock'} color={p.status === 'qc_pending' ? 'orange' : 'blue'} />
                    {(p.return_count ?? 0) > 0 && <Badge label={`Return #${p.return_count}`} color="red" />}
                  </div>
                )},
                {
                  key: 'actions', header: '', render: (p) => (
                    <div className="flex gap-2">
                      <Button size="sm" onClick={(e) => { e.stopPropagation(); setSelected(p); setGradeForm(EMPTY_GRADE_FORM); }}>
                        Grade
                      </Button>
                      <Button size="sm" variant="secondary" onClick={(e) => { e.stopPropagation(); refurbishMut.mutate(p); }}>
                        Refurbish
                      </Button>
                    </div>
                  ),
                },
              ]}
              data={products}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      <Modal open={!!selected} onClose={() => setSelected(null)} title={`Grade — ${selected?.brand} ${selected?.model}`}>
        <div className="space-y-4">
          <div className="text-xs text-gray-500 font-mono bg-gray-50 px-3 py-2 rounded">IMEI: {selected?.imei}</div>

          <Select
            label="Outcome *"
            value={gradeForm.outcome}
            onChange={(e) => setGradeForm({ ...gradeForm, outcome: e.target.value })}
            options={[
              { value: 'pass', label: 'Pass — grade & move to stock' },
              { value: 'reject', label: 'Reject — unsellable' },
            ]}
          />

          {gradeForm.outcome === 'pass' && (
            <>
              <Select
                label="Grade *"
                value={gradeForm.grade}
                onChange={(e) => setGradeForm({ ...gradeForm, grade: e.target.value })}
                options={GRADES.map((g) => ({ value: g, label: g }))}
              />
              <p className="text-xs text-gray-500">Selling price is calculated automatically from the grade — no need to enter it.</p>
            </>
          )}

          <Select
            label="Issues (select one to add)"
            value=""
            onChange={(e) => {
              if (!e.target.value) return;
              const current = gradeForm.issues ? gradeForm.issues.split(', ') : [];
              if (!current.includes(e.target.value)) {
                setGradeForm({ ...gradeForm, issues: [...current, e.target.value].join(', ') });
              }
            }}
            options={[{ value: '', label: 'Add issue...' }, ...ISSUE_OPTIONS.map((o) => ({ value: o, label: o }))]}
          />
          {gradeForm.issues && (
            <div className="text-xs text-gray-600 bg-orange-50 px-3 py-2 rounded">{gradeForm.issues}</div>
          )}

          <div className="flex gap-3 pt-2">
            <Button onClick={() => gradeMut.mutate()} loading={gradeMut.isPending} className="flex-1 justify-center">
              {gradeForm.outcome === 'pass' ? 'Confirm Grade' : 'Confirm Reject'}
            </Button>
            <Button variant="outline" onClick={() => setSelected(null)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
