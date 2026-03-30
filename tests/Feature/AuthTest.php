<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ============================
    // LOGIN
    // ============================

    public function test_login_with_valid_credentials_returns_token()
    {
        User::factory()->create([
            'username' => 'testadmin',
            'password' => Hash::make('password123'),
            'role'     => 'admin',
            'is_active'=> true,
        ]);

        $resp = $this->postJson('/api/login', [
            'username' => 'testadmin',
            'password' => 'password123',
        ]);

        $resp->assertStatus(200)
             ->assertJsonStructure(['success', 'data' => ['user', 'token']])
             ->assertJson(['success' => true]);

        $this->assertNotEmpty($resp->json('data.token'));
    }

    public function test_login_with_email_works()
    {
        User::factory()->create([
            'email'    => 'test@esl.local',
            'username' => 'emailtest',
            'password' => Hash::make('password123'),
            'role'     => 'admin',
            'is_active'=> true,
        ]);

        $this->postJson('/api/login', [
            'username' => 'test@esl.local',
            'password' => 'password123',
        ])->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_login_wrong_password_returns_422()
    {
        User::factory()->create([
            'username' => 'testuser2',
            'password' => Hash::make('correctpassword'),
            'role'     => 'admin',
            'is_active'=> true,
        ]);

        $this->postJson('/api/login', [
            'username' => 'testuser2',
            'password' => 'wrongpassword',
        ])->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_login_nonexistent_user_returns_422()
    {
        $this->postJson('/api/login', [
            'username' => 'nobody',
            'password' => 'anypassword',
        ])->assertStatus(422);
    }

    public function test_login_missing_fields_returns_422()
    {
        $this->postJson('/api/login', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_login_inactive_account_returns_403()
    {
        User::factory()->create([
            'username' => 'inactiveuser',
            'password' => Hash::make('password123'),
            'role'     => 'student',
            'is_active'=> false,
        ]);

        $this->postJson('/api/login', [
            'username' => 'inactiveuser',
            'password' => 'password123',
        ])->assertStatus(403);
    }

    // ============================
    // LOGOUT
    // ============================

    public function test_logout_requires_authentication()
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }

    public function test_logout_returns_success()
    {
        $user  = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertStatus(200)
             ->assertJson(['success' => true]);
    }

    public function test_token_is_invalidated_after_logout()
    {
        $user  = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $user->createToken('test')->plainTextToken;

        // Token works before logout
        $this->withToken($token)->getJson('/api/me')->assertStatus(200);

        // Logout
        $this->withToken($token)->postJson('/api/logout')->assertStatus(200);

        // Token entry should be removed from the DB
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ============================
    // GET /me
    // ============================

    public function test_me_returns_authenticated_user()
    {
        $user  = User::factory()->create([
            'username' => 'metest',
            'role'     => 'admin',
            'is_active'=> true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
             ->assertStatus(200)
             ->assertJsonPath('data.username', 'metest')
             ->assertJsonPath('data.role', 'admin');
    }

    public function test_me_requires_authentication()
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    // ============================
    // UPDATE PROFILE
    // ============================

    public function test_update_profile_updates_fields()
    {
        $user  = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson('/api/profile', [
            'first_name' => 'Updated',
            'last_name'  => 'Name',
            'phone'      => '0123456789',
        ])->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'first_name' => 'Updated',
            'phone'      => '0123456789',
        ]);
    }

    public function test_update_profile_requires_authentication()
    {
        $this->putJson('/api/profile', ['first_name' => 'X'])->assertStatus(401);
    }

    // ============================
    // CHANGE PASSWORD
    // ============================

    public function test_change_password_success()
    {
        $user  = User::factory()->create([
            'password' => Hash::make('OldPassword123'),
            'role'     => 'admin',
            'is_active'=> true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson('/api/change-password', [
            'current_password'      => 'OldPassword123',
            'password'              => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_change_password_wrong_current_returns_400()
    {
        $user  = User::factory()->create([
            'password' => Hash::make('CorrectPassword'),
            'role'     => 'admin',
            'is_active'=> true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson('/api/change-password', [
            'current_password'      => 'WrongPassword',
            'password'              => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])->assertStatus(400);
    }

    public function test_change_password_mismatch_returns_422()
    {
        $user  = User::factory()->create([
            'password' => Hash::make('Password123'),
            'role'     => 'admin',
            'is_active'=> true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson('/api/change-password', [
            'current_password'      => 'Password123',
            'password'              => 'NewPassword456!',
            'password_confirmation' => 'DifferentPassword',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_change_password_too_short_returns_422()
    {
        $user  = User::factory()->create([
            'password' => Hash::make('Password123'),
            'role'     => 'admin',
            'is_active'=> true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson('/api/change-password', [
            'current_password'      => 'Password123',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
