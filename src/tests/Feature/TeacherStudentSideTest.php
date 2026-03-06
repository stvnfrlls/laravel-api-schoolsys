<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\GradingComponent;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherStudentSideTest extends TestCase
{
    use RefreshDatabase;

    // Shared fixtures
    private User $teacherUser;
    private Teacher $teacher;
    private User $studentUser;
    private Student $student;
    private Enrollment $enrollment;
    private Section $section;
    private Subject $subject;
    private GradeLevel $gradeLevel;

    protected function setUp(): void
    {
        parent::setUp();

        // --- Teacher ---
        $this->teacherUser = User::factory()->create();
        $this->teacherUser->assignRole('faculty');
        // assignRole('faculty') auto-creates the Teacher record
        $this->teacher = $this->teacherUser->teacher()->first();

        // --- Student ---
        $this->studentUser = User::factory()->create();
        $this->studentUser->assignRole('student');
        // assignRole('student') auto-creates the Student record
        $this->student = $this->studentUser->student()->first();

        // --- Curriculum ---
        $this->gradeLevel = GradeLevel::factory()->create();
        $this->section = Section::factory()->create(['grade_level_id' => $this->gradeLevel->id]);
        $this->subject = Subject::factory()->create();

        // --- Active enrollment for the student ---
        $this->enrollment = Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'section_id' => $this->section->id,
            'grade_level_id' => $this->gradeLevel->id,
            'status' => 'active',
        ]);
    }

    // =================================================================
    // TEACHER SIDE
    // =================================================================

    // ── mySchedule ───────────────────────────────────────────────────

    public function test_teacher_can_view_own_schedule(): void
    {
        Schedule::factory()->create([
            'teacher_id' => $this->teacherUser->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/teacher/schedule');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $this->subject->id]);
    }

    public function test_teacher_schedule_only_shows_own_classes(): void
    {
        // Another teacher
        $otherUser = User::factory()->create();
        $otherUser->assignRole('faculty');

        Schedule::factory()->create([
            'teacher_id' => $this->teacherUser->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
        ]);

        Schedule::factory()->create([
            'teacher_id' => $otherUser->id,
            'section_id' => $this->section->id,
            'subject_id' => Subject::factory()->create()->id,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/teacher/schedule');

        $response->assertStatus(200)
            ->assertJsonCount(1); // Only their own schedule
    }

    public function test_teacher_can_filter_schedule_by_day(): void
    {
        Schedule::factory()->create([
            'teacher_id' => $this->teacherUser->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'day' => 'monday',                          // lowercase — matches enum
        ]);

        Schedule::factory()->create([
            'teacher_id' => $this->teacherUser->id,
            'section_id' => $this->section->id,
            'subject_id' => Subject::factory()->create()->id,
            'day' => 'wednesday',                       // lowercase — matches enum
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/teacher/schedule?day=monday');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['day' => 'monday']);
    }

    // ── mySubjects ───────────────────────────────────────────────────

    public function test_teacher_can_view_assigned_subjects_and_sections(): void
    {
        $subjectB = Subject::factory()->create();
        $sectionB = Section::factory()->create(['grade_level_id' => $this->gradeLevel->id]);

        Schedule::factory()->create([
            'teacher_id' => $this->teacherUser->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
        ]);

        // Same subject, different section
        Schedule::factory()->create([
            'teacher_id' => $this->teacherUser->id,
            'section_id' => $sectionB->id,
            'subject_id' => $this->subject->id,
        ]);

        // Entirely different subject
        Schedule::factory()->create([
            'teacher_id' => $this->teacherUser->id,
            'section_id' => $this->section->id,
            'subject_id' => $subjectB->id,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/teacher/subjects');

        $response->assertStatus(200)
            ->assertJsonCount(2); // 2 distinct subjects

        // First subject should have 2 sections
        $data = $response->json();
        $subjectEntry = collect($data)->firstWhere('subject.id', $this->subject->id);
        $this->assertCount(2, $subjectEntry['sections']);
    }

    // ── Non-faculty cannot access teacher routes ──────────────────────

    public function test_student_cannot_access_teacher_routes(): void
    {
        $this->actingAs($this->studentUser)
            ->getJson('/api/teacher/schedule')
            ->assertStatus(403);
    }

    // =================================================================
    // STUDENT SIDE
    // =================================================================

    // ── myProfile ────────────────────────────────────────────────────

    public function test_student_can_view_own_profile(): void
    {
        $response = $this->actingAs($this->studentUser)
            ->getJson('/api/student/profile');

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $this->student->id])
            ->assertJsonPath('active_enrollment.id', $this->enrollment->id);
    }

    // ── mySchedule ───────────────────────────────────────────────────

    public function test_student_can_view_class_schedule(): void
    {
        Schedule::factory()->create([
            'teacher_id' => $this->teacherUser->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
        ]);

        $response = $this->actingAs($this->studentUser)
            ->getJson('/api/student/schedule');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $this->subject->id]);
    }

    public function test_student_with_no_active_enrollment_gets_404_on_schedule(): void
    {
        // 'inactive' is not a valid status — use 'dropped' instead
        $this->enrollment->update(['status' => 'dropped']);

        $this->actingAs($this->studentUser)
            ->getJson('/api/student/schedule')
            ->assertStatus(404)
            ->assertJsonFragment(['message' => 'No active enrollment found.']);
    }

    // ── myGrades ─────────────────────────────────────────────────────

    public function test_student_can_view_own_grades(): void
    {
        $component = GradingComponent::factory()->create([
            'subject_id' => $this->subject->id,
            'weight' => 100,
        ]);

        StudentGrade::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'grading_component_id' => $component->id,
            'quarter' => 1,
            'score' => 88,
            'weighted_score' => 88,
            'final_grade' => 88,
            'is_failing' => false,
        ]);

        $response = $this->actingAs($this->studentUser)
            ->getJson('/api/student/grades');

        $response->assertStatus(200)
            ->assertJsonCount(1) // 1 subject group
            ->assertJsonFragment(['id' => $this->subject->id]);
    }

    public function test_student_can_filter_grades_by_quarter(): void
    {
        $component = GradingComponent::factory()->create([
            'subject_id' => $this->subject->id,
            'weight' => 100,
        ]);

        StudentGrade::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'grading_component_id' => $component->id,
            'quarter' => 1,
            'score' => 88,
            'weighted_score' => 88,
            'final_grade' => 88,
            'is_failing' => false,
        ]);

        StudentGrade::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'grading_component_id' => $component->id,
            'quarter' => 2,
            'score' => 90,
            'weighted_score' => 90,
            'final_grade' => 90,
            'is_failing' => false,
        ]);

        $response = $this->actingAs($this->studentUser)
            ->getJson('/api/student/grades?quarter=1');

        $data = $response->json();
        $allQuarters = collect($data)->pluck('grades')->flatten(1)->pluck('quarter')->unique()->values();

        $response->assertStatus(200);
        $this->assertEquals([1], $allQuarters->toArray());
    }

    public function test_student_cannot_see_other_students_grades(): void
    {
        // Another student's enrollment and grade
        $otherUser = User::factory()->create();
        $otherUser->assignRole('student');
        $otherStudent = $otherUser->student()->first();
        $otherEnrollment = Enrollment::factory()->create([
            'student_id' => $otherStudent->id,
            'section_id' => $this->section->id,
            'grade_level_id' => $this->gradeLevel->id,
            'status' => 'active',
        ]);

        $component = GradingComponent::factory()->create([
            'subject_id' => $this->subject->id,
            'weight' => 100,
        ]);

        StudentGrade::factory()->create([
            'enrollment_id' => $otherEnrollment->id,
            'subject_id' => $this->subject->id,
            'grading_component_id' => $component->id,
            'quarter' => 1,
            'score' => 95,
            'weighted_score' => 95,
            'final_grade' => 95,
            'is_failing' => false,
        ]);

        $response = $this->actingAs($this->studentUser)
            ->getJson('/api/student/grades');

        // Own student has no grades — should return empty
        $response->assertStatus(200)
            ->assertJsonCount(0);
    }

    // ── myAttendance ─────────────────────────────────────────────────

    public function test_student_can_view_own_attendance(): void
    {
        Attendance::factory()->present()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-01',
        ]);

        Attendance::factory()->absent()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-02',
        ]);

        $response = $this->actingAs($this->studentUser)
            ->getJson('/api/student/attendance');

        $response->assertStatus(200)
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.present', 1)
            ->assertJsonPath('summary.absent', 1)
            ->assertJsonPath('summary.is_flagged', false);
    }

    public function test_student_attendance_is_flagged_when_absences_reach_threshold(): void
    {
        for ($i = 1; $i <= Attendance::ABSENCE_THRESHOLD; $i++) {
            Attendance::factory()->absent()->create([
                'enrollment_id' => $this->enrollment->id,
                'subject_id' => $this->subject->id,
                'date' => "2026-03-0{$i}",
            ]);
        }

        $response = $this->actingAs($this->studentUser)
            ->getJson('/api/student/attendance');

        $response->assertStatus(200)
            ->assertJsonPath('summary.is_flagged', true);
    }

    public function test_student_cannot_see_other_students_attendance(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('student');
        $otherStudent = $otherUser->student()->first();
        $otherEnrollment = Enrollment::factory()->create([
            'student_id' => $otherStudent->id,
            'section_id' => $this->section->id,
            'grade_level_id' => $this->gradeLevel->id,
            'status' => 'active',
        ]);

        Attendance::factory()->absent()->create([
            'enrollment_id' => $otherEnrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-01',
        ]);

        $response = $this->actingAs($this->studentUser)
            ->getJson('/api/student/attendance');

        $response->assertStatus(200)
            ->assertJsonPath('summary.total', 0)
            ->assertJsonCount(0, 'records');
    }

    // ── Non-teacher cannot access student self routes ─────────────────

    public function test_teacher_cannot_access_student_self_routes(): void
    {
        $this->actingAs($this->teacherUser)
            ->getJson('/api/student/profile')
            ->assertStatus(403);
    }
}