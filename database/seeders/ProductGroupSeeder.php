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
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing product groups
        DB::table('product_groups')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create parent groups first
        $parentGroups = [
            [
                'name'        => 'Onderzoeken',
                'description' => 'Alles met MRI en CT / inwendig, bloedonderzoeken',
                'parent_id'   => null,
            ],
            [
                'name'        => 'Behandelingen',
                'description' => 'Alles met Operaties, Injecties, ...',
                'parent_id'   => null,
            ],
            [
                'name'        => 'Diensten',
                'description' => 'Alles met controles, bemiddelingskosten , vertalingen',
                'parent_id'   => null,
            ],
        ];

        $parentGroupIds = [];
        foreach ($parentGroups as $group) {
            $createdGroup = ProductGroup::create($group);
            $parentGroupIds[$group['name']] = $createdGroup->id;
        }

        // Create child groups with parent references
        $childGroups = [
            // Onderzoeken children
            [
                'name'        => 'Wervelkolom',
                'description' => 'Wervelkolom (gehele, deels, incl. en excl. KM)',
                'parent_id'   => $parentGroupIds['Onderzoeken'],
            ],
            [
                'name'        => 'Buik',
                'description' => 'Buik (prostaat, abdomen, onderbuik, bovenbuik, maag, darmen (alles) )',
                'parent_id'   => $parentGroupIds['Onderzoeken'],
            ],
            [
                'name'        => 'Hoofd',
                'description' => 'Hoofd (hersenen, halsslagaderen, TOF sequenties',
                'parent_id'   => $parentGroupIds['Onderzoeken'],
            ],
            [
                'name'        => 'Bloedvaten',
                'description' => 'Bloedvaten (carotiden los, Angiografie, bekken-been angio)',
                'parent_id'   => $parentGroupIds['Onderzoeken'],
            ],
            [
                'name'        => 'Bloedonderzoeken',
                'description' => 'Bloedonderzoeken (standaard lab, extra waarden??)',
                'parent_id'   => $parentGroupIds['Onderzoeken'],
            ],
            [
                'name'        => 'Bodyscan',
                'description' => 'Bodyscan (Tb1, Tb2, TB3, TB4, TB5)',
                'parent_id'   => $parentGroupIds['Onderzoeken'],
            ],

            // Behandelingen children
            [
                'name'        => 'PTED',
                'description' => 'PTED (alle niveaus)',
                'parent_id'   => $parentGroupIds['Behandelingen'],
            ],
            [
                'name'        => 'Micro',
                'description' => 'Micro (alle niveaus)',
                'parent_id'   => $parentGroupIds['Behandelingen'],
            ],
            [
                'name'        => 'ACDF',
                'description' => 'ACDF (alle niveaus)',
                'parent_id'   => $parentGroupIds['Behandelingen'],
            ],
            [
                'name'        => 'Injecties',
                'description' => 'Injecties (PRT, Facetinfiltratie enz)',
                'parent_id'   => $parentGroupIds['Behandelingen'],
            ],

            // Diensten children
            [
                'name'        => 'Bemiddelingskosten',
                'description' => 'Bemiddelingskosten',
                'parent_id'   => $parentGroupIds['Diensten'],
            ],
            [
                'name'        => 'Nazorg',
                'description' => 'Nazorg',
                'parent_id'   => $parentGroupIds['Diensten'],
            ],
            [
                'name'        => 'Controle arts',
                'description' => 'Controle artsen',
                'parent_id'   => $parentGroupIds['Diensten'],
            ],
            [
                'name'        => 'Vertalingen',
                'description' => 'Vertalingen HP en PS',
                'parent_id'   => $parentGroupIds['Diensten'],
            ],
        ];

        foreach ($childGroups as $group) {
            ProductGroup::create($group);
        }

        $this->command->info('Product groups seeded successfully!');
        $this->command->info('Created '.count($parentGroups).' parent groups and '.count($childGroups).' child groups.');
    }
}
