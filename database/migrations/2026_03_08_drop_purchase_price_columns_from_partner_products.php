<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_price_misc',
                'purchase_price_doctor',
                'purchase_price_cardiology',
                'purchase_price_clinic',
                'purchase_price_radiology',
                'purchase_price',
                'rel_purchase_price_misc',
                'rel_purchase_price_doctor',
                'rel_purchase_price_cardiology',
                'rel_purchase_price_clinic',
                'rel_purchase_price_radiology',
                'rel_purchase_price',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            $table->decimal('purchase_price_misc', 10, 2)->nullable();
            $table->decimal('purchase_price_doctor', 10, 2)->nullable();
            $table->decimal('purchase_price_cardiology', 10, 2)->nullable();
            $table->decimal('purchase_price_clinic', 10, 2)->nullable();
            $table->decimal('purchase_price_radiology', 10, 2)->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->decimal('rel_purchase_price_misc', 10, 2)->nullable();
            $table->decimal('rel_purchase_price_doctor', 10, 2)->nullable();
            $table->decimal('rel_purchase_price_cardiology', 10, 2)->nullable();
            $table->decimal('rel_purchase_price_clinic', 10, 2)->nullable();
            $table->decimal('rel_purchase_price_radiology', 10, 2)->nullable();
            $table->decimal('rel_purchase_price', 10, 2)->nullable();
        });
    }
};
