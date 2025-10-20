<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            // Add related sales price if it doesn't exist
            if (!Schema::hasColumn('partner_products', 'related_sales_price')) {
                $table->decimal('related_sales_price', 12, 2)->nullable()->after('sales_price');
            }
            
            // Add new related purchase price fields after purchase_price
            $table->decimal('rel_purchase_price_misc', 12, 2)->nullable()->after('purchase_price');
            $table->decimal('rel_purchase_price_doctor', 12, 2)->nullable()->after('rel_purchase_price_misc');
            $table->decimal('rel_purchase_price_cardiology', 12, 2)->nullable()->after('rel_purchase_price_doctor');
            $table->decimal('rel_purchase_price_clinic', 12, 2)->nullable()->after('rel_purchase_price_cardiology');
            $table->decimal('rel_purchase_price_radiology', 12, 2)->nullable()->after('rel_purchase_price_clinic');
            $table->decimal('rel_purchase_price', 12, 2)->nullable()->after('rel_purchase_price_radiology');
            
            // Remove purchase_price_royal_doctors field if it exists
            if (Schema::hasColumn('partner_products', 'purchase_price_royal_doctors')) {
                $table->dropColumn('purchase_price_royal_doctors');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            // Add back purchase_price_royal_doctors field
            $table->decimal('purchase_price_royal_doctors', 12, 2)->default(0)->after('purchase_price_clinic');
            
            // Remove related purchase price fields
            $table->dropColumn([
                'rel_purchase_price_misc',
                'rel_purchase_price_doctor',
                'rel_purchase_price_cardiology',
                'rel_purchase_price_clinic',
                'rel_purchase_price_radiology',
                'rel_purchase_price',
            ]);
            
            // Remove related sales price if it exists
            if (Schema::hasColumn('partner_products', 'related_sales_price')) {
                $table->dropColumn('related_sales_price');
            }
        });
    }
};