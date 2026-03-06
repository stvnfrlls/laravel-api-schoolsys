<?php

namespace Database\Factories;

use App\Models\GradingComponent;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GradingComponent>
 */
class GradingComponentFactory extends Factory
{
    protected $model = GradingComponent::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Written Work',
                'Performance Task',
                'Quarterly Assessment',
                'Recitation',
                'Project',
                'Laboratory',
            ]),
            'code' => strtoupper($this->faker->unique()->lexify('??')),
            'weight' => 25.00,
            'subject_id' => Subject::factory(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
