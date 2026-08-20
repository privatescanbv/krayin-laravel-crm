<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->boolean('has_duplicates')->default(false)->after('is_active');
            $table->index('has_duplicates');
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropIndex(['has_duplicates']);
            $table->dropColumn('has_duplicates');
        });
    }
};
