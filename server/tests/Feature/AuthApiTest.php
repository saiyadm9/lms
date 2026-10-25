<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_register_and_receives_token_with_student_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Alice Student',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ]);

        $this->assertEquals(User::ROLE_STUDENT, $response->json('user.role'));
        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'role' => User::ROLE_STUDENT,
        ]);
    }

    public function test_registration_forces_student_role_even_if_admin_is_requested(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(User::ROLE_STUDENT, $response->json('user.role'));
        $this->assertDatabaseHas('users', [
            'email' => 'attacker@example.com',
            'role' => User::ROLE_STUDENT,
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', 'jane@example.com');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_access_me_and_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('user.id', $user->id);

        $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');

        $logoutResponse->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Reset test authentication guard cache
        auth()->forgetGuards();

        // Subsequent authenticated request with same token fails
        $afterLogoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $afterLogoutResponse->assertStatus(401);
    }
}
