<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Enrollment $enrollment;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $gradeLevel = GradeLevel::factory()->create();
        $section = Section::factory()->create(['grade_level_id' => $gradeLevel->id]);
        $student = Student::factory()->create();
        $this->subject = Subject::factory()->create();
        $this->enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'section_id' => $section->id,
        ]);
    }

    // ---------------------------------------------------------------
    // RECORD ATTENDANCE
    // ---------------------------------------------------------------

    public function test_can_record_attendance(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/attendance', [
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-07',
            'status' => 'present',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'present']);

        $this->assertDatabaseHas('attendances', [
            'enrollment_id' => $this->enrollment->id,
            'date' => '2026-03-07',
            'status' => 'present',
        ]);
    }

    public function test_status_must_be_valid(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/attendance', [
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-07',
            'status' => 'excused', // not a valid value
        ]);

        $response->assertStatus(422);
    }

    public function test_duplicate_attendance_updates_existing_record(): void
    {
        $payload = [
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-07',
            'status' => 'present',
        ];

        $this->actingAs($this->admin)->postJson('/api/attendance', $payload);
        $this->actingAs($this->admin)->postJson('/api/attendance', array_merge($payload, ['status' => 'late']));

        // Should not create a second row
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', ['status' => 'late']);
    }

    // ---------------------------------------------------------------
    // READ / FILTER
    // ---------------------------------------------------------------

    public function test_can_list_attendance_filtered_by_date(): void
    {
        // Two different enrollments on the same target date
        $otherStudent = Student::factory()->create();
        $otherEnrollment = Enrollment::factory()->create([
            'student_id' => $otherStudent->id,
            'section_id' => $this->enrollment->section_id,
        ]);

        Attendance::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-07',
            'status' => 'present',
        ]);

        Attendance::factory()->create([
            'enrollment_id' => $otherEnrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-07',
            'status' => 'present',
        ]);

        // Different date — should NOT appear in results
        Attendance::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-06',
            'status' => 'absent',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/attendance?date=2026-03-07');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function test_can_filter_by_status(): void
    {
        Attendance::factory()->absent()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-05',
        ]);

        Attendance::factory()->present()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-06',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/attendance?status=absent');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['status' => 'absent']);
    }

    // ---------------------------------------------------------------
    // UPDATE
    // ---------------------------------------------------------------

    public function test_can_update_attendance_status(): void
    {
        $attendance = Attendance::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-07',
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/attendance/{$attendance->id}", ['status' => 'late']);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'late']);
    }

    // ---------------------------------------------------------------
    // DELETE
    // ---------------------------------------------------------------

    public function test_can_delete_attendance_record(): void
    {
        $attendance = Attendance::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-07',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/attendance/{$attendance->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
    }

    // ---------------------------------------------------------------
    // SUMMARY
    // ---------------------------------------------------------------

    public function test_attendance_summary_returns_correct_counts(): void
    {
        // 3 present — explicit unique dates to avoid unique constraint collision
        Attendance::factory()->present()->create(['enrollment_id' => $this->enrollment->id, 'subject_id' => $this->subject->id, 'date' => '2026-03-01']);
        Attendance::factory()->present()->create(['enrollment_id' => $this->enrollment->id, 'subject_id' => $this->subject->id, 'date' => '2026-03-02']);
        Attendance::factory()->present()->create(['enrollment_id' => $this->enrollment->id, 'subject_id' => $this->subject->id, 'date' => '2026-03-03']);

        // 2 absent
        Attendance::factory()->absent()->create(['enrollment_id' => $this->enrollment->id, 'subject_id' => $this->subject->id, 'date' => '2026-03-04']);
        Attendance::factory()->absent()->create(['enrollment_id' => $this->enrollment->id, 'subject_id' => $this->subject->id, 'date' => '2026-03-05']);

        // 1 late
        Attendance::factory()->late()->create(['enrollment_id' => $this->enrollment->id, 'subject_id' => $this->subject->id, 'date' => '2026-03-06']);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/attendance/summary/{$this->enrollment->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'total' => 6,
                'present' => 3,
                'absent' => 2,
                'late' => 1,
                'is_flagged' => false, // 2 absences < threshold of 5
            ]);
    }

    // ---------------------------------------------------------------
    // FLAGGED — EXCESSIVE ABSENCES
    // ---------------------------------------------------------------

    public function test_student_is_flagged_when_absences_reach_threshold(): void
    {
        // Create exactly ABSENCE_THRESHOLD (5) absent records
        for ($i = 1; $i <= Attendance::ABSENCE_THRESHOLD; $i++) {
            Attendance::factory()->absent()->create([
                'enrollment_id' => $this->enrollment->id,
                'subject_id' => $this->subject->id,
                'date' => "2026-03-0{$i}",
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->getJson("/api/attendance/summary/{$this->enrollment->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['is_flagged' => true]);
    }

    public function test_flagged_endpoint_returns_students_with_excessive_absences(): void
    {
        // This enrollment hits the threshold
        for ($i = 1; $i <= Attendance::ABSENCE_THRESHOLD; $i++) {
            Attendance::factory()->absent()->create([
                'enrollment_id' => $this->enrollment->id,
                'subject_id' => $this->subject->id,
                'date' => "2026-03-0{$i}",
            ]);
        }

        // Another enrollment with only 1 absence — should NOT appear
        $otherEnrollment = Enrollment::factory()->create();
        Attendance::factory()->absent()->create([
            'enrollment_id' => $otherEnrollment->id,
            'subject_id' => $this->subject->id,
            'date' => '2026-03-01',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/attendance/flagged');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $this->enrollment->id]);
    }

    public function test_student_not_flagged_below_threshold(): void
    {
        // 4 absences — one below threshold
        for ($i = 1; $i <= Attendance::ABSENCE_THRESHOLD - 1; $i++) {
            Attendance::factory()->absent()->create([
                'enrollment_id' => $this->enrollment->id,
                'subject_id' => $this->subject->id,
                'date' => "2026-03-0{$i}",
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->getJson("/api/attendance/summary/{$this->enrollment->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['is_flagged' => false]);
    }
}