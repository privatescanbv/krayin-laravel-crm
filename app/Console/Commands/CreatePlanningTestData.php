<?php

namespace App\Console\Commands;

use App\Models\ResourceType;
use App\Models\Shift;
use App\Repositories\ResourceRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;

class CreatePlanningTestData extends Command
{
    private const AUTO_GENERATED_TEST_SHIFT_NOTES = 'Auto-generated test shift';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'planning:create-test-data
                            {--resource-type=Artsen : Type resource om aan te maken}';

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

        $resourceType = $this->getOrCreateResourceType();
        $this->info("✅ Resource Type: {$resourceType->name} (ID: {$resourceType->id})");

        $resources = app(ResourceRepository::class)->queryWithActiveClinics()->get();
        $this->info('✅ Resources (actieve klinieken): '.count($resources).' stuks');

        $this->createShiftsForResources($resources);

        $this->createExampleEmail();

        $this->info('');
        $this->info('🎉 Alle test data succesvol aangemaakt!');
        $this->info('');
        $this->info('📋 Overzicht:');
        $this->info("   • Resource Type: {$resourceType->name} (ID: {$resourceType->id})");
        $this->info('   • Resources: '.count($resources).' stuks (alleen actieve klinieken)');
        $this->info('   • E-mail: Voorbeeld ongelezen e-mail aangemaakt');
        $this->info('');
        $this->info('🔗 Je kunt nu plannen met:');
        $this->info("   • Resource Type ID: {$resourceType->id}");
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

    private function createShiftsForResources(Collection $resources): void
    {
        $weekdayTimeBlocks = [];
        for ($d = 1; $d <= 5; $d++) {
            $weekdayTimeBlocks[$d] = [['from' => '08:00', 'to' => '17:00']];
        }

        $periodStart = Carbon::today()->toDateString();
        $totalShifts = 0;

        foreach ($resources as $resource) {
            logger()->info("Creating shift for resource ID: {$resource->id}");

            Shift::query()
                ->where('resource_id', $resource->id)
                ->where('notes', self::AUTO_GENERATED_TEST_SHIFT_NOTES)
                ->delete();

            Shift::create([
                'resource_id'         => $resource->id,
                'available'           => true,
                'notes'               => self::AUTO_GENERATED_TEST_SHIFT_NOTES,
                'period_start'        => $periodStart,
                'period_end'          => null,
                'weekday_time_blocks' => $weekdayTimeBlocks,
            ]);

            $totalShifts++;
        }

        $this->info("✅ Shifts aangemaakt: {$totalShifts} (ma–vr 08:00–17:00, per resource)");
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
