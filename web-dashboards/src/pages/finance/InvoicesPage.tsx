import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Download } from 'lucide-react';
import { financeService } from '../../services';
import { Card, Table, Pagination, Select, Badge, Button, PageHeader, Spinner, fmtINR, fmtDate } from '../../components/ui';
import type { Invoice } from '../../types';
import { useAuthStore } from '../../stores/authStore';
import PartnerInvoicesPage from '../partner/PartnerInvoicesPage';

export default function InvoicesPage() {
  const role = useAuthStore((s) => s.user?.role);
  if (role === 'b2b_partner') {
    return <PartnerInvoicesPage />;
  }
  return <StaffInvoicesPage />;
}

const PAYMENT_STATUS_COLOR: Record<string, string> = { unpaid: 'red', partial: 'orange', paid: 'green' };

function StaffInvoicesPage() {
  const [page, setPage] = useState(1);
  const [paymentStatus, setPaymentStatus] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['invoices', page, paymentStatus],
    queryFn: () => financeService.invoices({ page: String(page), ...(paymentStatus && { payment_status: paymentStatus }) }),
  });

  const handleDownload = async (id: number, number: string) => {
    const blob = await financeService.downloadInvoice(id);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = `invoice-${number}.pdf`; a.click();
  };

  const invoices: Invoice[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };

  return (
    <div>
      <PageHeader title="Invoices" subtitle={`${meta?.total ?? 0} invoices`} />

      <div className="mb-5">
        <Select
          value={paymentStatus}
          onChange={(e) => { setPaymentStatus(e.target.value); setPage(1); }}
          options={[
            { value: '', label: 'All Payment Statuses' },
            { value: 'unpaid', label: 'Unpaid' },
            { value: 'partial', label: 'Partially Paid' },
            { value: 'paid', label: 'Paid' },
          ]}
        />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'invoice_number', header: 'Invoice #', render: (i) => <span className="font-mono text-xs font-semibold">{i.invoice_number}</span> },
                { key: 'dealer', header: 'Dealer', render: (i) => i.dealer?.business_name ?? '—' },
                { key: 'payment_status', header: 'Payment', render: (i) => {
                  const ps = i.order?.payment_status ?? 'unpaid';
                  return <Badge label={ps.replace('_', ' ')} color={PAYMENT_STATUS_COLOR[ps] ?? 'gray'} />;
                }},
                { key: 'subtotal', header: 'Subtotal', render: (i) => fmtINR(i.subtotal ?? 0) },
                { key: 'gst', header: 'GST Breakdown', render: (i) => (
                  <div className="text-xs space-y-0.5">
                    {i.tax_type === 'intra' ? (
                      <>
                        <div className="text-gray-500">CGST: <span className="font-medium text-gray-800">{fmtINR(i.cgst_amount ?? 0)}</span></div>
                        <div className="text-gray-500">SGST: <span className="font-medium text-gray-800">{fmtINR(i.sgst_amount ?? 0)}</span></div>
                      </>
                    ) : (
                      <div className="text-gray-500">IGST: <span className="font-medium text-gray-800">{fmtINR(i.igst_amount ?? i.gst_amount ?? 0)}</span></div>
                    )}
                    <Badge label={i.tax_type === 'intra' ? 'Intra-State' : 'Inter-State'} color={i.tax_type === 'intra' ? 'blue' : 'purple'} />
                  </div>
                )},
                { key: 'total_amount', header: 'Total', render: (i) => <span className="font-semibold">{fmtINR(i.total ?? i.total_amount ?? 0)}</span> },
                { key: 'issued_at', header: 'Issued', render: (i) => <span className="text-xs text-gray-500">{fmtDate(i.issued_at ?? i.created_at ?? '')}</span> },
                {
                  key: 'download', header: '', render: (i) => (
                    <Button size="sm" variant="ghost" onClick={(e) => { e.stopPropagation(); handleDownload(i.id, i.invoice_number); }}>
                      <Download size={13} />
                    </Button>
                  ),
                },
              ]}
              data={invoices}
              keyField="id"
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>
    </div>
  );
}
