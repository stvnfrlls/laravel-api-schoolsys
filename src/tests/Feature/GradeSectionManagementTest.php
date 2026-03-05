<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\GradeLevel;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GradeSectionManagementTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function actingAsRole(string $role): static
    {
        $user = User::factory()->create();
        $user->assignRole($role); // assumes Spatie permissions
        return $this->actingAs($user);
    }

    // =========================================================================
    // UNAUTHENTICATED
    // =========================================================================

    /** @test */
    public function unauthenticated_user_cannot_access_grade_level_endpoints(): void
    {
        $grade = GradeLevel::factory()->create();

        $this->getJson('/api/grade-levels')->assertUnauthorized();
        $this->postJson('/api/grade-levels', [])->assertUnauthorized();
        $this->getJson("/api/grade-levels/{$grade->id}")->assertUnauthorized();
        $this->putJson("/api/grade-levels/{$grade->id}", [])->assertUnauthorized();
        $this->patchJson("/api/grade-levels/{$grade->id}/activate")->assertUnauthorized();
        $this->patchJson("/api/grade-levels/{$grade->id}/deactivate")->assertUnauthorized();
        $this->deleteJson("/api/grade-levels/{$grade->id}")->assertUnauthorized();
    }

    /** @test */
    public function unauthenticated_user_cannot_access_section_endpoints(): void
    {
        $section = Section::factory()->create();

        $this->getJson('/api/sections')->assertUnauthorized();
        $this->postJson('/api/sections', [])->assertUnauthorized();
        $this->getJson("/api/sections/{$section->id}")->assertUnauthorized();
        $this->putJson("/api/sections/{$section->id}", [])->assertUnauthorized();
        $this->patchJson("/api/sections/{$section->id}/activate")->assertUnauthorized();
        $this->patchJson("/api/sections/{$section->id}/deactivate")->assertUnauthorized();
        $this->deleteJson("/api/sections/{$section->id}")->assertUnauthorized();
    }

    // =========================================================================
    // READ — all authenticated roles
    // =========================================================================

    /**
     * @test
     * @dataProvider allRoles
     */
    public function any_authenticated_user_can_list_grade_levels(string $role): void
    {
        GradeLevel::factory()->count(3)->create();

        $this->actingAsRole($role)
            ->getJson('/api/grade-levels')
            ->assertOk()
            ->assertJsonCount(3);
    }

    /**
     * @test
     * @dataProvider allRoles
     */
    public function any_authenticated_user_can_view_a_grade_level(string $role): void
    {
        $grade = GradeLevel::factory()->level(7)->create();

        $this->actingAsRole($role)
            ->getJson("/api/grade-levels/{$grade->id}")
            ->assertOk()
            ->assertJsonFragment(['level' => 7, 'name' => 'Grade 7']);
    }

    /**
     * @test
     * @dataProvider allRoles
     */
    public function any_authenticated_user_can_list_sections(string $role): void
    {
        $grade = GradeLevel::factory()->create();
        Section::factory()->forGrade($grade)->count(3)->create();

        $this->actingAsRole($role)
            ->getJson('/api/sections')
            ->assertOk()
            ->assertJsonCount(3);
    }

    /**
     * @test
     * @dataProvider allRoles
     */
    public function any_authenticated_user_can_view_a_section(string $role): void
    {
        $section = Section::factory()->create();

        $this->actingAsRole($role)
            ->getJson("/api/sections/{$section->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $section->id]);
    }

    public static function allRoles(): array
    {
        return [
            'student' => ['student'],
            'faculty' => ['faculty'],
            'sub-admin' => ['sub-admin'],
            'admin' => ['admin'],
        ];
    }

    // =========================================================================
    // GRADE LEVELS — admin only
    // =========================================================================

    /** @test */
    public function admin_can_create_a_grade_level(): void
    {
        $this->actingAsRole('admin')
            ->postJson('/api/grade-levels', ['name' => 'Grade 7', 'level' => 7])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Grade 7', 'level' => 7]);

        $this->assertDatabaseHas('grade_levels', ['level' => 7]);
    }

    /** @test */
    public function creating_grade_level_requires_name_and_level(): void
    {
        $this->actingAsRole('admin')
            ->postJson('/api/grade-levels', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'level']);
    }

    /** @test */
    public function creating_a_duplicate_grade_level_fails(): void
    {
        GradeLevel::factory()->level(7)->create();

        $this->actingAsRole('admin')
            ->postJson('/api/grade-levels', ['name' => 'Grade 7 Copy', 'level' => 7])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['level']);
    }

    /** @test */
    public function admin_can_update_a_grade_level(): void
    {
        $grade = GradeLevel::factory()->level(7)->create();

        $this->actingAsRole('admin')
            ->putJson("/api/grade-levels/{$grade->id}", ['name' => 'Grade 7 Renamed'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Grade 7 Renamed']);
    }

    /** @test */
    public function admin_can_activate_an_inactive_grade_level(): void
    {
        $grade = GradeLevel::factory()->inactive()->create();

        $this->actingAsRole('admin')
            ->patchJson("/api/grade-levels/{$grade->id}/activate")
            ->assertOk();

        $this->assertTrue($grade->fresh()->is_active);
    }

    /** @test */
    public function admin_can_deactivate_a_grade_level(): void
    {
        $grade = GradeLevel::factory()->create();

        $this->actingAsRole('admin')
            ->patchJson("/api/grade-levels/{$grade->id}/deactivate")
            ->assertOk();

        $this->assertFalse($grade->fresh()->is_active);
    }

    /** @test */
    public function deactivating_a_grade_level_cascades_to_its_sections(): void
    {
        $grade = GradeLevel::factory()->create();
        $sections = Section::factory()->forGrade($grade)->count(3)->create();

        $this->actingAsRole('admin')
            ->patchJson("/api/grade-levels/{$grade->id}/deactivate")
            ->assertOk();

        foreach ($sections as $section) {
            $this->assertFalse(
                $section->fresh()->is_active,
                "Section {$section->name} should have been deactivated."
            );
        }
    }

    /** @test */
    public function admin_can_soft_delete_a_grade_level_with_no_active_sections(): void
    {
        $grade = GradeLevel::factory()->inactive()->create();

        $this->actingAsRole('admin')
            ->deleteJson("/api/grade-levels/{$grade->id}")
            ->assertOk();

        $this->assertSoftDeleted('grade_levels', ['id' => $grade->id]);
    }

    /** @test */
    public function admin_cannot_delete_a_grade_level_that_has_active_sections(): void
    {
        $grade = GradeLevel::factory()->create();
        Section::factory()->forGrade($grade)->create();

        $this->actingAsRole('admin')
            ->deleteJson("/api/grade-levels/{$grade->id}")
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Cannot delete a grade level with active sections. Deactivate first.',
            ]);
    }

    /**
     * @test
     * @dataProvider nonAdminRoles
     */
    public function non_admin_cannot_create_a_grade_level(string $role): void
    {
        $this->actingAsRole($role)
            ->postJson('/api/grade-levels', ['name' => 'Grade 7', 'level' => 7])
            ->assertForbidden();
    }

    /**
     * @test
     * @dataProvider nonAdminRoles
     */
    public function non_admin_cannot_update_a_grade_level(string $role): void
    {
        $grade = GradeLevel::factory()->create();

        $this->actingAsRole($role)
            ->putJson("/api/grade-levels/{$grade->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    /**
     * @test
     * @dataProvider nonAdminRoles
     */
    public function non_admin_cannot_activate_or_deactivate_a_grade_level(string $role): void
    {
        $grade = GradeLevel::factory()->inactive()->create();

        $this->actingAsRole($role)
            ->patchJson("/api/grade-levels/{$grade->id}/activate")
            ->assertForbidden();

        $this->actingAsRole($role)
            ->patchJson("/api/grade-levels/{$grade->id}/deactivate")
            ->assertForbidden();
    }

    /**
     * @test
     * @dataProvider nonAdminRoles
     */
    public function non_admin_cannot_delete_a_grade_level(string $role): void
    {
        $grade = GradeLevel::factory()->create();

        $this->actingAsRole($role)
            ->deleteJson("/api/grade-levels/{$grade->id}")
            ->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return [
            'student' => ['student'],
            'faculty' => ['faculty'],
            'sub-admin' => ['sub-admin'],
        ];
    }

    // =========================================================================
    // SECTIONS — sub-admin + admin write, admin-only delete
    // =========================================================================

    /**
     * @test
     * @dataProvider sectionWriteRoles
     */
    public function privileged_roles_can_create_a_section(string $role): void
    {
        $grade = GradeLevel::factory()->create();

        $this->actingAsRole($role)
            ->postJson('/api/sections', [
                'grade_level_id' => $grade->id,
                'name' => 'Section A',
                'room' => 'Room 101',
                'capacity' => 40,
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Section A', 'room' => 'Room 101', 'capacity' => 40]);

        $this->assertDatabaseHas('sections', [
            'grade_level_id' => $grade->id,
            'name' => 'Section A',
        ]);
    }

    /** @test */
    public function creating_a_section_requires_grade_level_id_and_name(): void
    {
        $this->actingAsRole('admin')
            ->postJson('/api/sections', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['grade_level_id', 'name']);
    }

    /** @test */
    public function cannot_create_duplicate_section_name_within_the_same_grade(): void
    {
        $grade = GradeLevel::factory()->create();
        Section::factory()->forGrade($grade)->create(['name' => 'Section A']);

        $this->actingAsRole('admin')
            ->postJson('/api/sections', [
                'grade_level_id' => $grade->id,
                'name' => 'Section A',
            ])
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => "A section named 'Section A' already exists in this grade level.",
            ]);
    }

    /** @test */
    public function same_section_name_is_allowed_in_different_grade_levels(): void
    {
        $grade1 = GradeLevel::factory()->level(7)->create();
        $grade2 = GradeLevel::factory()->level(8)->create();
        Section::factory()->forGrade($grade1)->create(['name' => 'Section A']);

        $this->actingAsRole('admin')
            ->postJson('/api/sections', [
                'grade_level_id' => $grade2->id,
                'name' => 'Section A',
            ])
            ->assertCreated();
    }

    /** @test */
    public function cannot_create_a_section_under_an_inactive_grade_level(): void
    {
        $grade = GradeLevel::factory()->inactive()->create();

        $this->actingAsRole('admin')
            ->postJson('/api/sections', [
                'grade_level_id' => $grade->id,
                'name' => 'Section A',
            ])
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Cannot add a section to an inactive grade level.',
            ]);
    }

    /**
     * @test
     * @dataProvider sectionWriteRoles
     */
    public function privileged_roles_can_update_a_section(string $role): void
    {
        $section = Section::factory()->create();

        $this->actingAsRole($role)
            ->putJson("/api/sections/{$section->id}", ['room' => 'Room 202', 'capacity' => 35])
            ->assertOk()
            ->assertJsonFragment(['room' => 'Room 202', 'capacity' => 35]);
    }

    /**
     * @test
     * @dataProvider sectionWriteRoles
     */
    public function privileged_roles_can_activate_a_section_under_active_grade(string $role): void
    {
        $grade = GradeLevel::factory()->create();
        $section = Section::factory()->forGrade($grade)->inactive()->create();

        $this->actingAsRole($role)
            ->patchJson("/api/sections/{$section->id}/activate")
            ->assertOk();

        $this->assertTrue($section->fresh()->is_active);
    }

    /** @test */
    public function cannot_activate_a_section_when_its_grade_level_is_inactive(): void
    {
        $grade = GradeLevel::factory()->inactive()->create();
        $section = Section::factory()->forGrade($grade)->inactive()->create();

        $this->actingAsRole('admin')
            ->patchJson("/api/sections/{$section->id}/activate")
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Cannot activate a section whose grade level is inactive.',
            ]);
    }

    /**
     * @test
     * @dataProvider sectionWriteRoles
     */
    public function privileged_roles_can_deactivate_a_section(string $role): void
    {
        $section = Section::factory()->create();

        $this->actingAsRole($role)
            ->patchJson("/api/sections/{$section->id}/deactivate")
            ->assertOk();

        $this->assertFalse($section->fresh()->is_active);
    }

    /** @test */
    public function admin_can_delete_a_section(): void
    {
        $section = Section::factory()->create();

        $this->actingAsRole('admin')
            ->deleteJson("/api/sections/{$section->id}")
            ->assertOk();

        $this->assertSoftDeleted('sections', ['id' => $section->id]);
    }

    /** @test */
    public function sub_admin_cannot_delete_a_section(): void
    {
        $section = Section::factory()->create();

        $this->actingAsRole('sub-admin')
            ->deleteJson("/api/sections/{$section->id}")
            ->assertForbidden();
    }

    /**
     * @test
     * @dataProvider lowPrivilegeRoles
     */
    public function low_privilege_roles_cannot_write_sections(string $role): void
    {
        $grade = GradeLevel::factory()->create();
        $section = Section::factory()->forGrade($grade)->create();

        $this->actingAsRole($role)
            ->postJson('/api/sections', ['grade_level_id' => $grade->id, 'name' => 'Section X'])
            ->assertForbidden();

        $this->actingAsRole($role)
            ->putJson("/api/sections/{$section->id}", ['room' => 'Room 999'])
            ->assertForbidden();

        $this->actingAsRole($role)
            ->patchJson("/api/sections/{$section->id}/activate")
            ->assertForbidden();

        $this->actingAsRole($role)
            ->patchJson("/api/sections/{$section->id}/deactivate")
            ->assertForbidden();

        $this->actingAsRole($role)
            ->deleteJson("/api/sections/{$section->id}")
            ->assertForbidden();
    }

    public static function sectionWriteRoles(): array
    {
        return [
            'sub-admin' => ['sub-admin'],
            'admin' => ['admin'],
        ];
    }

    public static function lowPrivilegeRoles(): array
    {
        return [
            'student' => ['student'],
            'faculty' => ['faculty'],
        ];
    }

    // =========================================================================
    // FILTERING
    // =========================================================================

    /** @test */
    public function sections_can_be_filtered_by_grade_level(): void
    {
        $grade1 = GradeLevel::factory()->level(7)->create();
        $grade2 = GradeLevel::factory()->level(8)->create();

        Section::factory()->forGrade($grade1)->count(3)->create();
        Section::factory()->forGrade($grade2)->count(2)->create();

        $this->actingAsRole('admin')
            ->getJson("/api/sections?grade_level_id={$grade1->id}")
            ->assertOk()
            ->assertJsonCount(3);
    }

    /** @test */
    public function section_list_without_filter_returns_all_sections(): void
    {
        $grade1 = GradeLevel::factory()->level(7)->create();
        $grade2 = GradeLevel::factory()->level(8)->create();

        Section::factory()->forGrade($grade1)->count(2)->create();
        Section::factory()->forGrade($grade2)->count(2)->create();

        $this->actingAsRole('admin')
            ->getJson('/api/sections')
            ->assertOk()
            ->assertJsonCount(4);
    }
}