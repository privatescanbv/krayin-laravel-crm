<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->unsignedInteger('contact_person_id')->nullable()->after('user_id');
            // Foreign key constraint removed - persons table may not exist in SQLite tests
        });
    }

    public function down(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->dropColumn('contact_person_id');
        });
    }
};
