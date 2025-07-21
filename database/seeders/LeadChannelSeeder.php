<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeadChannelSeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->truncateTables(['lead_channels']);
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
