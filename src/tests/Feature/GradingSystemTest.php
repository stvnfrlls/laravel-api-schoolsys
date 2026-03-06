<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\GradingComponent;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Subject $subject;
    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user and assign role — this is what was missing before
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
    // GRADING COMPONENTS
    // ---------------------------------------------------------------

    public function test_can_create_grading_component(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/grading-components', [
            'name' => 'Written Work',
            'code' => 'WW',
            'weight' => 25,
            'subject_id' => $this->subject->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['code' => 'WW', 'weight' => '25.00']);

        $this->assertDatabaseHas('grading_components', ['code' => 'WW']);
    }

    public function test_total_weight_cannot_exceed_100_percent(): void
    {
        GradingComponent::factory()->create([
            'subject_id' => $this->subject->id,
            'weight' => 80,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/grading-components', [
            'name' => 'Performance Task',
            'code' => 'PT',
            'weight' => 30, // 80 + 30 = 110 — should fail
            'subject_id' => $this->subject->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Total weight for this subject would exceed 100%.']);
    }

    public function test_can_list_grading_components_by_subject(): void
    {
        GradingComponent::factory()->count(3)->create(['subject_id' => $this->subject->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/grading-components?subject_id=' . $this->subject->id);

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_can_update_grading_component(): void
    {
        $component = GradingComponent::factory()->create([
            'subject_id' => $this->subject->id,
            'weight' => 25,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/grading-components/{$component->id}", ['weight' => 30]);

        $response->assertStatus(200)
            ->assertJsonFragment(['weight' => '30.00']);
    }

    public function test_can_delete_grading_component(): void
    {
        $component = GradingComponent::factory()->create(['subject_id' => $this->subject->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/grading-components/{$component->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('grading_components', ['id' => $component->id]);
    }

    // ---------------------------------------------------------------
    // STUDENT GRADES
    // ---------------------------------------------------------------

    public function test_can_input_grade_for_student(): void
    {
        $component = GradingComponent::factory()->create([
            'subject_id' => $this->subject->id,
            'weight' => 25,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/student-grades', [
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'grading_component_id' => $component->id,
            'quarter' => 1,
            'score' => 80,
        ]);

        // weighted_score = 80 * (25/100) = 20.00
        $response->assertStatus(201)
            ->assertJsonFragment(['score' => '80.00', 'weighted_score' => '20.00']);
    }

    public function test_weighted_score_is_computed_correctly(): void
    {
        // WW=25%, PT=50%, QA=25% — total = 100%
        $ww = GradingComponent::factory()->create(['subject_id' => $this->subject->id, 'weight' => 25, 'code' => 'WW']);
        $pt = GradingComponent::factory()->create(['subject_id' => $this->subject->id, 'weight' => 50, 'code' => 'PT']);
        $qa = GradingComponent::factory()->create(['subject_id' => $this->subject->id, 'weight' => 25, 'code' => 'QA']);

        $base = [
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'quarter' => 1,
        ];

        $this->actingAs($this->admin)->postJson('/api/student-grades', array_merge($base, ['grading_component_id' => $ww->id, 'score' => 80]));
        $this->actingAs($this->admin)->postJson('/api/student-grades', array_merge($base, ['grading_component_id' => $pt->id, 'score' => 90]));
        $this->actingAs($this->admin)->postJson('/api/student-grades', array_merge($base, ['grading_component_id' => $qa->id, 'score' => 70]));

        // Final = (80*0.25) + (90*0.50) + (70*0.25) = 20 + 45 + 17.5 = 82.5
        $grade = StudentGrade::where('enrollment_id', $this->enrollment->id)
            ->where('grading_component_id', $qa->id)
            ->first();

        $this->assertEquals(82.50, (float) $grade->final_grade);
        $this->assertFalse((bool) $grade->is_failing);
    }

    public function test_failing_grade_is_flagged(): void
    {
        $component = GradingComponent::factory()->create([
            'subject_id' => $this->subject->id,
            'weight' => 100,
        ]);

        $this->actingAs($this->admin)->postJson('/api/student-grades', [
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'grading_component_id' => $component->id,
            'quarter' => 1,
            'score' => 60, // weighted = 60 * 1.0 = 60.00 → failing
        ]);

        $grade = StudentGrade::first();
        $this->assertTrue((bool) $grade->is_failing);
        $this->assertEquals(60.00, (float) $grade->final_grade);
    }

    public function test_duplicate_grade_entry_is_updated_not_duplicated(): void
    {
        $component = GradingComponent::factory()->create([
            'subject_id' => $this->subject->id,
            'weight' => 100,
        ]);

        $payload = [
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'grading_component_id' => $component->id,
            'quarter' => 1,
            'score' => 80,
        ];

        $this->actingAs($this->admin)->postJson('/api/student-grades', $payload);
        $this->actingAs($this->admin)->postJson('/api/student-grades', array_merge($payload, ['score' => 90]));

        $this->assertDatabaseCount('student_grades', 1);
        $this->assertDatabaseHas('student_grades', ['score' => 90]);
    }

    public function test_score_must_be_between_0_and_100(): void
    {
        $component = GradingComponent::factory()->create([
            'subject_id' => $this->subject->id,
            'weight' => 25,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/student-grades', [
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'grading_component_id' => $component->id,
            'quarter' => 1,
            'score' => 150, // invalid
        ]);

        $response->assertStatus(422);
    }

    public function test_can_filter_failing_students(): void
    {
        $component = GradingComponent::factory()->create([
            'subject_id' => $this->subject->id,
            'weight' => 100,
        ]);

        StudentGrade::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'grading_component_id' => $component->id,
            'score' => 60,
            'weighted_score' => 60,
            'final_grade' => 60,
            'is_failing' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/student-grades?is_failing=1');

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }
}