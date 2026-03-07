<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'student_number' => 'LRN-' . $this->faker->unique()->numerify('##########'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'middle_name' => $this->faker->optional(0.7)->lastName(), // 70% chance of having one
            'suffix' => $this->faker->optional(0.1)->randomElement(['Jr.', 'Sr.', 'II', 'III']),
            'date_of_birth' => $this->faker->dateTimeBetween('-20 years', '-10 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(['male', 'female', 'other']),
        ];
    }
}