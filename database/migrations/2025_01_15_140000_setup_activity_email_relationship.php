<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add activity_id to emails table for the correct relationship
        // Email belongs to one Activity (0..1), Activity has many Emails (0..*)
        Schema::table('emails', function (Blueprint $table) {
            $table->unsignedInteger('activity_id')->nullable()->after('lead_id');
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('set null');
        });
    }

    public function down()
    {
        // Remove activity_id from emails table
        Schema::table('emails', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn('activity_id');
        });
    }
};
