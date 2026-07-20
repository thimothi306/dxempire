<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private string $adminToken;
    private string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->staff = User::factory()->create();
        $this->staff->assignRole('warehouse_staff');
        $this->staffToken = $this->staff->createToken('test')->plainTextToken;
    }

    // ── User management ─────────────────────────────────────────────────

    public function test_admin_can_list_users(): void
    {
        $this->withToken($this->adminToken)
             ->getJson('/api/v1/admin/users')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'meta']);
    }

    public function test_non_admin_cannot_access_admin_users(): void
    {
        $this->withToken($this->staffToken)
             ->getJson('/api/v1/admin/users')
             ->assertStatus(403);
    }

    public function test_admin_can_create_user(): void
    {
        $res = $this->withToken($this->adminToken)
            ->postJson('/api/v1/admin/users', [
                'name'  => 'Test Sales',
                'phone' => '9111111111',
                'role'  => 'sales',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.phone', '9111111111');

        $this->assertDatabaseHas('users', ['phone' => '9111111111']);
    }

    public function test_admin_can_assign_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales');

        $this->withToken($this->adminToken)
            ->putJson("/api/v1/admin/users/{$user->id}/role", ['role' => 'accounts'])
            ->assertStatus(200);

        $this->assertTrue($user->fresh()->hasRole('accounts'));
        $this->assertFalse($user->fresh()->hasRole('sales'));
    }

    public function test_admin_can_deactivate_user(): void
    {
        $user  = User::factory()->create(['is_active' => true]);
        $user->assignRole('sales');
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($this->adminToken)
            ->postJson("/api/v1/admin/users/{$user->id}/deactivate")
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);

        // Reset auth guard — Sanctum caches the resolved user in-process
        $this->refreshApplication();

        // Token should be revoked — /auth/me now returns 401
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $this->withToken($this->adminToken)
            ->postJson("/api/v1/admin/users/{$this->admin->id}/deactivate")
            ->assertStatus(422);
    }

    // ── Settings management ─────────────────────────────────────────────

    public function test_admin_can_list_settings(): void
    {
        $this->withToken($this->adminToken)
             ->getJson('/api/v1/admin/settings')
             ->assertStatus(200)
             ->assertJsonStructure(['data']);
    }

    public function test_admin_can_update_setting(): void
    {
        $this->withToken($this->adminToken)
            ->putJson('/api/v1/admin/settings/company_name', ['value' => 'DXEMPIRE TEST'])
            ->assertStatus(200)
            ->assertJsonPath('data.value', 'DXEMPIRE TEST');

        $this->assertEquals('DXEMPIRE TEST', Setting::get('company_name'));
    }

    public function test_non_editable_setting_returns_403(): void
    {
        Setting::create(['key' => 'internal_secret', 'value' => 'x']);

        $this->withToken($this->adminToken)
            ->putJson('/api/v1/admin/settings/internal_secret', ['value' => 'hacked'])
            ->assertStatus(403);
    }

    public function test_admin_can_bulk_update_settings(): void
    {
        $this->withToken($this->adminToken)
            ->putJson('/api/v1/admin/settings', [
                'settings' => [
                    ['key' => 'logistics_provider', 'value' => 'delhivery'],
                    ['key' => 'whatsapp_provider',  'value' => 'twilio'],
                ],
            ])
            ->assertStatus(200);

        $this->assertEquals('delhivery', Setting::get('logistics_provider'));
        $this->assertEquals('twilio', Setting::get('whatsapp_provider'));
    }
}
