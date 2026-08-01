import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2 } from 'lucide-react';
import toast from 'react-hot-toast';
import { partnerService, partnerCatalogService } from '../../services';
import { Card, Table, Button, Badge, PageHeader, Spinner, Modal, Select, Input, fmtINR, fmtDate } from '../../components/ui';
import type { Order } from '../../types';

interface CartLine {
  brand: string;
  model: string;
  grade: string;
  category?: string;
  quantity: number;
  price_from?: number;
}

export default function PartnerOrdersPage() {
  const qc = useQueryClient();
  const [modalOpen, setModalOpen] = useState(false);
  const [selectedBrand, setSelectedBrand] = useState('');
  const [selectedModel, setSelectedModel] = useState('');
  const [selectedGrade, setSelectedGrade] = useState('');
  const [quantity, setQuantity] = useState(1);
  const [cart, setCart] = useState<CartLine[]>([]);

  const { data, isLoading } = useQuery({ queryKey: ['partner-orders'], queryFn: () => partnerService.orders({ per_page: '50' }) });
  const orders: Order[] = (data as any)?.data ?? [];

  const { data: brands } = useQuery({
    queryKey: ['partner-catalog-brands'],
    queryFn: () => partnerCatalogService.brands(),
    enabled: modalOpen,
  });

  const { data: models } = useQuery({
    queryKey: ['partner-catalog-models', selectedBrand],
    queryFn: () => partnerCatalogService.models({ brand: selectedBrand }),
    enabled: modalOpen && !!selectedBrand,
  });

  const { data: gradeInfo } = useQuery({
    queryKey: ['partner-catalog-grades', selectedBrand, selectedModel],
    queryFn: () => partnerCatalogService.grades({ brand: selectedBrand, model: selectedModel }),
    enabled: modalOpen && !!selectedBrand && !!selectedModel,
  });

  const placeMut = useMutation({
    mutationFn: () => partnerService.placeOrder({ items: cart.map(({ brand, model, grade, category, quantity }) => ({ brand, model, grade, category, quantity })) }),
    onSuccess: () => {
      toast.success('Order placed successfully');
      setCart([]);
      setModalOpen(false);
      qc.invalidateQueries({ queryKey: ['partner-orders'] });
      qc.invalidateQueries({ queryKey: ['partner-dashboard'] });
    },
    onError: (e: any) => toast.error(e?.response?.data?.message || 'Could not place order'),
  });

  const addToCart = () => {
    if (!selectedBrand || !selectedModel || !selectedGrade) {
      toast.error('Select brand, model and grade first');
      return;
    }
    const gradeRow = gradeInfo?.grades?.find((g: any) => g.grade === selectedGrade);
    setCart((c) => [...c, {
      brand: selectedBrand, model: selectedModel, grade: selectedGrade,
      quantity, price_from: gradeRow?.price_from,
    }]);
    setSelectedModel('');
    setSelectedGrade('');
    setQuantity(1);
  };

  const removeFromCart = (i: number) => setCart((c) => c.filter((_, idx) => idx !== i));

  const closeModal = () => {
    setModalOpen(false);
    setSelectedBrand(''); setSelectedModel(''); setSelectedGrade(''); setQuantity(1); setCart([]);
  };

  if (isLoading) return <Spinner />;

  return (
    <div>
      <PageHeader
        title="My Orders"
        subtitle={`${orders.length} order(s)`}
        action={<Button onClick={() => setModalOpen(true)}><Plus size={15} /> New Order</Button>}
      />

      <Card>
        <Table
          columns={[
            { key: 'order_number', header: 'Order #', render: (o: Order) => <span className="font-medium">{o.order_number}</span> },
            { key: 'created_at', header: 'Date', render: (o: Order) => fmtDate(o.created_at) },
            { key: 'items_count', header: 'Items', render: (o: any) => `${o.items_count ?? '—'} item(s)` },
            { key: 'status', header: 'Status', render: (o: Order) => <Badge label={o.status} color="blue" /> },
            { key: 'payment_status', header: 'Payment', render: (o: Order) => <Badge label={o.payment_status} color={o.payment_status === 'paid' ? 'green' : 'red'} /> },
            { key: 'total_amount', header: 'Amount', render: (o: Order) => <span className="font-semibold">{fmtINR(o.total_amount)}</span> },
          ]}
          data={orders}
          keyField="id"
          emptyText="No orders yet — click New Order to place your first one"
        />
      </Card>

      <Modal open={modalOpen} onClose={closeModal} title="Place New Order" width="max-w-2xl">
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <Select
              label="Brand"
              value={selectedBrand}
              onChange={(e) => { setSelectedBrand(e.target.value); setSelectedModel(''); setSelectedGrade(''); }}
              options={[{ value: '', label: 'Select brand' }, ...(brands ?? []).map((b: any) => ({ value: b.brand, label: `${b.brand} (${b.available_qty} avail.)` }))]}
            />
            <Select
              label="Model"
              value={selectedModel}
              onChange={(e) => { setSelectedModel(e.target.value); setSelectedGrade(''); }}
              options={[{ value: '', label: 'Select model' }, ...(models ?? []).map((m: any) => ({ value: m.model, label: `${m.model} (${m.total_available} avail.)` }))]}
            />
            <Select
              label="Grade"
              value={selectedGrade}
              onChange={(e) => setSelectedGrade(e.target.value)}
              options={[{ value: '', label: 'Select grade' }, ...(gradeInfo?.grades ?? []).map((g: any) => ({ value: g.grade, label: `${g.grade} — ${fmtINR(g.price_from)} (${g.available_qty} avail.)` }))]}
            />
          </div>
          <div className="flex items-end gap-3">
            <div className="w-32">
              <Input label="Quantity" type="number" min={1} value={quantity} onChange={(e) => setQuantity(Number(e.target.value) || 1)} />
            </div>
            <Button variant="secondary" onClick={addToCart}><Plus size={14} /> Add to Order</Button>
          </div>

          {cart.length > 0 && (
            <div className="border border-gray-200 rounded-lg divide-y divide-gray-100">
              {cart.map((line, i) => (
                <div key={i} className="flex items-center justify-between px-3 py-2 text-sm">
                  <span>{line.brand} {line.model} — Grade {line.grade} × {line.quantity}</span>
                  <div className="flex items-center gap-3">
                    {line.price_from && <span className="text-gray-500">{fmtINR(line.price_from * line.quantity)}</span>}
                    <button onClick={() => removeFromCart(i)} className="text-red-400 hover:text-red-600"><Trash2 size={14} /></button>
                  </div>
                </div>
              ))}
            </div>
          )}

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="outline" onClick={closeModal}>Cancel</Button>
            <Button onClick={() => placeMut.mutate()} loading={placeMut.isPending} disabled={cart.length === 0}>
              Place Order ({cart.length} item{cart.length === 1 ? '' : 's'})
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
