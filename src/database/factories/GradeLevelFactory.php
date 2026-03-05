<?php

namespace Database\Factories;

use App\Models\GradeLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeLevelFactory extends Factory
{
    protected $model = GradeLevel::class;

    public function definition(): array
    {
        return [
            // unique() resets automatically per test with RefreshDatabase
            // because Faker's unique generator is re-instantiated each test
            'level' => $this->faker->unique()->numberBetween(1, 99),
            'name' => fn(array $attrs) => "Grade {$attrs['level']}",
            'is_active' => true,
        ];
    }

    // ─── States ───────────────────────────────────────────────────────────────

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }

    public function level(int $level): static
    {
        return $this->state(fn() => [
            'level' => $level,
            'name' => "Grade {$level}",
        ]);
    }
}