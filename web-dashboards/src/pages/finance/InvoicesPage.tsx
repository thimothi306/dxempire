import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Download } from 'lucide-react';
import { financeService } from '../../services';
import { Card, Table, Pagination, Select, Badge, Button, PageHeader, Spinner, fmtINR, fmtDate } from '../../components/ui';
import type { Invoice } from '../../types';

export default function InvoicesPage() {
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['invoices', page, status],
    queryFn: () => financeService.invoices({ page: String(page), ...(status && { status }) }),
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
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1); }}
          options={[
            { value: '', label: 'All Statuses' },
            { value: 'draft', label: 'Draft' },
            { value: 'sent', label: 'Sent' },
            { value: 'paid', label: 'Paid' },
            { value: 'overdue', label: 'Overdue' },
            { value: 'cancelled', label: 'Cancelled' },
          ]}
        />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'invoice_number', header: 'Invoice #', render: (i) => <span className="font-mono text-xs font-semibold">{i.invoice_number}</span> },
                { key: 'dealer', header: 'Dealer', render: (i) => i.order?.dealer?.business_name ?? '—' },
                { key: 'status', header: 'Status', render: (i) => {
                  const colors: Record<string, string> = { draft: 'gray', sent: 'blue', paid: 'green', overdue: 'red', cancelled: 'gray' };
                  return <Badge label={i.status} color={colors[i.status] ?? 'gray'} />;
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
                { key: 'due_date', header: 'Due Date', render: (i) => <span className={i.status === 'overdue' ? 'text-red-600 font-medium' : ''}>{fmtDate(i.due_date ?? '')}</span> },
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
