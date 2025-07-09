<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeadChannelSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('lead_channels')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        $now = Carbon::now();
        $channels = [
            'Telefoon',
            'Website',
            'E-mail',
            'Tel-en-Tel',
            'Agenten',
            'Partners',
            'Social media',
            'Webshop',
            'Campagne',
        ];
        $rows = [];
        foreach ($channels as $i => $name) {
            $rows[] = [
                'id'         => $i + 1,
                'name'       => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('lead_channels')->insert($rows);
    }
}
