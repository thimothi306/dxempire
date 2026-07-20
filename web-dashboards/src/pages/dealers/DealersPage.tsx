import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import toast from 'react-hot-toast';
import { dealersService } from '../../services';
import { Card, Table, Pagination, Select, Button, PageHeader, Spinner, Modal, Input, kycBadge, fmtINR, fmtDate } from '../../components/ui';
import type { Dealer } from '../../types';

const BLANK_FORM = {
  name: '', phone: '', email: '', business_name: '',
  gst_number: '', state: '', pincode: '', credit_limit: '', price_tier: '',
};

export default function DealersPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [kycFilter, setKycFilter] = useState('');
  const [selected, setSelected] = useState<Dealer | null>(null);
  const [creditForm, setCreditForm] = useState({ credit_limit: '' });
  const [activeTab, setActiveTab] = useState<'info' | 'ledger'>('info');
  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState({ ...BLANK_FORM });

  const { data, isLoading } = useQuery({
    queryKey: ['dealers', page, kycFilter],
    queryFn: () => dealersService.list({ page: String(page), ...(kycFilter && { kyc_status: kycFilter }) }),
  });

  const { data: detail } = useQuery({
    queryKey: ['dealer-detail', selected?.id],
    queryFn: () => dealersService.get(selected!.id),
    enabled: !!selected,
  });

  const { data: ledgerData } = useQuery({
    queryKey: ['dealer-ledger', selected?.id],
    queryFn: () => dealersService.ledger(selected!.id),
    enabled: !!selected && activeTab === 'ledger',
  });

  const kycApproveMut = useMutation({
    mutationFn: (id: number) => dealersService.approveKyc(id),
    onSuccess: () => { toast.success('KYC approved'); qc.invalidateQueries({ queryKey: ['dealers'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const kycRejectMut = useMutation({
    mutationFn: (id: number) => dealersService.rejectKyc(id),
    onSuccess: () => { toast.success('KYC rejected'); qc.invalidateQueries({ queryKey: ['dealers'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const creditMut = useMutation({
    mutationFn: (id: number) => dealersService.updateCredit(id, { credit_limit: Number(creditForm.credit_limit) }),
    onSuccess: () => { toast.success('Credit limit updated'); qc.invalidateQueries({ queryKey: ['dealers'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const createMut = useMutation({
    mutationFn: () => dealersService.create({
      ...createForm,
      credit_limit: createForm.credit_limit ? Number(createForm.credit_limit) : undefined,
    }),
    onSuccess: () => {
      toast.success('Dealer created successfully');
      qc.invalidateQueries({ queryKey: ['dealers'] });
      setCreateOpen(false);
      setCreateForm({ ...BLANK_FORM });
    },
    onError: (e: any) => toast.error(e?.response?.data?.message ?? 'Failed to create dealer'),
  });

  const dealers: Dealer[] = Array.isArray(data?.data) ? data.data : [];
  const meta = data?.meta || { current_page: 1, last_page: 1, total: 0 };
  const dealerDetail = detail?.data ?? selected;
  const ledger = ledgerData?.transactions ?? ledgerData?.data ?? [];
  const ledgerSummary = ledgerData?.summary ?? null;

  const openDealer = (d: Dealer) => {
    setSelected(d);
    setCreditForm({ credit_limit: String(d.credit_limit ?? '') });
    setActiveTab('info');
  };

  return (
    <div>
      <PageHeader
        title="Business Partners"
        subtitle={`${meta?.total ?? 0} registered business partners`}
        action={
          <Button onClick={() => setCreateOpen(true)}>
            <Plus size={15} /> New Dealer
          </Button>
        }
      />

      <div className="mb-5">
        <Select
          value={kycFilter}
          onChange={(e) => { setKycFilter(e.target.value); setPage(1); }}
          options={[
            { value: '', label: 'All KYC Status' },
            { value: 'pending', label: 'Pending' },
            { value: 'verified', label: 'Verified' },
            { value: 'rejected', label: 'Rejected' },
          ]}
        />
      </div>

      <Card>
        {isLoading ? <Spinner /> : (
          <>
            <Table
              columns={[
                { key: 'business_name', header: 'Business', render: (d) => <span className="font-medium">{d.business_name}</span> },
                { key: 'owner_name', header: 'Owner', render: (d) => d.owner_name ?? d.user?.name ?? '—' },
                { key: 'phone', header: 'Phone', render: (d) => d.phone ?? d.user?.phone ?? '—' },
                { key: 'city', header: 'City/State', render: (d) => d.city ?? d.state ?? '—' },
                { key: 'kyc_status', header: 'KYC', render: (d) => kycBadge(d.kyc_status) },
                { key: 'credit_limit', header: 'Credit Limit', render: (d) => fmtINR(d.credit_limit ?? 0) },
                { key: 'credit_used', header: 'Used', render: (d) => <span className={(d.credit_used ?? 0) > 0 ? 'text-red-600 font-medium' : ''}>{fmtINR(d.credit_used ?? d.outstanding_balance ?? 0)}</span> },
              ]}
              data={dealers}
              keyField="id"
              onRowClick={openDealer}
            />
            {meta && <Pagination current={meta.current_page} last={meta.last_page} total={meta.total} onChange={setPage} />}
          </>
        )}
      </Card>

      {/* Create Dealer Modal */}
      <Modal open={createOpen} onClose={() => setCreateOpen(false)} title="New Dealer">
        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <Input label="Owner Name *" value={createForm.name} onChange={(e) => setCreateForm({ ...createForm, name: e.target.value })} placeholder="Full name" />
            <Input label="Phone *" value={createForm.phone} onChange={(e) => setCreateForm({ ...createForm, phone: e.target.value })} placeholder="10-digit mobile" />
            <Input label="Email" value={createForm.email} onChange={(e) => setCreateForm({ ...createForm, email: e.target.value })} placeholder="Optional" />
            <Input label="Business Name *" value={createForm.business_name} onChange={(e) => setCreateForm({ ...createForm, business_name: e.target.value })} placeholder="Company / shop name" />
            <Input label="GST Number" value={createForm.gst_number} onChange={(e) => setCreateForm({ ...createForm, gst_number: e.target.value })} placeholder="Optional" />
            <Input label="State" value={createForm.state} onChange={(e) => setCreateForm({ ...createForm, state: e.target.value })} placeholder="e.g. Maharashtra" />
            <Input label="Pincode" value={createForm.pincode} onChange={(e) => setCreateForm({ ...createForm, pincode: e.target.value })} placeholder="6-digit pincode" />
            <Input label="Credit Limit (₹)" type="number" value={createForm.credit_limit} onChange={(e) => setCreateForm({ ...createForm, credit_limit: e.target.value })} placeholder="0" />
          </div>
          <Select
            label="Price Tier"
            value={createForm.price_tier}
            onChange={(e) => setCreateForm({ ...createForm, price_tier: e.target.value })}
            options={[
              { value: '', label: 'Select tier' },
              { value: 'A', label: 'Tier A (Best Price)' },
              { value: 'B', label: 'Tier B' },
              { value: 'C', label: 'Tier C' },
            ]}
          />
          <div className="flex gap-3 pt-2">
            <Button
              onClick={() => createMut.mutate()}
              loading={createMut.isPending}
              disabled={!createForm.name || !createForm.phone || !createForm.business_name}
              className="flex-1 justify-center"
            >
              Create Dealer
            </Button>
            <Button variant="outline" onClick={() => setCreateOpen(false)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>

      {/* Dealer Detail Modal */}
      <Modal open={!!selected} onClose={() => setSelected(null)} title={selected?.business_name ?? 'Dealer'}>
        {selected && (
          <div className="space-y-4">
            {/* Tabs */}
            <div className="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
              {(['info', 'ledger'] as const).map((t) => (
                <button
                  key={t}
                  onClick={() => setActiveTab(t)}
                  className={`px-4 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === t ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                >
                  {t.charAt(0).toUpperCase() + t.slice(1)}
                </button>
              ))}
            </div>

            {activeTab === 'info' && (
              <>
                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div><span className="text-gray-500 block text-xs">Owner</span>{dealerDetail?.owner_name ?? dealerDetail?.user?.name ?? '—'}</div>
                  <div><span className="text-gray-500 block text-xs">Phone</span>{dealerDetail?.phone ?? dealerDetail?.user?.phone ?? '—'}</div>
                  <div><span className="text-gray-500 block text-xs">Email</span>{dealerDetail?.email ?? dealerDetail?.user?.email ?? '—'}</div>
                  <div><span className="text-gray-500 block text-xs">GST</span><span className="font-mono text-xs">{dealerDetail?.gst_number ?? '—'}</span></div>
                  <div><span className="text-gray-500 block text-xs">State</span>{dealerDetail?.state ?? dealerDetail?.city ?? '—'}</div>
                  <div><span className="text-gray-500 block text-xs">KYC</span>{kycBadge(dealerDetail?.kyc_status ?? selected.kyc_status)}</div>
                  <div><span className="text-gray-500 block text-xs">Credit Limit</span><span className="font-semibold">{fmtINR(dealerDetail?.credit_limit ?? selected.credit_limit ?? 0)}</span></div>
                  <div><span className="text-gray-500 block text-xs">Credit Used</span><span className="font-semibold text-red-600">{fmtINR(dealerDetail?.credit_used ?? dealerDetail?.outstanding_balance ?? 0)}</span></div>
                  <div><span className="text-gray-500 block text-xs">Available</span><span className="font-semibold text-green-700">{fmtINR(dealerDetail?.available_credit ?? 0)}</span></div>
                  <div><span className="text-gray-500 block text-xs">Joined</span>{fmtDate(dealerDetail?.created_at ?? selected.created_at ?? '')}</div>
                </div>

                {/* KYC actions */}
                {(dealerDetail?.kyc_status ?? selected.kyc_status) === 'pending' && (
                  <div className="flex gap-2 pt-2 border-t">
                    <Button size="sm" onClick={() => kycApproveMut.mutate(selected.id)} loading={kycApproveMut.isPending}>Approve KYC</Button>
                    <Button size="sm" variant="danger" onClick={() => kycRejectMut.mutate(selected.id)} loading={kycRejectMut.isPending}>Reject KYC</Button>
                  </div>
                )}

                {/* Credit limit update */}
                <div className="border-t pt-4">
                  <div className="text-xs font-medium text-gray-500 mb-2">Update Credit Limit</div>
                  <div className="flex gap-2">
                    <Input
                      type="number"
                      placeholder="Amount in ₹"
                      value={creditForm.credit_limit}
                      onChange={(e) => setCreditForm({ credit_limit: e.target.value })}
                    />
                    <Button size="sm" onClick={() => creditMut.mutate(selected.id)} loading={creditMut.isPending}>Update</Button>
                  </div>
                </div>
              </>
            )}

            {activeTab === 'ledger' && (
              <div>
                {/* Ledger summary */}
                {ledgerSummary && (
                  <div className="grid grid-cols-3 gap-3 mb-4">
                    {[
                      { label: 'Total Billed', value: fmtINR(ledgerSummary.total_billed ?? 0), color: '' },
                      { label: 'Total Paid', value: fmtINR(ledgerSummary.total_paid ?? 0), color: 'text-green-700' },
                      { label: 'Outstanding', value: fmtINR(ledgerSummary.outstanding ?? 0), color: 'text-red-600' },
                    ].map((s) => (
                      <div key={s.label} className="bg-gray-50 rounded-lg p-3 text-center">
                        <div className="text-xs text-gray-500">{s.label}</div>
                        <div className={`font-bold text-sm mt-1 ${s.color}`}>{s.value}</div>
                      </div>
                    ))}
                  </div>
                )}
                {/* Transactions */}
                {ledger.length === 0
                  ? <div className="py-6 text-center text-sm text-gray-400">No transactions found</div>
                  : (
                    <div className="space-y-2 max-h-64 overflow-y-auto">
                      {ledger.map((t: any, i: number) => (
                        <div key={i} className="flex justify-between text-xs bg-gray-50 px-3 py-2 rounded">
                          <div>
                            <div className="font-medium">{t.order_number}</div>
                            <div className="text-gray-400">{fmtDate(t.date ?? t.created_at)} · {t.status}</div>
                          </div>
                          <div className="text-right">
                            <div className="font-semibold">{fmtINR(t.total_amount)}</div>
                            <div className={t.payment_status === 'paid' ? 'text-green-600' : 'text-red-500'}>{t.payment_status}</div>
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
