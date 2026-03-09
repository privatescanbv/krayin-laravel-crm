<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Enums\ProductReports;
use App\Models\Clinic;
use App\Models\PartnerProduct;
use App\Models\PurchasePrice;
use App\Models\ResourceType;
use Exception;
use RuntimeException;
use Webkul\Product\Models\Product;

class PartnerProductSeeder extends BaseSeeder
{
    public function run(): void
    {
        // get all resources
        $mappedResources = Clinic::with('resources.resourceType')->get()->mapWithKeys(function ($clinic) {
            $resources = $clinic->resources->mapWithKeys(function ($resource) {
                $typeName = optional($resource->resourceType)->name ?? 'unknown';

                return [
                    $typeName => [
                        'id'   => $resource->id,
                        'name' => $resource->name,
                    ],
                ];
            });

            return [$clinic->id => $resources];
        })->toArray();

        $csvPath = database_path('seeders/data/partner_products.csv');

        if (! file_exists($csvPath)) {
            // Fallback to the user provided path if local file doesn't exist (e.g. if not moved yet)
            // But since we wrote it to seeders/data, we should use that.
            // If the user insists on the absolute path, we might need to adjust, but best practice is project dir.
            throw new RuntimeException("File not found: $csvPath");
        }

        $file = fopen($csvPath, 'r');

        // BOM-stripping en header-parsing
        $firstLine = fgets($file);
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine); // Strip UTF-8 BOM
        $header = str_getcsv(trim($firstLine), ';');

        // Map CSV headers to array keys for easier access
        $headerMap = array_flip($header);

        while (($row = fgetcsv($file, 0, ';')) !== false) {
            $getValue = function ($key) use ($row, $headerMap) {
                return isset($headerMap[$key]) ? ($row[$headerMap[$key]] ?? null) : null;
            };

            $data = [
                'external_id'                     => $getValue('PartnerProductID-SuiteCRM'),
                'clinic_external_id'              => $getValue('KliniekId-SuiteCRM'),
                'product_external_id'             => $getValue('Product key'),
                'naam'                            => $getValue('Naam'),
                'omschrijving'                    => $getValue('Beschrijving'),
                'duur_minuten'                    => $getValue('Duur onderzoek'),
                'omschrijving_kliniek_afb'        => $getValue('Omschrijving kliniek (AFB)'),
                'valuta'                          => $getValue('Valuta'),
                'verkoopprijs'                    => $getValue('Verkoopprijs'),
                'resourcetype'                    => $getValue('Resource Type'),
                'rapportage'                      => $getValue('Rapporten'),
                'inkoopprijs_overig'              => $getValue('Inkoopprijs overig'),
                'inkoopprijs_arts'                => $getValue('Inkoopprijs Arts'),
                'inkoopprijs_cardiologie'         => $getValue('Inkoopprijs Cardiologie'),
                'inkoopprijs_kliniek'             => $getValue('Inkoopprijs Kliniek'),
                'inkoopprijs_radiologie'          => $getValue('Inkoopprijs Radiologie'),
                //                 wat is dit? Ga ik negeren en resources van de clinic zoeken op basis van resource type
                //                'resource'                        => $getValue('Planning basseren op andere resource'),
                'discount_info'                   => $getValue('Korting informatie'),
                'active'                          => $getValue('Active'),
            ];

            $this->seedProduct($data, $mappedResources);
        }

