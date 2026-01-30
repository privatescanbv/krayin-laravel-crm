<?php

namespace Database\Factories;

use App\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Address::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'street'              => $this->faker->streetName(),
            'house_number'        => $this->faker->buildingNumber(),
            'house_number_suffix' => $this->faker->optional()->randomLetter(),
            'postal_code'         => $this->faker->postcode(),
            'city'                => $this->faker->city(),
            'state'               => $this->faker->state(),
            'country'             => $this->faker->country(),
            'created_by'          => null, // Will be set by audit trail if user is authenticated
            'updated_by'          => null, // Will be set by audit trail if user is authenticated
        ];
    }
}
