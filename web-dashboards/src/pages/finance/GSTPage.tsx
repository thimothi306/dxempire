import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Download } from 'lucide-react';
import { financeService } from '../../services';
import { Card, Table, PageHeader, Spinner, Button, fmtINR } from '../../components/ui';

export default function GSTPage() {
  const [month, setMonth] = useState(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  });

  const { data, isLoading } = useQuery({
    queryKey: ['gst-report', month],
    queryFn: () => financeService.gstReport({ month }),
  });

  const report = data?.data ?? data ?? null;
  const rows: any[] = report?.invoices ?? [];

  const handleExport = async () => {
    const blob = await financeService.exportGST({ month });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = `gst-${month}.xlsx`; a.click();
  };

  return (
    <div>
      <PageHeader
        title="GST Report"
        subtitle="Monthly GST summary for filing"
        action={<Button variant="outline" onClick={handleExport}><Download size={15} /> Export</Button>}
      />

      <div className="mb-5">
        <input
          type="month"
          className="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30"
          value={month}
          onChange={(e) => setMonth(e.target.value)}
        />
      </div>

      {isLoading ? <Spinner /> : report && (
        <>
          {/* Summary */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            {[
              { label: 'Taxable Value', value: report.taxable_value ?? 0 },
              { label: 'CGST Collected', value: report.cgst ?? 0 },
              { label: 'SGST Collected', value: report.sgst ?? 0 },
              { label: 'Total GST', value: (report.cgst ?? 0) + (report.sgst ?? 0), bold: true },
            ].map((s) => (
              <Card key={s.label} className="p-4">
                <div className="text-xs text-gray-500 mb-1">{s.label}</div>
                <div className={`text-xl ${s.bold ? 'font-bold text-primary' : 'font-semibold text-gray-800'}`}>{fmtINR(s.value)}</div>
              </Card>
            ))}
          </div>

          {/* Invoice-wise breakup */}
          {rows.length > 0 && (
            <Card>
              <Table
                columns={[
                  { key: 'invoice_number', header: 'Invoice #', render: (i) => <span className="font-mono text-xs">{i.invoice_number}</span> },
                  { key: 'dealer', header: 'Dealer', render: (i) => i.dealer_name ?? '—' },
                  { key: 'dealer_gstin', header: 'GSTIN', render: (i) => <span className="font-mono text-xs">{i.dealer_gstin ?? '—'}</span> },
                  { key: 'taxable_value', header: 'Taxable', render: (i) => fmtINR(i.taxable_value) },
                  { key: 'cgst', header: 'CGST', render: (i) => fmtINR(i.cgst) },
                  { key: 'sgst', header: 'SGST', render: (i) => fmtINR(i.sgst) },
                  { key: 'total', header: 'Total', render: (i) => <span className="font-semibold">{fmtINR(i.total_amount)}</span> },
                ]}
                data={rows}
                keyField="id"
              />
            </Card>
          )}
        </>
      )}
    </div>
  );
}
