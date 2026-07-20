import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Eye, UserX, UserCheck } from 'lucide-react';
import toast from 'react-hot-toast';
import { retailCustomerService } from '../../services';
import { Card, Table, Pagination, Badge, Button, PageHeader, Spinner, Modal, Input, fmtINR, fmtDate } from '../../components/ui';

export default function RetailCustomersPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState<any>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['retail-customers', page, search],
    queryFn: () => retailCustomerService.list({ page: String(page), ...(search && { search }) }),
  });

  const { data: detail, isLoading: detailLoading } = useQuery({
    queryKey: ['retail-customer-detail', selected?.id],
    queryFn: () => retailCustomerService.show(selected!.id),
    enabled: !!selected,
  });

  const toggleMut = useMutation({
    mutationFn: (c: any) => retailCustomerService.update(c.id, { is_active: !c.is_active }),
    onSuccess: () => { toast.success('Customer updated'); qc.invalidateQueries({ queryKey: ['retail-customers'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const customers: any[] = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div>
      <PageHeader title="Retail Customers" subtitle="B2C customers who ordered via retail channel" />

      <div className="mb-4 max-w-xs">
        <Input placeholder="Search name or phone..." value={search}
          onChange={e => { setSearch(e.target.value); setPage(1); }} />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'name', header: 'Name', render: c => <span className="font-medium">{c.name}</span> },
                { key: 'phone', header: 'Phone', render: c => <code className="text-xs">{c.phone}</code> },
                { key: 'email', header: 'Email', render: c => c.email ?? '—' },
                { key: 'state', header: 'State', render: c => c.state ?? '—' },
                { key: 'orders_count', header: 'Orders', render: c => <Badge label={String(c.orders_count ?? 0)} color="blue" /> },
                { key: 'status', header: 'Status', render: c => <Badge label={c.is_active ? 'Active' : 'Blocked'} color={c.is_active ? 'green' : 'red'} /> },
                { key: 'created_at', header: 'Joined', render: c => <span className="text-xs text-gray-500">{fmtDate(c.created_at)}</span> },
                {
                  key: 'actions', header: '', render: c => (
                    <Button size="sm" variant="outline" onClick={e => { e.stopPropagation(); setSelected(c); }}>
                      <Eye size={13} /> View
                    </Button>
                  ),
                },
              ]}
              data={customers}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Detail Modal */}
      <Modal open={!!selected} onClose={() => setSelected(null)} title={selected?.name ?? 'Customer'}>
        {detailLoading ? <Spinner /> : detail && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div><span className="text-xs text-gray-500 block">Phone</span>{detail.customer?.phone}</div>
              <div><span className="text-xs text-gray-500 block">Email</span>{detail.customer?.email ?? '—'}</div>
              <div><span className="text-xs text-gray-500 block">City</span>{detail.customer?.city ?? '—'}</div>
              <div><span className="text-xs text-gray-500 block">State</span>{detail.customer?.state ?? '—'}</div>
              <div><span className="text-xs text-gray-500 block">Total Orders</span><span className="font-bold">{detail.orders_count}</span></div>
              <div><span className="text-xs text-gray-500 block">Total Spent</span><span className="font-bold text-green-600">{fmtINR(detail.total_spent ?? 0)}</span></div>
            </div>

            {detail.customer?.address && (
              <div className="text-sm">
                <span className="text-xs text-gray-500 block mb-1">Address</span>
                <p className="bg-gray-50 rounded p-2 text-xs">{detail.customer.address}</p>
              </div>
            )}

            {/* Recent Orders */}
            {detail.orders?.length > 0 && (
              <div>
                <div className="text-xs font-medium text-gray-500 mb-2">Recent Orders</div>
                <div className="space-y-1 max-h-48 overflow-y-auto">
                  {detail.orders.slice(0, 10).map((o: any) => (
                    <div key={o.id} className="flex justify-between text-xs bg-gray-50 px-3 py-2 rounded">
                      <span className="font-mono font-bold">{o.order_number}</span>
                      <Badge label={o.status} color={o.status === 'delivered' ? 'green' : o.status === 'cancelled' ? 'red' : 'blue'} />
                      <span className="font-semibold">{fmtINR(o.total_amount)}</span>
                      <span className="text-gray-400">{fmtDate(o.created_at)}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="flex gap-3 pt-2 border-t">
              <Button
                size="sm"
                variant={selected?.is_active ? 'danger' : 'outline'}
                onClick={() => toggleMut.mutate(selected)}
                loading={toggleMut.isPending}
              >
                {selected?.is_active ? <><UserX size={13} /> Block Customer</> : <><UserCheck size={13} /> Unblock</>}
              </Button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
