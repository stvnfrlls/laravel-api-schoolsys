<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAddressTest extends TestCase
{
    use RefreshDatabase;

    // ── Auth helper ───────────────────────────────────────────────────────

    private function actingAsRole(string $role): static
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $this->actingAs($user);
    }

    // ── Fixtures ──────────────────────────────────────────────────────────

    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'street' => '123 Rizal St.',
            'barangay' => 'Barangay Uno',
            'city' => 'Imus',
            'province' => 'Cavite',
            'region' => 'Region IV-A (CALABARZON)',
            'zip_code' => '4103',
        ], $overrides);
    }

    // =========================================================================
    // POST /users/{user}/address
    // =========================================================================

    public function test_admin_can_create_address_for_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAsRole('admin')
            ->postJson("/api/users/{$user->id}/address", $this->addressPayload())
            ->assertCreated()
            ->assertJsonPath('city', 'Imus');

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'city' => 'Imus',
        ]);
    }

    public function test_sub_admin_can_create_address_for_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAsRole('sub-admin')
            ->postJson("/api/users/{$user->id}/address", $this->addressPayload())
            ->assertCreated();
    }

    public function test_faculty_cannot_create_address(): void
    {
        $user = User::factory()->create();

        $this->actingAsRole('faculty')
            ->postJson("/api/users/{$user->id}/address", $this->addressPayload())
            ->assertForbidden();
    }

    public function test_address_can_be_created_with_all_fields_empty(): void
    {
        $user = User::factory()->create();

        $this->actingAsRole('admin')
            ->postJson("/api/users/{$user->id}/address", [])
            ->assertCreated();

        $this->assertDatabaseHas('user_addresses', ['user_id' => $user->id]);
    }

    public function test_cannot_create_duplicate_address_for_same_user(): void
    {
        $user = User::factory()->create();
        UserAddress::factory()->create(['user_id' => $user->id]);

        $this->actingAsRole('admin')
            ->postJson("/api/users/{$user->id}/address", $this->addressPayload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'User already has an address. Use PUT to update it.');
    }

    // =========================================================================
    // GET /users/{user}/address
    // =========================================================================

    public function test_authenticated_user_can_view_an_address(): void
    {
        $user = User::factory()->create();
        $address = UserAddress::factory()->create(['user_id' => $user->id]);

        $this->actingAsRole('student')
            ->getJson("/api/users/{$user->id}/address")
            ->assertOk()
            ->assertJsonPath('id', $address->id);
    }

    public function test_returns_null_when_user_has_no_address(): void
    {
        $user = User::factory()->create();

        $this->actingAsRole('admin')
            ->getJson("/api/users/{$user->id}/address")
            ->assertOk();
    }

    public function test_unauthenticated_user_cannot_view_address(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}/address")
            ->assertUnauthorized();
    }

    // =========================================================================
    // PUT /users/{user}/address
    // =========================================================================

    public function test_admin_can_update_an_existing_address(): void
    {
        $user = User::factory()->create();
        UserAddress::factory()->create(['user_id' => $user->id, 'city' => 'Bacoor']);

        $this->actingAsRole('admin')
            ->putJson("/api/users/{$user->id}/address", $this->addressPayload(['city' => 'Dasmariñas']))
            ->assertOk()
            ->assertJsonPath('city', 'Dasmariñas');
    }

    public function test_put_creates_address_if_none_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAsRole('admin')
            ->putJson("/api/users/{$user->id}/address", $this->addressPayload())
            ->assertOk();

        $this->assertDatabaseHas('user_addresses', ['user_id' => $user->id]);
    }

    public function test_faculty_cannot_update_address(): void
    {
        $user = User::factory()->create();
        UserAddress::factory()->create(['user_id' => $user->id]);

        $this->actingAsRole('faculty')
            ->putJson("/api/users/{$user->id}/address", $this->addressPayload())
            ->assertForbidden();
    }

    // =========================================================================
    // DELETE /users/{user}/address
    // =========================================================================

    public function test_admin_can_delete_an_address(): void
    {
        $user = User::factory()->create();
        UserAddress::factory()->create(['user_id' => $user->id]);

        $this->actingAsRole('admin')
            ->deleteJson("/api/users/{$user->id}/address")
            ->assertOk()
            ->assertJsonPath('message', 'Address removed successfully.');

        $this->assertDatabaseMissing('user_addresses', ['user_id' => $user->id]);
    }

    public function test_sub_admin_cannot_delete_address(): void
    {
        $user = User::factory()->create();
        UserAddress::factory()->create(['user_id' => $user->id]);

        $this->actingAsRole('sub-admin')
            ->deleteJson("/api/users/{$user->id}/address")
            ->assertForbidden();
    }

    public function test_delete_returns_404_when_no_address_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAsRole('admin')
            ->deleteJson("/api/users/{$user->id}/address")
            ->assertNotFound()
            ->assertJsonPath('message', 'No address found for this user.');
    }
}