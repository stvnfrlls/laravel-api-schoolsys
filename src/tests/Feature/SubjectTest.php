<?php

namespace Tests\Feature;

use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectTest extends TestCase
{
    use RefreshDatabase;

    private GradeLevel $gradeLevel;

    private function actingAsRole(string $role): static
    {
        $user = User::factory()->create();
        $user->assignRole($role); // assumes Spatie permissions
        return $this->actingAs($user);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->gradeLevel = GradeLevel::create([
            'name' => 'Grade 7',
            'level' => 7,
        ]);

        $this->actingAsRole('admin');
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function test_can_create_a_subject(): void
    {
        $this->postJson('/api/subjects', [
            'name' => 'Mathematics',
            'code' => 'MATH',
            'description' => 'Core math subject.',
        ])->assertCreated();

        $this->assertDatabaseHas('subjects', [
            'name' => 'Mathematics',
            'code' => 'MATH',
            'is_active' => true,
        ]);
    }

    public function test_subject_code_is_uppercased_on_create(): void
    {
        $this->postJson('/api/subjects', [
            'name' => 'Science',
            'code' => 'sci101',
        ])->assertCreated()
            ->assertJsonFragment(['code' => 'SCI101']);
    }

    public function test_subject_code_must_be_unique(): void
    {
        Subject::factory()->create(['code' => 'MATH']);

        $this->postJson('/api/subjects', [
            'name' => 'Mathematics Advanced',
            'code' => 'MATH',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_requires_name_and_code(): void
    {
        $this->postJson('/api/subjects', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code']);
    }

    // =========================================================================
    // READ
    // =========================================================================

    public function test_can_list_all_subjects(): void
    {
        Subject::factory()->count(3)->create(['is_active' => true]);
        Subject::factory()->count(2)->create(['is_active' => false]);

        $this->getJson('/api/subjects')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_can_filter_subjects_by_active_status(): void
    {
        Subject::factory()->count(3)->create(['is_active' => true]);
        Subject::factory()->count(2)->create(['is_active' => false]);

        $this->getJson('/api/subjects?status=active')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/subjects?status=inactive')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_get_a_single_subject(): void
    {
        $subject = Subject::factory()->create(['name' => 'English']);

        $this->getJson("/api/subjects/{$subject->id}")
            ->assertOk()
            ->assertJsonFragment(['name' => 'English']);
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function test_can_update_subject_name_and_description(): void
    {
        $subject = Subject::factory()->create(['name' => 'Old Name']);

        $this->putJson("/api/subjects/{$subject->id}", [
            'name' => 'New Name',
            'description' => 'Updated description.',
        ])->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => 'New Name']);
    }

    public function test_can_update_code_to_its_own_code(): void
    {
        $subject = Subject::factory()->create(['code' => 'MATH']);

        $this->putJson("/api/subjects/{$subject->id}", ['code' => 'MATH'])
            ->assertOk();
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    public function test_can_delete_unassigned_subject(): void
    {
        $subject = Subject::factory()->create();

        $this->deleteJson("/api/subjects/{$subject->id}")
            ->assertOk();

        $this->assertSoftDeleted('subjects', ['id' => $subject->id]);
    }

    public function test_cannot_delete_subject_assigned_to_grade_level(): void
    {
        $subject = Subject::factory()->create(['is_active' => true]);
        $subject->gradeLevels()->attach($this->gradeLevel->id, [
            'units' => 1.5,
            'hours_per_week' => 5,
        ]);

        $this->deleteJson("/api/subjects/{$subject->id}")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete a subject assigned to a grade level.']);
    }

    // =========================================================================
    // ACTIVATE / DEACTIVATE
    // =========================================================================

    public function test_can_activate_an_inactive_subject(): void
    {
        $subject = Subject::factory()->create(['is_active' => false]);

        $this->patchJson("/api/subjects/{$subject->id}/activate")
            ->assertOk()
            ->assertJsonFragment(['is_active' => true]);
    }

    public function test_can_deactivate_an_active_subject(): void
    {
        $subject = Subject::factory()->create(['is_active' => true]);

        $this->patchJson("/api/subjects/{$subject->id}/deactivate")
            ->assertOk()
            ->assertJsonFragment(['is_active' => false]);
    }

    // =========================================================================
    // GRADE LEVEL ASSIGNMENT
    // =========================================================================

    public function test_can_assign_subject_to_grade_level(): void
    {
        $subject = Subject::factory()->create(['is_active' => true]);

        $this->postJson("/api/subjects/{$subject->id}/grade-levels", [
            'grade_level_id' => $this->gradeLevel->id,
            'units' => 1.5,
            'hours_per_week' => 5,
        ])->assertOk();

        $this->assertDatabaseHas('grade_level_subjects', [
            'subject_id' => $subject->id,
            'grade_level_id' => $this->gradeLevel->id,
            'units' => 1.5,
            'hours_per_week' => 5,
        ]);
    }

    public function test_assigning_same_subject_twice_updates_instead_of_duplicate(): void
    {
        $subject = Subject::factory()->create(['is_active' => true]);

        $this->postJson("/api/subjects/{$subject->id}/grade-levels", [
            'grade_level_id' => $this->gradeLevel->id,
            'units' => 1.5,
            'hours_per_week' => 5,
        ])->assertOk();

        $this->postJson("/api/subjects/{$subject->id}/grade-levels", [
            'grade_level_id' => $this->gradeLevel->id,
            'units' => 3.0,
            'hours_per_week' => 10,
        ])->assertOk();

        $this->assertDatabaseCount('grade_level_subjects', 1);
        $this->assertDatabaseHas('grade_level_subjects', [
            'subject_id' => $subject->id,
            'grade_level_id' => $this->gradeLevel->id,
            'units' => 3.0,
            'hours_per_week' => 10,
        ]);
    }

    public function test_cannot_assign_inactive_subject_to_grade_level(): void
    {
        $subject = Subject::factory()->create(['is_active' => false]);

        $this->postJson("/api/subjects/{$subject->id}/grade-levels", [
            'grade_level_id' => $this->gradeLevel->id,
            'units' => 1.5,
            'hours_per_week' => 5,
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot assign an inactive subject to a grade level.']);
    }

    public function test_can_remove_subject_from_grade_level(): void
    {
        $subject = Subject::factory()->create(['is_active' => true]);
        $subject->gradeLevels()->attach($this->gradeLevel->id, [
            'units' => 1.5,
            'hours_per_week' => 5,
        ]);

        $this->deleteJson(
            "/api/subjects/{$subject->id}/grade-levels/{$this->gradeLevel->id}"
        )->assertOk();

        $this->assertDatabaseMissing('grade_level_subjects', [
            'subject_id' => $subject->id,
            'grade_level_id' => $this->gradeLevel->id,
        ]);
    }

    public function test_can_assign_subject_to_multiple_grade_levels(): void
    {
        $grade8 = GradeLevel::create(['name' => 'Grade 8', 'level' => 8]);
        $subject = Subject::factory()->create(['is_active' => true]);

        $subject->gradeLevels()->attach($this->gradeLevel->id, ['units' => 1.5, 'hours_per_week' => 5]);
        $subject->gradeLevels()->attach($grade8->id, ['units' => 2.0, 'hours_per_week' => 6]);

        $this->assertCount(2, $subject->fresh()->gradeLevels);
    }

    public function test_can_filter_subjects_by_grade_level(): void
    {
        $grade8 = GradeLevel::create(['name' => 'Grade 8', 'level' => 8]);
        $subjectA = Subject::factory()->create(['is_active' => true]);
        $subjectB = Subject::factory()->create(['is_active' => true]);

        $subjectA->gradeLevels()->attach($this->gradeLevel->id, ['units' => 1.5, 'hours_per_week' => 5]);
        $subjectB->gradeLevels()->attach($grade8->id, ['units' => 1.5, 'hours_per_week' => 5]);

        $this->getJson("/api/subjects?grade_level_id={$this->gradeLevel->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_assign_requires_valid_grade_level_id(): void
    {
        $subject = Subject::factory()->create(['is_active' => true]);

        $this->postJson("/api/subjects/{$subject->id}/grade-levels", [
            'grade_level_id' => 9999,
            'units' => 1.5,
            'hours_per_week' => 5,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['grade_level_id']);
    }
}