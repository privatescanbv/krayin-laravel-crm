<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use Carbon\Carbon;
use Database\Seeders\BaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Lead Type
 */
class TypeSeeder extends BaseSeeder
{
    /**
     * Seed the application's database.
     *
     * @param array $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        $this->truncateTables(['lead_types']);
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
                'id' => $i + 1,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('lead_types')->insert($rows);
    }
}
