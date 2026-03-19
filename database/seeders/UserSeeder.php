<?php

namespace Database\Seeders;

use App\Enums\Departments;
use App\Models\Department;
use App\Support\UserSignature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\Group;
use Webkul\User\Models\User;
use Webkul\User\Models\UserDefaultValue;

class UserSeeder extends Seeder
{
    /**
     * Get password for a user by email from the seeder data.
     */
    public static function getPasswordForEmail(string $email): ?string
    {
        return static::getUserPasswords()[$email] ?? null;
    }

    /**
     * Get user passwords - defined once, used everywhere.
     *
     * @return array<string, string>
     */
    protected static function getUserPasswords(): array
    {
        return [
            'robschwankhuizen@gmail.com'   => '8H5jc!e123',
            'mark.bulthuis@privatescan.nl' => '8AAZ5jc%e&AF',
            'mark.klaucke@privatescan.nl'  => '8AAZ5jc%e&Ad',
            'linda@privatescan.nl'         => '8AAZ5jc%e&3d',
            'petra@privatescan.nl'         => '8BBZ5jc%e&Ad',
            'wout@privatescan.nl'          => '8EEZ5jc%e&Ad',
            'lars@privatescan.nl'          => '8A4Z5jc%d3Ad',
            'frank@privatescan.nl'         => '8A115dc@d3Ad',
            'nihad@nime.dev'               => '8A144dc@d1Ab',
            'carolien@privatescan.nl'      => '8A115dc@d4Ab',
            'maria@privatescan.nl'         => '8A115dc@d4Ab',
            'esther@privatescan.nl'        => '8A115dc@d4Ab',
            'rowain@privatescan.nl'        => '8A115dc@d4Ab',
            'safak@privatescan.nl'         => '8A115dc@d4Ab',
        ];
    }

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

        // Get passwords from central location
        $passwords = static::getUserPasswords();

