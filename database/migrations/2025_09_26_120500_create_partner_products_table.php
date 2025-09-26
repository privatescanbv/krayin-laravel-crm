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
            $table->string('partner_name');
            $table->text('description')->nullable();
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->unique('partner_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_products');
    }
};

