<?php

namespace Tests\Feature\Api\Auth;

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'password' => Hash::make('password123'),
            'role_id' => Role::query()->where('slug', 'agent')->value('id'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', 'agent')
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inexistente@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()->assertJsonPath('message', 'As credenciais informadas são inválidas.');
    }

    public function test_the_initial_administrator_can_log_in(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@admin.com',
            'password' => 'abcd1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.role', 'admin');
    }
}
