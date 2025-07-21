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

        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support JSON_UNQUOTE, use simpler approach
            DB::statement("
                UPDATE {$tableName}
                SET unique_id = user_id || '|' || organization_id || '|' || 
                    COALESCE(substr(emails, instr(emails, '\"value\":\"') + 9, 
                        instr(substr(emails, instr(emails, '\"value\":\"') + 9), '\"') - 1), '') || '|' ||
                    COALESCE(substr(contact_numbers, instr(contact_numbers, '\"value\":\"') + 9, 
                        instr(substr(contact_numbers, instr(contact_numbers, '\"value\":\"') + 9), '\"') - 1), '')
            ");
        } else {
            // MySQL/PostgreSQL with JSON functions
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
