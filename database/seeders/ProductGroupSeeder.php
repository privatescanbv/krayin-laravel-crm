<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\Product\Models\ProductGroup;

class ProductGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('product_groups')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $groups = [
            // Top-level groups
            [
                'name'        => 'Onderzoeken',
                'description' => 'Alles met MRI en CT / inwendig, bloedonderzoeken',
                'parent'      => null,
            ],
            [
                'name'        => 'Diensten',
                'description' => 'Alles met controles, bemiddelingskosten, vertalingen',
                'parent'      => null,
            ],
            [
                'name'        => 'Behandelingen',
                'description' => 'Alles met Operaties, Injecties, ...',
                'parent'      => null,
            ],

            // Onderzoeken children
            [
                'name'        => 'Hoofd',
                'description' => 'Hoofd (hersenen, halsslagaderen, TOF sequenties',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Schedel (los)',
                'description' => 'MRI en CT Schedel, neustussenschot, excl. En incl. contrastmiddel',
                'parent'      => 'Hoofd',
            ],
            [
                'name'        => 'Schedel en Carotiden',
                'description' => 'MRI en CT Schedle en Carotiden incl. contrastmiddel',
                'parent'      => 'Hoofd',
            ],
            [
                'name'        => 'Hals weke delen',
                'description' => 'MRI en CT van de Hals weke delen',
                'parent'      => 'Hoofd',
            ],
            [
                'name'        => 'Bloedvaten',
                'description' => 'Bloedvaten (carotiden los, Angiografie, bekken-been angio)',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Gewrichten',
                'description' => 'Gewrichten (bekken, heupen, knieën, enkels, voeten, schouders, ellebogen, polsen, handen',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Schouder',
                'description' => 'MRI en CT Schouder',
                'parent'      => 'Gewrichten',
            ],
            [
                'name'        => 'Elleboog',
                'description' => 'MRI en CT Elleboog',
                'parent'      => 'Gewrichten',
            ],
            [
                'name'        => 'Pols',
                'description' => 'MRI en CT Pols',
                'parent'      => 'Gewrichten',
            ],
            [
                'name'        => 'Hand',
                'description' => 'MRI en CT Hand',
                'parent'      => 'Gewrichten',
            ],
            [
                'name'        => 'Heup',
                'description' => 'MRI en CT Heup',
                'parent'      => 'Gewrichten',
            ],
            [
                'name'        => 'Knie',
                'description' => 'MRI en CT Knie',
                'parent'      => 'Gewrichten',
            ],
            [
                'name'        => 'Enkel',
                'description' => 'MRI en CT Enkel',
                'parent'      => 'Gewrichten',
            ],
            [
                'name'        => 'Voet',
                'description' => 'MRI en CT Voet',
                'parent'      => 'Gewrichten',
            ],
            [
                'name'        => 'Bekken',
                'description' => 'MRI en CT Bekken',
                'parent'      => 'Gewrichten',
            ],
            [
                'name'        => 'Longen',
                'description' => 'Longen (longfunctie, MRI en CT incl. en excl. CM)',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Hart',
                'description' => 'Hart (cardiologie, losse cardiologieonderzoeken, alle hartchecks met CTC en CTA)',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Wervelkolom',
                'description' => 'Wervelkolom (gehele, deels, incl. en excl. CM)',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Gehele wervelkolom',
                'description' => 'MRI en CT Gehele wervelkolom incl. en excl. CM',
                'parent'      => 'Wervelkolom',
            ],
            [
                'name'        => 'Halswervelkolom (HWS)',
                'description' => 'MRI en CT Halswervelkolom (HWS) incl. en excl. CM',
                'parent'      => 'Wervelkolom',
            ],
            [
                'name'        => 'Borstwervelkolom (BWS)',
                'description' => 'MRI en CT Borstwervelkolom (BWS) incl. en excl. CM',
                'parent'      => 'Wervelkolom',
            ],
            [
                'name'        => 'Halswervel- en borstwervelkolom (HWS-BWS)',
                'description' => 'Halswervel- en borstwervelkolom (HWS-BWS)',
                'parent'      => 'Wervelkolom',
            ],
            [
                'name'        => 'Lendenwervelkolom (LWS)',
                'description' => 'MRI en CT Lendenwervelkolom (LWS) incl. en excl. CM',
                'parent'      => 'Wervelkolom',
            ],
            [
                'name'        => 'SI gewricht',
                'description' => 'MRI en CT SI gewricht incl. en excl. CM',
                'parent'      => 'Wervelkolom',
            ],
            [
                'name'        => 'Buik',
                'description' => 'Buik (abdomen, onderbuik, bovenbuik, maag, darmen (alles) )',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Abdomen (onderbuik en bovenbuik)',
                'description' => 'MRI en CT Abdomen (onderbuik en bovenbuik) incl. en excl. CM',
                'parent'      => 'Buik',
            ],
            [
                'name'        => 'Extra sequenties',
                'description' => 'MRI en CT Extra sequenties incl. en excl. CM',
                'parent'      => 'Buik',
            ],
            [
                'name'        => 'Bovenbuik',
                'description' => 'MRI en CT Bovenbuik incl. en excl. CM',
                'parent'      => 'Buik',
            ],
            [
                'name'        => 'Onderbuik',
                'description' => 'MRI en CT Onderbuik incl. en excl. CM',
                'parent'      => 'Buik',
            ],
            [
                'name'        => 'Abdomen en buikaorta',
                'description' => 'MRI en CT Abdomen en buikaorta incl. en excl. CM',
                'parent'      => 'Buik',
            ],
            [
                'name'        => 'Darmen',
                'description' => 'MRI en CT Darmen incl. en excl. CM',
                'parent'      => 'Buik',
            ],
            [
                'name'        => 'Hart en longen (Thorax)',
                'description' => 'Hart en longen (Thorax)',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Borsten',
                'description' => 'Borsten (Mammografie)',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Prostaat',
                'description' => 'MRI prostaat',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Bodyscans',
                'description' => 'Bodyscan (TB1, TB2, TB3, TB4, TB5)',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Last-Minute',
                'description' => 'Last-Minute bodyscans (TB1, TB2, TB3 en TB4)',
                'parent'      => 'Bodyscans',
            ],
            [
                'name'        => 'Royal',
                'description' => 'Royal bodyscans (TB1, TB2, TB3 en TB4)',
                'parent'      => 'Bodyscans',
            ],
            [
                'name'        => 'Regular',
                'description' => 'Regular bodyscans (TB1, TB2, TB3 en TB4)',
                'parent'      => 'Bodyscans',
            ],
            [
                'name'        => 'Bloedonderzoeken',
                'description' => 'Bloedonderzoeken (alle)',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Preventief bloed- en urineonderzoek uitgebreid',
                'description' => 'Preventief bloed- en urineonderzoek uitgebreid',
                'parent'      => 'Bloedonderzoeken',
            ],
            [
                'name'        => 'Cardiologie',
                'description' => 'Cardiologie Hart en Longen (ECG, Echocardiologie, Longfunctie)',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Hart Cardio',
                'description' => 'ECG, Echo, Cardio',
                'parent'      => 'Hart',
            ],
            [
                'name'        => 'Longen Cardio',
                'description' => 'Longfunctie',
                'parent'      => 'Longen',
            ],
            [
                'name'        => 'PET / CT',
                'description' => 'Alle Pet / CT scan onderzoeken',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Metastasen',
                'description' => 'Metastasen (uitzaaiingen)',
                'parent'      => 'PET / CT',
            ],
            [
                'name'        => 'Overig',
                'description' => 'NTB onderzoeken',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Schildklier',
                'description' => 'Schildklier',
                'parent'      => 'Overig',
            ],
            [
                'name'        => 'Carotiden',
                'description' => 'Carotiden',
                'parent'      => 'Overig',
            ],
            [
                'name'        => 'Ledematen',
                'description' => 'MRI en CT armen en benen',
                'parent'      => 'Onderzoeken',
            ],
            [
                'name'        => 'Deelonderzoek',
                'description' => 'Deelonderzoek',
                'parent'      => 'Overig',
            ],

            // Diensten children
            [
                'name'        => 'Vertaling',
                'description' => 'Alle vertalingen',
                'parent'      => 'Diensten',
            ],
            [
                'name'        => 'Volledige vertaling gericht onderzoek',
                'description' => 'Volledige vertaling gericht onderzoek',
                'parent'      => 'Vertaling',
            ],
            [
                'name'        => 'Volledige vertaling Total Bodyscan',
                'description' => 'Volledige vertaling Total Bodyscan',
                'parent'      => 'Vertaling',
            ],
            [
                'name'        => 'Vertaalde samenvatting',
                'description' => 'Vertaalde samenvatting',
                'parent'      => 'Vertaling',
            ],
            [
                'name'        => 'Overnachting',
                'description' => 'Overnachting in hotel / kliniek',
                'parent'      => 'Diensten',
            ],
            [
                'name'        => 'Administratief',
                'description' => 'Bemiddeling, korting en toeslagen, annulatie en extra cd',
                'parent'      => 'Diensten',
            ],
            [
                'name'        => 'Consult_Beoordeling',
                'description' => 'Alle arts consult en beoordelingen mogelijkheden',
                'parent'      => 'Diensten',
            ],
            [
                'name'        => 'Beoordeling',
                'description' => 'Beoordeling',
                'parent'      => 'Consult_Beoordeling',
            ],
            [
                'name'        => 'Arts consultatie',
                'description' => 'Arts consultatie',
                'parent'      => 'Consult_Beoordeling',
            ],

            // Behandelingen children
            [
                'name'        => 'Operatief',
                'description' => 'PTED, Microscopie, TLIF, ACDF',
                'parent'      => 'Behandelingen',
            ],
            [
                'name'        => 'PTED',
                'description' => 'Transf. En Interl. Endoscopische operatie',
                'parent'      => 'Operatief',
            ],
            [
                'name'        => 'Micro',
                'description' => 'Microscopische operatie',
                'parent'      => 'Operatief',
            ],
            [
                'name'        => 'TLIF',
                'description' => 'TLIF operaties',
                'parent'      => 'Operatief',
            ],
            [
                'name'        => 'ACDF',
                'description' => 'ACDF operaties',
                'parent'      => 'Operatief',
            ],
            [
                'name'        => 'Conservatief',
                'description' => 'Infiltratie, PRT, Denervatie',
                'parent'      => 'Behandelingen',
            ],
            [
                'name'        => 'Infiltratie',
                'description' => 'Facet- en SI-gewricht infiltratie',
                'parent'      => 'Conservatief',
            ],
            [
                'name'        => 'PRT',
                'description' => 'PRT injecties',
                'parent'      => 'Conservatief',
            ],
            [
                'name'        => 'Denervatie',
                'description' => 'Facetdenervatie',
                'parent'      => 'Conservatief',
            ],
        ];

        $createdGroups = [];

        foreach ($groups as $group) {
            $name = trim($group['name']);
            $parentName = $group['parent'] ? trim($group['parent']) : null;

            $productGroup = ProductGroup::create([
                'name'        => $name,
                'description' => $group['description'],
                'parent_id'   => $parentName ? ($createdGroups[$parentName] ?? null) : null,
            ]);

            $createdGroups[$name] = $productGroup->id;
        }

        $this->command->info('Product groups seeded successfully!');
        $this->command->info('Created '.count($groups).' product groups.');
    }
}
