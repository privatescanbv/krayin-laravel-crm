<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Core\Models\CoreConfig;

return new class extends Migration
{
    /**
     * Run the migrations - Modify Webkul package tables.
     */
    public function up(): void
    {
        // Modify Users Table
        Schema::table('users', function (Blueprint $table) {
            $table->string('external_id', 36)->nullable()->after('id')->index();
            AuditTrailMigrationHelper::addAuditTrailColumnsIfNotExists($table, 'users');
        });

        // Modify Groups Table
        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        });

        // Link groups to departments if needed (data migration would be here, but skipped for fresh install)

        // Modify Organizations Table
        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'address')) {
                $table->dropColumn('address');
            }
            AuditTrailMigrationHelper::addAuditTrailColumnsIfNotExists($table, 'organizations');
        });

        // Remove address attribute from organizations (data migration, skipped for fresh install)
        $addressAttribute = DB::table('attributes')->where([
            'code'        => 'address',
            'entity_type' => 'organizations',
        ])->first();

        if ($addressAttribute) {
            DB::table('attribute_values')->where([
                'attribute_id' => $addressAttribute->id,
                'entity_type'  => 'organizations',
            ])->delete();

            DB::table('attributes')->where('id', $addressAttribute->id)->delete();
        }

        // Modify Persons Table
        Schema::table('persons', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('emails')->nullable()->change();
            $table->string('external_id')->nullable()->after('name');
            $table->index('external_id');
            $table->string('salutation')->nullable()->after('external_id');
            $table->string('first_name')->nullable()->after('salutation');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('lastname_prefix')->nullable()->after('last_name');
            $table->string('married_name')->nullable()->after('lastname_prefix');
            $table->string('married_name_prefix')->nullable()->after('married_name');
            $table->string('initials')->nullable()->after('married_name_prefix');
            $table->date('date_of_birth')->nullable()->after('initials');
            $table->string('gender')->nullable()->after('date_of_birth');
            $table->json('phones')->nullable()->after('emails');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            if (Schema::hasColumn('persons', 'contact_numbers')) {
                $table->dropColumn('contact_numbers');
            }
        });

        // Make person attributes not unique
        if (Schema::hasTable('attributes') && Schema::hasColumn('attributes', 'is_unique') && Schema::hasColumn('attributes', 'entity_type')) {
            DB::table('attributes')
                ->where('entity_type', 'persons')
                ->update(['is_unique' => 0]);
        }

        // Modify Leads Table - Drop columns first (SQLite compatibility)
        if (Schema::hasColumn('leads', 'title')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('title');
            });
        }
        
        if (Schema::hasColumn('leads', 'lead_value')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('lead_value');
            });
        }

        // Modify Leads Table - Add new columns
        Schema::table('leads', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('id');
            $table->index('external_id');
            $table->string('salutation')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('lastname_prefix')->nullable();
            $table->string('married_name')->nullable();
            $table->string('married_name_prefix')->nullable();
            $table->string('initials')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->json('emails')->nullable();
            $table->json('phones')->nullable();
            
            $table->unsignedBigInteger('lead_channel_id')->nullable();
            $table->foreign('lead_channel_id')->references('id')->on('lead_channels')->nullOnDelete();
            
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            
            $table->integer('organization_id')->unsigned()->nullable();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            
            $table->boolean('combine_order')->default(true);
            $table->string('mri_status')->nullable();
            $table->boolean('has_diagnosis_form')->default(false);
            
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });

        // Modify Activities Table
        Schema::table('activities', function (Blueprint $table) {
            $table->string('external_id', 36)->nullable()->after('id')->index();
            $table->timestamp('assigned_at')->nullable()->after('user_id');
            $table->unsignedInteger('group_id')->nullable();
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');
            $table->unsignedInteger('lead_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
            $table->unsignedBigInteger('clinic_id')->nullable();
            $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('set null');
            $table->string('status')->default('active')->after('type');
        });

        // Drop lead_activities pivot table if exists
        Schema::dropIfExists('lead_activities');

        // Modify Emails Table
        Schema::table('emails', function (Blueprint $table) {
            // Try to drop unique index if it exists (SQLite compatibility)
            if (Schema::hasColumn('emails', 'message_id') && DB::getDriverName() !== 'sqlite') {
                try {
                    $table->dropUnique(['message_id']);
                } catch (\Exception $e) {
                    // Index doesn't exist, that's fine
                }
            }
            
            if (! Schema::hasColumn('emails', 'activity_id')) {
                $table->unsignedInteger('activity_id')->nullable()->after('lead_id');
                $table->foreign('activity_id')->references('id')->on('activities')->onDelete('set null');
            }
        });

        // Modify Lead Pipelines Table
        Schema::table('lead_pipelines', function (Blueprint $table) {
            $table->enum('type', ['lead', 'workflow'])->default('lead')->after('is_default');
        });

        // Modify Products Table - Drop columns (SQLite compatibility)
        if (Schema::hasColumn('products', 'sku')) {
            Schema::table('products', function (Blueprint $table) {
                // Try to drop unique index if it exists (SQLite compatibility)
                if (DB::getDriverName() !== 'sqlite') {
                    try {
                        $table->dropUnique(['sku']);
                    } catch (\Exception $e) {
                        // Index doesn't exist, that's fine
                    }
                }
                $table->dropColumn('sku');
            });
        }
        
        if (Schema::hasColumn('products', 'quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('quantity');
            });
        }

        // Modify Products Table - Add columns
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'currency')) {
                $table->string('currency', 3)->default('EUR')->after('name');
            }
            if (! Schema::hasColumn('products', 'resource_type_id')) {
                $table->unsignedBigInteger('resource_type_id')->nullable()->after('price');
                $table->unsignedBigInteger('product_type_id')->nullable()->after('resource_type_id');
                $table->foreign('resource_type_id')->references('id')->on('resource_types')->onDelete('set null');
                $table->foreign('product_type_id')->references('id')->on('product_types')->onDelete('set null');
            }
        });

        // Set default locale to Dutch
        $config = CoreConfig::where('code', 'general.general.locale_settings.locale')->first();

        if (! $config) {
            CoreConfig::create([
                'code'  => 'general.general.locale_settings.locale',
                'value' => 'nl',
            ]);
        } else {
            $config->update(['value' => 'nl']);
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This migration is designed for fresh installs only.
     * Rollback is not supported as we always use migrate:fresh.
     */
    public function down(): void
    {
        // Not implemented - this migration is for fresh installs only
    }
};
