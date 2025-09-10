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
        // First, add activity_id to emails table
        Schema::table('emails', function (Blueprint $table) {
            $table->unsignedInteger('activity_id')->nullable()->after('lead_id');
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('set null');
        });

        // Then, remove email_id from activities table
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['email_id']);
            $table->dropColumn('email_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // First, add email_id back to activities table
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('email_id')->nullable()->after('lead_id');
            $table->foreign('email_id')->references('id')->on('emails')->onDelete('set null');
        });

        // Then, remove activity_id from emails table
        Schema::table('emails', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn('activity_id');
        });
    }
};