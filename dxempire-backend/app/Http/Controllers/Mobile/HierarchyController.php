<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HierarchyController extends Controller
{
    use ApiResponse;

    /**
     * Get all subordinates (team) for current user
     * Returns only people directly or indirectly under current user
     */
    public function subordinates(Request $request): JsonResponse
    {
        $user = auth()->user();
        $subordinates = $this->getAllSubordinates($user);

        return $this->success([
            'total_subordinates' => count($subordinates),
            'subordinates' => $subordinates,
        ]);
    }

    /**
     * Get hierarchical tree structure of user's team
     * Shows the complete organization under the logged-in user
     */
    public function tree(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tree = $this->buildHierarchyTree($user);

        return $this->success($tree);
    }

    /**
     * Get team statistics
     * Total team size, role breakdown, performance metrics
     */
    public function teamStats(Request $request): JsonResponse
    {
        $user = auth()->user();
        $subordinates = $this->getAllSubordinates($user);

        $stats = [
            'total_team_size' => count($subordinates),
            'by_role' => $this->countByRole($subordinates),
            'direct_reports' => $user->subordinates()->count(),
            'total_orders' => $this->getTotalOrders($subordinates),
            'total_leads' => $this->getTotalLeads($subordinates),
        ];

        return $this->success($stats);
    }

    /**
     * Get colleague at same level
     * E.g., if I'm DM001, show other DM* under same AM001
     */
    public function colleagues(Request $request): JsonResponse
    {
        $user = auth()->user();
        $colleagues = [];

        if ($user->parent) {
            $colleagues = $user->parent->subordinates()
                ->where('id', '!=', $user->id)
                ->with('roles')
                ->get()
                ->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'unique_code' => $u->unique_code,
                    'role' => $u->roles->first()?->name,
                ])
                ->toArray();
        }

        return $this->success([
            'total_colleagues' => count($colleagues),
            'colleagues' => $colleagues,
        ]);
    }

    /**
     * Helper: Get all subordinates recursively
     */
    private function getAllSubordinates($user, &$allSubs = []): array
    {
        $directSubs = $user->subordinates()->with('roles')->get();

        foreach ($directSubs as $sub) {
            $allSubs[] = [
                'id' => $sub->id,
                'name' => $sub->name,
                'unique_code' => $sub->unique_code,
                'email' => $sub->email,
                'phone' => $sub->phone,
                'role' => $sub->roles->first()?->name,
                'is_active' => $sub->is_active,
            ];

            // Recursively get subordinates of subordinates
            $this->getAllSubordinates($sub, $allSubs);
        }

        return $allSubs;
    }

    /**
     * Helper: Build tree structure
     */
    private function buildHierarchyTree($user): array
    {
        $directs = $user->subordinates()->with('roles')->get();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'unique_code' => $user->unique_code,
            'role' => $user->roles->first()?->name,
            'subordinates' => $directs->map(fn($sub) => $this->buildHierarchyTree($sub))->toArray(),
        ];
    }

    /**
     * Helper: Count subordinates by role
     */
    private function countByRole(array $subordinates): array
    {
        $count = [];
        foreach ($subordinates as $sub) {
            $role = $sub['role'] ?? 'unknown';
            $count[$role] = ($count[$role] ?? 0) + 1;
        }
        return $count;
    }

    /**
     * Helper: Get total orders from subordinates
     * TODO: Implement based on your Orders model
     */
    private function getTotalOrders(array $subordinates): int
    {
        // Placeholder - implement based on your Order model
        return 0;
    }

    /**
     * Helper: Get total leads from subordinates
     * TODO: Implement based on your Leads model
     */
    private function getTotalLeads(array $subordinates): int
    {
        // Placeholder - implement based on your Lead model
        return 0;
    }
}
