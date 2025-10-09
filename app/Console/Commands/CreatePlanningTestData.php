<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Models\PartnerProduct;
use App\Models\ProductType;
use App\Models\Resource;
use App\Models\ResourceType;
use Illuminate\Console\Command;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductGroup;

class CreatePlanningTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'planning:create-test-data
                            {--clinic-id= : ID van bestaande kliniek (optioneel)}
                            {--resource-type=Artsen : Type resource om aan te maken}
                            {--count=3 : Aantal resources om aan te maken}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Maakt test data aan voor planning: ProductGroup, Resource, PartnerProduct, Product - allemaal gerelateerd';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Aanmaken van planning test data...');

        // 1. Kliniek ophalen of aanmaken
        $clinic = $this->getOrCreateClinic();
        $this->info("✅ Kliniek: {$clinic->name} (ID: {$clinic->id})");

        // 2. Resource Type ophalen of aanmaken
        $resourceType = $this->getOrCreateResourceType();
        $this->info("✅ Resource Type: {$resourceType->name} (ID: {$resourceType->id})");

        // 3. Product Type ophalen of aanmaken
        $productType = $this->getOrCreateProductType();
        $this->info("✅ Product Type: {$productType->name} (ID: {$productType->id})");

        // 4. Product Group aanmaken
        $productGroup = $this->createProductGroup();
        $this->info("✅ Product Group: {$productGroup->name} (ID: {$productGroup->id})");

        // 5. Product aanmaken
        $product = $this->createProduct($productGroup, $resourceType, $productType);
        $this->info("✅ Product: {$product->name} (ID: {$product->id})");

        // 6. Partner Product aanmaken
        $partnerProduct = $this->createPartnerProduct($product, $resourceType);
        $this->info("✅ Partner Product: {$partnerProduct->name} (ID: {$partnerProduct->id})");

        // 7. Resources aanmaken
        $resources = $this->createResources($clinic, $resourceType);
        $this->info('✅ Resources aangemaakt: '.count($resources));

        // 8. Koppelingen maken
        $this->createRelationships($partnerProduct, $clinic, $resources);

        $this->info('');
        $this->info('🎉 Alle test data succesvol aangemaakt!');
        $this->info('');
        $this->info('📋 Overzicht:');
        $this->info("   • Kliniek: {$clinic->name} (ID: {$clinic->id})");
        $this->info("   • Resource Type: {$resourceType->name} (ID: {$resourceType->id})");
        $this->info("   • Product Group: {$productGroup->name} (ID: {$productGroup->id})");
        $this->info("   • Product: {$product->name} (ID: {$product->id})");
        $this->info("   • Partner Product: {$partnerProduct->name} (ID: {$partnerProduct->id})");
        $this->info('   • Resources: '.count($resources).' stuks');
        $this->info('');
        $this->info('🔗 Je kunt nu plannen met:');
        $this->info("   • Resource Type ID: {$resourceType->id}");
        $this->info("   • Clinic ID: {$clinic->id}");
    }

    private function getOrCreateClinic(): Clinic
    {
        $clinicId = $this->option('clinic-id');

        if ($clinicId) {
            $clinic = Clinic::find($clinicId);
            if (! $clinic) {
                $this->error("Kliniek met ID {$clinicId} niet gevonden!");
                exit(1);
            }

            return $clinic;
        }

        // Zoek bestaande kliniek of maak nieuwe aan
        $clinic = Clinic::first();
        if (! $clinic) {
            $clinic = Clinic::create([
                'name'        => 'Test Kliniek Amsterdam',
                'website_url' => 'https://testkliniek.nl',
                'emails'      => ['info@testkliniek.nl'],
                'phones'      => ['+31 20 123 4567'],
            ]);
        }

        return $clinic;
    }

    private function getOrCreateResourceType(): ResourceType
    {
        $resourceTypeName = $this->option('resource-type');

        $resourceType = ResourceType::where('name', $resourceTypeName)->first();
        if (! $resourceType) {
            $resourceType = ResourceType::create([
                'name'        => $resourceTypeName,
                'description' => "Test resource type: {$resourceTypeName}",
            ]);
        }

        return $resourceType;
    }

    private function getOrCreateProductType(): ProductType
    {
        $productType = ProductType::where('name', 'Medische Dienst')->first();
        if (! $productType) {
            $productType = ProductType::create([
                'name'        => 'Medische Dienst',
                'description' => 'Test product type voor medische diensten',
            ]);
        }

        return $productType;
    }

    private function createProductGroup(): ProductGroup
    {
        return ProductGroup::create([
            'name'        => 'Test Planning Producten',
            'description' => 'Product groep voor planning test data',
        ]);
    }

    private function createProduct(ProductGroup $productGroup, ResourceType $resourceType, ProductType $productType): Product
    {
        return Product::create([
            'name'             => 'MRI Scan Planning',
            'description'      => 'MRI scan die kan worden ingepland',
            'active'           => true,
            'currency'         => 'EUR',
            'price'            => 450.00,
            'costs'            => 200.00,
            'product_group_id' => $productGroup->id,
            'resource_type_id' => $resourceType->id,
            'product_type_id'  => $productType->id,
        ]);
    }

    private function createPartnerProduct(Product $product, ResourceType $resourceType): PartnerProduct
    {
        return PartnerProduct::create([
            'name'                  => 'MRI Scan - Partner Product',
            'description'           => 'Partner product voor MRI scan planning',
            'active'                => true,
            'currency'              => 'EUR',
            'sales_price'           => 450.00,
            'duration'              => 60, // 60 minuten
            'resource_type_id'      => $resourceType->id,
            'product_id'            => $product->id,
            'purchase_price'        => 200.00,
            'purchase_price_clinic' => 180.00,
        ]);
    }

    private function createResources(Clinic $clinic, ResourceType $resourceType): array
    {
        $count = (int) $this->option('count');
        $resources = [];

        for ($i = 1; $i <= $count; $i++) {
            $resources[] = Resource::create([
                'name'             => "Test {$resourceType->name} {$i}",
                'resource_type_id' => $resourceType->id,
                'clinic_id'        => $clinic->id,
            ]);
        }

        return $resources;
    }

    private function createRelationships(PartnerProduct $partnerProduct, Clinic $clinic, array $resources): void
    {
        // Koppel partner product aan kliniek
        $partnerProduct->clinics()->attach($clinic->id);

        // Koppel partner product aan resources
        $resourceIds = collect($resources)->pluck('id')->toArray();
        $partnerProduct->resources()->attach($resourceIds);

        $this->info('✅ Koppelingen gemaakt:');
        $this->info('   • Partner Product gekoppeld aan Kliniek');
        $this->info('   • Partner Product gekoppeld aan '.count($resources).' Resources');
    }
}
