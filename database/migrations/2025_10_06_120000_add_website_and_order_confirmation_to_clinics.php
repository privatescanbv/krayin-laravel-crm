<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('website_url')->nullable()->after('registration_form_clinic_name');
            $table->text('order_confirmation_note')->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['website_url', 'order_confirmation_note']);
        });
    }
};
