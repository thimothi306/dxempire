<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreDealerRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\Exportable;
use App\Models\Dealer;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PartnerCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DealerController extends Controller
{
    use ApiResponse, Exportable;

    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $dealers = Dealer::with('user')
            ->when($request->kyc_status, fn($q) => $q->where('kyc_status', $request->kyc_status))
            ->when($request->state,      fn($q) => $q->where('state', $request->state))
            ->when($request->district,   fn($q) => $q->where('district', $request->district))
            ->when($request->search,     fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('business_name', 'like', '%' . $request->search . '%')
                   ->orWhere('gst_number', 'like', '%' . $request->search . '%');
            }))
            ->orderBy('business_name')
            ->paginate(50);

        return $this->paginated($dealers);
    }

    public function export(Request $request)
    {
        $dealers = Dealer::with('user')
            ->when($request->kyc_status, fn($q) => $q->where('kyc_status', $request->kyc_status))
            ->when($request->state,      fn($q) => $q->where('state', $request->state))
            ->when($request->district,   fn($q) => $q->where('district', $request->district))
            ->orderBy('business_name')
            ->get();

        $headers = ['Business', 'Owner', 'Phone', 'State', 'District', 'KYC Status', 'Credit Limit', 'Credit Used', 'Unique Code'];
        $rows = $dealers->map(fn($d) => [
            $d->business_name, $d->user?->name ?? '-', $d->user?->phone ?? '-', $d->state ?? '-',
            $d->district ?? '-', $d->kyc_status, $d->credit_limit, $d->credit_used, $d->unique_code ?? '-',
        ]);

        $stamp = now()->format('Ymd_His');
        return $request->get('format') === 'pdf'
            ? $this->exportPdf('Business Partners', $headers, $rows, "business_partners_{$stamp}.pdf")
            : $this->exportCsv("business_partners_{$stamp}.csv", $headers, $rows);
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
                'district'      => $request->district,
                'pincode'       => $request->pincode,
                'credit_limit'  => $request->credit_limit ?? 0,
                'price_tier'    => $request->price_tier,
                'kyc_status'    => 'pending',
                'unique_code'   => PartnerCodeGenerator::generate(),
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
            'kyc_documents'    => $this->documentTypes($dealer),
        ]));
    }

    /** Maps each document type to whether it's on file — admin uses this to know what's left to chase. */
    private function documentTypes(Dealer $dealer): array
    {
        return collect($this->documentColumns())->map(fn($column) => (bool) $dealer->{$column})->toArray();
    }

    /** @return array<string,string> document type => Dealer column holding its storage path */
    private function documentColumns(): array
    {
        return [
            'aadhaar_document'          => 'aadhaar_document_path',
            'pan_document'              => 'pan_document_path',
            'passport_photo'            => 'passport_photo_path',
            'education_certificate'     => 'education_certificate_path',
            'bank_passbook_document'    => 'bank_passbook_path',
            'signed_agreement_document' => 'signed_agreement_path',
        ];
    }

    /** Streams one of a partner's uploaded KYC documents for admin review. */
    public function downloadDocument(Dealer $dealer, string $type): Response|JsonResponse
    {
        $columns = $this->documentColumns();

        if (!isset($columns[$type])) {
            return $this->error('Unknown document type.', 404);
        }

        $path = $dealer->{$columns[$type]};

        if (!$path || !Storage::exists($path)) {
            return $this->error('Document not found. It may not have been uploaded yet.', 404);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return response(Storage::get($path), 200, [
            'Content-Type'        => Storage::mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => "inline; filename=\"{$dealer->business_name}_{$type}.{$extension}\"",
        ]);
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

    /** Enable/disable a partner's own login — does not touch KYC or credit. */
    public function deactivate(Dealer $dealer): JsonResponse
    {
        $dealer->loadMissing('user');
        $dealer->user?->update(['is_active' => false]);

        return $this->success($dealer->fresh('user')->toArray(), 'Partner account deactivated.');
    }

    public function activate(Dealer $dealer): JsonResponse
    {
        $dealer->loadMissing('user');
        $dealer->user?->update(['is_active' => true]);

        return $this->success($dealer->fresh('user')->toArray(), 'Partner account activated.');
    }

    /**
     * Hard delete — only for partners with zero order history, so no
     * financial/order record ever loses its dealer reference. Anyone with
     * real order history should be deactivated instead (see above), never
     * deleted.
     */
    public function destroy(Dealer $dealer): JsonResponse
    {
        if ($dealer->orders()->exists()) {
            return $this->error(
                'Cannot delete a partner with order history. Deactivate their account instead to preserve order records.',
                422
            );
        }

        $dealer->delete();

        return $this->success(null, 'Partner deleted.');
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

        $totalBilled = $dealer->orders()->where('payment_status', '!=', 'refunded')->sum('total_amount');
        $totalPaid   = $dealer->orders()->where('payment_status', 'paid')->sum('total_amount');

        $summary = [
            'total_orders'   => $dealer->orders()->count(),
            'total_billed'   => $totalBilled,
            'total_paid'     => $totalPaid,
            'outstanding'    => max(0, $totalBilled - $totalPaid),
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
