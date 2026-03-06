<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        $startYear = $this->faker->numberBetween(2022, 2025);

        return [
            'student_id' => Student::factory(),       // students.id — NOT users.id
            'section_id' => Section::factory(),
            'grade_level_id' => GradeLevel::factory(),
            'school_year' => "{$startYear}-" . ($startYear + 1),
            'semester' => $this->faker->randomElement(['1st', '2nd', 'summer']),
            'status' => 'active',
        ];
    }

    public function dropped(): static
    {
        return $this->state(['status' => 'dropped']);
    }
    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }
}
