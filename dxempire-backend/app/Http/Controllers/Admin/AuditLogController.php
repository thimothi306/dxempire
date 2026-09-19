<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\Exportable;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse, Exportable;

    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::with('user:id,name,phone')
            ->when($request->user_id,  fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->action,   fn($q) => $q->where('action', 'like', "%{$request->action}%"))
            ->when($request->model,    fn($q) => $q->where('model_type', 'like', "%{$request->model}%"))
            ->when($request->from,     fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to,       fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));

        return $this->paginated($logs);
    }

    public function export(Request $request)
    {
        $logs = AuditLog::with('user:id,name,phone')
            ->when($request->user_id,  fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->action,   fn($q) => $q->where('action', 'like', "%{$request->action}%"))
            ->when($request->model,    fn($q) => $q->where('model_type', 'like', "%{$request->model}%"))
            ->when($request->from,     fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to,       fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->get();

        $headers = ['User', 'Action', 'Model', 'Model ID', 'When'];
        $rows = $logs->map(fn($l) => [
            $l->user?->name ?? 'System', $l->action, class_basename($l->model_type ?? ''), $l->model_id ?? '-',
            $l->created_at->format('Y-m-d H:i'),
        ]);

        $stamp = now()->format('Ymd_His');
        return $request->get('format') === 'pdf'
            ? $this->exportPdf('Audit Logs', $headers, $rows, "audit_logs_{$stamp}.pdf")
            : $this->exportCsv("audit_logs_{$stamp}.csv", $headers, $rows);
    }
}
