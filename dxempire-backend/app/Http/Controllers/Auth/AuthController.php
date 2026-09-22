<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\SendOtpJob;
use App\Models\Dealer;
use App\Models\OtpCode;
use App\Models\PushToken;
use App\Models\SalesHierarchy;
use App\Models\User;
use App\Services\PartnerCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Single login for every role — staff, warehouse, and business partners
     * all sign in here with email + password. Partners used to have a
     * separate email-or-phone login on partner.dxempire.in; that subdomain
     * is being retired, so partner accounts now log in here too, email only.
     */
    public function adminLogin(AdminLoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            return $this->error('Invalid email or password.', 401);
        }

        if (!$user->is_active) {
            return $this->error('Your account has been deactivated. Contact support.', 403);
        }

        $user->update(['last_login_at' => now()]);

        $ability = $user->role === 'b2b_partner' ? ['partner'] : ['*'];
        $token = $user->createToken('admin_token_' . $user->role, $ability, now()->addDays(30));

        $userPayload = [
            'id'          => $user->id,
            'name'        => $user->name,
            'phone'       => $user->phone,
            'email'       => $user->email,
            'role'        => $user->role,
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];

        if ($user->role === 'b2b_partner') {
            $user->loadMissing('dealer');
            $dealer = $user->dealer;
            $userPayload = array_merge($userPayload, [
                'business_name' => $dealer?->business_name,
                'kyc_status'    => $dealer?->kyc_status,
                'gst_number'    => $dealer?->gst_number,
                'state'         => $dealer?->state,
                'district'      => $dealer?->district,
                'pincode'       => $dealer?->pincode,
                'price_tier'    => $dealer?->price_tier,
                'has_dealer'    => (bool) $dealer,
            ]);
        }

        return $this->success([
            'token' => $token->plainTextToken,
            'user'  => $userPayload,
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

    /**
     * Second step of partner self-registration — called with the token
     * verifyOtp() already issued. Turns the bare User created there into a
     * real partner by creating its Dealer profile. kyc_status always starts
     * 'pending' regardless of input; Dealer::canPlaceOrder() already blocks
     * ordering until an admin verifies it, so no separate gate is needed
     * here — creating the Dealer is what turns that gate on.
     */
    public function completeRegistration(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->dealer) {
            return $this->error('This account is already registered.', 422);
        }

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:200'],
            'business_name' => ['required', 'string', 'max:200'],
            'email'         => ['required', 'email', Rule::unique('users', 'email')],
            'password'      => ['required', 'string', 'min:8'],
            'gst_number'    => ['nullable', 'string', 'max:20'],
            // The unique_code of the STAFF MEMBER (salesman) this partner is
            // registering under — e.g. SM001, DM001. Required for self-registration.
            // This assigns the partner to that salesman (assigned_salesman_id) for
            // downline/commission tracking, same as an admin doing it manually via
            // Hierarchy > Assign Dealer. Admin-created dealers (CRM screen) go
            // through a separate, unrelated flow and are not subject to this.
            'unique_code'   => ['required', 'string', 'max:10'],

            // Address Details — optional, exactly matching the client's form (no
            // asterisk on any of these fields there, State and Pin Code included).
            'village_street' => ['nullable', 'string', 'max:150'],
            'post_office'    => ['nullable', 'string', 'max:100'],
            'police_station' => ['nullable', 'string', 'max:100'],
            'district'       => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:100'],
            'pincode'        => ['nullable', 'string', 'max:10'],

            // Bank Account Details (Payout & Settlement) — no longer collected at
            // registration per the client's later decision; the app doesn't send
            // these at all now. Kept nullable (not dropped) so they still validate
            // correctly if ever sent later (e.g. a future "complete KYC" step).
            // confirm_account_number is a frontend-only double-entry check (must
            // match bank_account_number when both are present) and is never stored.
            'bank_account_number'    => ['nullable', 'string', 'max:30'],
            'confirm_account_number' => ['nullable', 'same:bank_account_number'],
            'account_holder_name'    => ['nullable', 'string', 'max:150'],
            'bank_name'              => ['nullable', 'string', 'max:150'],
            'ifsc_code'              => ['nullable', 'string', 'max:15'],
        ]);

        $salesmanUser = User::where('unique_code', strtoupper($data['unique_code']))->first();

        if (!$salesmanUser) {
            return $this->error('Invalid unique code.', 422);
        }

        $salesmanNode = SalesHierarchy::where('user_id', $salesmanUser->id)->first();

        if (!$salesmanNode) {
            return $this->error('This code is not linked to an active salesman. Contact your admin.', 422);
        }

        DB::beginTransaction();
        try {
            $user->update([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $dealer = Dealer::create([
                'user_id'               => $user->id,
                'business_name'         => $data['business_name'],
                'gst_number'            => $data['gst_number'] ?? null,
                'kyc_status'            => 'pending',
                'state'                 => $data['state'] ?? null,
                'district'              => $data['district'] ?? null,
                'pincode'               => $data['pincode'] ?? null,
                'village_street'        => $data['village_street'] ?? null,
                'post_office'           => $data['post_office'] ?? null,
                'police_station'        => $data['police_station'] ?? null,
                'bank_account_number'   => $data['bank_account_number'] ?? null,
                'account_holder_name'   => $data['account_holder_name'] ?? null,
                'bank_name'             => $data['bank_name'] ?? null,
                'ifsc_code'             => isset($data['ifsc_code']) ? strtoupper($data['ifsc_code']) : null,
                'unique_code'           => PartnerCodeGenerator::generate(),
                'assigned_salesman_id'  => $salesmanNode->id,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->success([
            'business_name'     => $dealer->business_name,
            'kyc_status'        => $dealer->kyc_status,
            'unique_code'       => $dealer->unique_code,
            'assigned_salesman' => $salesmanNode->name,
        ], 'Registration complete. Your account is pending KYC approval.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $payload = [
            'id'          => $user->id,
            'name'        => $user->name,
            'phone'       => $user->phone,
            'email'       => $user->email,
            'role'        => $user->role,
            'partner_id'  => $user->partner_id,
            'is_active'   => $user->is_active,
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];

        if ($user->role === 'b2b_partner') {
            $user->loadMissing('dealer.salesman');
            $dealer = $user->dealer;
            $payload = array_merge($payload, [
                'kyc_status'          => $dealer?->kyc_status,
                'business_name'       => $dealer?->business_name,
                'gst_number'          => $dealer?->gst_number,
                'state'               => $dealer?->state,
                'district'            => $dealer?->district,
                'pincode'             => $dealer?->pincode,
                'village_street'      => $dealer?->village_street,
                'post_office'         => $dealer?->post_office,
                'police_station'      => $dealer?->police_station,
                'account_holder_name' => $dealer?->account_holder_name,
                'bank_name'           => $dealer?->bank_name,
                'ifsc_code'           => $dealer?->ifsc_code,
                'bank_account_last4'  => $dealer?->bank_account_number ? substr($dealer->bank_account_number, -4) : null,
                'price_tier'          => $dealer?->price_tier,
                'unique_code'         => $dealer?->unique_code,
                'assigned_salesman'   => $dealer?->salesman?->name,
                'has_dealer'          => (bool) $dealer,
                'kyc_documents'       => $dealer ? $this->documentChecklist($dealer) : null,
            ]);
        }

        return $this->success($payload);
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

    /**
     * KYC document uploads for a partner — deliberately separate from
     * completeRegistration() and callable any time after: a partner can
     * submit the core form first (per the client's form, only the bank
     * details are mandatory there) and add documents whenever they have
     * them, one at a time or all together. Each field is independent —
     * uploading just the Aadhaar photo today doesn't require the others.
     */
    public function uploadKycDocuments(Request $request): JsonResponse
    {
        $user = $request->user();
        $dealer = $user->dealer;

        if (!$dealer) {
            return $this->error('Complete your registration before uploading documents.', 422);
        }

        $data = $request->validate([
            'aadhaar_number'          => ['nullable', 'string', 'max:20'],
            'pan_number'              => ['nullable', 'string', 'max:15'],
            'aadhaar_document'        => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'pan_document'            => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'passport_photo'          => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'education_certificate'   => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'bank_passbook_document'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'signed_agreement_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $update = [];
        if (array_key_exists('aadhaar_number', $data)) $update['aadhaar_number'] = $data['aadhaar_number'];
        if (array_key_exists('pan_number', $data)) $update['pan_number'] = $data['pan_number'];

        $fileMap = [
            'aadhaar_document'          => ['column' => 'aadhaar_document_path', 'dir' => 'aadhaar'],
            'pan_document'              => ['column' => 'pan_document_path', 'dir' => 'pan'],
            'passport_photo'            => ['column' => 'passport_photo_path', 'dir' => 'photo'],
            'education_certificate'     => ['column' => 'education_certificate_path', 'dir' => 'education'],
            'bank_passbook_document'    => ['column' => 'bank_passbook_path', 'dir' => 'bank_passbook'],
            'signed_agreement_document' => ['column' => 'signed_agreement_path', 'dir' => 'agreement'],
        ];

        foreach ($fileMap as $field => $meta) {
            if (!$request->hasFile($field)) {
                continue;
            }

            $oldPath = $dealer->{$meta['column']};
            if ($oldPath && Storage::exists($oldPath)) {
                Storage::delete($oldPath);
            }

            $update[$meta['column']] = $request->file($field)->store("kyc/dealers/{$dealer->id}/{$meta['dir']}");
        }

        if (empty($update)) {
            return $this->error('No document or detail provided to save.', 422);
        }

        $dealer->update($update);

        return $this->success([
            'kyc_documents' => $this->documentChecklist($dealer->fresh()),
        ], 'Document(s) saved.');
    }

    /** Which KYC documents/numbers a dealer has on file — used by me() and after each upload. */
    private function documentChecklist(Dealer $dealer): array
    {
        return [
            'aadhaar_number'           => (bool) $dealer->aadhaar_number,
            'pan_number'               => (bool) $dealer->pan_number,
            'aadhaar_document'         => (bool) $dealer->aadhaar_document_path,
            'pan_document'             => (bool) $dealer->pan_document_path,
            'passport_photo'           => (bool) $dealer->passport_photo_path,
            'education_certificate'    => (bool) $dealer->education_certificate_path,
            'bank_passbook_document'   => (bool) $dealer->bank_passbook_path,
            'signed_agreement_document'=> (bool) $dealer->signed_agreement_path,
        ];
    }
}
