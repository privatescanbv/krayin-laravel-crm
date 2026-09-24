<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Re-grant Werkbakken: the role editor dropped 'operational-dashboard' on save while it was
     * missing from acl.php. Matched by name because role ids differ per environment.
     */
    public function up(): void
    {
        $roles = DB::table('roles')
            ->whereIn('name', ['Medewerker Afdeling', 'Kliniek Begeleider'])
            ->where('permission_type', 'custom')
            ->get();

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true) ?: [];

            if (! in_array('operational-dashboard', $permissions, true)) {
                array_unshift($permissions, 'operational-dashboard');

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally empty: the roles may have had this permission before this migration.
    }
};
