<?php

namespace Webkul\Installer\Database\Seeders\User;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\User\Models\Group;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('users')->delete();
        DB::table('groups')->delete();
        DB::table('user_groups')->delete();

        // Define group IDs for better readability
        $groupPrivatescanId = 1;
        $groupHerniaId = 2;

        // Define groups
        $groups = [
            [
                'id'          => $groupHerniaId,
                'name'        => 'Hernia',
                'description' => 'Hernia team',
            ],
            [
                'id'          => $groupPrivatescanId,
                'name'        => 'Privatescan',
                'description' => 'Privatescan team',
            ],
        ];

        // Create groups
        foreach ($groups as $group) {
            Group::updateOrCreate(
                ['name' => $group['name']],
                $group
            );
        }

        // Define users with their group assignments
        $users = [
            [
                'name'            => 'Mark Bulthuis',
                'email'           => 'mark.bulthuis@privatescan.nl',
                'password'        => '8AAZ5jc%e&AF',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null, // Admin has no specific group
            ],
            [
                'name'            => 'Mark Klaucke',
                'email'           => 'mark.klaucke@privatescan.nl',
                'password'        => '8AAZ5jc%e&Ad',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null, // Admin has no specific group
            ],
            [
                'name'            => 'Linda',
                'email'           => 'linda@privatescan.nl',
                'password'        => '8AAZ5jc%e&3d',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => $groupPrivatescanId,
            ],
            [
                'name'            => 'Petra',
                'email'           => 'petra@privatescan.nl',
                'password'        => '8BBZ5jc%e&Ad',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => $groupPrivatescanId,
            ],
            [
                'name'            => 'Wout',
                'email'           => 'wout@privatescan.nl',
                'password'        => '8EEZ5jc%e&Ad',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => $groupHerniaId,
            ],
            [
                'name'            => 'Lars',
                'email'           => 'lars@privatescan.nl',
                'password'        => '8A4Z5jc%d3Ad',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => $groupHerniaId,
            ],
        ];

        // Create users and assign to groups
        foreach ($users as $userData) {
            $groupId = $userData['group_id'];
            unset($userData['group_id']); // Remove group_id from user data

            $userId = DB::table('users')->insertGetId([
                'name'            => $userData['name'],
                'email'           => $userData['email'],
                'password'        => bcrypt($userData['password']),
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
                'status'          => $userData['status'],
                'role_id'         => $userData['role_id'],
                'view_permission' => $userData['view_permission'],
            ]);

            // Assign user to group if specified
            if ($groupId) {
                DB::table('user_groups')->insert([
                    'user_id'  => $userId,
                    'group_id' => $groupId,
                ]);
            }
        }
    }
}
