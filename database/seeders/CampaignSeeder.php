<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = [
            ['external_id' => '69b238c0-e630-b733-2bb3-4fd85ff554da', 'name' => 'Afspraakformulier Privatescan.nl'],
            ['external_id' => '39153848-eea7-9f47-cc53-5eb952a33583', 'name' => 'Herniapoli - Diagnose formulier'],
            ['external_id' => 'e24e5c44-5fc5-34e8-ed3c-653a2a1d8c10', 'name' => 'GA - Hernia behandeling - Hernia behandeling'],
            ['external_id' => '543acfe1-f0ac-8063-5e35-4ffbd2a1432b', 'name' => 'Bel mij terug formulier Privatescan'],
            ['external_id' => 'b29ce43f-9d76-6542-723c-5c62d57cccd7', 'name' => 'Vragen formulier website privatescan.nl'],
            ['external_id' => 'e76401c9-6efc-751b-cd57-653a2a3ac50e', 'name' => 'GA - Hernia behandeling - Hernia operatie'],
            ['external_id' => 'cc265538-835e-def4-6202-51cac84925be', 'name' => 'Adwords - BS (074 - 255 26 90)'],
            ['external_id' => '6e4d78b5-37e9-efd7-4224-653a2b05587e', 'name' => 'GA - Hernia behandeling - PTED operatie'],
            ['external_id' => '9d25174d-83c8-ac78-4f4c-653a20c2ffcd', 'name' => 'GA - Branded - Herniakliniek'],
            ['external_id' => 'c7dbd29a-0975-d5c6-4153-653a2c763aa4', 'name' => 'GA - Hernia symptomen - Hernia symptomen'],
            ['external_id' => '876f9c4a-0802-e61e-8035-653a2f8fe866', 'name' => 'GA - Hernia symptomen - Hernia klachten'],
            ['external_id' => '68aef775-5f7f-61f3-d361-653a2c978c2f', 'name' => 'GA - Hernia symptomen - Hernia pijn'],
            ['external_id' => '9276bda3-6c9c-abcb-575a-653a2c7430c5', 'name' => 'GA - Hernia symptomen - DSA-Hernia symptomen'],
            ['external_id' => '26221d52-feff-19b8-3680-64ae9e1224c1', 'name' => 'MRI Afspraak maken'],
            ['external_id' => 'a38acb85-a929-63df-69c4-653a1dcb6052', 'name' => 'GA - Branded - Herniapoli'],
            ['external_id' => '9f6ec983-42da-23c4-21f3-653a2b621340', 'name' => 'GA - Hernia behandeling - Hernia herstel'],
            ['external_id' => '81289196-5d51-817a-c8c5-5eb9419e4d9b', 'name' => 'Herniapoli - Contact pagina'],
            ['external_id' => '373d13b0-7386-3fe6-c887-5eb94c022c1a', 'name' => 'Herniapoli - Bel mij terug'],
            ['external_id' => 'ad679153-fee1-663c-8cec-5e41785d0bf1', 'name' => 'Vragenformulier sectie specials privatescan.nl'],
            ['external_id' => 'b5d4e16f-0246-36d7-bacc-4fd85e8ee774', 'name' => 'Contactformulier Privatescan.nl'],
            ['external_id' => 'c6020f18-517c-c2f1-dcf3-63ff4f550abf', 'name' => 'Facebook - Animatievideo - CPC'],
        ];

        foreach ($campaigns as $campaign) {
            $externalId = trim($campaign['external_id'] ?? '');
            $name = trim($campaign['name'] ?? '');

            if ($externalId === '' || $name === '') {
                continue;
            }

            $now = now();

            $exists = DB::table('marketing_campaigns')
                ->where('external_id', $externalId)
                ->exists();

            if ($exists) {
                DB::table('marketing_campaigns')
                    ->where('external_id', $externalId)
                    ->update([
                        'name'       => $name,
                        'subject'    => $name,
                        'updated_at' => $now,
                    ]);

                continue;
            }

            DB::table('marketing_campaigns')->insert([
                'name'        => $name,
                'external_id' => $externalId,
                'subject'     => $name,
                'status'      => 1,
                'type'        => 'email',
                'mail_to'     => 'persons',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }
}
