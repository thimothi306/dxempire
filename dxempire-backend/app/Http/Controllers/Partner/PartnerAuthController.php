<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Partner Web Portal — authentication.
 * Separate from staff/admin login. Only b2b_partner accounts may log in here.
 * Login = email OR phone + password (no OTP/SMS dependency).
 */
class PartnerAuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login'    => ['required', 'string'],   // email or phone
            'password' => ['required', 'string'],
        ]);

        $user = User::where('role', 'b2b_partner')
            ->where(function ($q) use ($data) {
                $q->where('email', $data['login'])
                  ->orWhere('phone', $data['login']);
            })
            ->first();

        if (!$user || !$user->password || !Hash::check($data['password'], $user->password)) {
            return $this->error('Invalid login or password.', 401);
        }

        if (!$user->is_active) {
            return $this->error('Your account has been deactivated. Please contact your sales representative.', 403);
        }

        $user->loadMissing('dealer');
        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('partner_portal', ['partner'], now()->addDays(30));

        return $this->success([
            'token' => $token->plainTextToken,
            'partner' => $this->partnerPayload($user),
        ], 'Login successful');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('dealer');
        return $this->success($this->partnerPayload($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Logged out successfully');
    }

    private function partnerPayload(User $user): array
    {
        $dealer = $user->dealer;
        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'business_name' => $dealer?->business_name,
            'kyc_status'    => $dealer?->kyc_status,
            'gst_number'    => $dealer?->gst_number,
            'state'         => $dealer?->state,
            'pincode'       => $dealer?->pincode,
            'price_tier'    => $dealer?->price_tier,
            'has_dealer'    => (bool) $dealer,
        ];
    }
}
