<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreSupportTicketRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\Exportable;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    use ApiResponse, Exportable;

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

    public function export(Request $request)
    {
        $tickets = SupportTicket::with(['creator', 'assignee', 'order'])
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->priority,    fn($q) => $q->where('priority', $request->priority))
            ->when($request->assigned_to, fn($q) => $q->where('assigned_to', $request->assigned_to))
            ->orderByDesc('created_at')
            ->get();

        $headers = ['Subject', 'Order #', 'Status', 'Priority', 'Created By', 'Assigned To', 'Created'];
        $rows = $tickets->map(fn($t) => [
            $t->subject, $t->order_id ?? '-', $t->status, $t->priority,
            $t->creator?->name ?? '-', $t->assignee?->name ?? '-', $t->created_at->format('Y-m-d'),
        ]);

        $stamp = now()->format('Ymd_His');
        return $request->get('format') === 'pdf'
            ? $this->exportPdf('Support Tickets', $headers, $rows, "support_tickets_{$stamp}.pdf")
            : $this->exportCsv("support_tickets_{$stamp}.csv", $headers, $rows);
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

    public function show(SupportTicket $supportTicket): JsonResponse
    {
        return $this->success(
            $supportTicket->load(['creator', 'assignee', 'order', 'replies.author'])->toArray()
        );
    }

    public function staffOptions(): JsonResponse
    {
        $staff = User::where('is_active', true)
            ->where('role', '!=', 'b2b_partner')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return $this->success($staff);
    }

    public function reply(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $reply = $supportTicket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ]);

        // A reply is staff picking the ticket up — reflect that in status
        // rather than leaving it sitting as "open" once someone's engaged.
        if ($supportTicket->status === 'open') {
            $supportTicket->update(['status' => 'in_progress']);
        }

        return $this->created($reply->load('author')->toArray());
    }
}
