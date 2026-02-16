<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
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

        DB::table('roles')->delete();

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        // Insert Administrator role
        DB::table('roles')->insert([
            'id'              => 1,
            'name'            => trans('installer::app.seeders.user.role.administrator', [], $defaultLocale),
            'description'     => trans('installer::app.seeders.user.role.administrator-role', [], $defaultLocale),
            'permission_type' => 'all',
            'permissions'     => null,
        ]);

        // Insert Medewerker Afdeling role
        DB::table('roles')->insert([
            'id'              => 2,
            'name'            => 'Medewerker Afdeling',
            'description'     => 'Medewerker met beperkte rechten voor afdelingswerkzaamheden',
            'permission_type' => 'custom',
            'permissions'     => json_encode([
                'dashboard',
                'leads',
                'leads.create',
                'leads.view',
                'leads.edit',
                'clinic-guide',
                'products',
                'products.view',
                'productgroups',
                'productgroups.view',
                'partner_products',
                'partner_products.view',
                'mail',
                'mail.create',
                'mail.view',
                'mail.edit',
                'mail.delete',
                'activities',
                'activities.create',
                'activities.edit',
                'activities.delete',
                'activities.takeover',
                'contacts',
                'contacts.persons',
                'contacts.persons.create',
                'contacts.persons.edit',
                'contacts.persons.view',
                'contacts.persons.portal-create',
                'contacts.organizations',
                'contacts.organizations.create',
                'contacts.organizations.edit',
                'settings',
                'sales-leads',
                'sales-leads.view',
                'sales-leads.create',
                'sales-leads.edit',
                'orders',
                'orders.view',
                'orders.create',
                'orders.edit',
            ]),
        ]);

        // Insert Kliniek Begeleider role
        DB::table('roles')->insert([
            'id'              => 3,
            'name'            => 'Kliniek Begeleider',
            'description'     => 'Kliniek begeleider met toegang tot dagplanning en basisrechten',
            'permission_type' => 'custom',
            'permissions'     => json_encode([
                'dashboard',
                'leads',
                'leads.view',
                'sales-leads',
                'sales-leads.view',
                'sales-leads.edit',
                'orders',
                'orders.view',
                'orders.edit',
                'clinic-guide',
                'sales-leads',
                'sales-leads.view',
                'contacts',
                'contacts.persons',
                'contacts.persons.view',
                'contacts.organizations',
                'orders',
                'orders.view',
                'activities',
                'activities.create',
                'activities.edit',
            ]),
        ]);
    }
}
