<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove the expected_close_date attribute from the attributes table
        DB::table('attributes')->where('code', 'expected_close_date')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the expected_close_date attribute
        DB::table('attributes')->insert([
            'code'            => 'expected_close_date',
            'name'            => 'Verwachte sluitingsdatum',
            'type'            => 'date',
            'entity_type'     => 'leads',
            'lookup_type'     => null,
            'validation'      => null,
            'sort_order'      => '8',
            'is_required'     => '0',
            'is_unique'       => '0',
            'quick_add'       => '1',
            'is_user_defined' => '0',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
};