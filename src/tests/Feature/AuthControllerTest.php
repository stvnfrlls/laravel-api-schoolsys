<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'password' => Hash::make('password123'),
        ], $overrides));
    }

    private function insertResetToken(string $email, string $token, int $minutesAgo = 0): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = $this->createUser(['email' => 'user@example.com']);

        $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'roles'],
            ]);
    }

    public function test_login_returns_user_roles(): void
    {
        $user = $this->createUser();
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user->roles()->attach($role);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('user.roles.0', 'admin');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'ghost@example.com',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_requires_valid_email_format(): void
    {
        $this->postJson('/api/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_revokes_previous_tokens(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123']);

        $this->assertCount(1, $user->fresh()->tokens);
    }

    public function test_user_can_logout(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_forgot_password_returns_token_for_existing_email(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonStructure(['message', 'token']);
    }

    public function test_forgot_password_stores_hashed_token_in_database(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk();

        $rawToken = $response->json('token');

        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        $this->assertNotNull($record);
        $this->assertTrue(Hash::check($rawToken, $record->token)); // stored as hash, not plain
    }

    public function test_forgot_password_replaces_existing_token(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/forgot-password', ['email' => $user->email]);
        $this->postJson('/api/forgot-password', ['email' => $user->email]);

        $count = DB::table('password_reset_tokens')->where('email', $user->email)->count();
        $this->assertEquals(1, $count);
    }

    public function test_forgot_password_fails_for_nonexistent_email(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'ghost@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_requires_email(): void
    {
        $this->postJson('/api/forgot-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = $this->createUser();
        $token = Str::random(64);
        $this->insertResetToken($user->email, $token);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])
            ->assertOk()
            ->assertJson(['message' => 'Password reset successfully.']);

        $this->assertTrue(Hash::check('newpassword1', $user->fresh()->password));
    }

    public function test_reset_password_deletes_token_after_use(): void
    {
        $user = $this->createUser();
        $token = Str::random(64);
        $this->insertResetToken($user->email, $token);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertOk();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_reset_password_revokes_all_sanctum_tokens(): void
    {
        $user = $this->createUser();
        $user->createToken('device-1');
        $user->createToken('device-2');

        $token = Str::random(64);
        $this->insertResetToken($user->email, $token);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertOk();

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = $this->createUser();
        $this->insertResetToken($user->email, 'correct-token');

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => 'wrong-token',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_reset_password_fails_with_expired_token(): void
    {
        $user = $this->createUser();
        $token = Str::random(64);
        $this->insertResetToken($user->email, $token, minutesAgo: 61); // 1 minute past expiry

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_reset_password_token_cannot_be_reused(): void
    {
        $user = $this->createUser();
        $token = Str::random(64);
        $this->insertResetToken($user->email, $token);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertOk();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'anotherpassword1',
            'password_confirmation' => 'anotherpassword1',
        ])->assertUnprocessable();
    }

    public function test_reset_password_fails_when_passwords_do_not_match(): void
    {
        $user = $this->createUser();
        $token = Str::random(64);
        $this->insertResetToken($user->email, $token);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword1',
            'password_confirmation' => 'differentpassword',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_requires_minimum_8_characters(): void
    {
        $user = $this->createUser();
        $token = Str::random(64);
        $this->insertResetToken($user->email, $token);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_fails_for_nonexistent_email(): void
    {
        $this->postJson('/api/reset-password', [
            'email' => 'ghost@example.com',
            'token' => 'sometoken',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
