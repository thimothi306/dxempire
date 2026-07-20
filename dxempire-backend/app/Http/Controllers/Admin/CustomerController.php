<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::withCount('orders')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%"))
            ->when($request->state, fn($q) => $q->where('state', $request->state))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($customers);
    }

    public function show(Customer $customer): JsonResponse
    {
        $orders = Order::with(['items'])
            ->where('retail_customer_id', $customer->id)
            ->latest()
            ->get();

        return $this->success([
            'customer'      => $customer,
            'orders_count'  => $orders->count(),
            'total_spent'   => $orders->sum('total_amount'),
            'orders'        => $orders,
        ]);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'name'      => ['sometimes', 'string', 'max:100'],
            'email'     => ['sometimes', 'nullable', 'email'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $customer->update($request->only(['name', 'email', 'is_active']));

        return $this->success($customer->fresh(), 'Customer updated.');
    }
}
