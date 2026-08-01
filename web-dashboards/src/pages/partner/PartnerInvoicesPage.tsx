import { useQuery } from '@tanstack/react-query';
import { partnerService } from '../../services';
import { Card, Table, PageHeader, Spinner, fmtINR, fmtDate } from '../../components/ui';
import type { Invoice } from '../../types';

export default function PartnerInvoicesPage() {
  const { data, isLoading } = useQuery({ queryKey: ['partner-invoices'], queryFn: () => partnerService.invoices({ per_page: '50' }) });
  const invoices: Invoice[] = (data as any)?.data ?? [];

  if (isLoading) return <Spinner />;

  return (
    <div>
      <PageHeader title="My Invoices" subtitle={`${invoices.length} invoice(s)`} />
      <Card>
        <Table
          columns={[
            { key: 'invoice_number', header: 'Invoice #', render: (i: Invoice) => <span className="font-medium">{i.invoice_number}</span> },
            { key: 'order_number', header: 'Order', render: (i: any) => i.order?.order_number ?? '—' },
            { key: 'issued_at', header: 'Date', render: (i: any) => fmtDate(i.issued_at ?? i.created_at) },
            { key: 'subtotal', header: 'Subtotal', render: (i: Invoice) => fmtINR(i.subtotal ?? 0) },
            { key: 'gst_amount', header: 'GST', render: (i: Invoice) => fmtINR(i.gst_amount) },
            { key: 'total', header: 'Total', render: (i: any) => <span className="font-semibold">{fmtINR(i.total ?? i.total_amount ?? 0)}</span> },
          ]}
          data={invoices}
          keyField="id"
          emptyText="No invoices yet"
        />
      </Card>
    </div>
  );
}
