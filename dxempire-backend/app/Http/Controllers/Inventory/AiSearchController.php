<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Grade;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSearchController extends Controller
{
    use ApiResponse;

    private const STATUSES = [
        'in_stock', 'qc_pending', 'received', 'reserved',
        'sold', 'returned', 'rejected', 'refurbishment',
    ];

    /**
     * Turn a natural-language query into the same filter shape the
     * Inventory page's dropdowns already use. The AI only ever proposes
     * filter values — every value is validated against the real, current
     * set of grades/categories/statuses before use, so a bad or
     * misunderstood response degrades to "fewer filters applied", never to
     * an invalid query or arbitrary code execution.
     */
    public function parse(Request $request, GeminiService $gemini): JsonResponse
    {
        $data = $request->validate(['query' => ['required', 'string', 'max:300']]);

        $gradeCodes = Grade::where('is_active', true)->pluck('code')->all();

        $prompt = $this->buildPrompt($data['query'], $gradeCodes);
        $raw    = $gemini->generate($prompt);

        $filters = $this->extractAndValidate($raw, $gradeCodes);

        return $this->success($filters);
    }

    private function buildPrompt(string $query, array $gradeCodes): string
    {
        $gradeList = implode(', ', $gradeCodes);

        return <<<PROMPT
            Extract search filters from this inventory search request: "{$query}"

            Respond with ONLY a JSON object (no markdown, no explanation), with these optional keys — omit any key that doesn't apply:
            - "search": free-text to match brand/model/IMEI (string) — e.g. "iPhone", "MacBook"
            - "category": one of exactly: phone, laptop
            - "grade": one of exactly: {$gradeList}
            - "status": one of exactly these, chosen by meaning:
              - "in_stock" — available to sell / in stock
              - "received" — newly received, awaiting its first QC check (use this for generic "pending QC" / "awaiting QC" / "needs QC" requests)
              - "qc_pending" — a RETURNED unit awaiting re-check specifically (only use if the request mentions a return, re-check, or re-QC)
              - "reserved" — held for an order
              - "sold" — already sold
              - "returned" — came back from a customer, not yet re-processed
              - "rejected" — failed QC, unsellable
              - "refurbishment" — being repaired

            Example: for "S2 iPhones in stock" respond {"search":"iPhone","category":"phone","grade":"S2","status":"in_stock"}
            Example: for "laptops pending QC" respond {"category":"laptop","status":"received"}
            If nothing recognizable is present, respond {}.
            PROMPT;
    }

    private function extractAndValidate(?string $raw, array $gradeCodes): array
    {
        if (!$raw) {
            return [];
        }

        // Strip markdown code fences the model sometimes wraps JSON in.
        $clean = trim(preg_replace('/^```json|```$/m', '', trim($raw)));
        $data  = json_decode($clean, true);

        if (!is_array($data)) {
            return [];
        }

        $filters = [];

        if (!empty($data['search']) && is_string($data['search'])) {
            $filters['search'] = substr($data['search'], 0, 100);
        }
        if (in_array($data['category'] ?? null, ['phone', 'laptop'], true)) {
            $filters['category'] = $data['category'];
        }
        if (in_array($data['grade'] ?? null, $gradeCodes, true)) {
            $filters['grade'] = $data['grade'];
        }
        if (in_array($data['status'] ?? null, self::STATUSES, true)) {
            $filters['status'] = $data['status'];
        }

        return $filters;
    }
}
