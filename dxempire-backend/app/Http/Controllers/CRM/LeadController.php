<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreLeadRequest;
use App\Http\Requests\CRM\UpdateLeadStageRequest;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Dealer;
use App\Models\Lead;
use App\Models\SalesHierarchy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $leads = Lead::with('assignedUser')
            ->filter($request)
            ->orderByDesc('updated_at')
            ->paginate(50);

        return $this->paginated($leads);
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::create([
            ...$request->validated(),
            'last_contact_at' => now(),
        ]);

        return $this->created($lead->load('assignedUser')->toArray());
    }

    public function show(Lead $lead): JsonResponse
    {
        return $this->success($lead->load('assignedUser')->toArray());
    }

    public function updateStage(UpdateLeadStageRequest $request, Lead $lead): JsonResponse
    {
        $oldStage = $lead->stage;

        $lead->update([
            'stage'           => $request->stage,
            'notes'           => $request->notes ?? $lead->notes,
            'last_contact_at' => now(),
        ]);

        AuditLog::record(
            $request->user()->id,
            'lead.stage_changed',
            Lead::class,
            $lead->id,
            ['stage' => $oldStage],
            ['stage' => $request->stage],
            $request->ip()
        );

        return $this->success($lead->fresh()->toArray(), "Lead moved to {$request->stage}.");
    }

    /**
     * Convert a lead: mark it won and, when it has a phone number, create (or
     * reuse) the resulting Partner account — carrying the lead's owning
     * salesman over to Dealer.assigned_salesman_id so their conversions
     * actually show up in their own sales numbers and incentive payout.
     */
    public function convert(Lead $lead): JsonResponse
    {
        if ($lead->stage === 'won') {
            return $this->error('Lead is already converted.', 422);
        }

        DB::beginTransaction();
        try {
            $lead->update(['stage' => 'won', 'last_contact_at' => now()]);

            $dealer = null;
            if ($lead->phone) {
                $user = User::firstOrCreate(
                    ['phone' => $lead->phone],
                    [
                        'name'      => $lead->contact_name ?? $lead->business_name ?? ('Lead ' . $lead->phone),
                        'email'     => $lead->email,
                        'role'      => 'b2b_partner',
                        'is_active' => true,
                    ]
                );

                $dealer = $user->dealer;
                if (!$dealer) {
                    $dealer = Dealer::create([
                        'user_id'       => $user->id,
                        'business_name' => $lead->business_name ?? $lead->contact_name ?? ('Partner ' . $lead->phone),
                        'kyc_status'    => 'pending',
                        'credit_limit'  => 0,
                    ]);
                    $user->update(['partner_id' => $dealer->id]);
                    $user->assignRole('b2b_partner');
                }

                if (!$dealer->assigned_salesman_id && $lead->assigned_to) {
                    $salesmanNode = SalesHierarchy::where('user_id', $lead->assigned_to)->first();
                    if ($salesmanNode) {
                        $dealer->update(['assigned_salesman_id' => $salesmanNode->id]);
                    }
                }
            }

            AuditLog::record(
                auth()->id(),
                'lead.converted',
                Lead::class,
                $lead->id,
                [],
                ['stage' => 'won', 'dealer_id' => $dealer?->id]
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->success(
            array_merge($lead->fresh()->toArray(), ['dealer' => $dealer?->fresh()]),
            $dealer ? 'Lead converted and partner account created.' : 'Lead converted successfully.'
        );
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $request->validate([
            'contact_name'  => ['sometimes', 'string', 'max:200'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'city'          => ['nullable', 'string', 'max:100'],
            'email'         => ['nullable', 'email', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:200'],
            'source'        => ['sometimes', 'in:b2b_inquiry,website,referral,walk_in,marketplace'],
            'assigned_to'   => ['nullable', 'exists:users,id'],
            'notes'         => ['nullable', 'string'],
        ]);

        $lead->update($request->only([
            'contact_name', 'phone', 'city', 'email', 'business_name', 'source', 'assigned_to', 'notes',
        ]));

        return $this->success($lead->fresh()->toArray(), 'Lead updated.');
    }
}
