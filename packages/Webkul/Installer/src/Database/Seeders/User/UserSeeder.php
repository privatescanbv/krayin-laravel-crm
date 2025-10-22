<?php

namespace Webkul\Installer\Database\Seeders\User;

use App\Enums\Departments;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\Group;
use Webkul\User\Models\User;
use Webkul\User\Models\UserDefaultValue;

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

        // Get department IDs
        $herniaDepartment = Department::where('name', Departments::HERNIA->value)->first();
        $privatescanDepartment = Department::where('name', Departments::PRIVATESCAN->value)->first();

        // Define groups with department relationships
        $groups = [
            [
                'id'            => $groupHerniaId,
                'name'          => Departments::HERNIA->value,
                'description'   => 'Hernia team',
                'department_id' => $herniaDepartment?->id,
            ],
            [
                'id'            => $groupPrivatescanId,
                'name'          => Departments::PRIVATESCAN->value,
                'description'   => 'Privatescan team',
                'department_id' => $privatescanDepartment?->id,
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
                'first_name'      => 'Mark',
                'last_name'       => 'Bulthuis',
                'email'           => 'mark.bulthuis@privatescan.nl',
                'password'        => '8AAZ5jc%e&AF',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null, // Admin has no specific group
                'signature'       => "Met vriendelijke groet,\n\nMark Bulthuis\nPrivatescan\nTel: +31 (0)6 12345678\nEmail: mark.bulthuis@privatescan.nl",
            ],
            [
                'first_name'      => 'Mark',
                'last_name'       => 'Klaucke',
                'email'           => 'mark.klaucke@privatescan.nl',
                'password'        => '8AAZ5jc%e&Ad',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null, // Admin has no specific group
                'signature'       => "Met vriendelijke groet,\n\nMark Klaucke\nPrivatescan\nTel: +31 (0)6 12345679\nEmail: mark.klaucke@privatescan.nl",
            ],
            [
                'first_name'      => 'Linda',
                'last_name'       => 'Meijer',
                'email'           => 'linda@privatescan.nl',
                'password'        => '8AAZ5jc%e&3d',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null,
                'signature'       => "Met vriendelijke groet,\n\nLinda Meijer\nPrivatescan\nTel: +31 (0)6 12345680\nEmail: linda@privatescan.nl",
            ],
            [
                'first_name'      => 'Petra',
                'last_name'       => 'Nijhof',
                'email'           => 'petra@privatescan.nl',
                'password'        => '8BBZ5jc%e&Ad',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => $groupPrivatescanId,
                'signature'       => "Met vriendelijke groet,\n\nPetra Nijhof\nPrivatescan\nTel: +31 (0)6 12345681\nEmail: petra@privatescan.nl",
            ],
            [
                'first_name'      => 'Wout',
                'last_name'       => 'Lansink',
                'email'           => 'wout@privatescan.nl',
                'password'        => '8EEZ5jc%e&Ad',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => $groupHerniaId,
                'signature'       => "Met vriendelijke groet,\n\nWout Lansink\nPrivatescan\nTel: +31 (0)6 12345682\nEmail: wout@privatescan.nl",
            ],
            [
                'first_name'      => 'Lars',
                'last_name'       => 'Kamphuis',
                'email'           => 'lars@privatescan.nl',
                'password'        => '8A4Z5jc%d3Ad',
                'status'          => 1,
                'role_id'         => 2, // Medewerker Afdeling role
                'view_permission' => 'group', // Restricted to group view
                'group_id'        => $groupHerniaId,
                'signature'       => "Met vriendelijke groet,\n\nLars Kamphuis\nPrivatescan\nTel: +31 (0)6 12345683\nEmail: lars@privatescan.nl",
            ],
            [
                'first_name'      => 'Frank',
                'last_name'       => 'Hefti',
                'email'           => 'frank@privatescan.nl',
                'password'        => '8A115dc@d3Ad',
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null,
                'signature'       => "Met vriendelijke groet,\n\nFrank Hefti\nPrivatescan\nTel: +31 (0)6 12345684\nEmail: frank@privatescan.nl",
            ],
        ];

        // Create users and assign to groups
        foreach ($users as $userData) {
            $groupId = $userData['group_id'];
            unset($userData['group_id']); // Remove group_id from user data

            // Use updateOrCreate to prevent duplicate key errors during parallel testing
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'first_name'      => $userData['first_name'],
                    'last_name'       => $userData['last_name'],
                    'password'        => bcrypt($userData['password']),
                    'created_at'      => date('Y-m-d H:i:s'),
                    'updated_at'      => date('Y-m-d H:i:s'),
                    'status'          => $userData['status'],
                    'role_id'         => $userData['role_id'],
                    'view_permission' => $userData['view_permission'],
                    'signature'       => $userData['signature'] ?? null,
                ]
            );

            // Assign user to group if specified
            if ($groupId) {
                DB::table('user_groups')->updateOrInsert(
                    [
                        'user_id'  => $user->id,
                        'group_id' => $groupId,
                    ],
                    [
                        'user_id'  => $user->id,
                        'group_id' => $groupId,
                    ]
                );
            }

            // Seed default user default values
            $defaultSettings = [
                'lead.department_id'   => '2',
                'lead.lead_channel_id' => '1',
                'lead.lead_source_id'  => '6',
                'lead.lead_type_id'  => '2',
            ];

            // Override for Mark Bulthuis
            if ($user->email === 'mark.bulthuis@privatescan.nl') {
                $defaultSettings['lead.department_id'] = '1';
            }

            foreach ($defaultSettings as $key => $value) {
                UserDefaultValue::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'key'     => $key,
                    ],
                    [
                        'value' => $value,
                    ]
                );
            }
        }
    }
}
