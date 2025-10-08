<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Webkul\User\Models\Role;

class UserFactory extends Factory
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
            'first_name'        => $this->faker->firstName,
            'last_name'         => $this->faker->lastName,
            'email'             => $this->faker->unique()->safeEmail,
            'status'            => 1,
            'role_id'           => $role->id,
            'password'          => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token'    => Str::random(10),
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
}
