<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('web_form_attributes');
        Schema::dropIfExists('web_forms');
    }

    public function down(): void
    {
        // Intentionally not restored — WebForm package has been removed
    }
};
