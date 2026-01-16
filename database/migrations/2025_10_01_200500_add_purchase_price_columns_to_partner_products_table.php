<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {

            // Purchase prices
            $table->decimal('purchase_price_misc', 12, 2)->default(0)->after('duration');
            $table->decimal('purchase_price_doctor', 12, 2)->default(0)->after('purchase_price_misc');
            $table->decimal('purchase_price_cardiology', 12, 2)->default(0)->after('purchase_price_doctor');
            $table->decimal('purchase_price_clinic', 12, 2)->default(0)->after('purchase_price_cardiology');
            $table->decimal('purchase_price_radiology', 12, 2)->default(0)->after('purchase_price_clinic');
            $table->decimal('purchase_price', 12, 2)->default(0)->after('purchase_price_radiology');

            // Related sales price
            $table->decimal('related_sales_price', 12, 2)->nullable()->after('sales_price');

            // Related purchase prices
            $table->decimal('rel_purchase_price_misc', 12, 2)->nullable()->after('purchase_price');
            $table->decimal('rel_purchase_price_doctor', 12, 2)->nullable()->after('rel_purchase_price_misc');
            $table->decimal('rel_purchase_price_cardiology', 12, 2)->nullable()->after('rel_purchase_price_doctor');
            $table->decimal('rel_purchase_price_clinic', 12, 2)->nullable()->after('rel_purchase_price_cardiology');
            $table->decimal('rel_purchase_price_radiology', 12, 2)->nullable()->after('rel_purchase_price_clinic');
            $table->decimal('rel_purchase_price', 12, 2)->nullable()->after('rel_purchase_price_radiology');
        });
    }

    public function down(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            $table->dropColumn([
                // Purchase prices
                'purchase_price_misc',
                'purchase_price_doctor',
                'purchase_price_cardiology',
                'purchase_price_clinic',
                'purchase_price_radiology',
                'purchase_price',

                // Related sales price
                'related_sales_price',

                // Related purchase prices
                'rel_purchase_price_misc',
                'rel_purchase_price_doctor',
                'rel_purchase_price_cardiology',
                'rel_purchase_price_clinic',
                'rel_purchase_price_radiology',
                'rel_purchase_price',
            ]);
        });
    }
};
