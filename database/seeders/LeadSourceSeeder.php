<?php

namespace Database\Seeders;

use App\Enums\LeadSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeadSourceSeeder extends BaseSeeder
{
    public function run(array $parameters = []): void
    {
        $this->truncateTables(['lead_sources']);
        $now = Carbon::now();
        $rows = [];
        foreach (LeadSource::cases() as $source) {
            $rows[] = [
                'id'         => $source->value,
                'name'       => $source->databaseName(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('lead_sources')->insert($rows);
    }
}
