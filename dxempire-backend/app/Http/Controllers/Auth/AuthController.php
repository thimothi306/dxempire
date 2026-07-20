<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\SendOtpJob;
use App\Models\OtpCode;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function adminLogin(AdminLoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)
            ->where('role', '!=', 'b2b_partner')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Invalid email or password.', 401);
        }

        if (!$user->is_active) {
            return $this->error('Your account has been deactivated. Contact support.', 403);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('admin_token_' . $user->role, ['*'], now()->addDays(30));

        return $this->success([
            'token' => $token->plainTextToken,
            'user'  => [
                'id'          => $user->id,
                'name'        => $user->name,
                'phone'       => $user->phone,
                'email'       => $user->email,
                'role'        => $user->role,
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ], 'Login successful');
    }

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $phone = $request->phone;

        // Delete previous unused OTPs for this phone
        OtpCode::where('phone', $phone)->whereNull('verified_at')->delete();

        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'phone'      => $phone,
            'code'       => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        SendOtpJob::dispatch($phone, $otp);

        return $this->success(null, 'OTP sent successfully');
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->phone;
        $code  = $request->code;

        $otpRecord = OtpCode::where('phone', $phone)
            ->whereNull('verified_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$otpRecord) {
            return $this->error('No OTP found for this number. Please request a new OTP.', 401);
        }

        if ($otpRecord->isExpired()) {
            return $this->error('OTP has expired. Please request a new one.', 401);
        }

        if (!Hash::check($code, $otpRecord->code)) {
            return $this->error('Invalid OTP. Please try again.', 401);
        }

        $otpRecord->update(['verified_at' => now()]);

        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['name' => 'User ' . $phone, 'role' => 'b2b_partner', 'is_active' => true]
        );

        if (!$user->is_active) {
            return $this->error('Your account has been deactivated. Contact support.', 403);
        }

        $user->update(['last_login_at' => now()]);

        // Store Expo push token if provided
        if ($request->filled('expo_push_token')) {
            PushToken::updateOrCreate(
                ['user_id' => $user->id, 'token' => $request->expo_push_token],
                ['device_type' => $request->device_type ?? 'android']
            );
        }

        $token = $user->createToken('auth_token_' . $user->role, ['*'], now()->addDays(30));

        $kycStatus = null;
        if ($user->role === 'b2b_partner') {
            $user->loadMissing('dealer');
            $kycStatus = $user->dealer?->kyc_status;
        }

        return $this->success([
            'token' => $token->plainTextToken,
            'user'  => [
                'id'          => $user->id,
                'name'        => $user->name,
                'phone'       => $user->phone,
                'role'        => $user->role,
                'partner_id'  => $user->partner_id,
                'kyc_status'  => $kycStatus,
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ], 'Login successful');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $kycStatus = null;
        if ($user->role === 'b2b_partner') {
            $user->loadMissing('dealer');
            $kycStatus = $user->dealer?->kyc_status;
        }

        return $this->success([
            'id'          => $user->id,
            'name'        => $user->name,
            'phone'       => $user->phone,
            'email'       => $user->email,
            'role'        => $user->role,
            'partner_id'  => $user->partner_id,
            'kyc_status'  => $kycStatus,
            'is_active'   => $user->is_active,
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $token = $user->createToken('auth_token_' . $user->role, ['*'], now()->addDays(30));

        return $this->success(['token' => $token->plainTextToken], 'Token refreshed.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully');
    }
}
