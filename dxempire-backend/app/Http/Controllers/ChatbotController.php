<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponse;
use App\Models\Attendance;
use App\Models\Dealer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\PayrollRun;
use App\Models\SalesHierarchy;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    use ApiResponse;

    /**
     * Mobile app / partner app chatbot. Always fetches the authenticated
     * user's own real account data first and hands it to Gemini as
     * read-only context — the model never queries anything itself and
     * never sees another user's data, so a personalized answer ("what's
     * my balance") can never be a guess or leak someone else's numbers.
     */
    public function ask(Request $request, GeminiService $gemini): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:300'],
        ]);

        $user = $request->user();
        $context = $user->role === 'b2b_partner'
            ? $this->dealerContext($user)
            : $this->staffContext($user);

        $prompt = $this->buildPrompt($context, $data['question']);
        $answer = $gemini->chat(
            [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            [],
            null,
            config('services.gemini.chat_model')
        );

        $text = $answer['parts'][0]['text'] ?? null;

        return $this->success([
            'answer' => $text ?? "I'm not able to answer that right now — please try again in a moment.",
        ]);
    }

    private function dealerContext($user): string
    {
        $dealer = Dealer::where('user_id', $user->id)->first();

        if (!$dealer) {
            return "This user has no dealer account on file.";
        }

        $lastOrder = Order::where('dealer_id', $dealer->id)->latest()->first();

        $lines = [
            "Business name: {$dealer->business_name}",
            "Credit limit: ₹" . number_format((float) $dealer->credit_limit, 2),
            "Credit used: ₹" . number_format((float) $dealer->credit_used, 2),
            "Credit available: ₹" . number_format((float) ($dealer->credit_limit - $dealer->credit_used), 2),
            "KYC status: {$dealer->kyc_status}",
        ];

        $lines[] = $lastOrder
            ? "Last order: #{$lastOrder->order_number}, status: {$lastOrder->status}, amount: ₹" . number_format((float) $lastOrder->total_amount, 2)
            : "Last order: no orders placed yet.";

        return implode("\n", $lines);
    }

    private function staffContext($user): string
    {
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return "This user has no employee record on file.";
        }

        $lines = ["Name: {$employee->name}", "Department: {$employee->department}"];

        $records = Attendance::where('employee_id', $employee->id)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->get();
        $present = $records->whereIn('status', ['present', 'half_day'])->count();
        $absent  = $records->where('status', 'absent')->count();
        $lines[] = "This month's attendance so far: {$present} present/half-day, {$absent} absent (out of {$records->count()} marked days).";

        $node = SalesHierarchy::where('user_id', $user->id)->first();
        if ($node) {
            $teamSize = $node->allDescendants()->count();
            $lines[] = $teamSize > 0
                ? "Team size (all reports): {$teamSize} people."
                : "No direct reports.";
        }

        $lastPayroll = PayrollRun::latest('id')->first();
        // We only track completed payroll runs, not a scheduled "next" date —
        // telling the assistant that plainly stops it from inventing one.
        $lines[] = $lastPayroll
            ? "Most recent payroll run: {$lastPayroll->month}/{$lastPayroll->year}, status: {$lastPayroll->status}. No upcoming payroll date is tracked in the system."
            : "No payroll runs on record.";

        return implode("\n", $lines);
    }

    private function buildPrompt(string $context, string $question): string
    {
        return <<<PROMPT
            You are the DXEMPIRE app assistant. DXEMPIRE is a B2B platform for certified refurbished electronics (grades S1 = best condition, down to S5 = parts-only).

            Here is this user's current account data — use it if the question is about their own account, otherwise answer from general DXEMPIRE knowledge. Never invent a number, date, or fact that isn't in this data or general knowledge about how DXEMPIRE works:

            {$context}

            Keep the answer brief (1-3 sentences). If asked about something not covered by the data above and not general knowledge (e.g. another user's information, or data not tracked in this system), say you don't have that information rather than guessing.

            Question: {$question}
            PROMPT;
    }
}
