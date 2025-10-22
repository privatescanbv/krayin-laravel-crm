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
        Schema::table('emails', function (Blueprint $table) {
            $table->integer('folder_id')->unsigned()->nullable()->after('is_read');
            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('set null');
        });

        // Remove the folders JSON column
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('folders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->json('folders')->nullable()->after('is_read');
            $table->dropForeign(['folder_id']);
            $table->dropColumn('folder_id');
        });
    }
};