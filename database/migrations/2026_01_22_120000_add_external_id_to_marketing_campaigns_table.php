<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('marketing_campaigns', 'external_id')) {
                $table->string('external_id')->nullable()->after('name');
                $table->index('external_id');
            }

            if (Schema::hasColumn('marketing_campaigns', 'marketing_event_id')) {
                $table->unsignedInteger('marketing_event_id')->nullable()->change();
            }

            if (Schema::hasColumn('marketing_campaigns', 'marketing_template_id')) {
                $table->unsignedInteger('marketing_template_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('marketing_campaigns', 'external_id')) {
                $table->dropIndex(['marketing_campaigns_external_id_index']);
                $table->dropColumn('external_id');
            }
        });
    }
};
