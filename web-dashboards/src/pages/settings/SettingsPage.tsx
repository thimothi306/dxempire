import { useQuery, useMutation } from '@tanstack/react-query';
import { useState, useEffect } from 'react';
import { Save } from 'lucide-react';
import toast from 'react-hot-toast';
import { adminService } from '../../services';
import { Card, Button, Input, Select, PageHeader, Spinner } from '../../components/ui';

const SETTING_KEYS = [
  { key: 'company_name', label: 'Company Name', type: 'text' },
  { key: 'company_address', label: 'Company Address', type: 'text' },
  { key: 'company_gst', label: 'GST Number', type: 'text' },
  { key: 'company_phone', label: 'Contact Phone', type: 'text' },
  { key: 'company_email', label: 'Contact Email', type: 'text' },
  { key: 'logistics_provider', label: 'Logistics Provider', type: 'select', options: ['shiprocket', 'delhivery', 'dtdc'] },
  { key: 'whatsapp_provider', label: 'WhatsApp Provider', type: 'select', options: ['interakt', 'twilio'] },
];

const WAREHOUSE_KEYS = [
  { key: 'warehouse_name', label: 'Pickup Location Name', type: 'text' },
  { key: 'warehouse_contact', label: 'Contact Person (C/O)', type: 'text' },
  { key: 'warehouse_phone', label: 'Phone', type: 'text' },
  { key: 'warehouse_email', label: 'Email', type: 'text' },
  { key: 'warehouse_address', label: 'Address', type: 'text' },
  { key: 'warehouse_city', label: 'City / District', type: 'text' },
  { key: 'warehouse_state', label: 'State', type: 'text' },
  { key: 'warehouse_pincode', label: 'Pincode', type: 'text' },
];

export default function SettingsPage() {
  const { data: settings, isLoading } = useQuery({ queryKey: ['settings'], queryFn: adminService.settings });
  const [values, setValues] = useState<Record<string, string>>({});
  // low_stock_threshold is stored as a per-category JSON object, e.g.
  // {"phone":10,"laptop":5} — it can't be crammed into the generic
  // string-keyed `values` map without corrupting it on save.
  const [thresholds, setThresholds] = useState({ phone: '10', laptop: '5' });

  useEffect(() => {
    if (settings) {
      const map: Record<string, string> = {};
      const entries: [string, unknown][] = Array.isArray(settings)
        ? settings.map((s: { key: string; value: unknown }) => [s.key, s.value])
        : Object.entries(settings);

      entries.forEach(([k, v]) => {
        if (k === 'low_stock_threshold') {
          const parsed = typeof v === 'string' ? (() => { try { return JSON.parse(v); } catch { return null; } })() : v;
          if (parsed && typeof parsed === 'object') {
            setThresholds({
              phone: String((parsed as any).phone ?? 10),
              laptop: String((parsed as any).laptop ?? 5),
            });
          }
          return;
        }
        map[k] = typeof v === 'string' ? v : String(v);
      });
      setValues(map);
    }
  }, [settings]);

  const editableKeys = [...SETTING_KEYS, ...WAREHOUSE_KEYS].map((s) => s.key);

  const saveMut = useMutation({
    mutationFn: () => {
      const payload: Record<string, unknown> = {};
      editableKeys.forEach((k) => { payload[k] = values[k] ?? ''; });
      payload.low_stock_threshold = { phone: Number(thresholds.phone) || 0, laptop: Number(thresholds.laptop) || 0 };
      return adminService.updateSettings(payload);
    },
    onSuccess: () => toast.success('Settings saved'),
    onError: () => toast.error('Failed to save settings'),
  });

  if (isLoading) return <Spinner />;

  return (
    <div>
      <PageHeader
        title="Settings"
        subtitle="System configuration"
        action={<Button onClick={() => saveMut.mutate()} loading={saveMut.isPending}><Save size={15} /> Save Changes</Button>}
      />

      <Card className="p-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
          {SETTING_KEYS.map((s) =>
            s.type === 'select' ? (
              <Select
                key={s.key}
                label={s.label}
                value={values[s.key] ?? ''}
                onChange={(e) => setValues({ ...values, [s.key]: e.target.value })}
                options={s.options!.map((o) => ({ value: o, label: o }))}
              />
            ) : (
              <Input
                key={s.key}
                label={s.label}
                type={s.type}
                value={values[s.key] ?? ''}
                onChange={(e) => setValues({ ...values, [s.key]: e.target.value })}
              />
            )
          )}
        </div>
      </Card>

      <Card className="p-6 mt-6">
        <h2 className="text-sm font-semibold text-gray-900 mb-1">Low Stock Threshold</h2>
        <p className="text-xs text-gray-500 mb-5">
          Below this many units in stock for a category, the dashboard and AI summary flag it as low stock.
        </p>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
          <Input
            label="Phones"
            type="number"
            value={thresholds.phone}
            onChange={(e) => setThresholds({ ...thresholds, phone: e.target.value })}
          />
          <Input
            label="Laptops"
            type="number"
            value={thresholds.laptop}
            onChange={(e) => setThresholds({ ...thresholds, laptop: e.target.value })}
          />
        </div>
      </Card>

      <Card className="p-6 mt-6">
        <h2 className="text-sm font-semibold text-gray-900 mb-1">Pickup Warehouse (Courier)</h2>
        <p className="text-xs text-gray-500 mb-5">
          Used to register a pickup location with your logistics provider (e.g. Delhivery) and to
          build the ship-from address on shipping labels.
        </p>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
          {WAREHOUSE_KEYS.map((s) => (
            <Input
              key={s.key}
              label={s.label}
              type={s.type}
              value={values[s.key] ?? ''}
              onChange={(e) => setValues({ ...values, [s.key]: e.target.value })}
            />
          ))}
        </div>
      </Card>
    </div>
  );
}
