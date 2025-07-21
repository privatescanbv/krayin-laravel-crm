<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            // SQLite doesn't support dropping foreign keys, so skip this for SQLite
            if (DB::getDriverName() !== 'sqlite') {
                if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['lead_pipeline_stage_id']);
            }
                $table->foreign('lead_pipeline_stage_id')->references('id')->on('lead_pipeline_stages')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            // SQLite doesn't support dropping foreign keys, so skip this for SQLite
            if (DB::getDriverName() !== 'sqlite') {
                if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['lead_pipeline_stage_id']);
            }
                $table->foreign('lead_pipeline_stage_id')->references('id')->on('lead_pipeline_stages')->onDelete('cascade');
            }
        });
    }
};
