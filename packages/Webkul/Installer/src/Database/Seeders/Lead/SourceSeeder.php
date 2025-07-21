<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use Carbon\Carbon;
use Database\Seeders\BaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SourceSeeder extends BaseSeeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        $this->truncateTables(['lead_sources']);

        $now = Carbon::now();

        $sources = [
            ['bodyscannl', 'bodyscan.nl'],
            ['privatescannl', 'privatescan.nl'],
            ['mriscannl', 'mri-scan.nl'],
            ['ccsvionlinenl', 'ccsvi-online.nl'],
            ['ccsvionlinecom', 'ccsvi-online.com'],
            ['googleorganisch', 'Google zoeken'],
            ['adwords', 'Adwords'],
            ['kranttelegraaf', 'Krant Telegraaf'],
            ['krantspits', 'Krant Spits'],
            ['krantregionaal', 'Krant regionaal'],
            ['krantoverigedagbladen', 'Krant overige dagbladen'],
            ['krantredactioneel', 'Krant redactioneel'],
            ['magazinedito', 'Magazine Dito'],
            ['magazinehumo', 'Magazine Humo Belgie'],
            ['dokterdokternl', 'dokterdokter.nl'],
            ['vrouwnl', 'vrouw.nl'],
            ['ditomagazinenl', 'dito-magazine.nl'],
            ['groupdealnl', 'groupdeal.nl'],
            ['marktplaatsnl', 'Marktplaats'],
            ['zorgplanet', 'Zorgplanet.nl'],
            ['linkpartner', 'Linkpartner'],
            ['youtube', 'Youtube'],
            ['linkedin', 'LinkedIn'],
            ['twitter', 'Twitter'],
            ['facebook', 'Facebook'],
            ['rtlbusinessclass', 'RTL Business Class'],
            ['nieuwsbrief', 'Nieuwsbrief'],
            ['Existing Customer', 'Bestaande klant'],
            ['zakenrelatie', 'Zakenrelatie'],
            ['mondtotmond', 'Vrienden, familie, kennissen'],
            ['collega', 'Collega'],
            ['Other', 'Anders'],
            ['Wegenerwebshop', 'Wegener webshop'],
            ['Herniapoli.nl', 'Herniapoli.nl'],
        ];

        $rows = [];
        foreach ($sources as $i => [$key, $label]) {
            $rows[] = [
                'id'         => $i + 1,
                'name'       => $label,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('lead_sources')->insert($rows);
    }
}
