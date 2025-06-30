<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CreatePerformanceLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:create
                            {count=10 : Number of leads to create}
                            {--department= : Department (Hernia or Privatescan)}
                            {--source= : Lead source ID}
                            {--type= : Lead type ID}
                            {--user= : User ID}
                            {--delay=100 : Delay between requests in milliseconds}
                            {--dry-run : Show what would be created without actually creating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create leads via API for data population';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->argument('count');
        $department = $this->option('department');
        $sourceId = $this->option('source');
        $typeId = $this->option('type');
        $userId = $this->option('user');
        $delay = (int) $this->option('delay');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No leads will be created');
        }

        $this->info("Creating {$count} leads...");
        if (! $dryRun && $delay > 0) {
            $this->info("Delay between requests: {$delay}ms");
        }

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        for ($i = 0; $i < $count; $i++) {
            $leadData = $this->generateLeadData($department, $sourceId, $typeId, $userId);
            logger()->info('Generated lead data', [
                'leadData' => $leadData,
            ]);
            if ($dryRun) {
                $this->line('Would create lead: '.json_encode($leadData, JSON_UNESCAPED_UNICODE));
                $successCount++;
            } else {
                try {
                    $response = Http::timeout(30)->post('http://crm/api/leads/', $leadData);

                    if ($response->successful()) {
                        $successCount++;
                    } else {
                        $errorCount++;
                        $errors[] = "HTTP {$response->status()}: ".$response->body();
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = 'Exception: '.$e->getMessage();
                }
            }

            $progressBar->advance();

            // Add delay between requests (except for the last one)
            if (! $dryRun && $delay > 0 && $i < $count - 1) {
                usleep($delay * 1000); // Convert milliseconds to microseconds
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info('Completed!');
        $this->info("Successfully created: {$successCount} leads");

        if ($errorCount > 0) {
            $this->error("Errors: {$errorCount} leads");
            $this->error('Errors encountered:');
            foreach (array_slice($errors, 0, 5) as $error) {
                $this->error("- {$error}");
            }
            if (count($errors) > 5) {
                $this->error('... and '.(count($errors) - 5).' more errors');
            }
        }

        return $errorCount === 0 ? 0 : 1;
    }

    private function generateLeadData(?string $department, ?string $sourceId, ?string $typeId, ?string $userId): array
    {
        $firstName = $this->generateRandomName();
        $lastName = $this->generateRandomName();
        $email = strtolower($firstName.'.'.$lastName.'@example.com');

        // Generate unique email to avoid conflicts
        $email = str_replace('@example.com', '.'.time().rand(1000, 9999).'@example.com', $email);

        $data = [
            'title'          => "Test Lead - {$firstName} {$lastName}",
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'email'          => $email,
            'lead_source_id' => $sourceId ?? 1, // Default source
            //            'lead_type_id' => $typeId ?? 1, // Default type
            'user_id' => $userId ?? 1, // Default user
        ];

        // Add department if specified
        if ($department) {
            $departmentId = $this->getDepartmentId($department);
            if ($departmentId) {
                $data['department'] = $departmentId;
            }
        }

        return $data;
    }

    private function generateRandomName(): string
    {
        $names = [
            'Jan', 'Piet', 'Klaas', 'Henk', 'Gerard', 'Willem', 'Johan', 'Peter', 'Hans', 'Frank',
            'Anna', 'Maria', 'Petra', 'Sandra', 'Linda', 'Monique', 'Ingrid', 'Ellen', 'Marieke', 'Judith',
            'Test', 'Demo', 'Sample', 'Data', 'Lead', 'Customer', 'Client', 'User', 'Person', 'Contact',
        ];

        return $names[array_rand($names)].'_'.Str::random(4);
    }

    private function getDepartmentId(string $department): ?int
    {
        $departments = [
            'hernia'      => 29, // Hernia department ID
            'privatescan' => 30, // Privatescan department ID
        ];

        return $departments[strtolower($department)] ?? null;
    }
}
