<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreLeadRequest;
use App\Http\Requests\CRM\UpdateLeadStageRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\Exportable;
use App\Models\AuditLog;
use App\Models\Dealer;
use App\Models\Lead;
use App\Models\SalesHierarchy;
use App\Models\User;
use App\Services\SalesVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    use ApiResponse, Exportable;

    public function __construct(private SalesVisibilityService $visibility) {}

    /**
     * A Salesman only ever sees their own leads; a District/Area/State
     * Manager or CEO sees their own plus their whole subordinate tree's.
     * Roles outside the sales hierarchy (accounts, super_admin) are
     * unrestricted, same as before.
     */
    public function index(Request $request): JsonResponse
    {
        $visibleIds = $this->visibility->visibleUserIds($request->user());

        // Unassigned leads (assigned_to IS NULL) — every website contact-form
        // submission lands this way — are shown to anyone with crm.edit
        // regardless of hierarchy scope. A NULL never matches whereIn(), so
        // without this an unclaimed lead was invisible to every scoped role
        // (Salesman, District/Area/State Manager) and only ever showed up
        // for super_admin/accounts — indistinguishable from the lead never
        // having been stored at all, from a Salesman's point of view.
        $leads = Lead::with('assignedUser')
            ->when($visibleIds !== null, fn($q) => $q->where(fn($q2) => $q2->whereIn('assigned_to', $visibleIds)->orWhereNull('assigned_to')))
            ->filter($request)
            ->orderByDesc('updated_at')
            ->paginate(50);

        return $this->paginated($leads);
    }

    public function export(Request $request)
    {
        $visibleIds = $this->visibility->visibleUserIds($request->user());

        $leads = Lead::with('assignedUser')
            ->when($visibleIds !== null, fn($q) => $q->whereIn('assigned_to', $visibleIds))
            ->filter($request)
            ->orderByDesc('updated_at')
            ->get();

        $headers = ['Contact Name', 'Business Name', 'Phone', 'Email', 'City', 'Source', 'Stage', 'Assigned To', 'Created'];
        $rows = $leads->map(fn($l) => [
            $l->contact_name, $l->business_name ?? '-', $l->phone ?? '-', $l->email ?? '-',
            $l->city ?? '-', $l->source, $l->stage, $l->assignedUser?->name ?? '-', $l->created_at->format('Y-m-d'),
        ]);

        $stamp = now()->format('Ymd_His');
        return $request->get('format') === 'pdf'
            ? $this->exportPdf('Leads', $headers, $rows, "leads_{$stamp}.pdf")
            : $this->exportCsv("leads_{$stamp}.csv", $headers, $rows);
    }

    /**
     * Public — hit directly by the marketing site's contact form, no auth.
     * Deliberately its own narrow method rather than reusing store(): only
     * the four real contact-form fields are accepted, source and stage are
     * forced server-side, so a public caller can never set assigned_to or
     * anything else store() otherwise allows a logged-in staff member to.
     */
    public function publicContact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:200'],
            'email'         => ['required', 'email', 'max:150'],
            'phone'         => ['required', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:200'],
            'city'          => ['nullable', 'string', 'max:100'],
            'subject'       => ['nullable', 'string', 'max:200'],
            'message'       => ['required', 'string', 'max:5000'],
        ]);

        $lead = Lead::create([
            'source'        => 'website',
            'contact_name'  => $data['name'],
            'email'         => $data['email'],
            'phone'         => $data['phone'],
            'business_name' => $data['business_name'] ?? null,
            'city'          => $data['city'] ?? null,
            'notes'         => trim(($data['subject'] ?? '') . "\n\n" . $data['message']),
            'stage'         => 'new',
        ]);

        return $this->created(['id' => $lead->id], 'Message received — our team will reach out soon.');
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
