<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\GradeLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            // Creates a GradeLevel on the fly if none is provided
            'grade_level_id' => GradeLevel::factory(),
            'name' => 'Section ' . $this->faker->unique()->randomElement(['A', 'B', 'C', 'D', 'E']),
            'room' => $this->faker->numerify('Room ###'),
            'capacity' => $this->faker->numberBetween(30, 50),
            'is_active' => true,
        ];
    }

    // ─── States ───────────────────────────────────────────────────────────────

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }

    public function noRoom(): static
    {
        return $this->state(fn() => ['room' => null]);
    }

    public function withCapacity(int $capacity): static
    {
        return $this->state(fn() => ['capacity' => $capacity]);
    }

    public function forGrade(GradeLevel $grade): static
    {
        return $this->state(fn() => ['grade_level_id' => $grade->id]);
    }
}