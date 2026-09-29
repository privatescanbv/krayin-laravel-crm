<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = DB::table('roles')->where('permission_type', 'custom')->get();

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true) ?: [];

            if (! in_array('metabase.crm-reports', $permissions, true)) {
                $permissions[] = 'metabase.crm-reports';

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
