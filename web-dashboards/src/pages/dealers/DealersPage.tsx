import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Download, CheckCircle2 } from 'lucide-react';
import toast from 'react-hot-toast';
import { dealersService } from '../../services';
import { Card, Table, Pagination, Select, Button, Badge, PageHeader, Spinner, Modal, Input, kycBadge, fmtINR, fmtDate, ExportButton } from '../../components/ui';
import { STATE_NAMES, districtsForState } from '../../data/statesDistricts';
import type { Dealer } from '../../types';

const BLANK_FORM = {
  name: '', phone: '', email: '', business_name: '',
  gst_number: '', state: '', district: '', pincode: '', credit_limit: '', price_tier: '',
};

const DOCUMENT_TYPES: { key: string; label: string }[] = [
  { key: 'aadhaar_document', label: 'Aadhaar Card' },
  { key: 'pan_document', label: 'PAN Card' },
  { key: 'passport_photo', label: 'Passport Size Photo' },
  { key: 'education_certificate', label: 'Educational Qualification Certificate' },
  { key: 'bank_passbook_document', label: 'Bank Passbook / Cancelled Cheque' },
  { key: 'signed_agreement_document', label: 'Signed Agreement Document' },
];

export default function DealersPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [kycFilter, setKycFilter] = useState('');
  const [stateFilter, setStateFilter] = useState('');
  const [districtFilter, setDistrictFilter] = useState('');
  const [selected, setSelected] = useState<Dealer | null>(null);
  const [creditForm, setCreditForm] = useState({ credit_limit: '' });
  const [activeTab, setActiveTab] = useState<'info' | 'ledger'>('info');
  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState({ ...BLANK_FORM });

  const { data, isLoading } = useQuery({
    queryKey: ['dealers', page, kycFilter, stateFilter, districtFilter],
    queryFn: () => dealersService.list({
      page: String(page),
      ...(kycFilter && { kyc_status: kycFilter }),
      ...(stateFilter && { state: stateFilter }),
      ...(districtFilter && { district: districtFilter }),
    }),
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

  const activateMut = useMutation({
    mutationFn: (id: number) => dealersService.activate(id),
    onSuccess: () => { toast.success('Partner account activated'); qc.invalidateQueries({ queryKey: ['dealers'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const deactivateMut = useMutation({
    mutationFn: (id: number) => dealersService.deactivate(id),
    onSuccess: () => { toast.success('Partner account deactivated'); qc.invalidateQueries({ queryKey: ['dealers'] }); setSelected(null); },
    onError: () => toast.error('Failed'),
  });

  const deleteMut = useMutation({
    mutationFn: (id: number) => dealersService.destroy(id),
    onSuccess: () => { toast.success('Partner deleted'); qc.invalidateQueries({ queryKey: ['dealers'] }); setSelected(null); },
    onError: (e: any) => toast.error(e?.response?.data?.message ?? 'Failed to delete partner'),
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
  const ledger: any[] = Array.isArray(ledgerData?.transactions)
    ? ledgerData.transactions
    : Array.isArray(ledgerData?.transactions?.data)
      ? ledgerData.transactions.data
      : [];
  const ledgerSummary = ledgerData?.summary ?? null;

  const downloadDealerDocument = async (id: number, type: string, label: string) => {
    const blob = await dealersService.document(id, type);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${dealerDetail?.business_name ?? 'partner'}_${label}`;
    a.click();
    URL.revokeObjectURL(url);
  };

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
          <div className="flex gap-2">
            <Button onClick={() => setCreateOpen(true)}>
              <Plus size={15} /> New Dealer
            </Button>
            <ExportButton
              filenameBase="business_partners"
              onExport={(format) => dealersService.export(format, { ...(kycFilter && { kyc_status: kycFilter }), ...(stateFilter && { state: stateFilter }), ...(districtFilter && { district: districtFilter }) })}
            />
          </div>
        }
      />

      <div className="mb-5 flex flex-wrap gap-3">
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
        <Select
          value={stateFilter}
          onChange={(e) => { setStateFilter(e.target.value); setDistrictFilter(''); setPage(1); }}
          options={[{ value: '', label: 'All States' }, ...STATE_NAMES.map((s) => ({ value: s, label: s }))]}
        />
        <Select
          value={districtFilter}
          onChange={(e) => { setDistrictFilter(e.target.value); setPage(1); }}
          disabled={!stateFilter}
          options={[{ value: '', label: stateFilter ? 'All Districts' : 'Select a state first' }, ...districtsForState(stateFilter).map((d) => ({ value: d, label: d }))]}
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
                { key: 'district', header: 'District', render: (d) => d.district ?? '—' },
                { key: 'kyc_status', header: 'KYC', render: (d) => kycBadge(d.kyc_status) },
                { key: 'status', header: 'Status', render: (d) => d.user?.is_active === false ? <Badge label="Inactive" color="red" /> : <Badge label="Active" color="green" /> },
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
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Input label="Owner Name *" value={createForm.name} onChange={(e) => setCreateForm({ ...createForm, name: e.target.value })} placeholder="Full name" />
            <Input label="Phone *" value={createForm.phone} onChange={(e) => setCreateForm({ ...createForm, phone: e.target.value })} placeholder="10-digit mobile" />
            <Input label="Email" value={createForm.email} onChange={(e) => setCreateForm({ ...createForm, email: e.target.value })} placeholder="Optional" />
            <Input label="Business Name *" value={createForm.business_name} onChange={(e) => setCreateForm({ ...createForm, business_name: e.target.value })} placeholder="Company / shop name" />
            <Input label="GST Number" value={createForm.gst_number} onChange={(e) => setCreateForm({ ...createForm, gst_number: e.target.value })} placeholder="Optional" />
            <Select
              label="State"
              value={createForm.state}
              onChange={(e) => setCreateForm({ ...createForm, state: e.target.value, district: '' })}
              options={[{ value: '', label: 'Select state...' }, ...STATE_NAMES.map((s) => ({ value: s, label: s }))]}
            />
            <Select
              label="District"
              value={createForm.district}
              onChange={(e) => setCreateForm({ ...createForm, district: e.target.value })}
              disabled={!createForm.state}
              options={[{ value: '', label: createForm.state ? 'Select district...' : 'Select a state first' }, ...districtsForState(createForm.state).map((d) => ({ value: d, label: d }))]}
            />
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
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                  <div><span className="text-gray-500 block text-xs">Owner</span>{dealerDetail?.owner_name ?? dealerDetail?.user?.name ?? '—'}</div>
                  <div><span className="text-gray-500 block text-xs">Phone</span>{dealerDetail?.phone ?? dealerDetail?.user?.phone ?? '—'}</div>
                  <div><span className="text-gray-500 block text-xs">Email</span>{dealerDetail?.email ?? dealerDetail?.user?.email ?? '—'}</div>
                  <div><span className="text-gray-500 block text-xs">GST</span><span className="font-mono text-xs">{dealerDetail?.gst_number ?? '—'}</span></div>
                  <div><span className="text-gray-500 block text-xs">State</span>{dealerDetail?.state ?? dealerDetail?.city ?? '—'}</div>
                  <div><span className="text-gray-500 block text-xs">KYC</span>{kycBadge(dealerDetail?.kyc_status ?? selected.kyc_status)}</div>
                  <div><span className="text-gray-500 block text-xs">Login Status</span>{(dealerDetail?.user?.is_active ?? selected.user?.is_active) === false ? <Badge label="Inactive" color="red" /> : <Badge label="Active" color="green" />}</div>
                  <div><span className="text-gray-500 block text-xs">Credit Limit</span><span className="font-semibold">{fmtINR(dealerDetail?.credit_limit ?? selected.credit_limit ?? 0)}</span></div>
                  <div><span className="text-gray-500 block text-xs">Credit Used</span><span className="font-semibold text-red-600">{fmtINR(dealerDetail?.credit_used ?? dealerDetail?.outstanding_balance ?? 0)}</span></div>
                  <div><span className="text-gray-500 block text-xs">Available</span><span className="font-semibold text-green-700">{fmtINR(dealerDetail?.available_credit ?? 0)}</span></div>
                  <div><span className="text-gray-500 block text-xs">Joined</span>{fmtDate(dealerDetail?.created_at ?? selected.created_at ?? '')}</div>
                </div>

                {/* Address & bank details — self-submitted by the partner via the mobile app registration form */}
                {(dealerDetail?.village_street || dealerDetail?.post_office || dealerDetail?.police_station || dealerDetail?.bank_account_number) && (
                  <div className="border-t pt-3 mt-2">
                    <div className="text-xs font-medium text-gray-500 mb-2">Address &amp; Bank Details (submitted by partner)</div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                      {dealerDetail?.village_street && <div><span className="text-gray-500 block text-xs">Village / Street</span>{dealerDetail.village_street}</div>}
                      {dealerDetail?.post_office && <div><span className="text-gray-500 block text-xs">Post Office</span>{dealerDetail.post_office}</div>}
                      {dealerDetail?.police_station && <div><span className="text-gray-500 block text-xs">Police Station</span>{dealerDetail.police_station}</div>}
                      {dealerDetail?.bank_account_number && <div><span className="text-gray-500 block text-xs">Bank Account No.</span><span className="font-mono text-xs">{dealerDetail.bank_account_number}</span></div>}
                      {dealerDetail?.account_holder_name && <div><span className="text-gray-500 block text-xs">Account Holder</span>{dealerDetail.account_holder_name}</div>}
                      {dealerDetail?.bank_name && <div><span className="text-gray-500 block text-xs">Bank Name</span>{dealerDetail.bank_name}</div>}
                      {dealerDetail?.ifsc_code && <div><span className="text-gray-500 block text-xs">IFSC Code</span><span className="font-mono text-xs">{dealerDetail.ifsc_code}</span></div>}
                      {dealerDetail?.aadhaar_number && <div><span className="text-gray-500 block text-xs">Aadhaar No.</span><span className="font-mono text-xs">{dealerDetail.aadhaar_number}</span></div>}
                      {dealerDetail?.pan_number && <div><span className="text-gray-500 block text-xs">PAN No.</span><span className="font-mono text-xs">{dealerDetail.pan_number}</span></div>}
                    </div>
                  </div>
                )}

                {/* KYC documents — uploaded by the partner from the mobile app, any time after registration */}
                {dealerDetail?.kyc_documents && (
                  <div className="border-t pt-3 mt-2">
                    <div className="text-xs font-medium text-gray-500 mb-2">KYC Documents</div>
                    <div className="space-y-1.5">
                      {DOCUMENT_TYPES.map((doc) => {
                        const uploaded = dealerDetail.kyc_documents[doc.key];
                        return (
                          <div key={doc.key} className="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2 text-xs">
                            <span className="flex items-center gap-2">
                              {uploaded ? <CheckCircle2 size={13} className="text-green-600" /> : <span className="w-[13px] h-[13px] rounded-full border border-gray-300 inline-block" />}
                              {doc.label}
                            </span>
                            {uploaded ? (
                              <button type="button" onClick={() => downloadDealerDocument(selected.id, doc.key, doc.label)} className="text-primary hover:underline flex items-center gap-1">
                                <Download size={12} /> View
                              </button>
                            ) : (
                              <span className="text-gray-400">Not uploaded</span>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}

                {/* KYC actions */}
                {(dealerDetail?.kyc_status ?? selected.kyc_status) === 'pending' && (
                  <div className="flex gap-2 pt-2 border-t">
                    <Button size="sm" onClick={() => kycApproveMut.mutate(selected.id)} loading={kycApproveMut.isPending}>Approve KYC</Button>
                    <Button size="sm" variant="danger" onClick={() => kycRejectMut.mutate(selected.id)} loading={kycRejectMut.isPending}>Reject KYC</Button>
                  </div>
                )}

                {/* Login activate/deactivate */}
                <div className="flex gap-2 pt-2 border-t mt-2">
                  {(dealerDetail?.user?.is_active ?? selected.user?.is_active) === false ? (
                    <Button size="sm" onClick={() => activateMut.mutate(selected.id)} loading={activateMut.isPending}>Activate Account</Button>
                  ) : (
                    <Button size="sm" variant="danger" onClick={() => deactivateMut.mutate(selected.id)} loading={deactivateMut.isPending}>Deactivate Account</Button>
                  )}
                </div>

                {/* Delete — only succeeds for partners with zero order history */}
                <div className="flex gap-2 pt-2 border-t mt-2">
                  <Button
                    size="sm"
                    variant="danger"
                    loading={deleteMut.isPending}
                    onClick={() => {
                      if (window.confirm(`Delete ${selected.business_name}? This can't be undone. Partners with any order history can't be deleted — deactivate them instead.`)) {
                        deleteMut.mutate(selected.id);
                      }
                    }}
                  >
                    Delete Partner
                  </Button>
                </div>

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
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
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
