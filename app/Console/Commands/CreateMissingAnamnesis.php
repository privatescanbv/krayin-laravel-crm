<?php

namespace App\Console\Commands;

use App\Models\Anamnesis;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Webkul\Lead\Models\Lead;

class CreateMissingAnamnesis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anamnesis:create-missing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create anamnesis records for existing leads that don\'t have one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to create missing anamnesis records...');

        // Get all leads that don't have an anamnesis record
        $leadsWithoutAnamnesis = Lead::whereDoesntHave('anamnesis')->get();

        if ($leadsWithoutAnamnesis->isEmpty()) {
            $this->info('All leads already have anamnesis records.');

            return;
        }

        $this->info("Found {$leadsWithoutAnamnesis->count()} leads without anamnesis records.");

        $created = 0;
        $errors = 0;

        foreach ($leadsWithoutAnamnesis as $lead) {
            try {
                Anamnesis::create([
                    'id'      => Str::uuid(),
                    'lead_id' => $lead->id,
                    'name'    => 'Anamnesis voor '.$lead->title,
                    'user_id' => $lead->user_id ?? 1,
                ]);

                $created++;
                $this->line("✓ Created anamnesis for lead: {$lead->title} (ID: {$lead->id})");

            } catch (\Exception $e) {
                $errors++;
                $this->error("✗ Failed to create anamnesis for lead: {$lead->title} (ID: {$lead->id})");
                $this->error("  Error: {$e->getMessage()}");
            }
        }

        $this->info("\nCompleted!");
        $this->info("Created: {$created} anamnesis records");

        if ($errors > 0) {
            $this->error("Errors: {$errors} anamnesis records failed to create");
        }
    }
}
