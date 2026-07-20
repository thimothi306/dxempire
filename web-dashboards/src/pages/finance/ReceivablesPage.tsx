import { useQuery } from '@tanstack/react-query';
import { financeService } from '../../services';
import { Card, Table, PageHeader, Spinner, fmtINR } from '../../components/ui';

export default function ReceivablesPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['receivables'],
    queryFn: () => financeService.receivables({}),
  });

  const dealers: any[] = data?.data?.dealers ?? [];
  const totalOutstanding = data?.data?.total_outstanding ?? 0;

  return (
    <div>
      <PageHeader title="Receivables" subtitle={`₹${fmtINR(totalOutstanding)} outstanding`} />

      <div className="mb-5 bg-red-50 border border-red-200 px-4 py-3 rounded-lg">
        <p className="font-semibold text-red-800">Total Outstanding: {fmtINR(totalOutstanding)}</p>
      </div>

      <Card>
        {isLoading ? <Spinner /> : dealers.length === 0 ? (
          <div className="text-center py-8 text-gray-500">No outstanding receivables</div>
        ) : (
          <Table
            columns={[
              { key: 'business_name', header: 'Dealer', render: (d) => <span className="font-medium">{d.business_name}</span> },
              { key: 'contact', header: 'Contact Person', render: (d) => d.contact ?? '—' },
              { key: 'phone', header: 'Phone', render: (d) => <span className="font-mono text-sm">{d.phone}</span> },
              { key: 'credit_limit', header: 'Credit Limit', render: (d) => fmtINR(d.credit_limit) },
              { key: 'credit_used', header: 'Credit Used', render: (d) => <span className="font-semibold text-red-600">{fmtINR(d.credit_used)}</span> },
              { key: 'credit_available', header: 'Available', render: (d) => <span className="text-green-700">{fmtINR(d.credit_available)}</span> },
              {
                key: 'utilisation_pct',
                header: 'Utilization',
                render: (d) => (
                  <div className="flex items-center gap-2">
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div className="bg-red-500 h-2 rounded-full" style={{width: `${d.utilisation_pct}%`}} />
                    </div>
                    <span className="text-xs font-semibold whitespace-nowrap">{d.utilisation_pct}%</span>
                  </div>
                )
              },
            ]}
            data={dealers}
            keyField="dealer_id"
          />
        )}
      </Card>

    </div>
  );
}
