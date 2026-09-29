<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\ChecksLinkedRecords;
use App\Http\Traits\Exportable;
use App\Models\Dealer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\SalesHierarchy;
use App\Models\User;
use App\Services\UniqueCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SalesHierarchyController extends Controller
{
    use ApiResponse, Exportable, ChecksLinkedRecords;

    /**
     * hierarchy_role (this table's own enum) => users.role (the login/permission
     * system's role column). The two tables have historically used different
     * role vocabularies for the same real-world positions (e.g. this table
     * says "salesman", Users says "sales") — this is the one place that maps
     * between them, used only when creating a brand new person from scratch.
     */
    private const HIERARCHY_TO_USER_ROLE = [
        'ceo'              => 'super_admin',
        'state_manager'    => 'state_manager',
        'area_manager'     => 'area_manager',
        'district_manager' => 'district_manager',
        'salesman'         => 'sales',
    ];

    public function index(Request $request): JsonResponse
    {
        $nodes = SalesHierarchy::with(['parent:id,name,tree_id', 'user:id,name,phone'])
            ->when($request->role,   fn($q) => $q->where('hierarchy_role', $request->role))
            ->when($request->state,  fn($q) => $q->where('state', $request->state))
            ->when($request->district, fn($q) => $q->where('district', $request->district))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('tree_id', 'like', "%{$request->search}%"))
            ->when($request->parent_id, fn($q) => $q->where('parent_id', $request->parent_id))
            ->orderBy('hierarchy_role')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return $this->paginated($nodes);
    }

    public function export(Request $request)
    {
        $nodes = SalesHierarchy::with(['parent:id,name,tree_id', 'user:id,name,phone'])
            ->when($request->role,   fn($q) => $q->where('hierarchy_role', $request->role))
            ->when($request->state,  fn($q) => $q->where('state', $request->state))
            ->when($request->district, fn($q) => $q->where('district', $request->district))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('tree_id', 'like', "%{$request->search}%"))
            ->orderBy('hierarchy_role')
            ->orderBy('name')
            ->get();

        $headers = ['Tree ID', 'Name', 'Role', 'Phone', 'State', 'Area', 'District', 'Parent'];
        $rows = $nodes->map(fn($n) => [
            $n->tree_id, $n->name, $n->hierarchy_role, $n->phone ?? '-', $n->state ?? '-',
            $n->area ?? '-', $n->district ?? '-', $n->parent?->name ?? '-',
        ]);

        $stamp = now()->format('Ymd_His');
        return $request->get('format') === 'pdf'
            ? $this->exportPdf('Sales Hierarchy', $headers, $rows, "hierarchy_{$stamp}.pdf")
            : $this->exportCsv("hierarchy_{$stamp}.csv", $headers, $rows);
    }

    /** Staff Users not yet placed anywhere in the hierarchy — powers the "existing person" picker. */
    public function availableUsers(): JsonResponse
    {
        $linkedUserIds = SalesHierarchy::whereNotNull('user_id')->pluck('user_id');

        $users = User::whereNotIn('id', $linkedUserIds)
            ->where('is_active', true)
            ->where('role', '!=', 'b2b_partner')
            ->orderBy('name')
            ->get(['id', 'name', 'unique_code', 'role', 'phone']);

        return $this->success($users);
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
        $mode = $request->input('mode', $request->filled('user_id') ? 'existing' : 'new');

        return $mode === 'existing'
            ? $this->storeExisting($request)
            : $this->storeNew($request);
    }

    /**
     * Place someone who is ALREADY a Staff User into the hierarchy — e.g.
     * promoting an existing salesman to District Manager. No new login or HR
     * record: just one new row, linked to their existing account. tree_id
     * reuses that account's own unique_code rather than generating a fresh
     * one, so the two are guaranteed to match instead of merely convention.
     */
    private function storeExisting(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'            => ['required', 'exists:users,id', 'unique:sales_hierarchy,user_id'],
            'hierarchy_role'     => ['required', 'in:ceo,state_manager,area_manager,district_manager,salesman'],
            'parent_id'          => ['nullable', 'exists:sales_hierarchy,id'],
            'parent_unique_code' => ['nullable', 'string', 'exists:sales_hierarchy,tree_id'],
            'state'              => ['nullable', 'string', 'max:100'],
            'area'               => ['nullable', 'string', 'max:100'],
            'district'           => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::findOrFail($request->user_id);
        $parentId = $this->resolveParentId($request);

        $node = SalesHierarchy::create([
            'tree_id'        => $user->unique_code,
            'name'           => $user->name,
            'phone'          => $user->phone,
            'email'          => $user->email,
            'hierarchy_role' => $request->hierarchy_role,
            'parent_id'      => $parentId,
            'state'          => $request->state,
            'area'           => $request->area,
            'district'       => $request->district,
            'user_id'        => $user->id,
            'is_active'      => true,
        ]);

        return $this->created($node->load(['parent:id,name,tree_id', 'user:id,name,phone']), 'Member added to hierarchy.');
    }

    /**
     * Onboard a genuinely NEW person: creates a Staff User (login), an
     * Employee (HR/payroll/documents) record, and the hierarchy placement —
     * all three in one transaction, all three linked, and all three sharing
     * ONE generated code (used as both the User's unique_code and this row's
     * tree_id) so they can never drift apart for people created this way.
     * Password is required — mobile Sales ID login now always needs one
     * (see MobileAuthController::login).
     */
    private function storeNew(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:200'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'email'              => ['nullable', 'email'],
            'password'           => ['required', 'string', 'min:8'],
            'hierarchy_role'     => ['required', 'in:ceo,state_manager,area_manager,district_manager,salesman'],
            'parent_id'          => ['nullable', 'exists:sales_hierarchy,id'],
            'parent_unique_code' => ['nullable', 'string', 'exists:sales_hierarchy,tree_id'],
            'state'              => ['nullable', 'string', 'max:100'],
            'area'               => ['nullable', 'string', 'max:100'],
            'district'           => ['nullable', 'string', 'max:100'],

            // Employee/HR fields — same shape as the Employees "Add" form.
            'department'         => ['nullable', 'string', 'max:100'],
            'designation'        => ['nullable', 'string', 'max:100'],
            'employment_type'    => ['nullable', 'in:full_time,part_time,contract'],
            'shift'              => ['nullable', 'in:morning,evening'],
            'salary'             => ['required', 'numeric', 'min:0'],
            'joining_date'       => ['required', 'date'],

            'village_street'     => ['nullable', 'string', 'max:150'],
            'post_office'        => ['nullable', 'string', 'max:100'],
            'police_station'     => ['nullable', 'string', 'max:100'],
            'pincode'            => ['nullable', 'string', 'max:10'],

            'bank_account_number'    => ['required', 'string', 'max:30'],
            'confirm_account_number' => ['required', 'same:bank_account_number'],
            'account_holder_name'    => ['required', 'string', 'max:150'],
            'bank_name'              => ['required', 'string', 'max:150'],
            'ifsc_code'              => ['required', 'string', 'max:15'],
        ]);

        $userRole   = self::HIERARCHY_TO_USER_ROLE[$data['hierarchy_role']];
        $uniqueCode = UniqueCodeGenerator::generateForRole($userRole);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name'        => $data['name'],
                'phone'       => $data['phone'] ?? null,
                'email'       => $data['email'] ?? null,
                'password'    => Hash::make($data['password']),
                'role'        => $userRole,
                'unique_code' => $uniqueCode,
                'is_active'   => true,
            ]);
            $user->assignRole($userRole);

            $employee = Employee::create([
                'user_id'             => $user->id,
                'name'                => $data['name'],
                'phone'               => $data['phone'] ?? null,
                'email'               => $data['email'] ?? null,
                'employee_code'       => Employee::generateEmployeeCode(),
                'department'          => $data['department'] ?? null,
                'designation'         => $data['designation'] ?? null,
                'employment_type'     => $data['employment_type'] ?? 'full_time',
                'shift'               => $data['shift'] ?? 'morning',
                'basic_salary'        => $data['salary'],
                'join_date'           => $data['joining_date'],
                'is_active'           => true,
                'village_street'      => $data['village_street'] ?? null,
                'post_office'         => $data['post_office'] ?? null,
                'police_station'      => $data['police_station'] ?? null,
                'district'            => $data['district'] ?? null,
                'state'               => $data['state'] ?? null,
                'pincode'             => $data['pincode'] ?? null,
                'bank_account_number' => $data['bank_account_number'],
                'account_holder_name' => $data['account_holder_name'],
                'bank_name'           => $data['bank_name'],
                'ifsc_code'           => strtoupper($data['ifsc_code']),
            ]);

            $parentId = $this->resolveParentId($request);

            $node = SalesHierarchy::create([
                'tree_id'        => $uniqueCode,
                'name'           => $data['name'],
                'phone'          => $data['phone'] ?? null,
                'email'          => $data['email'] ?? null,
                'hierarchy_role' => $data['hierarchy_role'],
                'parent_id'      => $parentId,
                'state'          => $data['state'] ?? null,
                'area'           => $data['area'] ?? null,
                'district'       => $data['district'] ?? null,
                'user_id'        => $user->id,
                'is_active'      => true,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->created([
            'hierarchy'   => $node->load(['parent:id,name,tree_id', 'user:id,name,phone']),
            'employee_id' => $employee->id,
            'unique_code' => $uniqueCode,
        ], 'New member created — login, HR record, and hierarchy placement all set up.');
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

    public function destroy(Request $request, SalesHierarchy $salesHierarchy): JsonResponse
    {
        if (!$request->boolean('cascade')) {
            $linked = $this->findLinkedRecords($salesHierarchy->user_id, 'hierarchy');
            if ($linked) {
                $confirmation = $this->linkedConfirmationPayload($linked);
                return $this->success($confirmation['data'], $confirmation['message']);
            }
        }

        $salesHierarchy->update(['is_active' => false]);

        if ($request->boolean('cascade')) {
            $this->deactivateLinkedRecords($salesHierarchy->user_id, 'hierarchy');
        }

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