        // Define users with their group assignments
        $users = [
            [
                'first_name'      => 'Mark',
                'last_name'       => 'Bulthuis',
                'email'           => 'mark.bulthuis@privatescan.nl',
                'password'        => $passwords['mark.bulthuis@privatescan.nl'],
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null, // Admin has no specific group
                'signature'       => $this->signatureTemplate('Mark', 'Bulthuis', 'mark.bulthuis@privatescan.nl'),
            ],
            [
                'first_name'      => 'Mark',
                'last_name'       => 'Klaucke',
                'email'           => 'mark.klaucke@privatescan.nl',
                'password'        => $passwords['mark.klaucke@privatescan.nl'],
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null, // Admin has no specific group
                'signature'       => $this->signatureTemplate('Mark', 'Klaucke', 'mark.klaucke@privatescan.nl'),
            ],
            [
                'first_name'      => 'Linda',
                'last_name'       => 'Meijer',
                'email'           => 'linda@privatescan.nl',
                'password'        => $passwords['linda@privatescan.nl'],
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null,
                'signature'       => $this->signatureTemplate('Linda', 'Meijer', 'linda@privatescan.nl'),
            ],
            [
                'first_name'      => 'Petra',
                'last_name'       => 'Nijhof',
                'email'           => 'petra@privatescan.nl',
                'password'        => $passwords['petra@privatescan.nl'],
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => $groupPrivatescanId,
                'signature'       => $this->signatureTemplate('Petra', 'Nijhof', 'petra@privatescan.nl'),
            ],
            [
                'first_name'      => 'Wout',
                'last_name'       => 'Lansink',
                'email'           => 'wout@privatescan.nl',
                'password'        => $passwords['wout@privatescan.nl'],
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => $groupHerniaId,
                'signature'       => $this->signatureTemplate('Wout', 'Lansink', 'wout@privatescan.nl'),
            ],
            [
                'first_name'      => 'Lars',
                'last_name'       => 'Kamphuis',
                'email'           => 'lars@privatescan.nl',
                'password'        => $passwords['lars@privatescan.nl'],
                'status'          => 1,
                'role_id'         => 2, // Medewerker Afdeling role
                'view_permission' => 'group', // Restricted to group view
                'group_id'        => $groupHerniaId,
                'signature'       => $this->signatureTemplate('Lars', 'Kamphuis', 'lars@privatescan.nl'),
            ],
            [
                'first_name'      => 'Frank',
                'last_name'       => 'Hefti',
                'email'           => 'frank@privatescan.nl',
                'password'        => $passwords['frank@privatescan.nl'],
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null,
                'signature'       => $this->signatureTemplate('Frank', 'Hefti', 'frank@privatescan.nl'),
            ],
            [
                'first_name'      => 'Carolien',
                'last_name'       => 'Cicek',
                'email'           => 'carolien@privatescan.nl',
                'password'        => $passwords['carolien@privatescan.nl'],
                'status'          => 1, // Medewerker Afdeling role
                'role_id'         => 2,
                'view_permission' => 'group', // Restricted to group view
                'group_id'        => $groupPrivatescanId,
                'signature'       => $this->signatureTemplate('Carolien', 'Cicek', 'carolien@privatescan.nl'),
            ],
            [
                'first_name'      => 'Maria',
                'last_name'       => 'Issa',
                'email'           => 'maria@privatescan.nl',
                'password'        => $passwords['maria@privatescan.nl'],
                'status'          => 1, // Medewerker Afdeling role
                'role_id'         => 2,
                'view_permission' => 'group', // Restricted to group view
                'group_id'        => $groupPrivatescanId,
                'signature'       => $this->signatureTemplate('Maria', 'Issa', 'maria@privatescan.nl'),
            ],
            [
                'first_name'      => 'Esther',
                'last_name'       => 'de Jonge',
                'email'           => 'esther@privatescan.nl',
                'password'        => $passwords['esther@privatescan.nl'],
                'status'          => 1, // Medewerker Afdeling role
                'role_id'         => 3, // Kliniek begeleidster
                'view_permission' => 'group', // Restricted to group view
                'group_id'        => $groupPrivatescanId,
                'signature'       => $this->signatureTemplate('Esther', 'de Jonge', 'esther@privatescan.nl'),
            ],
            [
                'first_name'      => 'Rob',
                'last_name'       => 'Schwankhuizen',
                'email'           => 'robschwankhuizen@gmail.com',
                'password'        => $passwords['robschwankhuizen@gmail.com'],
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null,
                'signature'       => $this->signatureTemplate('Rob', 'Schwankhuizen', 'robschwankhuizen@gmail.com'),
            ],
            [
                'first_name'      => 'Nihad',
                'last_name'       => 'Mehmedovic',
                'email'           => 'nihad@nime.dev',
                'password'        => $passwords['nihad@nime.dev'],
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null,
                'signature'       => $this->signatureTemplate('Nihad', 'Mehmedovic', 'nihad@nime.dev'),
            ],
            [
                'first_name'      => 'Rowain',
                'last_name'       => 'Morsink',
                'email'           => 'rowain@privatescan.nl',
                'password'        => $passwords['rowain@privatescan.nl'],
                'status'          => 1, // Medewerker Afdeling role
                'role_id'         => 2,
                'view_permission' => 'group', // Restricted to group view
                'group_id'        => $groupPrivatescanId,
                'signature'       => $this->signatureTemplate('Rowain', 'Morsink', 'rowain@privatescan.nl'),
            ],
            [
                'first_name'      => 'Safak',
                'last_name'       => 'Sel',
                'email'           => 'safak@privatescan.nl',
                'password'        => $passwords['safak@privatescan.nl'],
                'status'          => 1, // Medewerker Afdeling role
                'role_id'         => 2,
                'view_permission' => 'group', // Restricted to group view
                'group_id'        => $groupHerniaId,
                'signature'       => $this->signatureTemplate('Safak', 'Sel', 'safak@privatescan.nl'),
            ],

        ];

        // Create users and assign to groups
        foreach ($users as $userData) {
            $groupId = $userData['group_id'];
            unset($userData['group_id']); // Remove group_id from user data

            // Use updateOrCreate to prevent duplicate key errors during parallel testing
            // Pass plaintext password so User model mutator can capture it for Keycloak sync
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'first_name'      => $userData['first_name'],
                    'last_name'       => $userData['last_name'],
                    'password'        => $userData['password'], // Plaintext - User model will hash it and store plaintext for observer
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
                'lead.lead_type_id'    => '1',
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

    private function signatureTemplate(string $firstName, string $lastName, string $email): string
    {
        return UserSignature::generate($firstName, $lastName, $email);
    }
}
