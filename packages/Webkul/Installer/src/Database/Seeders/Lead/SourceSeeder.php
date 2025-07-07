<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SourceSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        if (DB::table('lead_sources')->count() > 0) {
            return;
        }

        $now = Carbon::now();

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        DB::table('lead_sources')->insert([
            [
                'id'         => 1,
                'name'       => 'Tel. (074-2552680)',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                // hernia
                'id'         => 2,
                'name'       => 'Tel. (074-8200100)',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'id'         => 3,
                'name'       => 'Website: privatescan.nl',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'id'         => 4,
                'name'       => 'Website: herniapoli.nl',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'id'         => 5,
                'name'       => trans('installer::app.seeders.lead.source.direct', [], $defaultLocale),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
