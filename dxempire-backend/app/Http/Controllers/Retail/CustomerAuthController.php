<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Customer;
use App\Models\OtpCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CustomerAuthController extends Controller
{
    use ApiResponse;

    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'digits_between:10,15']]);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put('retail_otp_' . $request->phone, $otp, now()->addMinutes(10));

        // In production, integrate SMS provider here (same as B2B flow)
        \Illuminate\Support\Facades\Log::info("Retail OTP for {$request->phone}: {$otp}");

        return $this->success(['otp' => $otp], 'OTP sent successfully.');
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'digits_between:10,15'],
            'otp'   => ['required', 'digits:6'],
        ]);

        $cached = Cache::get('retail_otp_' . $request->phone);

        if (!$cached || $cached !== $request->otp) {
            return $this->error('Invalid or expired OTP.', 401);
        }

        Cache::forget('retail_otp_' . $request->phone);

        $customer = Customer::firstOrCreate(
            ['phone' => $request->phone],
            ['name' => 'Customer ' . $request->phone, 'is_active' => true]
        );

        if (!$customer->is_active) {
            return $this->error('Your account has been deactivated.', 403);
        }

        $token = \Illuminate\Support\Str::random(60);
        Cache::put('retail_token_' . $token, $customer->id, now()->addDays(30));

        return $this->success([
            'token'    => $token,
            'customer' => $customer,
        ], 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($request->customer);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name'    => ['sometimes', 'string', 'max:100'],
            'email'   => ['sometimes', 'nullable', 'email'],
            'address' => ['sometimes', 'nullable', 'string'],
            'city'    => ['sometimes', 'nullable', 'string', 'max:60'],
            'state'   => ['sometimes', 'nullable', 'string', 'max:60'],
            'pincode' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $request->customer->update($request->only(['name', 'email', 'address', 'city', 'state', 'pincode']));

        return $this->success($request->customer->fresh(), 'Profile updated.');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if ($token) {
            Cache::forget('retail_token_' . $token);
        }

        return $this->success(null, 'Logged out.');
    }
}
