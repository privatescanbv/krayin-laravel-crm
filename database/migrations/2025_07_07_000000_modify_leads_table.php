<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Add external_id with index (2025_08_21_194227)
            $table->string('external_id')->nullable()->after('title');
            $table->index('external_id');

            // Add personal fields (2025_07_07_164324)
            $table->string('salutation')->nullable()->after('external_id');
            $table->string('first_name')->nullable()->after('salutation');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('lastname_prefix')->nullable()->after('last_name');
            $table->string('married_name')->nullable()->after('lastname_prefix');
            $table->string('married_name_prefix')->nullable()->after('married_name');
            $table->string('initials')->nullable()->after('married_name_prefix');
            $table->date('date_of_birth')->nullable()->after('initials');

            // Add gender (2025_07_07_204721)
            $table->string('gender')->nullable()->after('date_of_birth');

            // Add lead channel and department (2025_07_09_165751)
            $table->unsignedBigInteger('lead_channel_id')->nullable()->after('gender');
            $table->foreign('lead_channel_id')->references('id')->on('lead_channels')->nullOnDelete();
            $table->unsignedBigInteger('department_id')->nullable()->after('lead_channel_id');
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();

            // Add contact information (2025_07_09_195246, 2025_07_09_205529)
            $table->json('emails')->nullable()->after('description');
            $table->json('phones')->nullable()->after('emails');

            // Add organization_id (2025_08_22_000004)
            $table->unsignedInteger('organization_id')->nullable()->after('department_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');

            // Add combine_order (2025_08_22_000008)
            $table->boolean('combine_order')->default(true)->after('organization_id');
        });

        // Add audit trail columns (2025_07_10_164111)
        Schema::table('leads', function (Blueprint $table) {
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });

        // Remove columns that were dropped in later migrations
        Schema::table('leads', function (Blueprint $table) {
            // Remove person_id (2025_08_22_000002)
            if (Schema::hasColumn('leads', 'person_id')) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['person_id']);
                }
                $table->dropColumn('person_id');
            }

            // Remove title (2025_08_22_000006)
            if (Schema::hasColumn('leads', 'title')) {
                $table->dropColumn('title');
            }

            // Remove lead_value (2025_08_29_120000)
            if (Schema::hasColumn('leads', 'lead_value')) {
                $table->dropColumn('lead_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Restore dropped columns
            $table->string('title')->after('id');
            $table->decimal('lead_value', 12, 4)->nullable();
            $table->integer('person_id')->unsigned()->nullable();
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('restrict');

            // Remove audit trail
            AuditTrailMigrationHelper::dropAuditTrailColumns($table);

            // Remove added columns (in reverse order)
            $table->dropColumn([
                'combine_order',
                'organization_id',
                'department_id', 
                'lead_channel_id',
                'phones',
                'emails',
                'gender',
                'date_of_birth',
                'initials',
                'married_name_prefix',
                'married_name',
                'lastname_prefix',
                'last_name',
                'first_name',
                'salutation',
                'external_id'
            ]);
        });
    }
};