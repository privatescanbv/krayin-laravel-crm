<?php

namespace Webkul\Contact\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Contact\Models\Organization;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
        ];
    }
}
