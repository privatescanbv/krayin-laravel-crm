<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_products', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Shared/base product fields
            $table->string('currency', 3)->default('EUR');
            $table->decimal('sales_price', 12, 2)->default(0);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->text('discount_info')->nullable();
            $table->unsignedBigInteger('resource_type_id')->nullable();

            // Purchase price fields
            $table->decimal('purchase_price', 12, 2)->default(0)->comment('Sum of all purchase price components');
            $table->decimal('purchase_price_misc', 12, 2)->default(0);
            $table->decimal('purchase_price_doctor', 12, 2)->default(0);
            $table->decimal('purchase_price_cardiology', 12, 2)->default(0);
            $table->decimal('purchase_price_clinic', 12, 2)->default(0);
            $table->decimal('purchase_price_royal_doctors', 12, 2)->default(0);
            $table->decimal('purchase_price_radiology', 12, 2)->default(0);

            // Partner product only
            $table->text('clinic_description')->nullable();
            $table->unsignedInteger('duration')->nullable(); // minutes

            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->foreign('resource_type_id')->references('id')->on('resource_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_products');
    }
};
