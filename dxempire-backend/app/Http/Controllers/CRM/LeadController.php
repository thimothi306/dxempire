<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreLeadRequest;
use App\Http\Requests\CRM\UpdateLeadStageRequest;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function convert(Lead $lead): JsonResponse
    {
        if ($lead->stage === 'won') {
            return $this->error('Lead is already converted.', 422);
        }

        $lead->update(['stage' => 'won', 'last_contact_at' => now()]);

        AuditLog::record(
            auth()->id(),
            'lead.converted',
            Lead::class,
            $lead->id,
            [],
            ['stage' => 'won']
        );

        return $this->success($lead->fresh()->toArray(), 'Lead converted successfully.');
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $request->validate([
            'contact_name'  => ['sometimes', 'string', 'max:200'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:200'],
            'source'        => ['sometimes', 'in:b2b_inquiry,website,referral,walk_in,marketplace'],
            'assigned_to'   => ['nullable', 'exists:users,id'],
            'notes'         => ['nullable', 'string'],
        ]);

        $lead->update($request->only([
            'contact_name', 'phone', 'business_name', 'source', 'assigned_to', 'notes',
        ]));

        return $this->success($lead->fresh()->toArray(), 'Lead updated.');
    }
}
