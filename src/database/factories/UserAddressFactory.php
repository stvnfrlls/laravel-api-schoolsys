<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserAddress>
 */
class UserAddressFactory extends Factory
{
    protected $model = UserAddress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'street' => $this->faker->buildingNumber() . ' ' . $this->faker->streetName(),
            'barangay' => 'Barangay ' . $this->faker->word(),
            'city' => $this->faker->city(),
            'province' => $this->faker->state(),
            'region' => 'Region ' . $this->faker->randomElement(['I', 'II', 'III', 'IV-A', 'V', 'VI', 'VII', 'NCR']),
            'zip_code' => $this->faker->numerify('####'),
        ];
    }

    public function blank(): static
    {
        return $this->state([
            'street' => null,
            'barangay' => null,
            'city' => null,
            'province' => null,
            'region' => null,
            'zip_code' => null,
        ]);
    }
}
