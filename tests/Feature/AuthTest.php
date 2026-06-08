<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_a_new_user_successfully()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_logs_in_a_user_successfully()
    {
        $user = User::factory()->create([
            'tenant_id' => Str::uuid(),
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['user', 'token']]);
    }

    public function test_fails_to_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'tenant_id' => Str::uuid(),
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_fetch_the_authenticated_user_profile()
    {
        $user = User::factory()->create([
            'tenant_id' => Str::uuid(),
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_can_log_out_a_user_successfully()
    {
        $user = User::factory()->create([
            'tenant_id' => Str::uuid(),
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
    }
}
