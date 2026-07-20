<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreDealerRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Dealer;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealerController extends Controller
{
    use ApiResponse;

    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $dealers = Dealer::with('user')
            ->when($request->kyc_status, fn($q) => $q->where('kyc_status', $request->kyc_status))
            ->when($request->state,      fn($q) => $q->where('state', $request->state))
            ->when($request->search,     fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('business_name', 'like', '%' . $request->search . '%')
                   ->orWhere('gst_number', 'like', '%' . $request->search . '%');
            }))
            ->orderBy('business_name')
            ->paginate(50);

        return $this->paginated($dealers);
    }

    public function store(StoreDealerRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Create or find user by phone
            $user = User::firstOrCreate(
                ['phone' => $request->phone],
                [
                    'name'      => $request->name,
                    'email'     => $request->email,
                    'role'      => 'b2b_partner',
                    'is_active' => true,
                ]
            );

            if ($user->dealer) {
                DB::rollBack();
                return $this->error('A dealer account already exists for this phone number.', 422);
            }

            $dealer = Dealer::create([
                'user_id'       => $user->id,
                'business_name' => $request->business_name,
                'gst_number'    => $request->gst_number,
                'state'         => $request->state,
                'pincode'       => $request->pincode,
                'credit_limit'  => $request->credit_limit ?? 0,
                'price_tier'    => $request->price_tier,
                'kyc_status'    => 'pending',
            ]);

            $user->update(['partner_id' => $dealer->id]);
            $user->assignRole('b2b_partner');

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Dealer registration failed: ' . $e->getMessage(), 500);
        }

        return $this->created($dealer->load('user')->toArray());
    }

    public function show(Dealer $dealer): JsonResponse
    {
        $dealer->load('user');
        $dealer->loadCount('orders');

        return $this->success(array_merge($dealer->toArray(), [
            'available_credit' => $dealer->availableCredit(),
        ]));
    }

    public function updateKyc(Request $request, Dealer $dealer): JsonResponse
    {
        $request->validate([
            'kyc_status' => ['required', 'in:verified,approved,rejected'],
            'reason'     => ['nullable', 'string'],
        ]);

        // frontend sends 'approved' — normalise to 'verified'
        if ($request->kyc_status === 'approved') {
            $request->merge(['kyc_status' => 'verified']);
        }

        $previous = $dealer->kyc_status;
        $dealer->update(['kyc_status' => $request->kyc_status]);

        if ($request->kyc_status === 'verified' && $previous !== 'verified') {
            $dealer->load('user.pushTokens');
            if ($dealer->user) {
                $this->notifications->notify(
                    $dealer->user,
                    'order_update',
                    'KYC Approved',
                    'Your KYC has been verified. You can now place orders.',
                    []
                );
            }
        }

        return $this->success(
            $dealer->fresh()->toArray(),
            'KYC status updated to ' . $request->kyc_status . '.'
        );
    }

    public function updateCredit(Request $request, Dealer $dealer): JsonResponse
    {
        $request->validate([
            'credit_limit' => ['required', 'numeric', 'min:0'],
        ]);

        $dealer->update(['credit_limit' => $request->credit_limit]);

        return $this->success([
            'credit_limit'     => $dealer->fresh()->credit_limit,
            'credit_used'      => $dealer->credit_used,
            'available_credit' => $dealer->fresh()->availableCredit(),
        ], 'Credit limit updated.');
    }

    public function ledger(Request $request, Dealer $dealer): JsonResponse
    {
        $orders = $dealer->orders()
            ->with(['payments', 'invoices'])
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to,   fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->paginate(50);

        $summary = [
            'total_orders'   => $dealer->orders()->count(),
            'total_billed'   => $dealer->orders()->where('payment_status', '!=', 'refunded')->sum('total_amount'),
            'total_paid'     => $dealer->orders()->where('payment_status', 'paid')->sum('total_amount'),
            'credit_limit'   => $dealer->credit_limit,
            'credit_used'    => $dealer->credit_used,
            'available_credit' => $dealer->availableCredit(),
        ];

        return $this->success([
            'summary' => $summary,
            'transactions' => $orders->toArray(),
        ]);
    }
}
