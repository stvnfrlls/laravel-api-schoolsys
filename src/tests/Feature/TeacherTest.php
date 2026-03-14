<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherTest extends TestCase
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

    private function makeTeacher(): Teacher
    {
        $user = User::factory()->create();
        $user->assignRole('faculty'); // triggers auto-creation via assignRole()
        return $user->fresh()->teacher;
    }

    // =========================================================================
    // Auto-creation
    // =========================================================================

    public function test_teacher_profile_is_auto_created_when_faculty_role_is_assigned(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->teacher()->first());

        $user->assignRole('faculty');

        $this->assertNotNull($user->fresh()->teacher);
        $this->assertDatabaseHas('teachers', ['user_id' => $user->id]);
    }

    public function test_teacher_profile_is_not_duplicated_on_multiple_role_assignments(): void
    {
        $user = User::factory()->create();
        $user->assignRole('faculty');
        $user->assignRole('faculty'); // assign again — should not duplicate

        $this->assertDatabaseCount('teachers', 1);
    }

    public function test_auto_created_teacher_has_unique_employee_number(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->assignRole('faculty');
        $user2->assignRole('faculty');

        $this->assertNotEquals(
            $user1->fresh()->teacher->employee_number,
            $user2->fresh()->teacher->employee_number
        );
    }

    public function test_auto_created_teacher_has_placeholder_names(): void
    {
        $user = User::factory()->create();
        $user->assignRole('faculty');

        $teacher = $user->fresh()->teacher;

        $this->assertEquals('Unknown', $teacher->first_name);
        $this->assertEquals('Unknown', $teacher->last_name);
        $this->assertNull($teacher->middle_name);
        $this->assertNull($teacher->suffix);
    }

    // =========================================================================
    // GET /teachers
    // =========================================================================

    public function test_admin_can_list_teachers(): void
    {
        Teacher::factory()->count(3)->create();

        $this->actingAsRole('admin')
            ->getJson('/api/teachers')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'per_page']]);
    }

    public function test_sub_admin_can_list_teachers(): void
    {
        $this->actingAsRole('sub-admin')
            ->getJson('/api/teachers')
            ->assertOk();
    }

    public function test_faculty_cannot_list_teachers(): void
    {
        $this->actingAsRole('faculty')
            ->getJson('/api/teachers')
            ->assertForbidden();
    }

    public function test_student_cannot_list_teachers(): void
    {
        $this->actingAsRole('student')
            ->getJson('/api/teachers')
            ->assertForbidden();
    }

    // =========================================================================
    // GET /teachers/{teacher}
    // =========================================================================

    public function test_admin_can_view_a_teacher(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAsRole('admin')
            ->getJson("/api/teachers/{$teacher->id}")
            ->assertOk()
            ->assertJsonPath('id', $teacher->id);
    }

    public function test_teacher_response_includes_name_fields(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAsRole('admin')
            ->getJson("/api/teachers/{$teacher->id}")
            ->assertOk()
            ->assertJsonStructure(['id', 'first_name', 'last_name', 'middle_name', 'suffix']);
    }

    public function test_sub_admin_can_view_a_teacher(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAsRole('sub-admin')
            ->getJson("/api/teachers/{$teacher->id}")
            ->assertOk();
    }

    // =========================================================================
    // PUT /teachers/{teacher}
    // =========================================================================

    public function test_admin_can_update_teacher_profile(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAsRole('admin')
            ->putJson("/api/teachers/{$teacher->id}", [
                'date_of_birth' => '1990-05-15',
                'gender' => 'male',
            ])
            ->assertOk()
            ->assertJsonPath('gender', 'male')
            ->assertJsonPath('date_of_birth', '1990-05-15');
    }

    public function test_admin_can_update_teacher_name_fields(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAsRole('admin')
            ->putJson("/api/teachers/{$teacher->id}", [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'middle_name' => 'Cruz',
                'suffix' => null,
            ])
            ->assertOk()
            ->assertJsonPath('first_name', 'Maria')
            ->assertJsonPath('last_name', 'Santos')
            ->assertJsonPath('middle_name', 'Cruz')
            ->assertJsonPath('suffix', null);

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'middle_name' => 'Cruz',
        ]);
    }

    public function test_admin_can_update_teacher_with_suffix(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAsRole('admin')
            ->putJson("/api/teachers/{$teacher->id}", [
                'first_name' => 'Jose',
                'last_name' => 'Reyes',
                'suffix' => 'Jr.',
            ])
            ->assertOk()
            ->assertJsonPath('suffix', 'Jr.');
    }

    public function test_sub_admin_can_update_teacher_profile(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAsRole('sub-admin')
            ->putJson("/api/teachers/{$teacher->id}", ['gender' => 'female'])
            ->assertOk()
            ->assertJsonPath('gender', 'female');
    }

    public function test_employee_number_must_be_unique(): void
    {
        $teacher1 = $this->makeTeacher();
        $teacher2 = $this->makeTeacher();

        $this->actingAsRole('admin')
            ->putJson("/api/teachers/{$teacher2->id}", [
                'employee_number' => $teacher1->employee_number,
            ])
            ->assertUnprocessable();
    }

    public function test_faculty_cannot_update_teacher_profile(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAsRole('faculty')
            ->putJson("/api/teachers/{$teacher->id}", ['gender' => 'male'])
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_teachers(): void
    {
        $this->getJson('/api/teachers')->assertUnauthorized();
    }
}