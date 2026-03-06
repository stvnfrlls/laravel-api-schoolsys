<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'subject_id' => Subject::factory(),
            'date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'status' => $this->faker->randomElement(['present', 'absent', 'late']),
            'remarks' => $this->faker->optional()->sentence(),
        ];
    }

    public function present(): static
    {
        return $this->state(['status' => 'present']);
    }

    public function absent(): static
    {
        return $this->state(['status' => 'absent']);
    }

    public function late(): static
    {
        return $this->state(['status' => 'late']);
    }
}