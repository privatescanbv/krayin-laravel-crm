<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Webkul\User\Models\User>
 */
class WebkulUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // Get or create a role
        $role = Role::first() ?? Role::create([
            'name'            => 'Admin',
            'description'     => 'Administrator role',
            'permission_type' => 'all',
            'permissions'     => [],
        ]);

        return [
            'first_name' => $this->faker->firstName,
            'last_name'  => $this->faker->lastName,
            'email'      => $this->faker->unique()->safeEmail,
            'password'   => bcrypt('password'), // Default password
            'status'     => true,
            'role_id'    => $role->id,
        ];
    }

    /**
     * Indicate that the user is active.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => true,
            ];
        });
    }

    /**
     * Indicate that the user is inactive.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => false,
            ];
        });
    }

    /**
     * Indicate that the user has an API token.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withApiToken()
    {
        return $this->state(function (array $attributes) {
            return [
                'api_token' => $this->faker->uuid,
            ];
        });
    }

    /**
     * Indicate that the user has an image.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withImage()
    {
        return $this->state(function (array $attributes) {
            return [
                'image' => 'users/'.$this->faker->image('public/storage/users', 400, 400, null, false),
            ];
        });
    }
}
