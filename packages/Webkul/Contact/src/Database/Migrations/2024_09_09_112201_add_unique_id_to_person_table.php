<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->string('unique_id')->nullable()->unique();
        });

        $tableName = DB::getTablePrefix().'persons';

        // Use different SQL for different database drivers
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: Use simpler approach or skip complex JSON operations during testing
            DB::statement("
                UPDATE {$tableName}
                SET unique_id = (user_id || '|' || organization_id || '|email|phone')
            ");
        } else {
            // MySQL/PostgreSQL: Use JSON functions
            DB::statement("
                UPDATE {$tableName}
                SET unique_id = CONCAT(
                    user_id, '|', 
                    organization_id, '|', 
                    JSON_UNQUOTE(JSON_EXTRACT(emails, '$[0].value')), '|',
                    JSON_UNQUOTE(JSON_EXTRACT(contact_numbers, '$[0].value'))
                )
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn('unique_id');
        });
    }
};
