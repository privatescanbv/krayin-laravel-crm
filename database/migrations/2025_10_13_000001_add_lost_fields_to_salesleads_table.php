<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            if (! Schema::hasColumn('salesleads', 'lost_reason')) {
                $table->string('lost_reason')->nullable()->after('description');
            }
            if (! Schema::hasColumn('salesleads', 'closed_at')) {
                $table->date('closed_at')->nullable()->after('lost_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            if (Schema::hasColumn('salesleads', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
            if (Schema::hasColumn('salesleads', 'lost_reason')) {
                $table->dropColumn('lost_reason');
            }
        });
    }
};
