<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
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

    private function makeSection(): Section
    {
        $gradeLevel = GradeLevel::factory()->create();
        return Section::factory()->create(['grade_level_id' => $gradeLevel->id]);
    }

    /**
     * Creates a User, assigns the student role, and explicitly creates
     * the Student profile. We do NOT rely on any observer or side effect
     * of assignRole() since that varies by implementation.
     */
    private function makeStudent(): User
    {
        $user = User::factory()->create();
        $user->assignRole('student');

        return $user;
    }

    private function enrollmentPayload(Section $section, User $user, array $overrides = []): array
    {
        $student = $user->student; // get the observer-created student

        return array_merge([
            'student_id' => $student->id,            // students.id — NOT users.id
            'section_id' => $section->id,
            'grade_level_id' => $section->grade_level_id,
            'school_year' => '2025-2026',
            'semester' => '1st',
        ], $overrides);
    }

    // =========================================================================
    // POST /enrollments
    // =========================================================================

    public function test_admin_can_enroll_a_student(): void
    {
        $section = $this->makeSection();
        $user = $this->makeStudent();
        $student = $user->student;

        $response = $this->actingAsRole('admin')
            ->postJson('/api/enrollments', $this->enrollmentPayload($section, $user));

        $response->assertCreated()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('school_year', '2025-2026')
            ->assertJsonPath('semester', '1st');

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'section_id' => $section->id,
            'school_year' => '2025-2026',
            'semester' => '1st',
        ]);
    }

    public function test_sub_admin_can_enroll_a_student(): void
    {
        $section = $this->makeSection();
        $student = $this->makeStudent();

        $this->actingAsRole('sub-admin')
            ->postJson('/api/enrollments', $this->enrollmentPayload($section, $student))
            ->assertCreated();
    }

    public function test_faculty_cannot_enroll_a_student(): void
    {
        $section = $this->makeSection();
        $student = $this->makeStudent();

        $this->actingAsRole('faculty')
            ->postJson('/api/enrollments', $this->enrollmentPayload($section, $student))
            ->assertForbidden();
    }

    public function test_student_cannot_enroll_another_student(): void
    {
        $section = $this->makeSection();
        $student = $this->makeStudent();

        $this->actingAsRole('student')
            ->postJson('/api/enrollments', $this->enrollmentPayload($section, $student))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_enroll(): void
    {
        $section = $this->makeSection();
        $student = $this->makeStudent();

        $this->postJson('/api/enrollments', $this->enrollmentPayload($section, $student))
            ->assertUnauthorized();
    }

    // =========================================================================
    // Duplicate enrollment prevention
    // =========================================================================

    public function test_cannot_enroll_same_student_twice_in_same_period(): void
    {
        $section = $this->makeSection();
        $student = $this->makeStudent();
        $payload = $this->enrollmentPayload($section, $student);

        $this->actingAsRole('admin')->postJson('/api/enrollments', $payload)->assertCreated();

        $this->actingAsRole('admin')
            ->postJson('/api/enrollments', $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Student is already enrolled for this school year and semester.');
    }

    public function test_student_can_be_enrolled_in_different_semesters(): void
    {
        $section = $this->makeSection();
        $student = $this->makeStudent();

        $this->actingAsRole('admin')
            ->postJson('/api/enrollments', $this->enrollmentPayload($section, $student, ['semester' => '1st']))
            ->assertCreated();

        $this->actingAsRole('admin')
            ->postJson('/api/enrollments', $this->enrollmentPayload($section, $student, ['semester' => '2nd']))
            ->assertCreated();
    }

    // =========================================================================
    // Section / grade level mismatch guard
    // =========================================================================

    public function test_cannot_enroll_student_in_section_mismatching_grade_level(): void
    {
        $gradeLevel1 = GradeLevel::factory()->create();
        $gradeLevel2 = GradeLevel::factory()->create();
        $section = Section::factory()->create(['grade_level_id' => $gradeLevel1->id]);
        $student = $this->makeStudent();

        $payload = $this->enrollmentPayload($section, $student, [
            'grade_level_id' => $gradeLevel2->id, // intentional mismatch
        ]);

        $this->actingAsRole('admin')
            ->postJson('/api/enrollments', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The selected section does not belong to the specified grade level.');
    }

    // =========================================================================
    // GET /enrollments
    // =========================================================================

    public function test_authenticated_user_can_list_enrollments(): void
    {
        Enrollment::factory()->count(3)->create();

        $this->actingAsRole('student')
            ->getJson('/api/enrollments')
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page']);
    }

    public function test_enrollments_can_be_filtered_by_section(): void
    {
        $section = $this->makeSection();
        Enrollment::factory()->count(2)->create(['section_id' => $section->id]);
        Enrollment::factory()->count(3)->create(); // other sections

        $response = $this->actingAsRole('admin')
            ->getJson("/api/enrollments?section_id={$section->id}")
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    // =========================================================================
    // GET /enrollments/{id}
    // =========================================================================

    public function test_authenticated_user_can_view_a_single_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->actingAsRole('student')
            ->getJson("/api/enrollments/{$enrollment->id}")
            ->assertOk()
            ->assertJsonPath('id', $enrollment->id);
    }

    // =========================================================================
    // PUT /enrollments/{id}
    // =========================================================================

    public function test_admin_can_update_enrollment_status(): void
    {
        $enrollment = Enrollment::factory()->create(['status' => 'active']);

        $this->actingAsRole('admin')
            ->putJson("/api/enrollments/{$enrollment->id}", ['status' => 'dropped'])
            ->assertOk()
            ->assertJsonPath('status', 'dropped');
    }

    public function test_faculty_cannot_update_an_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->actingAsRole('faculty')
            ->putJson("/api/enrollments/{$enrollment->id}", ['status' => 'dropped'])
            ->assertForbidden();
    }

    // =========================================================================
    // DELETE /enrollments/{id}
    // =========================================================================

    public function test_admin_can_delete_an_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->actingAsRole('admin')
            ->deleteJson("/api/enrollments/{$enrollment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Enrollment removed successfully.');

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->id]);
    }

    public function test_sub_admin_cannot_delete_an_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->actingAsRole('sub-admin')
            ->deleteJson("/api/enrollments/{$enrollment->id}")
            ->assertForbidden();
    }

    // =========================================================================
    // GET /sections/{section}/enrollments
    // =========================================================================

    public function test_can_view_enrolled_students_by_section(): void
    {
        $section = $this->makeSection();
        Enrollment::factory()->count(4)->create(['section_id' => $section->id, 'status' => 'active']);

        $this->actingAsRole('faculty')
            ->getJson("/api/sections/{$section->id}/enrollments")
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_section_enrollment_list_can_be_filtered_by_status(): void
    {
        $section = $this->makeSection();
        Enrollment::factory()->count(2)->create(['section_id' => $section->id, 'status' => 'active']);
        Enrollment::factory()->count(1)->create(['section_id' => $section->id, 'status' => 'dropped']);

        $response = $this->actingAsRole('admin')
            ->getJson("/api/sections/{$section->id}/enrollments?status=active")
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }
}