<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreSupportTicketRequest;
use App\Http\Traits\ApiResponse;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::with(['creator', 'assignee', 'order'])
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->priority,    fn($q) => $q->where('priority', $request->priority))
            ->when($request->assigned_to, fn($q) => $q->where('assigned_to', $request->assigned_to))
            ->orderByDesc('created_at')
            ->paginate(50);

        return $this->paginated($tickets);
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $ticket = SupportTicket::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'status'     => 'open',
        ]);

        return $this->created($ticket->load('creator', 'order')->toArray());
    }

    public function update(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $request->validate([
            'status'      => ['sometimes', 'in:open,in_progress,resolved,closed'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'priority'    => ['sometimes', 'in:low,medium,high'],
        ]);

        $data = $request->only(['status', 'assigned_to', 'priority']);

        if (isset($data['status']) && $data['status'] === 'resolved' && $supportTicket->status !== 'resolved') {
            $data['resolved_at'] = now();
        }

        $supportTicket->update($data);

        return $this->success($supportTicket->fresh()->load('assignee')->toArray(), 'Ticket updated.');
    }
}
