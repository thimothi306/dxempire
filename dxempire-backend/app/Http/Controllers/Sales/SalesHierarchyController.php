<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\SalesHierarchy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesHierarchyController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $nodes = SalesHierarchy::with(['parent:id,name,tree_id', 'user:id,name,phone'])
            ->when($request->role,   fn($q) => $q->where('hierarchy_role', $request->role))
            ->when($request->state,  fn($q) => $q->where('state', $request->state))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('tree_id', 'like', "%{$request->search}%"))
            ->when($request->parent_id, fn($q) => $q->where('parent_id', $request->parent_id))
            ->orderBy('hierarchy_role')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return $this->paginated($nodes);
    }

    public function tree(): JsonResponse
    {
        $roots = SalesHierarchy::with('children.children.children.children')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->get();

        return $this->success($roots);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'               => ['required', 'string', 'max:200'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'email'              => ['nullable', 'email'],
            'hierarchy_role'     => ['required', 'in:ceo,state_manager,area_manager,district_manager,salesman'],
            'parent_id'          => ['nullable', 'exists:sales_hierarchy,id'],
            'parent_unique_code' => ['nullable', 'string', 'exists:sales_hierarchy,tree_id'],
            'state'              => ['nullable', 'string', 'max:100'],
            'area'               => ['nullable', 'string', 'max:100'],
            'district'           => ['nullable', 'string', 'max:100'],
            'user_id'            => ['nullable', 'exists:users,id'],
        ]);

        $parentId = $this->resolveParentId($request);
        $treeId   = SalesHierarchy::generateTreeId($request->hierarchy_role);

        $node = SalesHierarchy::create([
            'tree_id'        => $treeId,
            'name'           => $request->name,
            'phone'          => $request->phone,
            'email'          => $request->email,
            'hierarchy_role' => $request->hierarchy_role,
            'parent_id'      => $parentId,
            'state'          => $request->state,
            'area'           => $request->area,
            'district'       => $request->district,
            'user_id'        => $request->user_id,
            'is_active'      => true,
        ]);

        return $this->created($node->load(['parent:id,name,tree_id', 'user:id,name,phone']), 'Member added to hierarchy.');
    }

    /** Resolve parent_id from parent_unique_code (tree_id lookup) when the numeric parent_id isn't given. */
    private function resolveParentId(Request $request): ?int
    {
        if ($request->filled('parent_id')) {
            return (int) $request->parent_id;
        }
        if ($request->filled('parent_unique_code')) {
            return SalesHierarchy::where('tree_id', $request->parent_unique_code)->value('id');
        }
        return null;
    }

    public function show(SalesHierarchy $salesHierarchy): JsonResponse
    {
        $salesHierarchy->load([
            'parent:id,name,tree_id,hierarchy_role',
            'children.children',
            'user:id,name,phone',
            'dealers:id,business_name,kyc_status,credit_used,assigned_salesman_id',
        ]);

        return $this->success($salesHierarchy);
    }

    public function update(Request $request, SalesHierarchy $salesHierarchy): JsonResponse
    {
        $request->validate([
            'name'               => ['sometimes', 'string', 'max:200'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'email'              => ['nullable', 'email'],
            'parent_id'          => ['nullable', 'exists:sales_hierarchy,id'],
            'parent_unique_code' => ['nullable', 'string', 'exists:sales_hierarchy,tree_id'],
            'state'              => ['nullable', 'string', 'max:100'],
            'area'               => ['nullable', 'string', 'max:100'],
            'district'           => ['nullable', 'string', 'max:100'],
            'user_id'            => ['nullable', 'exists:users,id'],
            'is_active'          => ['boolean'],
        ]);

        $data = $request->only(['name', 'phone', 'email', 'state', 'area', 'district', 'user_id', 'is_active']);

        if ($request->filled('parent_id') || $request->filled('parent_unique_code')) {
            $data['parent_id'] = $this->resolveParentId($request);
        }

        $salesHierarchy->update($data);

        return $this->success($salesHierarchy->fresh(['parent:id,name,tree_id', 'user:id,name,phone']), 'Member updated.');
    }

    public function destroy(SalesHierarchy $salesHierarchy): JsonResponse
    {
        $salesHierarchy->update(['is_active' => false]);

        return $this->success(null, 'Member deactivated.');
    }

    public function downline(SalesHierarchy $salesHierarchy): JsonResponse
    {
        $salesHierarchy->load('children.children.children.children');

        $descendants = $salesHierarchy->allDescendants();

        $salesmanIds = $descendants
            ->where('hierarchy_role', 'salesman')
            ->pluck('id');

        $totalDealers = Dealer::whereIn('assigned_salesman_id', $salesmanIds)->count();

        $dealerUserIds = Dealer::whereIn('assigned_salesman_id', $salesmanIds)
            ->pluck('user_id');

        $totalOrders = Order::whereIn('dealer_id',
            Dealer::whereIn('assigned_salesman_id', $salesmanIds)->pluck('id')
        )->whereIn('status', ['delivered', 'dispatched'])->count();

        $totalRevenue = Order::whereIn('dealer_id',
            Dealer::whereIn('assigned_salesman_id', $salesmanIds)->pluck('id')
        )->whereIn('status', ['delivered', 'dispatched'])->sum('total_amount');

        return $this->success([
            'node'           => $salesHierarchy->only(['id', 'tree_id', 'name', 'hierarchy_role', 'state', 'area', 'district']),
            'total_members'  => $descendants->count(),
            'total_dealers'  => $totalDealers,
            'total_orders'   => $totalOrders,
            'total_revenue'  => round($totalRevenue, 2),
            'tree'           => $salesHierarchy->children,
        ]);
    }

    public function performance(SalesHierarchy $salesHierarchy): JsonResponse
    {
        $descendants = $salesHierarchy->allDescendants();
        $allIds = $descendants->pluck('id')->push($salesHierarchy->id);

        $salesmanIds = SalesHierarchy::whereIn('id', $allIds)
            ->where('hierarchy_role', 'salesman')
            ->pluck('id');

        $dealers = Dealer::whereIn('assigned_salesman_id', $salesmanIds)
            ->with('user:id,name,phone')
            ->get();

        $dealerIds = $dealers->pluck('id');

        $orders = Order::whereIn('dealer_id', $dealerIds)
            ->whereIn('status', ['delivered', 'dispatched', 'approved'])
            ->selectRaw('dealer_id, COUNT(*) as order_count, SUM(total_amount) as revenue')
            ->groupBy('dealer_id')
            ->get()
            ->keyBy('dealer_id');

        $dealerPerformance = $dealers->map(fn($d) => [
            'dealer_id'    => $d->id,
            'business_name'=> $d->business_name,
            'phone'        => $d->user?->phone,
            'kyc_status'   => $d->kyc_status,
            'order_count'  => $orders[$d->id]->order_count ?? 0,
            'revenue'      => $orders[$d->id]->revenue ?? 0,
            'credit_used'  => $d->credit_used,
        ])->sortByDesc('revenue')->values();

        return $this->success([
            'node'               => $salesHierarchy->only(['id', 'tree_id', 'name', 'hierarchy_role']),
            'team_size'          => $descendants->count(),
            'total_dealers'      => $dealers->count(),
            'total_revenue'      => round($dealerPerformance->sum('revenue'), 2),
            'total_orders'       => $dealerPerformance->sum('order_count'),
            'dealer_performance' => $dealerPerformance,
        ]);
    }

    public function assignDealer(Request $request, SalesHierarchy $salesHierarchy): JsonResponse
    {
        if ($salesHierarchy->hierarchy_role !== 'salesman') {
            return $this->error('Only salesmen can be assigned dealers.', 422);
        }

        $request->validate([
            'dealer_id' => ['required', 'exists:dealers,id'],
        ]);

        Dealer::where('id', $request->dealer_id)
            ->update(['assigned_salesman_id' => $salesHierarchy->id]);

        return $this->success(null, 'Dealer assigned to salesman.');
    }
}
