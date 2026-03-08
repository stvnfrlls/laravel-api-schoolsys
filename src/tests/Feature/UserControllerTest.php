<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
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
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);
        $user->load('roles');

        return $user;
    }

    public function test_admin_can_create_a_user(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'password123',
                'role' => 'student',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'date_of_birth' => '2005-06-15',
                'gender' => 'male',
            ])
            ->assertCreated()
            ->assertJsonFragment(['email' => 'john@example.com']);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_admin_can_create_a_user_without_name(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'email' => 'noname@example.com',
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonFragment(['email' => 'noname@example.com']);

        $this->assertDatabaseHas('users', ['email' => 'noname@example.com', 'name' => null]);
    }

    public function test_new_user_gets_unassigned_role_by_default(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'password123',
            ])
            ->assertCreated();

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertTrue($user->load('roles')->hasRole('unassigned'));
    }

    // name is now nullable — only email and password are required
    public function test_create_user_requires_email_and_password(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonMissingValidationErrors(['name']); // name is now optional
    }

    public function test_create_user_rejects_duplicate_email(): void
    {
        $admin = $this->createUserWithRole('admin');
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Another User',
                'email' => 'taken@example.com',
                'password' => 'password123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_user_rejects_invalid_role(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
                'role' => 'ghost-role',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $subAdmin = $this->createUserWithRole('sub-admin');

        $this->actingAs($subAdmin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Test',
                'email' => 'test@example.com',
                'password' => 'password123',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_user_name_and_email(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('student');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$target->id}", [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name', 'email' => 'updated@example.com']);
    }

    public function test_admin_can_set_user_name_to_null(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('student');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$target->id}", ['name' => null])
            ->assertOk();

        $this->assertNull($target->fresh()->name);
    }

    public function test_update_rejects_duplicate_email(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('student');
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$target->id}", ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_allows_same_email_for_same_user(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('student');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$target->id}", ['email' => $target->email])
            ->assertOk();
    }

    public function test_admin_can_deactivate_a_user(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('student');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$target->id}/deactivate")
            ->assertOk()
            ->assertJson(['message' => 'User deactivated successfully.']);

        $this->assertFalse((bool) $target->fresh()->is_active);
    }

    public function test_deactivation_revokes_user_tokens(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('student');
        $target->createToken('device-1');
        $target->createToken('device-2');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$target->id}/deactivate")
            ->assertOk();

        $this->assertCount(0, $target->fresh()->tokens);
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$admin->id}/deactivate")
            ->assertForbidden();
    }

    public function test_cannot_deactivate_already_deactivated_user(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('student');
        $target->update(['is_active' => false]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$target->id}/deactivate")
            ->assertUnprocessable()
            ->assertJson(['message' => 'User is already deactivated.']);
    }

    public function test_admin_can_reactivate_a_deactivated_user(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('student');
        $target->update(['is_active' => false]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$target->id}/activate")
            ->assertOk()
            ->assertJson(['message' => 'User activated successfully.']);

        $this->assertTrue((bool) $target->fresh()->is_active);
    }

    public function test_cannot_activate_already_active_user(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('student');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$target->id}/activate")
            ->assertUnprocessable()
            ->assertJson(['message' => 'User is already active.']);
    }

    public function test_non_admin_cannot_deactivate_user(): void
    {
        $subAdmin = $this->createUserWithRole('sub-admin');
        $target = $this->createUserWithRole('student');

        $this->actingAs($subAdmin, 'sanctum')
            ->patchJson("/api/users/{$target->id}/deactivate")
            ->assertForbidden();
    }

    public function test_authenticated_user_can_view_own_profile(): void
    {
        $user = $this->createUserWithRole('student');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'email', 'is_active', 'roles'])
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_profile_returns_correct_role(): void
    {
        $user = $this->createUserWithRole('faculty');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonFragment(['roles' => ['faculty']]);
    }

    public function test_user_can_update_own_profile(): void
    {
        $user = $this->createUserWithRole('student');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_user_can_clear_own_name(): void
    {
        $user = $this->createUserWithRole('student');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['name' => null])
            ->assertOk();

        $this->assertNull($user->fresh()->name);
    }

    public function test_user_cannot_update_their_own_role_via_profile(): void
    {
        $user = $this->createUserWithRole('student');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['name' => 'New Name', 'role' => 'admin'])
            ->assertOk();

        $this->assertTrue($user->fresh()->load('roles')->hasRole('student'));
        $this->assertFalse($user->fresh()->load('roles')->hasRole('admin'));
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_admin_can_delete_a_user(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('student');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/users/{$target->id}")
            ->assertOk()
            ->assertJson(['message' => 'User deleted.']);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/users/{$admin->id}")
            ->assertForbidden();
    }

    public function test_admin_can_create_student_user_with_profile(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'email' => 'student@example.com',
                'password' => 'password123',
                'role' => 'student',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'middle_name' => 'Cruz',
                'date_of_birth' => '2007-03-10',
                'gender' => 'female',
            ])
            ->assertCreated()
            ->assertJsonStructure(['id', 'email', 'roles', 'student']);

        $this->assertDatabaseHas('students', [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'gender' => 'female',
        ]);
    }

    public function test_admin_can_create_faculty_user_with_profile(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'email' => 'teacher@example.com',
                'password' => 'password123',
                'role' => 'faculty',
                'first_name' => 'Jose',
                'last_name' => 'Reyes',
                'date_of_birth' => '1985-08-20',
                'gender' => 'male',
                'specialization' => 'Mathematics',
            ])
            ->assertCreated()
            ->assertJsonStructure(['id', 'email', 'roles', 'teacher']);

        $this->assertDatabaseHas('teachers', [
            'first_name' => 'Jose',
            'last_name' => 'Reyes',
            'specialization' => 'Mathematics',
        ]);
    }

    public function test_student_profile_fields_are_required_when_role_is_student(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'email' => 'student@example.com',
                'password' => 'password123',
                'role' => 'student',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'date_of_birth', 'gender']);
    }

    public function test_faculty_profile_fields_are_required_when_role_is_faculty(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'email' => 'teacher@example.com',
                'password' => 'password123',
                'role' => 'faculty',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'date_of_birth', 'gender']);
    }

    public function test_profile_fields_not_required_for_non_student_faculty_roles(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'email' => 'subadmin@example.com',
                'password' => 'password123',
                'role' => 'sub-admin',
            ])
            ->assertCreated();
    }

    public function test_student_and_teacher_numbers_are_auto_generated(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'email' => 'stu@example.com',
                'password' => 'password123',
                'role' => 'student',
                'first_name' => 'Ana',
                'last_name' => 'Lim',
                'date_of_birth' => '2008-01-01',
                'gender' => 'female',
            ])
            ->assertCreated();

        $student = \App\Models\Student::where('first_name', 'Ana')->first();
        $this->assertNotNull($student);
        $this->assertStringStartsWith('STU-', $student->student_number);
    }
}