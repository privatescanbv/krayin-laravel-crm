<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // SQLite doesn't support dropping foreign keys, so skip this for SQLite
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('product_inventories', function (Blueprint $table) {
                // SQLite doesn't support dropping foreign keys, so skip this for SQLite
            if (DB::getDriverName() !== 'sqlite') {
                if (DB::getDriverName() !== 'sqlite') { $table->dropForeign(['warehouse_location_id']);
                $table->foreign('warehouse_location_id')->references('id')->on('warehouse_locations')->onDelete('cascade');
            }
        });
        }
    }

    public function down()
    {
        // SQLite doesn't support dropping foreign keys, so skip this for SQLite
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('product_inventories', function (Blueprint $table) {
                // SQLite doesn't support dropping foreign keys, so skip this for SQLite
            if (DB::getDriverName() !== 'sqlite') {
                if (DB::getDriverName() !== 'sqlite') { $table->dropForeign(['warehouse_location_id']);
                $table->foreign('warehouse_location_id')->references('id')->on('warehouse_locations')->onDelete('set null');
            }
        });
        }
    }
};
