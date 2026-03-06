<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        $startHour = $this->faker->numberBetween(7, 16);
        $startTime = sprintf('%02d:00', $startHour);
        $endTime = sprintf('%02d:00', $startHour + 1);
        $startYear = $this->faker->numberBetween(2022, 2025);

        return [
            'section_id' => Section::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory()->create()->user_id,
            'day' => $this->faker->randomElement(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'school_year' => "{$startYear}-" . ($startYear + 1),
            'semester' => $this->faker->randomElement(['1st', '2nd', 'summer']),
        ];
    }
}