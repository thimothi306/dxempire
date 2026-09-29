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

// Three-step self-service recovery: identify the account (email, phone, or
// staff unique_code all work — the server always texts the OTP to whatever
// phone is on that account) -> verify the OTP + pick a new password -> done.
// No separate "reset link" email step exists in this project; this reuses
// the same SMS OTP system the mobile app already runs on.
function ForgotPasswordFlow({ onBack }: { onBack: () => void }) {
  const [step, setStep] = useState<'request' | 'reset' | 'done'>('request');
  const [identifier, setIdentifier] = useState('');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const sendOtp = async () => {
    if (!identifier.trim()) { toast.error('Enter your email, phone, or staff code first.'); return; }
    setLoading(true);
    try {
      await authService.forgotPassword(identifier.trim());
      toast.success('If an account matches, an OTP has been sent to the mobile number on file.');
      setStep('reset');
    } catch {
      toast.error('Something went wrong. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const resetPassword = async () => {
    if (password !== confirmPassword) { toast.error('Passwords do not match.'); return; }
    if (password.length < 8) { toast.error('Password must be at least 8 characters.'); return; }
    setLoading(true);
    try {
      await authService.resetPassword(identifier.trim(), code.trim(), password, confirmPassword);
      setStep('done');
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Failed to reset password.');
    } finally {
      setLoading(false);
    }
  };

  if (step === 'done') {
    return (
      <div className="space-y-4 text-center">
        <p className="text-sm text-navy-700">Password reset successfully. You can now sign in with your new password.</p>
        <Button onClick={onBack} className="w-full justify-center">Back to Sign In</Button>
      </div>
    );
  }

  if (step === 'reset') {
    return (
      <div className="space-y-4">
        <p className="text-xs text-primary-500">Enter the OTP sent to the mobile number on your account, and your new password.</p>
        <Input label="OTP" placeholder="6-digit code" value={code} onChange={(e) => setCode(e.target.value)}
          className="bg-white border-primary-200" />
        <Input label="New Password" type="password" placeholder="••••••••" value={password} onChange={(e) => setPassword(e.target.value)}
          className="bg-white border-primary-200" />
        <Input label="Confirm New Password" type="password" placeholder="••••••••" value={confirmPassword} onChange={(e) => setConfirmPassword(e.target.value)}
          className="bg-white border-primary-200" />
        <Button onClick={resetPassword} loading={loading} className="w-full justify-center">Reset Password</Button>
        <button type="button" onClick={() => setStep('request')} className="text-xs text-primary-500 hover:text-primary-700 w-full text-center">
          Didn't get an OTP? Go back
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <p className="text-xs text-primary-500">Enter your email, phone number, or staff code — we'll text an OTP to the mobile number on your account.</p>
      <Input label="Email / Phone / Staff Code" placeholder="you@dxempire.com" value={identifier} onChange={(e) => setIdentifier(e.target.value)}
        className="bg-white border-primary-200" />
      <Button onClick={sendOtp} loading={loading} className="w-full justify-center">Send OTP</Button>
      <button type="button" onClick={onBack} className="text-xs text-primary-500 hover:text-primary-700 w-full text-center">
        Back to Sign In
      </button>
    </div>
  );
}

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
  const [showForgotPassword, setShowForgotPassword] = useState(false);

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
      className="min-h-screen flex items-center justify-center md:justify-end px-4 md:pr-[8%] relative overflow-hidden bg-cover bg-[position:30%_center] md:bg-center"
      style={{ backgroundImage: "url('/login-bg.png')" }}
    >
      <div className="w-full max-w-sm relative">
        <div className="flex items-center justify-center mb-8">
          <p className="text-white text-3xl font-bold">Admin Panel</p>
        </div>

        <div className="bg-primary-50/95 backdrop-blur-sm rounded-2xl shadow-xl border border-primary-100 p-8">
          {showForgotPassword ? (
            <>
              <h2 className="text-lg font-semibold text-navy-800 mb-6">Reset Password</h2>
              <ForgotPasswordFlow onBack={() => setShowForgotPassword(false)} />
            </>
          ) : (
            <>
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
                <button
                  type="button"
                  onClick={() => setShowForgotPassword(true)}
                  className="text-xs text-primary-500 hover:text-primary-700 w-full text-center"
                >
                  Forgot Password?
                </button>
              </form>
            </>
          )}

          {/* Test mode role picker — hidden when connecting to a real backend */}
          {!showForgotPassword && DEMO_MODE && (
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
