<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->unsignedBigInteger('visit_address_id')->nullable()->after('phones');
            $table->unsignedBigInteger('postal_address_id')->nullable()->after('visit_address_id');
            $table->boolean('is_postal_address_same_as_visit_address')->default(false)->after('postal_address_id');

            $table->foreign('visit_address_id')->references('id')->on('addresses')->onDelete('set null');
            $table->foreign('postal_address_id')->references('id')->on('addresses')->onDelete('set null');
        });

        // Migrate existing single address to both visit/postal and mark as same.
        if (Schema::hasColumn('clinics', 'address_id')) {
            DB::table('clinics')->update([
                'visit_address_id'                        => DB::raw('address_id'),
                'postal_address_id'                       => DB::raw('address_id'),
                'is_postal_address_same_as_visit_address' => true,
            ]);

            // SQLite can't drop foreign keys; in tests we keep the old column.
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                Schema::table('clinics', function (Blueprint $table) {
                    $table->dropForeign(['address_id']);
                    $table->dropColumn('address_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clinics', 'address_id')) {
            Schema::table('clinics', function (Blueprint $table) {
                $table->unsignedBigInteger('address_id')->nullable()->after('phones');
                $table->foreign('address_id')->references('id')->on('addresses')->onDelete('set null');
            });
        }

        DB::table('clinics')->update([
            'address_id' => DB::raw('visit_address_id'),
        ]);

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('clinics', function (Blueprint $table) {
                $table->dropForeign(['visit_address_id']);
                $table->dropForeign(['postal_address_id']);
                $table->dropColumn([
                    'visit_address_id',
                    'postal_address_id',
                    'is_postal_address_same_as_visit_address',
                ]);
            });
        }
    }
};
