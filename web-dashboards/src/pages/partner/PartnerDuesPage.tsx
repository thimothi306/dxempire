import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { partnerService } from '../../services';
import { Card, Table, Button, Badge, PageHeader, Spinner, fmtINR, fmtDate } from '../../components/ui';
import type { Order } from '../../types';

declare global {
  interface Window {
    Cashfree?: (opts: { mode: 'sandbox' | 'production' }) => {
      checkout: (opts: { paymentSessionId: string; redirectTarget?: string }) => void;
    };
  }
}

// Must match the backend's CASHFREE_MODE — flip both together when going live.
const CASHFREE_MODE: 'sandbox' | 'production' = 'sandbox';

export default function PartnerDuesPage() {
  const [payingId, setPayingId] = useState<number | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ['partner-dues'], queryFn: partnerService.dues });

  const unpaidOrders: Order[] = data?.unpaid_orders ?? [];

  const payNow = async (order: Order) => {
    if (!window.Cashfree) {
      toast.error('Payment system is still loading — please try again in a moment.');
      return;
    }
    setPayingId(order.id);
    try {
      const result = await partnerService.initiatePayment(order.id);
      if (!result?.payment_session_id) throw new Error('Could not start payment.');

      const cashfree = window.Cashfree({ mode: CASHFREE_MODE });
      cashfree.checkout({
        paymentSessionId: result.payment_session_id,
        redirectTarget: '_self',
      });
    } catch (ex: any) {
      toast.error(ex?.response?.data?.message || ex.message || 'Payment could not be started');
      setPayingId(null);
    }
  };

  if (isLoading) return <Spinner />;

  return (
    <div>
      <PageHeader title="My Dues" subtitle="Outstanding balance and unpaid orders" />

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <Card className="p-5">
          <p className="text-xs font-medium text-gray-500 uppercase">Credit Limit</p>
          <p className="text-2xl font-bold mt-1 text-gray-700">{fmtINR(data?.credit_limit ?? 0)}</p>
        </Card>
        <Card className="p-5">
          <p className="text-xs font-medium text-gray-500 uppercase">Outstanding</p>
          <p className="text-2xl font-bold mt-1 text-red-600">{fmtINR(data?.outstanding_amount ?? 0)}</p>
        </Card>
        <Card className="p-5">
          <p className="text-xs font-medium text-gray-500 uppercase">Available Credit</p>
          <p className="text-2xl font-bold mt-1 text-green-600">{fmtINR(data?.available_credit ?? 0)}</p>
        </Card>
      </div>

      <Card>
        <div className="px-5 py-4 border-b border-gray-100">
          <h3 className="text-sm font-semibold text-gray-700">Unpaid / Partial Orders</h3>
        </div>
        <Table
          columns={[
            { key: 'order_number', header: 'Order #', render: (o: Order) => <span className="font-medium">{o.order_number}</span> },
            { key: 'created_at', header: 'Date', render: (o: Order) => fmtDate(o.created_at) },
            { key: 'status', header: 'Status', render: (o: Order) => <Badge label={o.status} color="blue" /> },
            { key: 'payment_status', header: 'Payment', render: (o: Order) => <Badge label={o.payment_status} color="red" /> },
            { key: 'total_amount', header: 'Amount', render: (o: Order) => <span className="font-semibold">{fmtINR(o.total_amount)}</span> },
            {
              key: 'pay', header: '', render: (o: Order) => (
                <Button size="sm" onClick={() => payNow(o)} loading={payingId === o.id}>Pay Now</Button>
              ),
            },
          ]}
          data={unpaidOrders}
          keyField="id"
          emptyText="No outstanding orders 🎉"
        />
      </Card>
    </div>
  );
}
