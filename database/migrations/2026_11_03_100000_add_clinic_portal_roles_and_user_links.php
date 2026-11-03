<?php

use App\Enums\RoleCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('permissions');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('resource_id')->nullable()->unique()->after('role_id')
                ->constrained('resources')->nullOnDelete();
            $table->foreignId('clinic_id')->nullable()->after('resource_id')
                ->constrained('clinics')->nullOnDelete();
        });

        // Only for existing installs; fresh installs get these roles from RoleSeeder (and an empty
        // table must not end up with a permissionless portal role as its first role).
        if (! DB::table('roles')->exists()) {
            return;
        }

        foreach (RoleCode::cases() as $code) {
            if (DB::table('roles')->where('code', $code->value)->exists()) {
                continue;
            }

            // Existing role with the same name (e.g. created by hand, or kept by an earlier rollback): tag it
            if (DB::table('roles')->where('name', $code->label())->whereNull('code')->update(['code' => $code->value]) > 0) {
                continue;
            }

            DB::table('roles')->insert([
                'name'            => $code->label(),
                'description'     => $code->description(),
                'permission_type' => 'custom',
                'permissions'     => json_encode([]),
                'code'            => $code->value,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_id');
            $table->dropConstrainedForeignId('resource_id');
        });

        // Role rows stay: users.role_id cascades on delete and would remove their users.
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
