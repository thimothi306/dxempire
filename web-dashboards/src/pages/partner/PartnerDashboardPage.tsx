import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { ShoppingCart, TrendingUp, Wallet, PackageCheck } from 'lucide-react';
import { partnerService } from '../../services';
import { useAuthStore } from '../../stores/authStore';
import { StatCard, Card, Table, Badge, PageHeader, Spinner, fmtINR, fmtDate } from '../../components/ui';
import type { Order } from '../../types';

export default function PartnerDashboardPage() {
  const user = useAuthStore((s) => s.user) as any;
  const { data, isLoading } = useQuery({ queryKey: ['partner-dashboard'], queryFn: partnerService.dashboard });

  if (isLoading) return <Spinner />;

  const kycVerified = data?.kyc_status === 'verified';

  return (
    <div>
      <PageHeader
        title={`Welcome, ${data?.business_name || user?.name || 'Partner'}`}
        subtitle="DXEmpire Partner Portal"
        action={kycVerified
          ? <Badge label="KYC Verified" color="green" />
          : <Badge label={`KYC ${data?.kyc_status || 'pending'}`} color="yellow" />}
      />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard label="Total Orders" value={data?.total_orders ?? 0} icon={<ShoppingCart size={24} />} color="text-blue-600" />
        <StatCard label="Active Orders" value={data?.active_orders ?? 0} icon={<PackageCheck size={24} />} color="text-orange-600" />
        <StatCard label="Delivered" value={data?.delivered_orders ?? 0} icon={<TrendingUp size={24} />} color="text-green-600" />
        <StatCard label="Lifetime Purchases" value={fmtINR(data?.lifetime_purchases ?? 0)} icon={<Wallet size={24} />} color="text-purple-600" />
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <StatCard label="Credit Limit" value={fmtINR(data?.credit_limit ?? 0)} icon={<Wallet size={20} />} color="text-gray-700" />
        <StatCard label="Credit Used" value={fmtINR(data?.credit_used ?? 0)} icon={<Wallet size={20} />} color="text-red-600" />
        <StatCard label="Available Credit" value={fmtINR(data?.available_credit ?? 0)} icon={<Wallet size={20} />} color="text-green-600" />
      </div>

      <Card>
        <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 className="text-sm font-semibold text-gray-700">Recent Orders</h3>
          <Link to="/orders" className="text-xs text-primary hover:underline">View all</Link>
        </div>
        <Table
          columns={[
            { key: 'order_number', header: 'Order #', render: (o: Order) => <span className="font-medium">{o.order_number}</span> },
            { key: 'created_at', header: 'Date', render: (o: Order) => fmtDate(o.created_at) },
            { key: 'status', header: 'Status', render: (o: Order) => <Badge label={o.status} color="blue" /> },
            { key: 'total_amount', header: 'Amount', render: (o: Order) => <span className="font-semibold">{fmtINR(o.total_amount)}</span> },
          ]}
          data={data?.recent_orders ?? []}
          keyField="id"
          emptyText="No orders yet"
        />
      </Card>
    </div>
  );
}
