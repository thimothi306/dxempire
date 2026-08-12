<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Bin;
use App\Models\Dealer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\PayrollRun;
use App\Models\Product;
use App\Models\Setting;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    use ApiResponse;

    private const MAX_TOOL_ROUNDS = 3;

    /**
     * Read-only, super_admin-scoped chat assistant. The AI never queries the
     * database directly — it can only request one of these named tools, each
     * of which runs a fixed, parameterized, read-only query and returns
     * real data. There is no write tool, no raw-query tool, and no way for
     * a model response to reach the database except through this list.
     */
    public function chat(Request $request, GeminiService $gemini): JsonResponse
    {
        $data = $request->validate([
            'message'         => ['required', 'string', 'max:500'],
            'history'         => ['nullable', 'array', 'max:40'],
            'history.*.role'  => ['required_with:history', 'in:user,model'],
            'history.*.text'  => ['required_with:history', 'string'],
        ]);

        $contents = [];
        foreach ($data['history'] ?? [] as $turn) {
            $contents[] = ['role' => $turn['role'], 'parts' => [['text' => $turn['text']]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $data['message']]]];

        $tools = $this->toolDeclarations();
        $systemInstruction = 'You are the internal business assistant for DXEMPIRE, a refurbished phone and laptop reseller in India. '
            . 'You only answer questions about DXEMPIRE\'s own orders, inventory, dealers, and payroll, using the tools provided. '
            . 'All monetary amounts in tool results are in Indian Rupees — always format them with the ₹ symbol (e.g. ₹1,23,456), '
            . 'never $ or USD. Keep answers short and direct. Only answer using data returned by the tools — '
            . 'never guess or estimate a number that wasn\'t returned by a tool call. '
            . 'If a question is unrelated to DXEMPIRE\'s business (general knowledge, other companies, personal topics, etc.), '
            . 'politely decline and say you can only help with DXEMPIRE\'s orders, inventory, dealers, and payroll.';
        $finalText = null;

        $model = config('services.gemini.chat_model');

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $content = $gemini->chat($contents, $tools, $systemInstruction, $model);

            if (!$content) {
                break;
            }

            $parts = $content['parts'] ?? [];
            $functionCallPart = null;
            $functionCall = null;
            $text = null;

            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $functionCallPart = $part;
                    $functionCall = $part['functionCall'];
                } elseif (isset($part['text'])) {
                    $text = $part['text'];
                }
            }

            if ($functionCall) {
                $result = $this->runTool($functionCall['name'], $functionCall['args'] ?? []);

                // An empty `args` (no-parameter tools like get_payroll_summary)
                // round-trips through PHP's array-based JSON decoding as `[]`,
                // which Laravel's Http client then re-encodes as a JSON array
                // instead of the `{}` object Gemini requires — force it back
                // to an object so no-arg tool calls don't 400 on replay.
                if (empty($functionCallPart['functionCall']['args'])) {
                    $functionCallPart['functionCall']['args'] = new \stdClass();
                }

                // Must replay the exact part Gemini returned (including its
                // thoughtSignature) — rebuilding a stripped-down functionCall
                // part causes a 400 "missing thought_signature" error on the
                // next turn.
                $contents[] = ['role' => 'model', 'parts' => [$functionCallPart]];
                $contents[] = ['role' => 'user', 'parts' => [[
                    'functionResponse' => [
                        'name'     => $functionCall['name'],
                        'response' => ['result' => $result],
                    ],
                ]]];
                continue;
            }

            $finalText = $text;
            break;
        }

        if ($finalText === null) {
            return $this->success([
                'reply' => "I couldn't process that — try rephrasing, or ask about a specific order, dealer, inventory count, or payroll status.",
            ]);
        }

        return $this->success(['reply' => $finalText]);
    }

    private function toolDeclarations(): array
    {
        return [
            [
                'name'        => 'get_order_status',
                'description' => 'Get the status, dealer, total amount, and dispatch/delivery info for one order by its order number.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'order_number' => ['type' => 'string', 'description' => 'e.g. DX-2026-00037'],
                    ],
                    'required' => ['order_number'],
                ],
            ],
            [
                'name'        => 'get_inventory_count',
                'description' => 'Count in-stock products, optionally filtered by category, grade, or status.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'category' => ['type' => 'string', 'description' => 'phone or laptop'],
                        'grade'    => ['type' => 'string', 'description' => 'e.g. S1, S2, S3'],
                        'status'   => ['type' => 'string', 'description' => 'e.g. in_stock, received, sold'],
                    ],
                ],
            ],
            [
                'name'        => 'get_dealer_balance',
                'description' => 'Get a business partner/dealer\'s credit limit, credit used, and available credit by business name.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'business_name' => ['type' => 'string'],
                    ],
                    'required' => ['business_name'],
                ],
            ],
            [
                'name'        => 'get_payroll_summary',
                'description' => 'Get the most recent payroll run — month, status, and total payout.',
                'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'        => 'get_low_stock_alerts',
                'description' => 'Get the current list of grade/category combinations below the admin-configured low-stock threshold.',
                'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'        => 'get_dealer_list',
                'description' => 'List active business partner/dealer accounts with their credit limit, credit used, and available credit. Use this when asked to list, count, or summarize dealers rather than look up one specific dealer.',
                'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'        => 'get_employee_count',
                'description' => 'Get the total number of active employees, broken down by department.',
                'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'        => 'get_bin_contents',
                'description' => 'Get the current item count, capacity, and products stored in a specific warehouse bin by its bin code.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'bin_code' => ['type' => 'string', 'description' => 'e.g. BIN-007'],
                    ],
                    'required' => ['bin_code'],
                ],
            ],
        ];
    }

    private function runTool(string $name, array $args): array
    {
        return match ($name) {
            'get_order_status'     => $this->toolOrderStatus($args['order_number'] ?? ''),
            'get_inventory_count'  => $this->toolInventoryCount($args),
            'get_dealer_balance'   => $this->toolDealerBalance($args['business_name'] ?? ''),
            'get_payroll_summary'  => $this->toolPayrollSummary(),
            'get_low_stock_alerts' => $this->toolLowStock(),
            'get_dealer_list'      => $this->toolDealerList(),
            'get_employee_count'   => $this->toolEmployeeCount(),
            'get_bin_contents'     => $this->toolBinContents($args['bin_code'] ?? ''),
            default                => ['error' => 'Unknown tool'],
        };
    }

    private function toolOrderStatus(string $orderNumber): array
    {
        $order = Order::with('dealer:id,business_name')
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            return ['found' => false];
        }

        return [
            'found'          => true,
            'order_number'   => $order->order_number,
            'status'         => $order->status,
            'dealer'         => $order->dealer?->business_name,
            'total_amount'   => (float) $order->total_amount,
            'awb_number'     => $order->awb_number,
            'dispatched_at'  => $order->dispatched_at?->toDateString(),
            'delivered_at'   => $order->delivered_at?->toDateString(),
        ];
    }

    private function toolInventoryCount(array $args): array
    {
        $query = Product::where('deleted_at', null);

        if (in_array($args['category'] ?? null, ['phone', 'laptop'], true)) {
            $query->where('category', $args['category']);
        }
        if (!empty($args['grade']) && is_string($args['grade'])) {
            $query->where('grade', $args['grade']);
        }
        if (!empty($args['status']) && is_string($args['status'])) {
            $query->where('status', $args['status']);
        }

        return ['count' => $query->count()];
    }

    private function toolDealerBalance(string $businessName): array
    {
        $dealer = Dealer::where('business_name', 'like', '%' . $businessName . '%')->first();

        if (!$dealer) {
            return ['found' => false];
        }

        return [
            'found'            => true,
            'business_name'    => $dealer->business_name,
            'credit_limit'     => (float) $dealer->credit_limit,
            'credit_used'      => (float) $dealer->credit_used,
            'credit_available' => (float) ($dealer->credit_limit - $dealer->credit_used),
            'kyc_status'       => $dealer->kyc_status,
        ];
    }

    private function toolPayrollSummary(): array
    {
        $run = PayrollRun::latest('id')->first();

        if (!$run) {
            return ['found' => false];
        }

        return [
            'found'         => true,
            'month'         => $run->month,
            'year'          => $run->year,
            'status'        => $run->status,
            'total_payout'  => (float) $run->total_payout,
        ];
    }

    private function toolLowStock(): array
    {
        $thresholds = Setting::getJson('low_stock_threshold', ['phone' => 10, 'laptop' => 5]);

        $rows = Product::inStock()
            ->selectRaw('category, grade, count(*) as count')
            ->groupBy('category', 'grade')
            ->get();

        $alerts = [];
        foreach ($rows as $row) {
            $threshold = $thresholds[$row->category] ?? null;
            if ($threshold !== null && $row->grade && $row->count < $threshold) {
                $alerts[] = ['category' => $row->category, 'grade' => $row->grade, 'count' => $row->count, 'threshold' => $threshold];
            }
        }

        return ['alerts' => $alerts];
    }

    private function toolDealerList(): array
    {
        $dealers = Dealer::orderBy('business_name')
            ->limit(30)
            ->get(['business_name', 'credit_limit', 'credit_used', 'kyc_status'])
            ->map(fn ($d) => [
                'business_name'    => $d->business_name,
                'credit_limit'     => (float) $d->credit_limit,
                'credit_used'      => (float) $d->credit_used,
                'credit_available' => (float) ($d->credit_limit - $d->credit_used),
                'kyc_status'       => $d->kyc_status,
            ]);

        return ['count' => $dealers->count(), 'dealers' => $dealers];
    }

    private function toolEmployeeCount(): array
    {
        $total = Employee::where('is_active', true)->count();

        $byDepartment = Employee::where('is_active', true)
            ->selectRaw('department, count(*) as count')
            ->groupBy('department')
            ->pluck('count', 'department');

        return ['total' => $total, 'by_department' => $byDepartment];
    }

    private function toolBinContents(string $binCode): array
    {
        $bin = Bin::where('code', $binCode)->first();

        if (!$bin) {
            return ['found' => false];
        }

        $products = Product::where('bin_id', $bin->id)
            ->limit(20)
            ->get(['brand', 'model', 'grade', 'imei'])
            ->map(fn ($p) => "{$p->brand} {$p->model} (grade {$p->grade})");

        return [
            'found'         => true,
            'bin_code'      => $bin->code,
            'current_count' => $bin->current_count,
            'capacity'      => $bin->capacity,
            'products'      => $products,
        ];
    }
}
