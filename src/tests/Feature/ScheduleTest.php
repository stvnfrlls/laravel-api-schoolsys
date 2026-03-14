<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
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

    private function makeTeacher(): User
    {
        $user = User::factory()->create();
        $user->assignRole('faculty');
        return $user;
    }

    private function schedulePayload(array $overrides = []): array
    {
        return array_merge([
            'section_id' => Section::factory()->create()->id,
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => $this->makeTeacher()->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'school_year' => '2025-2026',
            'semester' => '1st',
        ], $overrides);
    }

    // =========================================================================
    // POST /schedules
    // =========================================================================

    public function test_admin_can_create_a_schedule(): void
    {
        $payload = $this->schedulePayload();

        $this->actingAsRole('admin')
            ->postJson('/api/schedules', $payload)
            ->assertCreated()
            ->assertJsonPath('day', 'monday')
            ->assertJsonPath('start_time', '08:00')
            ->assertJsonPath('end_time', '09:00');
    }

    public function test_sub_admin_can_create_a_schedule(): void
    {
        $this->actingAsRole('sub-admin')
            ->postJson('/api/schedules', $this->schedulePayload())
            ->assertCreated();
    }

    public function test_faculty_cannot_create_a_schedule(): void
    {
        $this->actingAsRole('faculty')
            ->postJson('/api/schedules', $this->schedulePayload())
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_a_schedule(): void
    {
        $this->postJson('/api/schedules', $this->schedulePayload())
            ->assertUnauthorized();
    }

    public function test_cannot_assign_non_faculty_user_as_teacher(): void
    {
        $nonFaculty = User::factory()->create();
        $nonFaculty->assignRole('student');

        $this->actingAsRole('admin')
            ->postJson('/api/schedules', $this->schedulePayload(['teacher_id' => $nonFaculty->id]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The specified user does not have the faculty role.');
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->actingAsRole('admin')
            ->postJson('/api/schedules', $this->schedulePayload([
                'start_time' => '10:00',
                'end_time' => '09:00',    // before start
            ]))
            ->assertUnprocessable();
    }

    public function test_weekend_days_are_rejected(): void
    {
        $this->actingAsRole('admin')
            ->postJson('/api/schedules', $this->schedulePayload(['day' => 'saturday']))
            ->assertUnprocessable();
    }

    // =========================================================================
    // Conflict detection
    // =========================================================================

    public function test_section_conflict_is_blocked(): void
    {
        $section = Section::factory()->create();
        $teacher1 = $this->makeTeacher();
        $teacher2 = $this->makeTeacher();
        $subject = Subject::factory()->create();

        // First schedule: section on monday 08:00–09:00
        Schedule::factory()->create([
            'section_id' => $section->id,
            'teacher_id' => $teacher1->id,
            'subject_id' => $subject->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'school_year' => '2025-2026',
            'semester' => '1st',
        ]);

        // Second schedule: same section, overlapping time slot
        $this->actingAsRole('admin')
            ->postJson('/api/schedules', [
                'section_id' => $section->id,
                'subject_id' => Subject::factory()->create()->id,
                'teacher_id' => $teacher2->id,
                'day' => 'monday',
                'start_time' => '08:30',   // overlaps
                'end_time' => '09:30',
                'school_year' => '2025-2026',
                'semester' => '1st',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The section already has a subject scheduled during this time slot.');
    }

    public function test_teacher_conflict_is_blocked(): void
    {
        $teacher = $this->makeTeacher();

        // First schedule: teacher on monday 08:00–09:00
        Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'school_year' => '2025-2026',
            'semester' => '1st',
        ]);

        // Second schedule: same teacher, different section, overlapping time
        $this->actingAsRole('admin')
            ->postJson('/api/schedules', [
                'section_id' => Section::factory()->create()->id,
                'subject_id' => Subject::factory()->create()->id,
                'teacher_id' => $teacher->id,
                'day' => 'monday',
                'start_time' => '08:30',   // overlaps
                'end_time' => '09:30',
                'school_year' => '2025-2026',
                'semester' => '1st',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The teacher is already assigned to another class during this time slot.');
    }

    public function test_same_teacher_different_day_is_allowed(): void
    {
        $teacher = $this->makeTeacher();

        Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'school_year' => '2025-2026',
            'semester' => '1st',
        ]);

        $this->actingAsRole('admin')
            ->postJson('/api/schedules', $this->schedulePayload([
                'teacher_id' => $teacher->id,
                'day' => 'tuesday',     // different day — no conflict
                'start_time' => '08:00',
                'end_time' => '09:00',
                'school_year' => '2025-2026',
                'semester' => '1st',
            ]))
            ->assertCreated();
    }

    public function test_same_teacher_non_overlapping_time_is_allowed(): void
    {
        $teacher = $this->makeTeacher();

        Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'school_year' => '2025-2026',
            'semester' => '1st',
        ]);

        $this->actingAsRole('admin')
            ->postJson('/api/schedules', $this->schedulePayload([
                'teacher_id' => $teacher->id,
                'day' => 'monday',
                'start_time' => '09:00',   // starts exactly when the other ends — no overlap
                'end_time' => '10:00',
                'school_year' => '2025-2026',
                'semester' => '1st',
            ]))
            ->assertCreated();
    }

    public function test_same_teacher_different_semester_is_allowed(): void
    {
        $teacher = $this->makeTeacher();

        Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'school_year' => '2025-2026',
            'semester' => '1st',
        ]);

        $this->actingAsRole('admin')
            ->postJson('/api/schedules', $this->schedulePayload([
                'teacher_id' => $teacher->id,
                'day' => 'monday',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'school_year' => '2025-2026',
                'semester' => '2nd',     // different semester — no conflict
            ]))
            ->assertCreated();
    }

    // =========================================================================
    // GET /schedules
    // =========================================================================

    public function test_authenticated_user_can_list_schedules(): void
    {
        Schedule::factory()->count(3)->create();

        $this->actingAsRole('student')
            ->getJson('/api/schedules')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'per_page']]);
    }

    public function test_schedules_can_be_filtered_by_teacher(): void
    {
        $teacher = $this->makeTeacher();
        Schedule::factory()->count(2)->create(['teacher_id' => $teacher->id]);
        Schedule::factory()->count(3)->create();

        $response = $this->actingAsRole('admin')
            ->getJson("/api/schedules?teacher_id={$teacher->id}")
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    // =========================================================================
    // GET /schedules/{id}
    // =========================================================================

    public function test_authenticated_user_can_view_a_single_schedule(): void
    {
        $schedule = Schedule::factory()->create();

        $this->actingAsRole('student')
            ->getJson("/api/schedules/{$schedule->id}")
            ->assertOk()
            ->assertJsonPath('id', $schedule->id);
    }

    // =========================================================================
    // PUT /schedules/{id}
    // =========================================================================

    public function test_admin_can_update_a_schedule(): void
    {
        $schedule = Schedule::factory()->create([
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);

        $this->actingAsRole('admin')
            ->putJson("/api/schedules/{$schedule->id}", ['day' => 'wednesday'])
            ->assertOk()
            ->assertJsonPath('day', 'wednesday');
    }

    public function test_update_conflict_is_blocked(): void
    {
        $section = Section::factory()->create();
        $teacher = $this->makeTeacher();

        $existing = Schedule::factory()->create([
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'school_year' => '2025-2026',
            'semester' => '1st',
        ]);

        // A second schedule for the same section on a different slot
        $toUpdate = Schedule::factory()->create([
            'section_id' => $section->id,
            'day' => 'tuesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'school_year' => '2025-2026',
            'semester' => '1st',
        ]);

        // Try to move it into the conflicting slot
        $this->actingAsRole('admin')
            ->putJson("/api/schedules/{$toUpdate->id}", [
                'day' => 'monday',
                'start_time' => '08:30',
                'end_time' => '09:30',
            ])
            ->assertUnprocessable();
    }

    public function test_faculty_cannot_update_a_schedule(): void
    {
        $schedule = Schedule::factory()->create();

        $this->actingAsRole('faculty')
            ->putJson("/api/schedules/{$schedule->id}", ['day' => 'wednesday'])
            ->assertForbidden();
    }

    // =========================================================================
    // DELETE /schedules/{id}
    // =========================================================================

    public function test_admin_can_delete_a_schedule(): void
    {
        $schedule = Schedule::factory()->create();

        $this->actingAsRole('admin')
            ->deleteJson("/api/schedules/{$schedule->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Schedule removed successfully.');

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_sub_admin_cannot_delete_a_schedule(): void
    {
        $schedule = Schedule::factory()->create();

        $this->actingAsRole('sub-admin')
            ->deleteJson("/api/schedules/{$schedule->id}")
            ->assertForbidden();
    }

    // =========================================================================
    // GET /sections/{section}/schedules
    // =========================================================================

    public function test_can_view_schedules_by_section(): void
    {
        $section = Section::factory()->create();
        Schedule::factory()->count(3)->create([
            'section_id' => $section->id,
            'school_year' => '2025-2026',
            'semester' => '1st',
        ]);

        $this->actingAsRole('faculty')
            ->getJson("/api/sections/{$section->id}/schedules?school_year=2025-2026&semester=1st")
            ->assertOk()
            ->assertJsonCount(3);
    }
}