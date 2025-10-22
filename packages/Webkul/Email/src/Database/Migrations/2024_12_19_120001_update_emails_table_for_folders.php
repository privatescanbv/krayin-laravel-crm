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

        // Note: We'll keep the folders column for now to allow migration
        // It will be removed in a later migration after data migration is complete
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