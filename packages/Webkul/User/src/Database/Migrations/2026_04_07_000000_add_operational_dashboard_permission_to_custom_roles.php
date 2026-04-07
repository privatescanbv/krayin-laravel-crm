<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roles = DB::table('roles')
            ->where('permission_type', 'custom')
            ->whereNotNull('permissions')
            ->get();

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions, true);

            if (is_array($permissions) && ! in_array('operational-dashboard', $permissions)) {
                array_unshift($permissions, 'operational-dashboard');

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roles = DB::table('roles')
            ->where('permission_type', 'custom')
            ->whereNotNull('permissions')
            ->get();

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions, true);

            if (is_array($permissions)) {
                $permissions = array_filter($permissions, fn ($p) => $p !== 'operational-dashboard');

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }
    }
};
