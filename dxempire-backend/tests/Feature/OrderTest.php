<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    private function makeProduct(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'status'         => 'in_stock',
            'selling_price'  => 10000.00,
            'purchase_price' => 8000.00,
            'grade'          => 'S1',
        ], $attrs));
    }

    public function test_create_order_with_in_stock_products(): void
    {
        $product = $this->makeProduct();

        $res = $this->withToken($this->token)
            ->postJson('/api/v1/orders', [
                'product_ids' => [$product->id],
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['order_number', 'total_amount', 'items']]);

        $this->assertDatabaseHas('orders', ['id' => $res->json('data.id')]);
    }

    public function test_create_order_rejects_out_of_stock_product(): void
    {
        $product = $this->makeProduct(['status' => 'sold']);

        $this->withToken($this->token)
            ->postJson('/api/v1/orders', ['product_ids' => [$product->id]])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_create_order_rejects_duplicate_product_across_items(): void
    {
        $product = $this->makeProduct();

        // Same product id twice — should be de-duped and treated as one
        $res = $this->withToken($this->token)
            ->postJson('/api/v1/orders', [
                'product_ids' => [$product->id, $product->id],
            ]);

        $res->assertStatus(201);
        $this->assertCount(1, $res->json('data.items'));
    }

    public function test_approve_order_marks_products_sold(): void
    {
        $product = $this->makeProduct();

        $order = $this->withToken($this->token)
            ->postJson('/api/v1/orders', ['product_ids' => [$product->id]])
            ->json('data');

        $this->withToken($this->token)
            ->postJson("/api/v1/orders/{$order['id']}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'sold']);
    }

    public function test_cannot_approve_already_approved_order(): void
    {
        $product = $this->makeProduct();

        $orderId = $this->withToken($this->token)
            ->postJson('/api/v1/orders', ['product_ids' => [$product->id]])
            ->json('data.id');

        $this->withToken($this->token)->postJson("/api/v1/orders/{$orderId}/approve");

        $this->withToken($this->token)
            ->postJson("/api/v1/orders/{$orderId}/approve")
            ->assertStatus(422);
    }

    public function test_cancel_pending_order_restores_nothing(): void
    {
        $product = $this->makeProduct();

        $orderId = $this->withToken($this->token)
            ->postJson('/api/v1/orders', ['product_ids' => [$product->id]])
            ->json('data.id');

        $this->withToken($this->token)
            ->postJson("/api/v1/orders/{$orderId}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // Product stays in_stock (was never sold — order was just pending)
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'in_stock']);
    }

    public function test_order_gst_calculated_correctly(): void
    {
        $product = $this->makeProduct(['selling_price' => 10000.00]);

        $data = $this->withToken($this->token)
            ->postJson('/api/v1/orders', ['product_ids' => [$product->id]])
            ->json('data');

        // 18% GST on 10000 = 1800
        $this->assertEquals(10000.00, $data['subtotal']);
        $this->assertEquals(1800.00, $data['gst_amount']);
        $this->assertEquals(11800.00, $data['total_amount']);
    }

    public function test_order_requires_auth(): void
    {
        $this->postJson('/api/v1/orders', ['product_ids' => [1]])
             ->assertStatus(401);
    }

    public function test_list_orders_returns_paginated_results(): void
    {
        Product::factory()->count(3)->create(['status' => 'in_stock', 'selling_price' => 5000]);

        $this->withToken($this->token)
             ->getJson('/api/v1/orders?per_page=10')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'meta' => ['total', 'per_page', 'current_page']]);
    }
}
