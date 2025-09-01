<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('lead_pipelines', function (Blueprint $table) {
            $table->enum('type', ['lead', 'workflow'])->default('lead')->after('is_default');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lead_pipelines', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};