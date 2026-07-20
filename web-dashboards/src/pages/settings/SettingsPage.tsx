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
  { key: 'low_stock_threshold', label: 'Low Stock Threshold', type: 'number' },
  { key: 'logistics_provider', label: 'Logistics Provider', type: 'select', options: ['shiprocket', 'delhivery', 'dtdc'] },
  { key: 'whatsapp_provider', label: 'WhatsApp Provider', type: 'select', options: ['interakt', 'twilio'] },
];

export default function SettingsPage() {
  const { data: settings, isLoading } = useQuery({ queryKey: ['settings'], queryFn: adminService.settings });
  const [values, setValues] = useState<Record<string, string>>({});

  useEffect(() => {
    if (settings) {
      const map: Record<string, string> = {};
      if (Array.isArray(settings)) {
        settings.forEach((s: { key: string; value: string }) => { map[s.key] = s.value; });
      } else {
        Object.entries(settings).forEach(([k, v]) => { map[k] = String(v); });
      }
      setValues(map);
    }
  }, [settings]);

  const saveMut = useMutation({
    mutationFn: () => adminService.updateSettings(values),
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
    </div>
  );
}
