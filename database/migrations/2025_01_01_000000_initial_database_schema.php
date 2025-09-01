<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Core\Models\CoreConfig;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel framework tables
        $this->createFailedJobsTable();
        $this->createPersonalAccessTokensTable();
        $this->createJobsTable();
        $this->createJobBatchesTable();

        // Business tables (in dependency order)
        $this->createLeadChannelsTable();
        $this->createDepartmentsTable();
        $this->createAddressesTable();
        $this->createLeadPersonsTable();
        $this->createAnamnesisTable();
        $this->createWorkflowLeadsTable();

        // Add columns to existing tables (assuming these tables exist in the base system)
        $this->addColumnsToActivitiesTable();
        $this->addColumnsToLeadsTable();
        $this->addColumnsToPersonsTable();
        $this->addColumnsToUsersTable();
        $this->addColumnsToOrganizationsTable();
        $this->addColumnsToLeadPipelinesTable();
        $this->addColumnsToWorkflowsTable();

        // Set default configuration
        $this->setDefaultLocale();
    }

    public function down(): void
    {
        // Drop in reverse order
        Schema::dropIfExists('workflowleads');
        Schema::dropIfExists('anamnesis');
        Schema::dropIfExists('lead_persons');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('lead_channels');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('failed_jobs');

        // Remove added columns from existing tables
        $this->removeColumnsFromTables();
    }

    private function createFailedJobsTable(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    private function createPersonalAccessTokensTable(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function createJobsTable(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    private function createJobBatchesTable(): void
    {
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->text('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }

    private function createLeadChannelsTable(): void
    {
        Schema::create('lead_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    private function createDepartmentsTable(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    private function createAddressesTable(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('street')->nullable();
            $table->string('house_number');
            $table->string('postal_code');
            $table->string('house_number_suffix')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            // Foreign keys
            $table->unsignedInteger('lead_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');

            $table->unsignedInteger('person_id')->nullable();
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');

            $table->unsignedInteger('organization_id')->nullable();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

            $table->timestamps();

            // Add audit trail columns
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });
    }

    private function createLeadPersonsTable(): void
    {
        Schema::create('lead_persons', function (Blueprint $table) {
            $table->integer('lead_id')->unsigned();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');

            $table->integer('person_id')->unsigned();
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');

            // Add unique constraint to prevent duplicates
            $table->unique(['lead_id', 'person_id']);
        });
    }

    private function createAnamnesisTable(): void
    {
        Schema::create('anamnesis', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255)->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('deleted')->default(0);
            $table->char('team_id', 36)->nullable();
            $table->char('team_set_id', 36)->nullable();
            $table->text('comment_clinic')->nullable();
            $table->integer('height')->nullable();
            $table->integer('weight')->nullable();
            $table->tinyInteger('metals')->nullable();
            $table->text('metals_notes')->nullable();
            $table->tinyInteger('medications')->nullable();
            $table->text('medications_notes')->nullable();
            $table->tinyInteger('glaucoma')->nullable();
            $table->text('glaucoma_notes')->nullable();
            $table->tinyInteger('claustrophobia')->nullable();
            $table->tinyInteger('dormicum')->nullable();
            $table->tinyInteger('heart_surgery')->nullable();
            $table->text('heart_surgery_notes')->nullable();
            $table->tinyInteger('implant')->nullable();
            $table->text('implant_notes')->nullable();
            $table->tinyInteger('surgeries')->nullable();
            $table->text('surgeries_notes')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->tinyInteger('hereditary_heart')->nullable();
            $table->text('hereditary_heart_notes')->nullable();
            $table->tinyInteger('hereditary_vascular')->nullable();
            $table->text('hereditary_vascular_notes')->nullable();
            $table->tinyInteger('hereditary_tumors')->nullable();
            $table->text('hereditary_tumors_notes')->nullable();
            $table->tinyInteger('allergies')->nullable();
            $table->text('allergies_notes')->nullable();
            $table->tinyInteger('back_problems')->nullable();
            $table->text('back_problems_notes')->nullable();
            $table->tinyInteger('heart_problems')->nullable();
            $table->text('heart_problems_notes')->nullable();
            $table->tinyInteger('smoking')->nullable();
            $table->text('smoking_notes')->nullable();
            $table->tinyInteger('diabetes')->nullable();
            $table->text('diabetes_notes')->nullable();
            $table->tinyInteger('digestive_problems')->nullable();
            $table->text('digestive_problems_notes')->nullable();
            $table->text('heart_attack_risk')->nullable();
            $table->tinyInteger('active')->nullable();
            $table->text('advice_notes')->nullable();
            
            // Add spijsverteringsklachten fields
            $table->boolean('spijsverteringsklachten')->default(false);
            $table->text('digestive_complaints_notes')->nullable();

            // Foreign keys
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('person_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('set null');

            // Unique constraint for lead_id + person_id
            $table->unique(['lead_id', 'person_id'], 'anamnesis_lead_person_unique');

            // Add audit trail columns (correct unsignedInteger type)
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });
    }

    private function createWorkflowLeadsTable(): void
    {
        Schema::create('workflowleads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('pipeline_stage_id');
            $table->foreign('pipeline_stage_id')->references('id')->on('lead_pipeline_stages')->onDelete('cascade');
            $table->unsignedInteger('lead_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
            $table->unsignedInteger('quote_id')->nullable();
            $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('set null');
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    private function addColumnsToActivitiesTable(): void
    {
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
                if (!Schema::hasColumn('activities', 'group_id')) {
                    $table->unsignedInteger('group_id')->nullable();
                    $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');
                }
                
                if (!Schema::hasColumn('activities', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable();
                }
                
                if (!Schema::hasColumn('activities', 'lead_id')) {
                    $table->unsignedInteger('lead_id')->nullable();
                    $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
                }
            });
        }
    }

    private function addColumnsToLeadsTable(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                // External ID with index
                if (!Schema::hasColumn('leads', 'external_id')) {
                    $table->string('external_id')->nullable()->after('title');
                    $table->index('external_id');
                }

                // Personal fields
                if (!Schema::hasColumn('leads', 'salutation')) {
                    $table->string('salutation')->nullable()->after('external_id');
                }
                if (!Schema::hasColumn('leads', 'first_name')) {
                    $table->string('first_name')->nullable()->after('salutation');
                }
                if (!Schema::hasColumn('leads', 'last_name')) {
                    $table->string('last_name')->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('leads', 'lastname_prefix')) {
                    $table->string('lastname_prefix')->nullable()->after('last_name');
                }
                if (!Schema::hasColumn('leads', 'married_name')) {
                    $table->string('married_name')->nullable()->after('lastname_prefix');
                }
                if (!Schema::hasColumn('leads', 'married_name_prefix')) {
                    $table->string('married_name_prefix')->nullable()->after('married_name');
                }
                if (!Schema::hasColumn('leads', 'initials')) {
                    $table->string('initials')->nullable()->after('married_name_prefix');
                }
                if (!Schema::hasColumn('leads', 'date_of_birth')) {
                    $table->date('date_of_birth')->nullable()->after('initials');
                }
                if (!Schema::hasColumn('leads', 'gender')) {
                    $table->string('gender')->nullable()->after('date_of_birth');
                }

                // Contact information
                if (!Schema::hasColumn('leads', 'emails')) {
                    $table->json('emails')->nullable()->after('description');
                }
                if (!Schema::hasColumn('leads', 'phones')) {
                    $table->json('phones')->nullable()->after('emails');
                }

                // Foreign keys
                if (!Schema::hasColumn('leads', 'lead_channel_id')) {
                    $table->unsignedBigInteger('lead_channel_id')->nullable();
                    $table->foreign('lead_channel_id')->references('id')->on('lead_channels')->nullOnDelete();
                }
                if (!Schema::hasColumn('leads', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable();
                    $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
                }
                if (!Schema::hasColumn('leads', 'organization_id')) {
                    $table->unsignedInteger('organization_id')->nullable();
                    $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
                }

                // Additional fields
                if (!Schema::hasColumn('leads', 'combine_order')) {
                    $table->boolean('combine_order')->default(true);
                }

                // Audit trail
                AuditTrailMigrationHelper::addAuditTrailColumnsIfNotExists($table, 'leads');
            });
        }
    }

    private function addColumnsToPersonsTable(): void
    {
        if (Schema::hasTable('persons')) {
            Schema::table('persons', function (Blueprint $table) {
                // Make name nullable
                if (Schema::hasColumn('persons', 'name')) {
                    $table->string('name')->nullable()->change();
                }

                // Personal fields
                if (!Schema::hasColumn('persons', 'salutation')) {
                    $table->string('salutation')->nullable()->after('name');
                }
                if (!Schema::hasColumn('persons', 'first_name')) {
                    $table->string('first_name')->nullable()->after('salutation');
                }
                if (!Schema::hasColumn('persons', 'last_name')) {
                    $table->string('last_name')->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('persons', 'lastname_prefix')) {
                    $table->string('lastname_prefix')->nullable()->after('last_name');
                }
                if (!Schema::hasColumn('persons', 'married_name')) {
                    $table->string('married_name')->nullable()->after('lastname_prefix');
                }
                if (!Schema::hasColumn('persons', 'married_name_prefix')) {
                    $table->string('married_name_prefix')->nullable()->after('married_name');
                }
                if (!Schema::hasColumn('persons', 'initials')) {
                    $table->string('initials')->nullable()->after('married_name_prefix');
                }
                if (!Schema::hasColumn('persons', 'date_of_birth')) {
                    $table->date('date_of_birth')->nullable()->after('initials');
                }
                if (!Schema::hasColumn('persons', 'gender')) {
                    $table->string('gender')->nullable()->after('date_of_birth');
                }

                // Contact information
                if (!Schema::hasColumn('persons', 'phones')) {
                    $table->json('phones')->nullable();
                }

                // Make emails nullable if it exists
                if (Schema::hasColumn('persons', 'emails')) {
                    $table->json('emails')->nullable()->change();
                }

                // External ID
                if (!Schema::hasColumn('persons', 'external_id')) {
                    $table->string('external_id')->nullable();
                    $table->index('external_id');
                }

                // Audit trail
                AuditTrailMigrationHelper::addAuditTrailColumnsIfNotExists($table, 'persons');
            });

            // Rename contact_numbers to phones if contact_numbers exists
            if (Schema::hasColumn('persons', 'contact_numbers') && !Schema::hasColumn('persons', 'phones')) {
                Schema::table('persons', function (Blueprint $table) {
                    $table->renameColumn('contact_numbers', 'phones');
                });
            }
        }
    }

    private function addColumnsToUsersTable(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'external_id')) {
                    $table->string('external_id')->nullable();
                    $table->index('external_id');
                }

                // Audit trail
                AuditTrailMigrationHelper::addAuditTrailColumnsIfNotExists($table, 'users');
            });
        }
    }

    private function addColumnsToOrganizationsTable(): void
    {
        if (Schema::hasTable('organizations')) {
            Schema::table('organizations', function (Blueprint $table) {
                // Audit trail
                AuditTrailMigrationHelper::addAuditTrailColumnsIfNotExists($table, 'organizations');
            });

            // Remove address column if it exists (data should be migrated to addresses table)
            if (Schema::hasColumn('organizations', 'address')) {
                Schema::table('organizations', function (Blueprint $table) {
                    $table->dropColumn('address');
                });
            }
        }
    }

    private function addColumnsToLeadPipelinesTable(): void
    {
        if (Schema::hasTable('lead_pipelines')) {
            Schema::table('lead_pipelines', function (Blueprint $table) {
                if (!Schema::hasColumn('lead_pipelines', 'type')) {
                    $table->string('type')->default('default');
                }
            });
        }
    }

    private function addColumnsToWorkflowsTable(): void
    {
        if (Schema::hasTable('workflows')) {
            Schema::table('workflows', function (Blueprint $table) {
                if (!Schema::hasColumn('workflows', 'workflow_type')) {
                    $table->string('workflow_type')->default('default');
                }
            });
        }
    }

    private function setDefaultLocale(): void
    {
        // Set default locale to Dutch
        $config = CoreConfig::where('code', 'general.general.locale_settings.locale')->first();

        if (!$config) {
            CoreConfig::create([
                'code'  => 'general.general.locale_settings.locale',
                'value' => 'nl',
            ]);
        } else {
            $config->update(['value' => 'nl']);
        }
    }

    private function removeColumnsFromTables(): void
    {
        // This would be complex to implement properly for rollback
        // Since this is a squashed migration for fresh installs, 
        // the down() method dropping the created tables should be sufficient
    }
};