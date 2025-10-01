<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            $table->decimal('purchase_price_misc', 12, 2)->default(0)->after('duration');
            $table->decimal('purchase_price_doctor', 12, 2)->default(0)->after('purchase_price_misc');
            $table->decimal('purchase_price_cardiology', 12, 2)->default(0)->after('purchase_price_doctor');
            $table->decimal('purchase_price_clinic', 12, 2)->default(0)->after('purchase_price_cardiology');
            $table->decimal('purchase_price_royal_doctors', 12, 2)->default(0)->after('purchase_price_clinic');
            $table->decimal('purchase_price_radiology', 12, 2)->default(0)->after('purchase_price_royal_doctors');
            $table->decimal('purchase_price', 12, 2)->default(0)->after('purchase_price_radiology');
        });
    }

    public function down(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_price_misc',
                'purchase_price_doctor',
                'purchase_price_cardiology',
                'purchase_price_clinic',
                'purchase_price_royal_doctors',
                'purchase_price_radiology',
                'purchase_price',
            ]);
        });
    }
};
