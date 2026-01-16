<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_activities');
        Schema::dropIfExists('warehouse_activities');
        //        Schema::dropIfExists('warehouses');
    }

    public function down(): void
    {
        //
    }
};
