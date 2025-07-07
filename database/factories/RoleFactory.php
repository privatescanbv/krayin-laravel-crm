<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\User\Models\Role;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Webkul\User\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name'             => $this->faker->unique()->word,
            'description'      => $this->faker->sentence,
            'permission_type'  => 'all',
            'permissions'      => null,
        ];
    }

    /**
     * Indicate that the role has custom permissions.
     */
    public function custom()
    {
        return $this->state(function (array $attributes) {
            return [
                'permission_type' => 'custom',
                'permissions'     => [
                    'view'   => true,
                    'edit'   => true,
                    'delete' => true,
                    'create' => true,
                ],
            ];
        });
    }
}
