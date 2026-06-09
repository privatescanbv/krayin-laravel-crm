<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('activity_comments');
    }

    public function down(): void
    {
        // Data is preserved in activity_actions (type = notitie); restore is not supported.
    }
};
