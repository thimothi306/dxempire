import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import toast from 'react-hot-toast';
import { authService } from '../../services';
import { DEMO_MODE } from '../../services/demoData';
import { useAuthStore } from '../../stores/authStore';
import { Button, Input } from '../../components/ui';
import type { Role } from '../../types';

const schema = z.object({
  email: z.string().email('Enter a valid email'),
  password: z.string().min(6, 'Password must be at least 6 characters'),
});
type FormData = z.infer<typeof schema>;

const TEST_ROLES: { role: Role; label: string }[] = [
  { role: 'super_admin', label: 'Super Admin' },
  { role: 'sales', label: 'Sales' },
  { role: 'warehouse_staff', label: 'Warehouse' },
  { role: 'qc_engineer', label: 'QC Engineer' },
  { role: 'accounts', label: 'Accounts' },
  { role: 'hr_manager', label: 'HR Manager' },
  { role: 'logistics', label: 'Logistics' },
];

export default function LoginPage() {
  const navigate = useNavigate();
  const { setAuth } = useAuthStore();
  const [loading, setLoading] = useState(false);

  const { register, handleSubmit, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
  });

  const onSubmit = async (data: FormData) => {
    setLoading(true);
    try {
      const res = await authService.login(data.email, data.password);
      setAuth(res.token, res.user);
      navigate('/dashboard');
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Invalid credentials';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  };

  const loginAsRole = (role: Role) => {
    setAuth('test-token-bypass', {
      id: 1,
      name: `Test ${role.replace('_', ' ')}`,
      email: `${role}@dxempire.com`,
      phone: '',
      role,
      is_active: true,
      partner_id: null,
      kyc_status: null,
      permissions: [],
    });
    navigate('/dashboard');
  };

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
      <div className="w-full max-w-sm">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900">
            DX<span className="text-primary">EMPIRE</span>
          </h1>
          <p className="text-gray-500 text-sm mt-1">Admin Panel</p>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
          <h2 className="text-lg font-semibold text-gray-900 mb-6">Sign in</h2>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <Input
              label="Email"
              type="email"
              placeholder="admin@dxempire.com"
              error={errors.email?.message}
              {...register('email')}
            />
            <Input
              label="Password"
              type="password"
              placeholder="••••••••"
              error={errors.password?.message}
              {...register('password')}
            />
            <Button type="submit" loading={loading} className="w-full justify-center mt-2">
              Sign In
            </Button>
          </form>

          {/* Test mode role picker — hidden when connecting to a real backend */}
          {DEMO_MODE && (
            <div className="mt-4 pt-4 border-t border-dashed border-gray-200">
              <p className="text-xs text-gray-400 text-center mb-3">Test mode — login as role</p>
              <div className="grid grid-cols-2 gap-2">
                {TEST_ROLES.map(({ role, label }) => (
                  <button
                    key={role}
                    type="button"
                    onClick={() => loginAsRole(role)}
                    className="text-xs px-3 py-2 rounded-lg border border-gray-200 text-gray-500 hover:border-primary hover:text-primary transition-colors text-left"
                  >
                    {label}
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
