<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
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
        $role = $user->roles->first()?->name;

        // Get role-specific data
        $dashboard = match($role) {
            'salesman' => $this->getSalesmanDashboard($user),
            'district_manager' => $this->getDistrictManagerDashboard($user),
            'area_manager' => $this->getAreaManagerDashboard($user),
            'state_manager' => $this->getStateManagerDashboard($user),
            'ceo' => $this->getCEODashboard($user),
            default => ['message' => 'Role not configured'],
        };

        return $this->success($dashboard);
    }

    /**
     * Salesman (SG*) Dashboard
     * Shows: Own profile, orders, leads, performance
     */
    private function getSalesmanDashboard($user): array
    {
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
                'total_orders' => 0, // TODO: fetch from Order model where user_id = $user->id
                'total_leads' => 0, // TODO: fetch from Lead model where user_id = $user->id
                'conversion_rate' => '0%', // TODO: calculate
                'month_revenue' => '₹0', // TODO: calculate
            ],
            'quick_actions' => [
                'create_lead',
                'create_order',
                'view_orders',
                'view_leads',
                'update_profile',
            ],
            'recent_orders' => [], // TODO: fetch last 5 orders
            'recent_leads' => [], // TODO: fetch last 5 leads
        ];
    }

    /**
     * District Manager (DM*) Dashboard
     * Shows: Own data + All SG* under me + Team performance
     */
    private function getDistrictManagerDashboard($user): array
    {
        $subordinates = $this->getAllSubordinates($user);

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
                'total_orders' => 0, // TODO: sum of all team members' orders
                'total_leads' => 0, // TODO: sum of all team members' leads
                'team_revenue' => '₹0', // TODO: sum of all team members' revenue
                'average_conversion' => '0%', // TODO: average conversion rate
            ],
            'my_stats' => [
                'my_orders' => 0, // TODO: my personal orders
                'my_leads' => 0, // TODO: my personal leads
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
                'total_orders' => 0, // TODO: sum of all zone members' orders
                'total_leads' => 0, // TODO: sum of all zone members' leads
                'zone_revenue' => '₹0', // TODO: sum of zone revenue
                'zone_conversion' => '0%',
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
                'total_orders' => 0, // TODO: sum of all state members' orders
                'total_leads' => 0, // TODO: sum of all state members' leads
                'state_revenue' => '₹0', // TODO: sum of state revenue
                'state_conversion' => '0%',
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
        return [
            'user_info' => [
                'name' => $user->name,
                'role' => 'CEO',
            ],
            'company_stats' => [
                'total_users' => 0, // TODO: count all users
                'total_state_managers' => 0, // TODO: count SM*
                'total_area_managers' => 0, // TODO: count AM*
                'total_district_managers' => 0, // TODO: count DM*
                'total_salesmen' => 0, // TODO: count SG*
            ],
            'company_performance' => [
                'total_orders' => 0, // TODO: sum all orders
                'total_leads' => 0, // TODO: sum all leads
                'total_revenue' => '₹0', // TODO: sum all revenue
                'overall_conversion' => '0%',
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
     * Helper: Get all subordinates recursively
     */
    private function getAllSubordinates($user): array
    {
        $allSubs = [];
        $directSubs = $user->subordinates()->with('roles')->get();

        foreach ($directSubs as $sub) {
            $allSubs[] = [
                'id' => $sub->id,
                'name' => $sub->name,
                'unique_code' => $sub->unique_code,
                'role' => $sub->roles->first()?->name,
            ];

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
