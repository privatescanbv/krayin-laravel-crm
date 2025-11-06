<?php

namespace Webkul\Installer\Database\Seeders\User;

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
                'leads',
                'leads.create',
                'leads.view',
                'leads.edit',
                'products',
                'products.create',
                'products.edit',
                'products.delete',
                'products.view',
                'productgroups',
                'productgroups.create',
                'productgroups.edit',
                'productgroups.delete',
                'mail',
                'mail.inbox',
                'mail.draft',
                'mail.outbox',
                'mail.sent',
                'mail.trash',
                'mail.compose',
                'mail.view',
                'mail.edit',
                'mail.delete',
                'activities',
                'activities.create',
                'activities.edit',
                'contacts',
                'contacts.persons',
                'contacts.persons.create',
                'contacts.persons.edit',
                'contacts.persons.delete',
                'contacts.persons.view',
                'contacts.organizations',
                'contacts.organizations.create',
                'contacts.organizations.edit',
                'contacts.organizations.delete',
                'settings',
                'settings.user',
                'settings.user.groups',
                'settings.user.groups.create',
                'settings.user.groups.edit',
                'settings.user.groups.delete',
                'settings.user.roles',
                'settings.user.roles.create',
                'settings.user.roles.edit',
                'settings.user.roles.delete',
                'settings.user.users',
                'settings.user.users.create',
                'settings.user.users.edit',
                'settings.user.users.delete',
                'settings.lead',
                'settings.lead.types',
                'settings.lead.types.create',
                'settings.lead.types.edit',
                'settings.lead.types.delete',
                'sales-leads',
                'sales-leads.create',
                'sales-leads.edit',
                'sales-leads.delete',
            ]),
        ]);
    }
}
