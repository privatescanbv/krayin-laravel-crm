<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\User\Models\User;
use Webkul\User\Models\UserDefaultValue;

class UserDefaultValueFactory extends Factory
{
    protected $model = UserDefaultValue::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key'     => $this->faker->randomElement([
                'lead.department_id',
                'lead.lead_channel_id',
                'lead.lead_source_id',
            ]),
            'value'   => (string) $this->faker->numberBetween(1, 10),
        ];
    }
}
