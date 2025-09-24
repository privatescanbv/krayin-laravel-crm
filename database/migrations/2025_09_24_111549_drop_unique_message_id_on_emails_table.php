<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Drop the unique index on `message_id`
            $table->dropUnique(['message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Restore the unique index on `message_id`
            $table->unique('message_id');
        });
    }
};
