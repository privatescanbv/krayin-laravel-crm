<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            // Make name nullable (2025_07_22_152123)
            $table->string('name')->nullable()->change();

            // Add personal fields (2025_07_07_164354)
            $table->string('salutation')->nullable()->after('name');
            $table->string('first_name')->nullable()->after('salutation');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('lastname_prefix')->nullable()->after('last_name');
            $table->string('married_name')->nullable()->after('lastname_prefix');
            $table->string('married_name_prefix')->nullable()->after('married_name');
            $table->string('initials')->nullable()->after('married_name_prefix');
            $table->date('date_of_birth')->nullable()->after('initials');

            // Add gender (2025_07_07_204826)
            $table->string('gender')->nullable()->after('date_of_birth');

            // Add phones (2025_07_09_205801)
            $table->json('phones')->nullable()->after('gender');

            // Add external_id (2025_08_21_194949)
            $table->string('external_id')->nullable()->after('phones');
            $table->index('external_id');
        });

        // Make emails nullable if it exists (2025_07_25_152123)
        if (Schema::hasColumn('persons', 'emails')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->json('emails')->nullable()->change();
            });
        }

        // Rename contact_numbers to phones if it exists (2025_08_22_000007)
        if (Schema::hasColumn('persons', 'contact_numbers') && !Schema::hasColumn('persons', 'phones')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->renameColumn('contact_numbers', 'phones');
            });
        }

        // Add audit trail (2025_07_10_170351)
        Schema::table('persons', function (Blueprint $table) {
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            // Remove audit trail
            AuditTrailMigrationHelper::dropAuditTrailColumns($table);

            // Remove added columns
            $table->dropColumn([
                'external_id',
                'phones',
                'gender',
                'date_of_birth',
                'initials',
                'married_name_prefix',
                'married_name',
                'lastname_prefix',
                'last_name',
                'first_name',
                'salutation'
            ]);

            // Restore name as not nullable
            $table->string('name')->nullable(false)->change();
        });

        // Restore contact_numbers if phones was renamed
        if (Schema::hasColumn('persons', 'phones')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->renameColumn('phones', 'contact_numbers');
            });
        }
    }
};