<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\GradingComponent;
use App\Models\StudentGrade;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentGrade>
 */
class StudentGradeFactory extends Factory
{
    protected $model = StudentGrade::class;

    public function definition(): array
    {
        $score = $this->faker->randomFloat(2, 60, 100);
        $weight = 100.00; // default: single component covering full weight

        return [
            'enrollment_id' => Enrollment::factory(),
            'subject_id' => Subject::factory(),
            'grading_component_id' => GradingComponent::factory(),
            'quarter' => $this->faker->numberBetween(1, 4),
            'score' => $score,
            'weighted_score' => round($score * ($weight / 100), 2),
            'final_grade' => round($score * ($weight / 100), 2),
            'is_failing' => $score < 75,
        ];
    }

    public function failing(): static
    {
        return $this->state(function () {
            $score = $this->faker->randomFloat(2, 40, 74);
            return [
                'score' => $score,
                'weighted_score' => $score,
                'final_grade' => $score,
                'is_failing' => true,
            ];
        });
    }

    public function passing(): static
    {
        return $this->state(function () {
            $score = $this->faker->randomFloat(2, 75, 100);
            return [
                'score' => $score,
                'weighted_score' => $score,
                'final_grade' => $score,
                'is_failing' => false,
            ];
        });
    }
}
