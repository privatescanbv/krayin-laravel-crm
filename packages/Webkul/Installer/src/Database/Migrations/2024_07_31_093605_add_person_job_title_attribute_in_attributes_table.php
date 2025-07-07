<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // skip removing job title for person
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
