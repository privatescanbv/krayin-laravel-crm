<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Enums\ProductReports;
use App\Models\Clinic;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\ResourceType;
use RuntimeException;
use Webkul\Product\Models\Product;

class PartnerProductSeeder extends BaseSeeder
{
    public function run(): void
    {
        $products = [
            [
                'template_product'                => 'TB3 Royal Bodyscan + Wervelkolom',
                'kliniek'                         => 'Evidia - Augusta Klinik',
                'naam'                            => 'TB3 Royal Bodyscan + Wervelkolom',
                'omschrijving'                    => 'TB3 Royal Bodyscan + Wervelkolom, bestaande uit:

MRI onderzoeken:

- MRI Schedel en hersenen

- MRI Hals en aanvoerende bloedvaten hersenen (carotiden)

- MRI Aortaboog (hoofdslagader lichaam)

- MRI Longen (overzichtsscan)

- MRI Boven- en onderbuik organen (m.u.v. slokdarm, maag en darmen)

- MRI Bekken organen (prostaat, baarmoeder en eierstokken)

- MRI Gehele wervelkolom gedetailleerd',
                'duur_minuten'                    => '45',
                'omschrijving_kliniek_afb'        => 'TB3',
                'valuta'                          => 'EUR',
                'verkoopprijs'                    => '2690,00',
                'resourcetype'                    => 'MRI scanner',
                'rapportage'                      => 'Radiologie MRI',
                'inkoopprijs_overig'              => '0,00',
                'inkoopprijs_arts'                => '0,00',
                'inkoopprijs_cardiologie'         => '0,00',
                'inkoopprijs_kliniek'             => '0,00',
                'inkoopprijs_radiologie'          => '506,00',
                'resource'                        => 'Ranova - Augusta MRI Scanner 1',
            ],
            [
                'template_product'                 => 'Bloed- en urineonderzoek preventief uitgebreid heren',
                'kliniek'                          => 'Ambulante Kardiologie Augusta',
                'naam'                             => 'Bloed- en urineonderzoek preventief uitgebreid heren',
                'omschrijving'                     => 'Uitgebreid laboratoriumonderzoek (bloed en urine) met PSA bepaling',
                'duur_minuten'                     => '0',
                'omschrijving_kliniek_afb'         => 'Grosses Blut bild, inkl. Urin + PSA',
                'valuta'                           => 'EUR',
                'verkoopprijs'                     => '0,00',
                'resourcetype'                     => 'Cardiologie',
                'rapportage'                       => 'Laboratoriumuitslag',
                'inkoopprijs_overig'               => '0,00',
                'inkoopprijs_arts'                 => '0,00',
                'inkoopprijs_cardiologie'          => '38,00',
                'inkoopprijs_kliniek'              => '0,00',
                'inkoopprijs_radiologie'           => '0,00',
                'resource'                         => 'Augusta Cardiologie',
            ],
        ];

        foreach ($products as $productData) {
            // Look up Product by name
            $product = Product::where('name', $productData['template_product'])->firstOrFail();

            logger()->info('Search clinic: '.$productData['kliniek']);
            // Look up Clinic by name
            $clinic = Clinic::where('name', $productData['kliniek'])->firstOrFail();

            $resource = Resource::where('name', $productData['resource'])->firstOrFail();

            // Look up ResourceType by name
            $resourceType = null;
            if (! empty($productData['resourcetype'])) {
                $resourceType = ResourceType::where('name', $productData['resourcetype'])->firstOrFail();
            }

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
                        throw new RuntimeException("ProductReports label not found: {$label}");
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

            $partnerProduct = PartnerProduct::create([
                'product_id'                => $product->id,
                'name'                      => $productData['naam'],
                'description'               => $productData['omschrijving'] ?? null,
                'duration'                  => $duration,
                'clinic_description'        => $productData['omschrijving_kliniek_afb'] ?? null,
                'currency'                  => $productData['valuta'] ?? 'EUR',
                'sales_price'               => $normalizePrice($productData['verkoopprijs'] ?? null),
                'resource_type_id'          => $resourceType?->id,
                'reporting'                 => ! empty($reporting) ? $reporting : null,
                'purchase_price_misc'       => $normalizePrice($productData['inkoopprijs_overig'] ?? null),
                'purchase_price_doctor'     => $normalizePrice($productData['inkoopprijs_arts'] ?? null),
                'purchase_price_cardiology' => $normalizePrice($productData['inkoopprijs_cardiologie'] ?? null),
                'purchase_price_clinic'     => $normalizePrice($productData['inkoopprijs_kliniek'] ?? null),
                'purchase_price_radiology'  => $normalizePrice($productData['inkoopprijs_radiologie'] ?? null),
                'active'                    => true,
            ]);

            // Attach clinic via many-to-many relationship
            $partnerProduct->clinics()->attach($clinic->id);
            $partnerProduct->resources()->attach($resource->id);

        }
    }
}
