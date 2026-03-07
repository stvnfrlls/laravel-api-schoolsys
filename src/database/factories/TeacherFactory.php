<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_number' => 'EMP-' . $this->faker->unique()->numerify('########'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'middle_name' => $this->faker->optional(0.7)->lastName(),
            'suffix' => $this->faker->optional(0.1)->randomElement(['Jr.', 'Sr.', 'II', 'III']),
            'date_of_birth' => $this->faker->dateTimeBetween('-60 years', '-22 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(['male', 'female', 'other']),
        ];
    }
}