<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Models\PartnerProduct;
use App\Models\ProductType;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Shift;
use App\Repositories\ClinicRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
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

        // 3. Producten aanmaken
        $products = $this->createProducts();
        $this->info('✅ Producten aangemaakt: '.count($products));

        // 7. Resources aanmaken
        $resources = $this->createResources($clinic, $resourceType);
        $this->info('✅ Resources aangemaakt: '.count($resources));

        // 8. Shifts aanmaken voor resources (08:00 - 17:00 op verschillende dagen)
        $this->createShiftsForResources($resources);

        // 9. Voorbeeld mail aanmaken (ongelezen, niet assigned)
        $this->createExampleEmail();

        // 10. Koppelingen maken
        $this->createRelationships($products, $clinic, $resources);

        $this->info('');
        $this->info('🎉 Alle test data succesvol aangemaakt!');
        $this->info('');
        $this->info('📋 Overzicht:');
        $this->info("   • Kliniek: {$clinic->name} (ID: {$clinic->id})");
        $this->info("   • Resource Type: {$resourceType->name} (ID: {$resourceType->id})");
        $this->info('   • Producten: '.count($products).' stuks');
        $this->info('   • Resources: '.count($resources).' stuks');
        $this->info('   • E-mail: Voorbeeld ongelezen e-mail aangemaakt');
        $this->info('');
        $this->info('🔗 Je kunt nu plannen met:');
        $this->info("   • Resource Type ID: {$resourceType->id}");
        $this->info("   • Clinic ID: {$clinic->id}");
        $this->info('   • E-mail: Bekijk in admin/mail/inbox');
    }

    /**
     * Get the inbox folder ID
     *
     * @return int|null
     */
    protected function getInboxFolderId()
    {
        $folder = Folder::where('name', 'inbox')->first();

        return $folder ? $folder->id : null;
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
        $clinic = app(ClinicRepository::class)->allActive()->first();
        if (! $clinic) {
            $clinic = Clinic::create([
                'name'        => 'Test Kliniek Amsterdam',
                'website_url' => 'https://testkliniek.nl',
                'emails'      => ['info@testkliniek.nl'],
                'phones'      => ['+31 20 123 4567'],
                'is_active'   => true,
            ]);
        }

        return $clinic;
    }

    private function getOrCreateResourceType(string $name = null): ResourceType
    {
        $resourceTypeName = $name ?: $this->option('resource-type');

        $resourceType = ResourceType::where('name', $resourceTypeName)->first();
        if (! $resourceType) {
            $resourceType = ResourceType::create([
                'name'        => $resourceTypeName,
                'description' => "Test resource type: {$resourceTypeName}",
            ]);
        }

        return $resourceType;
    }

    private function getOrCreateProductType(string $name): ProductType
    {
        $productType = ProductType::where('name', $name)->first();
        if (! $productType) {
            $productType = ProductType::create([
                'name'        => $name,
                'description' => "Product type: {$name}",
            ]);
        }

        return $productType;
    }

    private function getOrCreateProductGroup(string $name): ProductGroup
    {
        $productGroup = ProductGroup::where('name', $name)->first();
        if (! $productGroup) {
            $productGroup = ProductGroup::create([
                'name'        => $name,
                'description' => "Product group: {$name}",
            ]);
        }

        return $productGroup;
    }

    private function createProducts(): array
    {
        $products = [];
        
        // Product data from the provided table
        $productData = [
            [
                'name' => 'TB3 Royal Bodyscan + Wervelkolom',
                'description' => 'TB3 Royal Bodyscan + Wervelkolom, bestaande uit:',
                'currency' => 'EUR',
                'purchase_price' => 600.00,
                'sales_price' => 1900.00,
                'product_type' => 'Total Bodyscan',
                'resource_type' => 'MRI scanner',
                'product_group' => 'Bodyscan'
            ],
            [
                'name' => 'MRI Abdomen exclusief CM',
                'description' => 'MRI scan van de onder- en bovenbuik (abdomen), exclusief contrastmiddel',
                'currency' => 'EUR',
                'purchase_price' => 220.00,
                'sales_price' => 850.00,
                'product_type' => 'MRI scan',
                'resource_type' => 'MRI scanner',
                'product_group' => 'Buik'
            ],
            [
                'name' => 'CT Abdomen inclusief CM',
                'description' => 'CT scan van de onder- en bovenbuik (abdomen), inclusief contrastmiddel',
                'currency' => 'EUR',
                'purchase_price' => 300.00,
                'sales_price' => 650.00,
                'product_type' => 'CT scan',
                'resource_type' => 'CT scanner',
                'product_group' => 'Buik'
            ],
            [
                'name' => 'Bloed- en urineonderzoek preventief uitgebreid heren',
                'description' => 'Uitgebreid laboratoriumonderzoek (bloed en urine) met PSA bepaling',
                'currency' => 'EUR',
                'purchase_price' => 85.00,
                'sales_price' => 220.00,
                'product_type' => 'Laboratorium',
                'resource_type' => 'Cardiologie',
                'product_group' => 'Bloedonderzoeken'
            ],
            [
                'name' => 'Coördinatie- en bemiddelingskosten',
                'description' => 'Coördinatie- en bemiddelingskosten',
                'currency' => 'EUR',
                'purchase_price' => 0.00,
                'sales_price' => 59.00,
                'product_type' => 'Diensten',
                'resource_type' => 'Overig',
                'product_group' => 'Bemiddelingskosten'
            ],
            [
                'name' => 'Volledige vertaling TB3',
                'description' => 'Nederlandse vertaling van de rapportage',
                'currency' => 'EUR',
                'purchase_price' => 80.00,
                'sales_price' => 185.00,
                'product_type' => 'Vertaling',
                'resource_type' => 'Overig',
                'product_group' => 'Vertalingen'
            ],
            [
                'name' => 'Transf. Endosc. Operatie 1 n',
                'description' => 'Transforaminale endoscopische operatie 1 niveau',
                'currency' => 'EUR',
                'purchase_price' => 4950.00,
                'sales_price' => 9500.00,
                'product_type' => '',
                'resource_type' => 'Artsen',
                'product_group' => 'PTED'
            ],
            [
                'name' => 'Transf. Endosc. Operatie 2 n',
                'description' => 'Transforaminale endoscopische operatie 2 niveaus',
                'currency' => 'EUR',
                'purchase_price' => 9000.00,
                'sales_price' => 16000.00,
                'product_type' => '',
                'resource_type' => 'Artsen',
                'product_group' => 'PTED'
            ],
            [
                'name' => 'ACDF (Anterior Cervical Discectomy and Fusion) operatie',
                'description' => 'ACDF (Anterior Cervical Discectomy and Fusion) operatie',
                'currency' => 'EUR',
                'purchase_price' => 5900.00,
                'sales_price' => 13900.00,
                'product_type' => 'Overig',
                'resource_type' => 'Artsen',
                'product_group' => 'ACDF'
            ]
        ];

        foreach ($productData as $data) {
            // Get or create ProductType
            $productType = $this->getOrCreateProductType($data['product_type'] ?: 'Overig');
            
            // Get or create ResourceType
            $resourceType = $this->getOrCreateResourceType($data['resource_type']);
            
            // Get or create ProductGroup
            $productGroup = $this->getOrCreateProductGroup($data['product_group']);
            
            // Create Product
            $product = Product::create([
                'name'             => $data['name'],
                'description'      => $data['description'],
                'active'           => true,
                'currency'         => $data['currency'],
                'price'            => $data['sales_price'],
                'costs'            => $data['purchase_price'],
                'product_group_id' => $productGroup->id,
                'resource_type_id' => $resourceType->id,
                'product_type_id'  => $productType->id,
            ]);
            
            // Create PartnerProduct
            $partnerProduct = PartnerProduct::create([
                'name'                  => $data['name'] . ' - Partner Product',
                'description'           => $data['description'],
                'active'                => true,
                'currency'              => $data['currency'],
                'sales_price'           => $data['sales_price'],
                'duration'              => 60, // Default 60 minutes
                'resource_type_id'      => $resourceType->id,
                'product_id'            => $product->id,
                'purchase_price'        => $data['purchase_price'],
                'purchase_price_clinic' => $data['purchase_price'] * 0.9, // 10% discount for clinic
            ]);
            
            $products[] = [
                'product' => $product,
                'partner_product' => $partnerProduct
            ];
            
            $this->info("   • {$product->name} (ID: {$product->id})");
        }
        
        return $products;
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

    private function createRelationships(array $products, Clinic $clinic, array $resources): void
    {
        $resourceIds = collect($resources)->pluck('id')->toArray();
        
        foreach ($products as $productData) {
            $partnerProduct = $productData['partner_product'];
            
            // Koppel partner product aan kliniek
            $partnerProduct->clinics()->attach($clinic->id);

            // Koppel partner product aan resources
            $partnerProduct->resources()->attach($resourceIds);
        }

        $this->info('✅ Koppelingen gemaakt:');
        $this->info('   • '.count($products).' Partner Products gekoppeld aan Kliniek');
        $this->info('   • '.count($products).' Partner Products gekoppeld aan '.count($resources).' Resources');
    }

    private function createShiftsForResources(array $resources): void
    {
        $totalShifts = 0;

        foreach ($resources as $resource) {
            // Kies een paar verschillende komende dagen
            $dates = [
                Carbon::today()->addDays(5),
                Carbon::today()->addDays(21),
                Carbon::today()->addDays(60),
            ];

            foreach ($dates as $date) {
                // dayOfWeekIso: 1 (ma) t/m 7 (zo)
                $weekday = $date->dayOfWeekIso;

                $timeBlocks = [
                    $weekday => [
                        ['from' => '08:00', 'to' => '17:00'],
                    ],
                ];

                Shift::create([
                    'resource_id'         => $resource->id,
                    'available'           => true,
                    'notes'               => 'Auto-generated test shift',
                    'period_start'        => $date->toDateString(),
                    'period_end'          => null,
                    'weekday_time_blocks' => $timeBlocks,
                ]);

                $totalShifts++;
            }
        }

        $this->info('✅ Shifts aangemaakt: '.$totalShifts.' (08:00 - 17:00)');
    }

    private function createExampleEmail(): void
    {
        $email = Email::create([
            'name'        => 'Test Gebruiker',
            'from'        => ['test@example.com'],
            'subject'     => 'Voorbeeld e-mail voor planning test data',
            'reply'       => 'Dit is een voorbeeld e-mail die is aangemaakt door de planning:create-test-data command. Deze e-mail is ongelezen en niet gekoppeld aan een entity.',
            'folder_id'   => $this->getInboxFolderId(),
            'is_read'     => false,
            'person_id'   => null,
            'lead_id'     => null,
            'activity_id' => null,
            'reply_to'    => null,
            'cc'          => null,
            'bcc'         => null,
            'created_at'  => now()->subHours(2),
            'updated_at'  => now()->subHours(2),
        ]);

        $this->info("✅ Voorbeeld e-mail aangemaakt: '{$email->subject}' (ID: {$email->id})");
        $this->info('   • Status: Ongelezen');
        $this->info('   • Entity: Niet gekoppeld');
        $this->info('   • Folder: Inbox');
    }
}
