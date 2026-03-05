<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'unassigned'], ['description' => 'No specific role assigned']);
        Role::firstOrCreate(['name' => 'admin'], ['description' => 'Full access to all resources']);
        Role::firstOrCreate(['name' => 'sub-admin'], ['description' => 'Limited admin access']);
        Role::firstOrCreate(['name' => 'faculty'], ['description' => 'Access to faculty resources']);
        Role::firstOrCreate(['name' => 'student'], ['description' => 'Access to student resources']);
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);
        $user->load('roles');

        return $user;
    }

    public function test_all_roles_exist_in_database(): void
    {
        foreach (['unassigned', 'admin', 'sub-admin', 'faculty', 'student'] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
    }

    public function test_roles_have_correct_descriptions(): void
    {
        $this->assertDatabaseHas('roles', [
            'name' => 'admin',
            'description' => 'Full access to all resources',
        ]);
        $this->assertDatabaseHas('roles', [
            'name' => 'sub-admin',
            'description' => 'Limited admin access',
        ]);
        $this->assertDatabaseHas('roles', [
            'name' => 'faculty',
            'description' => 'Access to faculty resources',
        ]);
        $this->assertDatabaseHas('roles', [
            'name' => 'student',
            'description' => 'Access to student resources',
        ]);
    }

    public function test_admin_has_admin_role(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('sub-admin'));
        $this->assertFalse($user->hasRole('faculty'));
        $this->assertFalse($user->hasRole('student'));
    }

    public function test_sub_admin_has_sub_admin_role_only(): void
    {
        $user = $this->createUserWithRole('sub-admin');
        $this->assertTrue($user->hasRole('sub-admin'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('faculty'));
        $this->assertFalse($user->hasRole('student'));
    }

    public function test_faculty_has_faculty_role_only(): void
    {
        $user = $this->createUserWithRole('faculty');
        $this->assertTrue($user->hasRole('faculty'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('student'));
    }

    public function test_student_has_student_role_only(): void
    {
        $user = $this->createUserWithRole('student');
        $this->assertTrue($user->hasRole('student'));
        $this->assertFalse($user->hasRole('faculty'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_unassigned_user_has_no_meaningful_role(): void
    {
        $user = $this->createUserWithRole('unassigned');
        $this->assertTrue($user->hasRole('unassigned'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('faculty'));
        $this->assertFalse($user->hasRole('student'));
    }

    public function test_admin_passes_admin_or_sub_admin_check(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->assertTrue($user->hasAnyRole(['admin', 'sub-admin']));
    }

    public function test_sub_admin_passes_admin_or_sub_admin_check(): void
    {
        $user = $this->createUserWithRole('sub-admin');
        $this->assertTrue($user->hasAnyRole(['admin', 'sub-admin']));
    }

    public function test_student_fails_admin_or_sub_admin_check(): void
    {
        $user = $this->createUserWithRole('student');
        $this->assertFalse($user->hasAnyRole(['admin', 'sub-admin']));
    }

    public function test_unassigned_fails_all_role_checks(): void
    {
        $user = $this->createUserWithRole('unassigned');
        $this->assertFalse($user->hasAnyRole(['admin', 'sub-admin', 'faculty', 'student']));
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/roles')->assertUnauthorized();
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_admin_can_access_admin_only_route(): void
    {
        $user = $this->createUserWithRole('admin');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users')
            ->assertOk();
    }

    public function test_sub_admin_is_blocked_from_admin_only_route(): void
    {
        $user = $this->createUserWithRole('sub-admin');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users')
            ->assertForbidden()
            ->assertJson(['message' => 'Forbidden.']);
    }

    public function test_faculty_is_blocked_from_admin_only_route(): void
    {
        $user = $this->createUserWithRole('faculty');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_student_is_blocked_from_admin_only_route(): void
    {
        $user = $this->createUserWithRole('student');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_unassigned_is_blocked_from_all_protected_routes(): void
    {
        $user = $this->createUserWithRole('unassigned');

        $this->actingAs($user, 'sanctum')->getJson('/api/users')->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson('/api/roles')->assertForbidden();
    }

    public function test_admin_and_sub_admin_can_access_shared_route(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/roles')
            ->assertOk();
    }

    public function test_faculty_and_student_cannot_access_admin_shared_route(): void
    {
        foreach (['faculty', 'student', 'unassigned'] as $roleName) {
            $user = $this->createUserWithRole($roleName);
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/roles')
                ->assertForbidden();
        }
    }

    public function test_admin_can_list_roles(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/roles')
            ->assertOk()
            ->assertJsonCount(5, '*'); // all 5 seeded roles
    }

    public function test_sub_admin_cannot_manage_roles(): void
    {
        $subAdmin = $this->createUserWithRole('sub-admin');

        $this->actingAs($subAdmin, 'sanctum')
            ->getJson('/api/roles')
            ->assertForbidden();
    }

    public function test_admin_can_create_a_new_role(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/roles', ['name' => 'moderator', 'description' => 'Moderates content'])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'moderator']);

        $this->assertDatabaseHas('roles', ['name' => 'moderator']);
    }

    public function test_duplicate_role_name_is_rejected(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/roles', ['name' => 'student']) // already exists
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_delete_a_non_core_role(): void
    {
        $admin = $this->createUserWithRole('admin');
        $role = Role::create(['name' => 'temp-role', 'description' => 'Temporary']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/roles/{$role->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Role deleted.']);

        $this->assertDatabaseMissing('roles', ['name' => 'temp-role']);
    }

    public function test_admin_can_assign_faculty_role_to_user(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('unassigned'); // starts unassigned

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$target->id}/roles", ['roles' => ['faculty']])
            ->assertOk()
            ->assertJsonFragment(['name' => 'faculty']);

        $this->assertTrue($target->fresh()->load('roles')->hasRole('faculty'));
    }

    public function test_admin_can_promote_student_to_sub_admin(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('student');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$target->id}/roles", ['roles' => ['sub-admin']])
            ->assertOk();

        $fresh = $target->fresh()->load('roles');
        $this->assertTrue($fresh->hasRole('sub-admin'));
        $this->assertFalse($fresh->hasRole('student')); // sync removes old role
    }

    public function test_assigning_invalid_role_name_fails_validation(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$target->id}/roles", ['roles' => ['superuser']]) // does not exist
            ->assertUnprocessable();
    }

    public function test_deleting_role_cleans_up_pivot(): void
    {
        $user = $this->createUserWithRole('faculty');
        $role = Role::where('name', 'faculty')->first();

        $role->delete();

        $this->assertDatabaseMissing('role_user', ['role_id' => $role->id]);
    }

    public function test_assigning_same_role_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'student')->first();

        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->assertCount(1, $user->fresh()->roles);
    }
}