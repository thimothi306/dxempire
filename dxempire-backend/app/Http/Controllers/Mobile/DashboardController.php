<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Dealer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\SalesHierarchy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Get mobile dashboard data
     * Returns role-specific data based on logged-in user's hierarchy level
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Determine hierarchy level from the unique-code prefix
        // (CEO*, SM*, AM*, DM*, SG*) since one DB role ("sales") covers
        // both state managers and salesmen.
        $code   = strtoupper((string) $user->unique_code);
        $prefix = preg_replace('/[0-9]+$/', '', $code);

        $dashboard = match($prefix) {
            'SG'  => $this->getSalesmanDashboard($user),
            'DM'  => $this->getDistrictManagerDashboard($user),
            'AM'  => $this->getAreaManagerDashboard($user),
            'SM'  => $this->getStateManagerDashboard($user),
            'CEO' => $this->getCEODashboard($user),
            default => ['message' => 'Dashboard not configured for this user'],
        };

        return $this->success($dashboard);
    }

    /**
     * Salesman (SG*) Dashboard
     * Shows: Own profile, orders, leads, performance
     */
    private function getSalesmanDashboard($user): array
    {
        $dealerIds = $this->dealerIdsFor([$user->id]);

        $totalLeads = Lead::where('assigned_to', $user->id)->count();
        $wonLeads   = Lead::where('assigned_to', $user->id)->where('stage', 'won')->count();

        $monthRevenue = Order::whereIn('dealer_id', $dealerIds)
            ->where('status', 'delivered')
            ->whereMonth('delivered_at', now()->month)
            ->whereYear('delivered_at', now()->year)
            ->sum('total_amount');

        return [
            'user_info' => [
                'name' => $user->name,
                'unique_code' => $user->unique_code,
                'phone' => $user->phone,
                'role' => 'Salesman',
                'reports_to' => $user->parent?->name,
                'reports_to_code' => $user->parent?->unique_code,
            ],
            'my_stats' => [
                'total_orders'    => Order::whereIn('dealer_id', $dealerIds)->count(),
                'total_leads'     => $totalLeads,
                'conversion_rate' => $totalLeads > 0 ? round($wonLeads / $totalLeads * 100, 1) . '%' : '0%',
                'month_revenue'   => '₹' . number_format((float) $monthRevenue, 2),
            ],
            'quick_actions' => [
                'create_lead',
                'create_order',
                'view_orders',
                'view_leads',
                'update_profile',
            ],
            'recent_orders' => Order::whereIn('dealer_id', $dealerIds)
                ->latest()
                ->limit(5)
                ->get(['id', 'order_number', 'status', 'total_amount', 'created_at']),
            'recent_leads' => Lead::where('assigned_to', $user->id)
                ->latest('updated_at')
                ->limit(5)
                ->get(['id', 'contact_name', 'business_name', 'stage', 'updated_at']),
        ];
    }

    /**
     * Dealer IDs attributed to the given salesman user IDs, via their
     * SalesHierarchy node's assigned_salesman_id link.
     */
    private function dealerIdsFor(array $userIds): \Illuminate\Support\Collection
    {
        $nodeIds = SalesHierarchy::whereIn('user_id', $userIds)->pluck('id');

        return Dealer::whereIn('assigned_salesman_id', $nodeIds)->pluck('id');
    }

    /**
     * Real order/lead/revenue totals for a set of salesman user IDs — used
     * to replace the hardcoded-zero team/zone/state stats below.
     */
    private function computeSalesMetrics(array $userIds): array
    {
        if (empty($userIds)) {
            return ['orders' => 0, 'leads' => 0, 'revenue' => 0.0];
        }

        $dealerIds = $this->dealerIdsFor($userIds);

        return [
            'orders'  => Order::whereIn('dealer_id', $dealerIds)->count(),
            'leads'   => Lead::whereIn('assigned_to', $userIds)->count(),
            'revenue' => (float) Order::whereIn('dealer_id', $dealerIds)
                ->where('status', 'delivered')
                ->sum('total_amount'),
        ];
    }

    /**
     * District Manager (DM*) Dashboard
     * Shows: Own data + All SG* under me + Team performance
     */
    private function getDistrictManagerDashboard($user): array
    {
        $subordinates = $this->getAllSubordinates($user);
        $subIds       = array_column($subordinates, 'id');
        $teamMetrics  = $this->computeSalesMetrics($subIds);
        $myMetrics    = $this->computeSalesMetrics([$user->id]);

        return [
            'user_info' => [
                'name' => $user->name,
                'unique_code' => $user->unique_code,
                'phone' => $user->phone,
                'role' => 'District Manager',
                'territory' => $user->department ?? null,
                'reports_to' => $user->parent?->name,
                'reports_to_code' => $user->parent?->unique_code,
            ],
            'team_info' => [
                'total_team_members' => count($subordinates),
                'direct_reports' => $user->subordinates()->count(),
                'team_members' => $subordinates,
            ],
            'team_stats' => [
                'total_orders'       => $teamMetrics['orders'],
                'total_leads'        => $teamMetrics['leads'],
                'team_revenue'       => '₹' . number_format($teamMetrics['revenue'], 2),
                'average_conversion' => $this->conversionRate($subIds),
            ],
            'my_stats' => [
                'my_orders' => $myMetrics['orders'],
                'my_leads'  => $myMetrics['leads'],
            ],
            'quick_actions' => [
                'view_team',
                'view_team_orders',
                'view_team_leads',
                'view_team_performance',
                'create_lead',
                'create_order',
            ],
            'team_performance' => [], // TODO: top 3 performers
            'recent_team_orders' => [], // TODO: fetch last 5 team orders
        ];
    }

    /**
     * Area Manager (AM*) Dashboard
     * Shows: Own data + All DM* under me + All SG* under those DM* + Zone performance
     */
    private function getAreaManagerDashboard($user): array
    {
        $subordinates = $this->getAllSubordinates($user);
        $subIds       = array_column($subordinates, 'id');
        $zoneMetrics  = $this->computeSalesMetrics($subIds);

        return [
            'user_info' => [
                'name' => $user->name,
                'unique_code' => $user->unique_code,
                'phone' => $user->phone,
                'role' => 'Area Manager',
                'zone' => $user->department ?? null,
                'reports_to' => $user->parent?->name,
                'reports_to_code' => $user->parent?->unique_code,
            ],
            'zone_info' => [
                'total_zone_members' => count($subordinates),
                'district_managers' => $user->subordinates()->count(),
                'salesmen' => $this->countByRole($subordinates)['salesman'] ?? 0,
            ],
            'zone_stats' => [
                'total_orders'     => $zoneMetrics['orders'],
                'total_leads'      => $zoneMetrics['leads'],
                'zone_revenue'     => '₹' . number_format($zoneMetrics['revenue'], 2),
                'zone_conversion'  => $this->conversionRate($subIds),
            ],
            'quick_actions' => [
                'view_zone',
                'view_zone_orders',
                'view_zone_leads',
                'view_zone_performance',
                'manage_district_managers',
            ],
            'zone_performance' => [], // TODO: district manager performance
            'top_salesmen' => [], // TODO: top 5 salesmen in zone
        ];
    }

    /**
     * State Manager (SM*) Dashboard
     * Shows: Entire state structure + Performance metrics
     */
    private function getStateManagerDashboard($user): array
    {
        $subordinates = $this->getAllSubordinates($user);
        $subIds       = array_column($subordinates, 'id');
        $stateMetrics = $this->computeSalesMetrics($subIds);

        return [
            'user_info' => [
                'name' => $user->name,
                'unique_code' => $user->unique_code,
                'phone' => $user->phone,
                'role' => 'State Manager',
                'state' => $user->department ?? null,
                'reports_to' => $user->parent?->name ?? 'CEO',
            ],
            'state_info' => [
                'total_state_members' => count($subordinates),
                'area_managers' => $user->subordinates()->count(),
            ],
            'state_stats' => [
                'total_orders'      => $stateMetrics['orders'],
                'total_leads'       => $stateMetrics['leads'],
                'state_revenue'     => '₹' . number_format($stateMetrics['revenue'], 2),
                'state_conversion'  => $this->conversionRate($subIds),
            ],
            'quick_actions' => [
                'view_state_structure',
                'view_state_orders',
                'view_state_leads',
                'view_state_performance',
                'manage_area_managers',
            ],
            'area_performance' => [], // TODO: area manager performance
            'top_district_managers' => [], // TODO: top DM in state
        ];
    }

    /**
     * CEO Dashboard
     * Shows: Company-wide statistics
     */
    private function getCEODashboard($user): array
    {
        $allSalesUserIds = SalesHierarchy::whereNotNull('user_id')->pluck('user_id')->toArray();
        $companyMetrics  = $this->computeSalesMetrics($allSalesUserIds);

        return [
            'user_info' => [
                'name' => $user->name,
                'role' => 'CEO',
            ],
            'company_stats' => [
                'total_users'             => \App\Models\User::count(),
                'total_state_managers'    => \App\Models\User::where('unique_code', 'like', 'SM%')->count(),
                'total_area_managers'     => \App\Models\User::where('unique_code', 'like', 'AM%')->count(),
                'total_district_managers' => \App\Models\User::where('unique_code', 'like', 'DM%')->count(),
                'total_salesmen'          => \App\Models\User::where('unique_code', 'like', 'SG%')->count(),
            ],
            'company_performance' => [
                'total_orders'        => $companyMetrics['orders'],
                'total_leads'         => $companyMetrics['leads'],
                'total_revenue'       => '₹' . number_format($companyMetrics['revenue'], 2),
                'overall_conversion'  => $this->conversionRate($allSalesUserIds),
            ],
            'quick_actions' => [
                'view_company_structure',
                'view_all_orders',
                'view_all_leads',
                'view_performance_reports',
                'manage_state_managers',
            ],
            'state_performance' => [], // TODO: all states performance
        ];
    }

    /**
     * Won-lead percentage for a set of salesman user IDs, formatted for display.
     */
    private function conversionRate(array $userIds): string
    {
        if (empty($userIds)) {
            return '0%';
        }

        $total = Lead::whereIn('assigned_to', $userIds)->count();
        if ($total === 0) {
            return '0%';
        }

        $won = Lead::whereIn('assigned_to', $userIds)->where('stage', 'won')->count();

        return round($won / $total * 100, 1) . '%';
    }

    /**
     * Helper: Get all subordinates recursively
     */
    private function getAllSubordinates($user, array &$allSubs = []): array
    {
        $directSubs = $user->subordinates()->with('roles')->get();

        foreach ($directSubs as $sub) {
            $allSubs[] = [
                'id' => $sub->id,
                'name' => $sub->name,
                'unique_code' => $sub->unique_code,
                'role' => $sub->roles->first()?->name,
            ];

            // Recurse into this subordinate's own team (by reference)
            $this->getAllSubordinates($sub, $allSubs);
        }

        return $allSubs;
    }

    /**
     * Helper: Count by role
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
}
