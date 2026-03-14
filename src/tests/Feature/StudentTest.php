<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTest extends TestCase
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

    private function makeStudent(): Student
    {
        $user = User::factory()->create();
        $user->assignRole('student'); // triggers auto-creation via assignRole()
        return $user->fresh()->student;
    }

    // =========================================================================
    // Auto-creation
    // =========================================================================

    public function test_student_profile_is_auto_created_when_student_role_is_assigned(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->student()->first());

        $user->assignRole('student');

        $this->assertNotNull($user->fresh()->student);
        $this->assertDatabaseHas('students', ['user_id' => $user->id]);
    }

    public function test_student_profile_is_not_duplicated_on_multiple_role_assignments(): void
    {
        $user = User::factory()->create();
        $user->assignRole('student');
        $user->assignRole('student'); // assign again — should not duplicate

        $this->assertDatabaseCount('students', 1);
    }

    public function test_auto_created_student_has_unique_student_number(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->assignRole('student');
        $user2->assignRole('student');

        $this->assertNotEquals(
            $user1->fresh()->student->student_number,
            $user2->fresh()->student->student_number
        );
    }

    public function test_auto_created_student_has_placeholder_names(): void
    {
        $user = User::factory()->create();
        $user->assignRole('student');

        $student = $user->fresh()->student;

        $this->assertEquals('Unknown', $student->first_name);
        $this->assertEquals('Unknown', $student->last_name);
        $this->assertNull($student->middle_name);
        $this->assertNull($student->suffix);
    }

    // =========================================================================
    // GET /students
    // =========================================================================

    public function test_admin_can_list_students(): void
    {
        Student::factory()->count(3)->create();

        $this->actingAsRole('admin')
            ->getJson('/api/students')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'per_page']]);
    }

    public function test_sub_admin_can_list_students(): void
    {
        $this->actingAsRole('sub-admin')
            ->getJson('/api/students')
            ->assertOk();
    }

    public function test_faculty_cannot_list_students(): void
    {
        $this->actingAsRole('faculty')
            ->getJson('/api/students')
            ->assertForbidden();
    }

    public function test_student_cannot_list_students(): void
    {
        $this->actingAsRole('student')
            ->getJson('/api/students')
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_list_students(): void
    {
        $this->getJson('/api/students')->assertUnauthorized();
    }

    // =========================================================================
    // GET /students/{student}
    // =========================================================================

    public function test_admin_can_view_a_student(): void
    {
        $student = $this->makeStudent();

        $this->actingAsRole('admin')
            ->getJson("/api/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('id', $student->id);
    }

    public function test_student_response_includes_name_fields(): void
    {
        $student = $this->makeStudent();

        $this->actingAsRole('admin')
            ->getJson("/api/students/{$student->id}")
            ->assertOk()
            ->assertJsonStructure(['id', 'first_name', 'last_name', 'middle_name', 'suffix']);
    }

    public function test_sub_admin_can_view_a_student(): void
    {
        $student = $this->makeStudent();

        $this->actingAsRole('sub-admin')
            ->getJson("/api/students/{$student->id}")
            ->assertOk();
    }

    // =========================================================================
    // PUT /students/{student}
    // =========================================================================

    public function test_admin_can_update_student_profile(): void
    {
        $student = $this->makeStudent();

        $this->actingAsRole('admin')
            ->putJson("/api/students/{$student->id}", [
                'date_of_birth' => '2005-08-20',
                'gender' => 'female',
            ])
            ->assertOk()
            ->assertJsonPath('gender', 'female')
            ->assertJsonPath('date_of_birth', '2005-08-20');
    }

    public function test_admin_can_update_student_name_fields(): void
    {
        $student = $this->makeStudent();

        $this->actingAsRole('admin')
            ->putJson("/api/students/{$student->id}", [
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

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'middle_name' => 'Cruz',
        ]);
    }

    public function test_admin_can_update_student_with_suffix(): void
    {
        $student = $this->makeStudent();

        $this->actingAsRole('admin')
            ->putJson("/api/students/{$student->id}", [
                'first_name' => 'Jose',
                'last_name' => 'Reyes',
                'suffix' => 'Jr.',
            ])
            ->assertOk()
            ->assertJsonPath('suffix', 'Jr.');
    }

    public function test_sub_admin_can_update_student_profile(): void
    {
        $student = $this->makeStudent();

        $this->actingAsRole('sub-admin')
            ->putJson("/api/students/{$student->id}", ['gender' => 'male'])
            ->assertOk()
            ->assertJsonPath('gender', 'male');
    }

    public function test_student_number_must_be_unique(): void
    {
        $student1 = $this->makeStudent();
        $student2 = $this->makeStudent();

        $this->actingAsRole('admin')
            ->putJson("/api/students/{$student2->id}", [
                'student_number' => $student1->student_number, // taken
            ])
            ->assertUnprocessable();
    }

    public function test_student_number_can_be_updated_to_its_own_value(): void
    {
        $student = $this->makeStudent();

        $this->actingAsRole('admin')
            ->putJson("/api/students/{$student->id}", [
                'student_number' => $student->student_number, // same — should be fine
            ])
            ->assertOk();
    }

    public function test_faculty_cannot_update_student_profile(): void
    {
        $student = $this->makeStudent();

        $this->actingAsRole('faculty')
            ->putJson("/api/students/{$student->id}", ['gender' => 'male'])
            ->assertForbidden();
    }

    public function test_student_cannot_update_another_students_profile(): void
    {
        $student = $this->makeStudent();

        $this->actingAsRole('student')
            ->putJson("/api/students/{$student->id}", ['gender' => 'male'])
            ->assertForbidden();
    }
}