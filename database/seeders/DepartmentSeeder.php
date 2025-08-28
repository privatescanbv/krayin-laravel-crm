<?php

namespace Database\Seeders;

use App\Enums\Departments;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends BaseSeeder
{
    public function run(array $parameters = []): void
    {
        $this->truncateTables(['departments']);
        $now = Carbon::now();
        $departments = Departments::allValues();
        $rows = [];
        foreach ($departments as $i => $name) {
            $rows[] = [
                'id'         => $i + 1,
                'name'       => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('departments')->insert($rows);
    }
}
