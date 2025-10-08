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
     * Configure the model factory with attributes.
     */
    public function configure()
    {
        return $this->afterMaking(function (User $user) {
            // If 'name' was passed in attributes, split it into first_name and last_name
            if (isset($this->attributes['name'])) {
                $nameParts = explode(' ', $this->attributes['name'], 2);
                $user->first_name = $nameParts[0] ?? '';
                $user->last_name = $nameParts[1] ?? '';
                unset($this->attributes['name']);
            }
        });
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
