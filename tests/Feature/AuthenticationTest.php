<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\NtbRolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NtbRolesAndPermissionsSeeder::class);
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_public_home_loads(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_user_can_login_via_api(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@ntb.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole('system_administrator');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@ntb.test',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('user.email', 'admin@ntb.test');
        $this->assertAuthenticatedAs($user);
    }
}