        fclose($file);
    }

    protected function seedProduct(array $productData, array $mappedResources): void
    {
        logger()->info('Seeding partner product: '.$productData['naam']);

        $product = Product::where('external_id', (string) $productData['product_external_id'])->first();
        if (is_null($product)) {
            throw new Exception("Product not found for external_id: {$productData['product_external_id']}");
        }

        $clinic = Clinic::where('external_id', $productData['clinic_external_id'])->first();
        if (! $clinic) {
            throw new Exception("Clinic not found for external_id: {$productData['clinic_external_id']}");
        }
        $resourceType = ResourceType::where('name', strtolower($productData['resourcetype']))->first();
        if (! $resourceType) {
            throw new Exception("Resource Type not found: {$productData['resourcetype']}");
        }

        $availableTypes = array_keys($mappedResources[$clinic->id] ?? []);
        $resourceId = $mappedResources[$clinic->id][$resourceType->name]['id']
            ?? throw new Exception(
                'No resource of type "'.$resourceType->name.'" found for clinic "'.$clinic->name.'". '.
                'Available types: ['.implode(', ', $availableTypes).']'
            );
        // Parse reporting field (comma-separated string matching ProductReports enum labels)
        $reporting = [];
        if (! empty($productData['rapportage'])) {
            $reportingLabels = array_map('trim', explode(',', $productData['rapportage']));
            foreach ($reportingLabels as $label) {
                // Find matching ProductReports enum by label
                $matched = false;
                foreach (ProductReports::cases() as $report) {
                    if ($report->getLabel() === $label) {
                        $reporting[] = $report->value;
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    logger()->warning("ProductReports label not found: {$label}");
                }
            }
        }

        // Normalize prices using Currency::normalizePrice
        $normalizePrice = fn ($value) => ($value !== null && $value !== '') ? Currency::normalizePrice($value) : '0.00';

        // Parse duration - convert to int if provided, otherwise null
        $duration = null;
        if (isset($productData['duur_minuten']) && trim($productData['duur_minuten']) !== '') {
            $duration = (int) $productData['duur_minuten'];
        }

        $partnerProduct = PartnerProduct::updateOrCreate(
            ['external_id' => $productData['external_id']],
            [
                'product_id'                => $product->id,
                'name'                      => $productData['naam'],
                'description'               => $productData['omschrijving'] ?? null,
                'duration'                  => $duration,
                'clinic_description'        => $productData['omschrijving_kliniek_afb'] ?? null,
                'currency'                  => $productData['valuta'] ?? 'EUR',
                'sales_price'               => $normalizePrice($productData['verkoopprijs'] ?? null),
                'resource_type_id'          => $resourceType?->id,
                'reporting'                 => ! empty($reporting) ? $reporting : null,
                'active'                    => (bool) $productData['active'],
                'discount_info'             => $productData['discount_info'] ?? null,
            ]);

        $purchaseMisc = $normalizePrice($productData['inkoopprijs_overig'] ?? null);
        $purchaseDoctor = $normalizePrice($productData['inkoopprijs_arts'] ?? null);
        $purchaseCardiology = $normalizePrice($productData['inkoopprijs_cardiologie'] ?? null);
        $purchaseClinic = $normalizePrice($productData['inkoopprijs_kliniek'] ?? null);
        $purchaseRadiology = $normalizePrice($productData['inkoopprijs_radiologie'] ?? null);

        $mainTotal = (float) $purchaseMisc
            + (float) $purchaseDoctor
            + (float) $purchaseCardiology
            + (float) $purchaseClinic
            + (float) $purchaseRadiology;

        PurchasePrice::updateOrCreate(
            [
                'priceable_type' => PartnerProduct::class,
                'priceable_id'   => $partnerProduct->id,
                'type'           => 'main',
            ],
            [
                'purchase_price_misc'       => $purchaseMisc,
                'purchase_price_doctor'     => $purchaseDoctor,
                'purchase_price_cardiology' => $purchaseCardiology,
                'purchase_price_clinic'     => $purchaseClinic,
                'purchase_price_radiology'  => $purchaseRadiology,
                'purchase_price'            => number_format($mainTotal, 2, '.', ''),
            ]
        );

        $partnerProduct->clinics()->syncWithoutDetaching([$clinic->id]);
        $partnerProduct->resources()->syncWithoutDetaching([$resourceId]);
    }
}
