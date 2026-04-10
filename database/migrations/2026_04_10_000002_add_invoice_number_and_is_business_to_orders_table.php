<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('order_number');
            }
            if (! Schema::hasColumn('orders', 'is_business')) {
                $table->boolean('is_business')->default(false)->after('invoice_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'is_business')) {
                $table->dropColumn('is_business');
            }
            if (Schema::hasColumn('orders', 'invoice_number')) {
                $table->dropColumn('invoice_number');
            }
        });
    }
};
