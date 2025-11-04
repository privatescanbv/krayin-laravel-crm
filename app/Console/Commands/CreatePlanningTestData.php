<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Models\ProductType;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Shift;
use App\Repositories\ClinicRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
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

        // 3. retrieve all existing resources of the specified type
        $resources = Resource::all();

        // 4. Shifts aanmaken voor resources (08:00 - 17:00 op verschillende dagen)
        $this->createShiftsForResources($resources);

        // 5. Voorbeeld mail aanmaken (ongelezen, niet assigned)
        $this->createExampleEmail();

        $this->info('');
        $this->info('🎉 Alle test data succesvol aangemaakt!');
        $this->info('');
        $this->info('📋 Overzicht:');
        $this->info("   • Kliniek: {$clinic->name} (ID: {$clinic->id})");
        $this->info("   • Resource Type: {$resourceType->name} (ID: {$resourceType->id})");
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

    private function getOrCreateResourceType(?string $name = null): ResourceType
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

    private function createShiftsForResources(Collection $resources): void
    {
        $totalShifts = 0;
        $resources->each(function ($resource) use (&$totalShifts) {
            logger()->info("Creating shifts for resource ID: {$resource->id}");
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
            $this->info('✅ Shifts aangemaakt: '.$totalShifts.' (08:00 - 17:00)');
        });
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
