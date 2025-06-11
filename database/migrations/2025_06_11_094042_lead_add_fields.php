<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('attributes')->insert([
            [
                'code' => 'lastname',
                'name' => 'Achternaam',
                'type' => 'text',
                'entity_type' => 'leads',
                'is_required' => 1,
                'is_unique' => 0,
                'validation' => null,
                'position' => 1,
                'is_visible' => 1,
                'is_user_defined' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'firstName',
                'name' => 'Voornaam',
                'type' => 'text',
                'entity_type' => 'leads',
                'is_required' => 1,
                'is_unique' => 0,
                'validation' => null,
                'position' => 2,
                'is_visible' => 1,
                'is_user_defined' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('attributes')
            ->whereIn('code', ['lastname', 'firstName'])
            ->where('entity_type', 'leads')
            ->delete();
    }
};
