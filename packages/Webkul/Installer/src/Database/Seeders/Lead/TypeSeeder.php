<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Lead Type
 */
class TypeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('lead_types')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = Carbon::now();

        $types = [
            'Preventie',
            'Gericht',
            'Operatie',
            'Overig',
        ];

        $rows = [];
        foreach ($types as $i => $name) {
            $rows[] = [
                'id'         => $i + 1,
                'name'       => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('lead_types')->insert($rows);
    }
}
