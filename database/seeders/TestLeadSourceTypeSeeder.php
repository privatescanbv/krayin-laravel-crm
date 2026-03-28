<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds lead_sources and lead_types with IDs expected by {@see UserTestSeeder} and factories.
 * Replaces Webkul Installer SourceSeeder / TypeSeeder.
 */
class TestLeadSourceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $sources = [];
        $names = [
            1 => 'Website',
            2 => 'E-mail',
            3 => 'Telefoon',
            4 => 'Social media',
            5 => 'Agenten',
            6 => 'Partners',
        ];
        foreach ($names as $id => $name) {
            $sources[] = [
                'id'         => $id,
                'name'       => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('lead_sources')->insert($sources);

        DB::table('lead_types')->insert([
            [
                'id'         => 1,
                'name'       => 'New Lead',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
