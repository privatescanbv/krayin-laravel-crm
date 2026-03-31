<?php

namespace Database\Seeders;

use App\Enums\LeadType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeadTypeSeeder extends BaseSeeder
{
    public function run(array $parameters = []): void
    {
        $this->truncateTables(['lead_types']);
        $now = Carbon::now();
        $rows = [];
        foreach (LeadType::cases() as $type) {
            $rows[] = [
                'id'         => $type->value,
                'name'       => $type->databaseName(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('lead_types')->insert($rows);
    }
}
