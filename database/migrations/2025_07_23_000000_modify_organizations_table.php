<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add audit trail (2025_07_25_000002)
        Schema::table('organizations', function (Blueprint $table) {
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });

        // Remove address column (2025_07_23_000003)
        if (Schema::hasColumn('organizations', 'address')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }

        // Remove address attribute from attributes table (2025_07_23_000001)
        $this->removeAddressAttribute();
    }

    public function down(): void
    {
        // Restore address column
        Schema::table('organizations', function (Blueprint $table) {
            $table->json('address')->nullable();
        });

        // Remove audit trail
        Schema::table('organizations', function (Blueprint $table) {
            AuditTrailMigrationHelper::dropAuditTrailColumns($table);
        });

        // Restore address attribute
        $this->restoreAddressAttribute();
    }

    private function removeAddressAttribute(): void
    {
        // First get the attribute ID for the address attribute
        $addressAttribute = DB::table('attributes')->where([
            'code'        => 'address',
            'entity_type' => 'organizations',
        ])->first();

        if ($addressAttribute) {
            // Remove any attribute values for the address attribute
            DB::table('attribute_values')->where([
                'attribute_id' => $addressAttribute->id,
                'entity_type'  => 'organizations',
            ])->delete();

            // Remove the address attribute from organizations
            DB::table('attributes')->where('id', $addressAttribute->id)->delete();
        }
    }

    private function restoreAddressAttribute(): void
    {
        // Re-add the address attribute
        DB::table('attributes')->insert([
            'code'            => 'address',
            'name'            => 'Adres',
            'type'            => 'address',
            'entity_type'     => 'organizations',
            'lookup_type'     => null,
            'validation'      => null,
            'sort_order'      => 2,
            'is_required'     => 0,
            'is_unique'       => 0,
            'quick_add'       => 1,
            'is_user_defined' => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
};