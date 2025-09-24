<?php

namespace Webkul\Contact\Database\Factories;

use App\Models\Address;
use App\Enums\ContactLabel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;

class PersonFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Person::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name'            => $this->faker->name(),
            'emails'          => [['value' => $this->faker->unique()->safeEmail(), 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
            'phones' => [], // Empty by default to avoid conflicts in tests
        ];
    }

    /**
     * Indicate that the person should have an address.
     */
    public function withAddress(): static
    {
        return $this->afterCreating(function (Person $person) {
            Address::factory()->forPerson($person)->create();
        });
    }

    /**
     * Indicate that the person should have an organization.
     */
    public function withOrganisation(string $name): static
    {
        return $this->afterCreating(function (Person $person) use ($name) {
            $organization = Organization::factory()->create([
                'name' => $name,
            ]);
            $person->organization()->associate($organization);
            $person->save();
        });
    }
}
