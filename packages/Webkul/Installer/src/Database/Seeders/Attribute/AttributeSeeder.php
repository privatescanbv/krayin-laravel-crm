<?php

namespace Webkul\Installer\Database\Seeders\Attribute;

use App\Enums\LeadAttributeKeys;
use App\Enums\PersonAttributeKeys;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\Attribute\Models\Attribute;

class AttributeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Note: code needs to be unique over alle entity types!
     * @param array $parameters
     * @return void
     */
    public function run($parameters = [])
    {
       // none, we moved required fields to the entities itself (performance and control)
    }
}
