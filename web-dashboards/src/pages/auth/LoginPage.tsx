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
    <div
      className="min-h-screen flex items-center justify-center px-4 relative overflow-hidden bg-cover bg-center"
      style={{ backgroundImage: "url('/login-bg.png')" }}
    >
      <div className="w-full max-w-sm relative">
        <div className="flex items-center justify-center gap-4 mb-8">
          <div className="bg-white rounded-2xl p-3 shadow-lg flex-shrink-0">
            <img src="/DX_EmpireLogo.png" alt="DXEmpire" className="h-20 w-20 object-contain" />
          </div>
          <div>
            <p className="text-white text-lg font-medium">Admin Panel</p>
          </div>
        </div>

        <div className="bg-primary-50/95 backdrop-blur-sm rounded-2xl shadow-xl border border-primary-100 p-8">
          <h2 className="text-lg font-semibold text-navy-800 mb-6">Sign in</h2>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <Input
              label="Email"
              type="email"
              placeholder="admin@dxempire.com"
              error={errors.email?.message}
              className="bg-white border-primary-200 placeholder:text-primary-300 hover:border-primary-400 hover:bg-primary-50 transition-colors"
              {...register('email')}
            />
            <Input
              label="Password"
              type="password"
              placeholder="••••••••"
              error={errors.password?.message}
              className="bg-white border-primary-200 placeholder:text-primary-300 hover:border-primary-400 hover:bg-primary-50 transition-colors"
              {...register('password')}
            />
            <Button type="submit" loading={loading} className="w-full justify-center mt-2">
              Sign In
            </Button>
          </form>

          {/* Test mode role picker — hidden when connecting to a real backend */}
          {DEMO_MODE && (
            <div className="mt-4 pt-4 border-t border-dashed border-primary-200">
              <p className="text-xs text-primary-400 text-center mb-3">Test mode — login as role</p>
              <div className="grid grid-cols-2 gap-2">
                {TEST_ROLES.map(({ role, label }) => (
                  <button
                    key={role}
                    type="button"
                    onClick={() => loginAsRole(role)}
                    className="text-xs px-3 py-2 rounded-lg border border-primary-200 bg-white text-primary-600 hover:border-accent hover:bg-accent-50 hover:text-accent-600 transition-colors text-left"
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
