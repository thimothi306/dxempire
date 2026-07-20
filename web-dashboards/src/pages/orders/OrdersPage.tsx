import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Search, ExternalLink } from 'lucide-react';
import toast from 'react-hot-toast';
import { ordersService, logisticsService } from '../../services';
import { Card, Table, Pagination, Select, Button, PageHeader, Spinner, Modal, Input, orderStatusBadge, fmtINR, fmtDateTime } from '../../components/ui';
import type { Order, OrderStatus } from '../../types';

const STATUS_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'All Statuses' },
  ...(['pending', 'approved', 'picking', 'packed', 'dispatched', 'delivered', 'cancelled', 'returned'] as OrderStatus[])
    .map((s) => ({ value: s, label: s.replace(/_/g, ' ') })),
];

export default function OrdersPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState<Order | null>(null);
  const [trackingInput, setTrackingInput] = useState('');
  const [activeTab, setActiveTab] = useState<'details' | 'payments'>('details');

  const { data, isLoading } = useQuery({
    queryKey: ['orders', page, status, search],
    queryFn: () => ordersService.list({ page: String(page), ...(status && { status }), ...(search && { search }) }),
  });

  const { data: detail } = useQuery({
    queryKey: ['order-detail', selected?.id],
    queryFn: () => ordersService.get(selected!.id),
    enabled: !!selected,
  });

  const { data: paymentsData } = useQuery({
    queryKey: ['order-payments', selected?.id],
    queryFn: () => ordersService.payments(selected!.id),
    enabled: !!selected && activeTab === 'payments',
  });

  const approveMut = useMutation({
    mutationFn: (id: number) => ordersService.approve(id),
    onSuccess: () => { toast.success('Order approved'); qc.invalidateQueries({ queryKey: ['orders'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const pickingMut = useMutation({
    mutationFn: (id: number) => ordersService.picking(id),
    onSuccess: () => { toast.success('Picking started'); qc.invalidateQueries({ queryKey: ['orders'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const packingMut = useMutation({
    mutationFn: (id: number) => ordersService.packingComplete(id),
    onSuccess: () => { toast.success('Packing completed'); qc.invalidateQueries({ queryKey: ['orders'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const dispatchMut = useMutation({
    mutationFn: ({ id, tracking }: { id: number; tracking: string }) =>
      ordersService.dispatch(id, { awb_number: tracking, tracking_number: tracking, logistics_provider: 'shiprocket' }),
    onSuccess: () => { toast.success('Order dispatched'); qc.invalidateQueries({ queryKey: ['orders'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const deliverMut = useMutation({
    mutationFn: (id: number) => ordersService.deliver(id),
    onSuccess: () => { toast.success('Order marked delivered'); qc.invalidateQueries({ queryKey: ['orders'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const cancelMut = useMutation({
    mutationFn: (id: number) => ordersService.cancel(id, { reason: 'Cancelled by admin' }),
    onSuccess: () => { toast.success('Order cancelled'); qc.invalidateQueries({ queryKey: ['orders'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const returnMut = useMutation({
    mutationFn: (id: number) => ordersService.return(id),
    onSuccess: () => { toast.success('Return initiated'); qc.invalidateQueries({ queryKey: ['orders'] }); setSelected(null); },
    onError: () => toast.error('Failed to initiate return'),
  });

  const [trackResult, setTrackResult] = useState<string | null>(null);
  const trackMut = useMutation({
    mutationFn: (awb: string) => logisticsService.track(awb),
    onSuccess: (data) => {
      const status = data?.current_status ?? data?.status ?? JSON.stringify(data);
      setTrackResult(typeof status === 'string' ? status : JSON.stringify(status));
    },
    onError: () => toast.error('Tracking info unavailable'),
  });

  const orders: Order[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };
  const orderDetail = detail?.data ?? selected;
  const payments: any[] = Array.isArray(paymentsData) ? paymentsData : paymentsData?.data ?? [];

  const openOrder = (o: Order) => { setSelected(o); setActiveTab('details'); setTrackingInput(''); setTrackResult(null); };

  return (
    <div>
      <PageHeader title="Orders" subtitle={`${meta?.total ?? 0} total orders`} />

      <div className="flex gap-3 mb-5">
        <div className="relative flex-1 max-w-sm">
          <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            className="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30"
            placeholder="Search order #, dealer..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          />
        </div>
        <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} options={STATUS_OPTIONS} />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'order_number', header: 'Order #', render: (o) => <span className="font-mono text-xs font-semibold">{o.order_number}</span> },
                { key: 'dealer', header: 'Dealer', render: (o) => o.dealer?.business_name ?? '—' },
                { key: 'status', header: 'Status', render: (o) => orderStatusBadge(o.status) },
                { key: 'total_amount', header: 'Amount', render: (o) => fmtINR(o.total_amount) },
                { key: 'items_count', header: 'Items', render: (o) => o.items?.length ?? o.items_count ?? 0 },
                { key: 'created_at', header: 'Date', render: (o) => <span className="text-xs text-gray-500">{fmtDateTime(o.created_at)}</span> },
              ]}
              data={orders}
              keyField="id"
              onRowClick={openOrder}
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Order Detail Modal */}
      <Modal open={!!selected} onClose={() => setSelected(null)} title={`Order ${selected?.order_number}`}>
        {selected && (
          <div className="space-y-4">
            {/* Tabs */}
            <div className="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
              {(['details', 'payments'] as const).map((t) => (
                <button
                  key={t}
                  onClick={() => setActiveTab(t)}
                  className={`px-4 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === t ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                >
                  {t.charAt(0).toUpperCase() + t.slice(1)}
                </button>
              ))}
            </div>

            {activeTab === 'details' && (
              <>
                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div><span className="text-xs text-gray-500 block">Status</span>{orderStatusBadge(orderDetail?.status ?? selected.status)}</div>
                  <div><span className="text-xs text-gray-500 block">Dealer</span><span className="font-medium">{orderDetail?.dealer?.business_name ?? selected.dealer?.business_name ?? '—'}</span></div>
                  <div><span className="text-xs text-gray-500 block">Subtotal</span>{fmtINR(orderDetail?.subtotal ?? selected.subtotal ?? 0)}</div>
                  <div><span className="text-xs text-gray-500 block">GST</span>{fmtINR(orderDetail?.gst_amount ?? selected.gst_amount ?? 0)}</div>
                  <div><span className="text-xs text-gray-500 block">Total</span><span className="font-bold">{fmtINR(orderDetail?.total_amount ?? selected.total_amount)}</span></div>
                  <div><span className="text-xs text-gray-500 block">Payment</span><span className="capitalize">{orderDetail?.payment_status ?? selected.payment_status ?? '—'}</span></div>
                  {((orderDetail as any)?.awb_number ?? (selected as any).awb_number) && (
                    <div className="col-span-2"><span className="text-xs text-gray-500 block">AWB / Tracking</span><span className="font-mono text-xs">{(orderDetail as any)?.awb_number ?? (selected as any).awb_number}</span></div>
                  )}
                </div>

                {/* Items */}
                {(orderDetail?.items ?? selected.items)?.length > 0 && (
                  <div>
                    <div className="text-xs font-medium text-gray-500 mb-2">Items</div>
                    <div className="space-y-1">
                      {(orderDetail?.items ?? selected.items).map((item: any) => (
                        <div key={item.id} className="flex justify-between text-xs bg-gray-50 px-3 py-2 rounded">
                          <span>{item.product?.brand} {item.product?.model} <span className="text-gray-400">({item.product?.imei})</span></span>
                          <span className="font-medium">{fmtINR(item.unit_price ?? item.line_total)}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {trackResult && (
                  <div className="text-xs bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 text-blue-800">
                    <span className="font-semibold">Tracking: </span>{trackResult}
                  </div>
                )}

                {/* Action buttons based on status */}
                <div className="flex flex-wrap gap-2 pt-2 border-t">
                  {selected.status === 'pending' && (
                    <Button size="sm" onClick={() => approveMut.mutate(selected.id)} loading={approveMut.isPending}>Approve</Button>
                  )}
                  {selected.status === 'approved' && (
                    <Button size="sm" onClick={() => pickingMut.mutate(selected.id)} loading={pickingMut.isPending}>Start Picking</Button>
                  )}
                  {selected.status === 'picking' && (
                    <Button size="sm" onClick={() => packingMut.mutate(selected.id)} loading={packingMut.isPending}>Complete Packing</Button>
                  )}
                  {selected.status === 'packed' && (
                    <div className="flex gap-2 w-full flex-wrap">
                      <Input placeholder="AWB / Tracking #" value={trackingInput} onChange={(e) => setTrackingInput(e.target.value)} />
                      <Button size="sm" onClick={() => dispatchMut.mutate({ id: selected.id, tracking: trackingInput })} loading={dispatchMut.isPending}>
                        Dispatch
                      </Button>
                    </div>
                  )}
                  {selected.status === 'dispatched' && (
                    <Button size="sm" onClick={() => deliverMut.mutate(selected.id)} loading={deliverMut.isPending}>Mark Delivered</Button>
                  )}
                  {selected.status === 'delivered' && (
                    <Button size="sm" variant="secondary" onClick={() => returnMut.mutate(selected.id)} loading={returnMut.isPending}>
                      Return
                    </Button>
                  )}
                  {!['delivered', 'cancelled', 'returned'].includes(selected.status) && (
                    <Button size="sm" variant="danger" onClick={() => cancelMut.mutate(selected.id)} loading={cancelMut.isPending}>Cancel</Button>
                  )}
                  {(orderDetail?.tracking_number ?? selected.tracking_number) && (
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => trackMut.mutate(orderDetail?.tracking_number ?? selected.tracking_number!)}
                      loading={trackMut.isPending}
                    >
                      <ExternalLink size={13} /> Track
                    </Button>
                  )}
                </div>
              </>
            )}

            {activeTab === 'payments' && (
              <div>
                {payments.length === 0
                  ? <div className="py-8 text-center text-sm text-gray-400">No payments recorded yet</div>
                  : (
                    <div className="space-y-2">
                      {payments.map((p: any) => (
                        <div key={p.id} className="flex justify-between text-sm bg-gray-50 px-3 py-2 rounded-lg">
                          <div>
                            <span className="font-medium capitalize">{p.method ?? 'razorpay'}</span>
                            {p.razorpay_payment_id && <span className="text-xs text-gray-400 ml-2 font-mono">{p.razorpay_payment_id}</span>}
                          </div>
                          <div className="text-right">
                            <div className="font-semibold text-green-700">{fmtINR(p.amount)}</div>
                            <div className="text-xs text-gray-400 capitalize">{p.status}</div>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}
