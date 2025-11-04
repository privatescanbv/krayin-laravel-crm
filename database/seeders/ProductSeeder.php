<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Models\ProductType;
use App\Models\ResourceType;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductGroup;

class ProductSeeder extends BaseSeeder
{
    public function run(): void
    {
        $rows = [
            [
                'productnaam'   => 'TB3 Royal Bodyscan + Wervelkolom',
                'omschrijving'  => 'TB3 Royal Bodyscan + Wervelkolom, bestaande uit:',
                'valuta'        => 'EUR',
                'inkoopprijs'   => '600,00',
                'verkoopprijs'  => '1900,00',
                'producttype'   => 'Total Bodyscan',
                'resourcetype'  => 'MRI scanner',
                'productgroep'  => 'Bodyscan',
            ],
            [
                'productnaam'   => 'MRI Abdomen exclusief CM',
                'omschrijving'  => 'MRI scan van de onder- en bovenbuik (abdomen), exclusief contrastmiddel',
                'valuta'        => 'EUR',
                'inkoopprijs'   => '220,00',
                'verkoopprijs'  => '850,00',
                'producttype'   => 'MRI scan',
                'resourcetype'  => 'MRI scanner',
                'productgroep'  => 'Buik',
            ],
            [
                'productnaam'   => 'CT Abdomen inclusief CM',
                'omschrijving'  => 'CT scan van de onder- en bovenbuik (abdomen), inclusief contrastmiddel',
                'valuta'        => 'EUR',
                'inkoopprijs'   => '300,00',
                'verkoopprijs'  => '650,00',
                'producttype'   => 'CT scan',
                'resourcetype'  => 'CT scanner',
                'productgroep'  => 'Buik',
            ],
            [
                'productnaam'   => 'Bloed- en urineonderzoek preventief uitgebreid heren',
                'omschrijving'  => 'Uitgebreid laboratoriumonderzoek (bloed en urine) met PSA bepaling',
                'valuta'        => 'EUR',
                'inkoopprijs'   => '85,00',
                'verkoopprijs'  => '220,00',
                'producttype'   => 'Laboratorium',
                'resourcetype'  => 'Cardiologie',
                'productgroep'  => 'Bloedonderzoeken',
            ],
            [
                'productnaam'   => 'Coördinatie- en bemiddelingskosten',
                'omschrijving'  => 'Coördinatie- en bemiddelingskosten',
                'valuta'        => 'EUR',
                'inkoopprijs'   => '0,00',
                'verkoopprijs'  => '59,00',
                'producttype'   => 'Diensten',
                'resourcetype'  => 'Overig',
                'productgroep'  => 'Bemiddelingskosten',
            ],
            [
                'productnaam'   => 'Volledige vertaling TB3',
                'omschrijving'  => 'Nederlandse vertaling van de rapportage',
                'valuta'        => 'EUR',
                'inkoopprijs'   => '80,00',
                'verkoopprijs'  => '185,00',
                'producttype'   => 'Vertaling',
                'resourcetype'  => 'Overig',
                'productgroep'  => 'Vertalingen',
            ],
            [
                'productnaam'   => 'Transf. Endosc. Operatie 1 n',
                'omschrijving'  => 'Transforaminale endoscopische operatie 1 niveau',
                'valuta'        => 'EUR',
                'inkoopprijs'   => '4950,00',
                'verkoopprijs'  => '9500,00',
                'producttype'   => null,
                'resourcetype'  => 'Artsen',
                'productgroep'  => 'PTED',
            ],
            [
                'productnaam'   => 'Transf. Endosc. Operatie 2 n',
                'omschrijving'  => 'Transforaminale endoscopische operatie 2 niveaus',
                'valuta'        => 'EUR',
                'inkoopprijs'   => '9000,00',
                'verkoopprijs'  => '16000,00',
                'producttype'   => null,
                'resourcetype'  => 'Artsen',
                'productgroep'  => 'PTED',
            ],
            [
                'productnaam'   => 'ACDF (Anterior Cervical Discectomy and Fusion) operatie',
                'omschrijving'  => 'ACDF (Anterior Cervical Discectomy and Fusion) operatie',
                'valuta'        => 'EUR',
                'inkoopprijs'   => '5900,00',
                'verkoopprijs'  => '13900,00',
                'producttype'   => 'Overig',
                'resourcetype'  => 'Artsen',
                'productgroep'  => 'ACDF',
            ],
        ];

        foreach ($rows as $row) {
            $name = $row['productnaam'];

            // Resolve ProductType by name (label) if provided
            $productTypeId = null;
            if (! empty($row['producttype'])) {
                $productType = ProductType::where('name', $row['producttype'])->first();
                if (! $productType) {
                    throw new \RuntimeException("ProductType not found: {$row['producttype']}");
                }
                $productTypeId = $productType->id;
            }

            // Map Dutch 'Overig' to seeded 'Other' for ResourceType labels
            $resourceTypeName = $row['resourcetype'];
            if ($resourceTypeName === 'Overig') {
                $resourceTypeName = 'Other';
            }

            $resourceType = ResourceType::where('name', $resourceTypeName)->first();
            if (! $resourceType) {
                throw new \RuntimeException("ResourceType not found: {$row['resourcetype']}");
            }

            // Resolve ProductGroup by name
            $group = ProductGroup::where('name', $row['productgroep'])->first();
            if (! $group) {
                throw new \RuntimeException("ProductGroup not found: {$row['productgroep']}");
            }

            $price = Currency::normalizePrice($row['verkoopprijs'] ?? null) ?? '0.00';
            $costs = Currency::normalizePrice($row['inkoopprijs'] ?? null) ?? '0.00';
            $currency = $row['valuta'] ?? 'EUR';

            Product::create([
                'name'             => $name,
                'description'      => $row['omschrijving'] ?? null,
                'currency'         => $currency,
                'price'            => $price,
                'costs'            => $costs,
                'resource_type_id' => $resourceType->id,
                'product_type_id'  => $productTypeId,
                'product_group_id' => $group->id,
                'active'           => true,
            ]);
        }
    }
}
