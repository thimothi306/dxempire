<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
    }

    public function test_send_otp_requires_phone(): void
    {
        $this->postJson('/api/v1/auth/send-otp', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['phone']);
    }

    public function test_send_otp_creates_otp_record(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9999999999'])
             ->assertStatus(200)
             ->assertJsonPath('success', true);

        $this->assertDatabaseHas('otp_codes', ['phone' => '9999999999']);
    }

    public function test_verify_otp_with_valid_code_issues_token(): void
    {
        $phone = '9888888888';
        $otp   = '123456';

        OtpCode::create([
            'phone'      => $phone,
            'code'   => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        $res = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $phone,
            'code'  => $otp,
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', ['phone' => $phone]);
    }

    public function test_verify_otp_with_wrong_code_returns_401(): void
    {
        $phone = '9777777777';

        OtpCode::create([
            'phone'      => $phone,
            'code'   => Hash::make('111111'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $phone,
            'code'  => '999999',
        ])->assertStatus(401);
    }

    public function test_verify_otp_with_expired_code_returns_401(): void
    {
        $phone = '9666666666';

        OtpCode::create([
            'phone'      => $phone,
            'code'   => Hash::make('123456'),
            'expires_at' => now()->subMinutes(1),
        ]);

        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $phone,
            'code'  => '123456',
        ])->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales');

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
             ->getJson('/api/v1/auth/me')
             ->assertStatus(200)
             ->assertJsonPath('data.id', $user->id);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
             ->assertStatus(401);
    }

    public function test_logout_revokes_token(): void
    {
        $user  = User::factory()->create();
        $user->assignRole('sales');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')
             ->assertStatus(200);

        // Reset auth guard — Sanctum caches the resolved user in-process
        $this->refreshApplication();

        $this->withToken($token)->getJson('/api/v1/auth/me')
             ->assertStatus(401);
    }
}
