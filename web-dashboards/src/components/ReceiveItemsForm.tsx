import { Plus, Trash2 } from 'lucide-react';
import { Input, Select } from './ui';

export interface ReceiveItemRow {
  category: string;
  brand: string;
  model: string;
  purchase_price: string;
  quantity: string;
  imei: string;
}

export const EMPTY_RECEIVE_ITEM: ReceiveItemRow = {
  category: 'phone', brand: '', model: '', purchase_price: '', quantity: '1', imei: '',
};

/**
 * Expands UI rows (one row can represent N identical units via `quantity`)
 * into the flat item list the backend expects (POST /procurement/receive
 * and POST /purchase-orders/{id}/receive both take one entry per physical
 * unit). IMEI is only kept when quantity === 1 — a shared IMEI across
 * multiple units isn't valid (IMEIs must be unique).
 */
export function expandReceiveItems(rows: ReceiveItemRow[]) {
  return rows.flatMap((r) => {
    const qty = Math.max(1, Number(r.quantity) || 1);
    return Array.from({ length: qty }, () => ({
      category: r.category,
      brand: r.brand,
      model: r.model,
      purchase_price: Number(r.purchase_price),
      ...(qty === 1 && r.imei ? { imei: r.imei } : {}),
    }));
  });
}

export function ReceiveItemsForm({
  items, setItems,
}: {
  items: ReceiveItemRow[];
  setItems: (rows: ReceiveItemRow[]) => void;
}) {
  const addItem = () => setItems([...items, { ...EMPTY_RECEIVE_ITEM }]);
  const removeItem = (idx: number) => setItems(items.filter((_, i) => i !== idx));
  const updateItem = (idx: number, field: keyof ReceiveItemRow, value: string) =>
    setItems(items.map((it, i) => (i === idx ? { ...it, [field]: value } : it)));

  return (
    <div>
      <div className="flex justify-between items-center mb-2">
        <span className="text-xs font-medium text-gray-600">Items Received</span>
        <button onClick={addItem} className="text-xs text-primary hover:underline flex items-center gap-1">
          <Plus size={12} /> Add item
        </button>
      </div>
      <div className="space-y-2">
        {items.map((item, idx) => {
          const qty = Number(item.quantity) || 1;
          return (
            <div key={idx} className="grid grid-cols-6 gap-2 items-end bg-gray-50 p-2 rounded-lg">
              <Select
                label={idx === 0 ? 'Category' : ''}
                value={item.category}
                onChange={(e) => updateItem(idx, 'category', e.target.value)}
                options={['phone', 'laptop'].map((c) => ({ value: c, label: c }))}
              />
              <Input label={idx === 0 ? 'Brand' : ''} value={item.brand} onChange={(e) => updateItem(idx, 'brand', e.target.value)} placeholder="Samsung" />
              <Input label={idx === 0 ? 'Model' : ''} value={item.model} onChange={(e) => updateItem(idx, 'model', e.target.value)} placeholder="Galaxy S22" />
              <Input label={idx === 0 ? 'Cost (₹)' : ''} type="number" value={item.purchase_price} onChange={(e) => updateItem(idx, 'purchase_price', e.target.value)} placeholder="8000" />
              <Input label={idx === 0 ? 'Qty' : ''} type="number" min={1} value={item.quantity} onChange={(e) => updateItem(idx, 'quantity', e.target.value)} />
              <div className="flex items-end gap-1">
                <Input
                  label={idx === 0 ? 'IMEI (if qty=1)' : ''}
                  value={item.imei}
                  onChange={(e) => updateItem(idx, 'imei', e.target.value)}
                  placeholder={qty === 1 ? 'Optional, 15 digits' : 'N/A for qty > 1'}
                  disabled={qty !== 1}
                />
                {items.length > 1 && (
                  <button onClick={() => removeItem(idx)} className="mb-0.5 text-red-400 hover:text-red-600">
                    <Trash2 size={14} />
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
