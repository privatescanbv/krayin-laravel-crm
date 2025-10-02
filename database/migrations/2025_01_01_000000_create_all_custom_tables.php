<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Core tables (from packages or base Laravel setup)
        // These are created by their respective packages/migrations

        // Workflows table
        Schema::create('workflowleads', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lead_id');
            $table->unsignedInteger('lead_pipeline_id');
            $table->unsignedInteger('lead_status_id');
            $table->timestamp('created_at')->nullable();
            $table->string('type', 50)->nullable();

            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            $table->foreign('lead_pipeline_id')->references('id')->on('lead_pipelines')->onDelete('cascade');
            $table->foreign('lead_status_id')->references('id')->on('lead_statuses')->onDelete('cascade');
        });

        // Addresses table
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('street')->nullable();
            $table->string('house_number');
            $table->string('postal_code');
            $table->string('house_number_suffix')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('person_id')->nullable();
            $table->unsignedInteger('organization_id')->nullable();
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        // Lead channels table
        Schema::create('lead_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Departments table
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Anamnesis table
        Schema::create('anamnesis', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255)->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->char('created_by', 36)->nullable();
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
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('person_id')->nullable();

            $table->unique(['lead_id', 'person_id'], 'unique_anamnesis_lead_person');
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('set null');
        });

        // Lead persons pivot table
        Schema::create('lead_persons', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('lead_id');
            $table->unsignedInteger('person_id');
            $table->enum('role', ['patient', 'family', 'other'])->default('patient');
            $table->timestamps();

            $table->unique(['lead_id', 'person_id']);
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');
        });

        // Call statuses table
        Schema::create('call_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('status');
            $table->text('omschrijving')->nullable();
            $table->unsignedInteger('activity_id');
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');
        });

        // Clinics table
        Schema::create('clinics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('external_id')->nullable();
            $table->json('emails')->nullable();
            $table->json('phones')->nullable();
            $table->unsignedBigInteger('address_id')->nullable();
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->unique('name');
            $table->index('external_id');
            $table->foreign('address_id')->references('id')->on('addresses')->onDelete('set null');
        });

        // Resource types table
        Schema::create('resource_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('external_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->unique('name');
            $table->index('external_id');
        });

        // Resources table
        Schema::create('resources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('external_id')->nullable();
            $table->unsignedBigInteger('resource_type_id');
            $table->unsignedBigInteger('clinic_id')->nullable();
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->index('name');
            $table->index('external_id');
            $table->foreign('resource_type_id')->references('id')->on('resource_types')->onDelete('restrict');
            $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('set null');
        });

        // Shifts table
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->text('notes')->nullable();
            $table->boolean('available')->default(true);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('weekday_time_blocks')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('resource_id')->references('id')->on('resources')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->index(['resource_id']);
        });

        // Product types table
        Schema::create('product_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->string('external_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->index('external_id');
        });

        // Partner products table
        Schema::create('partner_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('sales_price', 12, 2)->default(0);
            $table->string('name');
            $table->string('external_id')->nullable();
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->text('discount_info')->nullable();
            $table->unsignedBigInteger('resource_type_id')->nullable();
            $table->text('clinic_description')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->decimal('purchase_price_misc', 12, 2)->default(0);
            $table->decimal('purchase_price_doctor', 12, 2)->default(0);
            $table->decimal('purchase_price_cardiology', 12, 2)->default(0);
            $table->decimal('purchase_price_clinic', 12, 2)->default(0);
            $table->decimal('purchase_price_royal_doctors', 12, 2)->default(0);
            $table->decimal('purchase_price_radiology', 12, 2)->default(0);
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->index('external_id');
            $table->foreign('resource_type_id')->references('id')->on('resource_types')->nullOnDelete();
        });

        // Partner product activities pivot table
        Schema::create('partner_product_activities', function (Blueprint $table) {
            $table->unsignedInteger('activity_id');
            $table->unsignedBigInteger('partner_product_id');

            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');
            $table->foreign('partner_product_id')->references('id')->on('partner_products')->onDelete('cascade');
        });

        // Clinic partner product pivot table
        Schema::create('clinic_partner_product', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedBigInteger('partner_product_id');

            $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('cascade');
            $table->foreign('partner_product_id')->references('id')->on('partner_products')->onDelete('cascade');
            $table->primary(['clinic_id', 'partner_product_id']);
        });

        // Partner product related pivot table
        Schema::create('partner_product_related', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_product_id');
            $table->unsignedBigInteger('related_product_id');

            $table->foreign('partner_product_id')->references('id')->on('partner_products')->onDelete('cascade');
            $table->foreign('related_product_id')->references('id')->on('partner_products')->onDelete('cascade');
            $table->primary(['partner_product_id', 'related_product_id'], 'partner_product_related_primary');
            $table->unique(['related_product_id', 'partner_product_id'], 'partner_product_related_unique');
        });

        // Partner product resource pivot table
        Schema::create('partner_product_resource', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_product_id');
            $table->unsignedBigInteger('resource_id');

            $table->foreign('partner_product_id')->references('id')->on('partner_products')->onDelete('cascade');
            $table->foreign('resource_id')->references('id')->on('resources')->onDelete('cascade');
            $table->primary(['partner_product_id', 'resource_id'], 'partner_product_resource_primary');
        });

        // Product partner product pivot table
        Schema::create('product_partner_product', function (Blueprint $table) {
            $table->unsignedInteger('product_id');
            $table->unsignedBigInteger('partner_product_id');

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('partner_product_id')->references('id')->on('partner_products')->onDelete('cascade');
            $table->primary(['product_id', 'partner_product_id'], 'product_partner_product_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_partner_product');
        Schema::dropIfExists('partner_product_resource');
        Schema::dropIfExists('partner_product_related');
        Schema::dropIfExists('clinic_partner_product');
        Schema::dropIfExists('partner_product_activities');
        Schema::dropIfExists('partner_products');
        Schema::dropIfExists('product_types');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('resource_types');
        Schema::dropIfExists('clinics');
        Schema::dropIfExists('call_statuses');
        Schema::dropIfExists('lead_persons');
        Schema::dropIfExists('anamnesis');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('lead_channels');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('workflowleads');
    }
};
