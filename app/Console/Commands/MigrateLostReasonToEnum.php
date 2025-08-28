<?php

namespace App\Console\Commands;

use App\Enums\LostReason;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateLostReasonToEnum extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:lost-reason-enum {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing lost_reason values to match the new LostReason enum';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in dry-run mode. No changes will be made.');
        }

        // Define mapping from legacy values to enum values
        $mapping = [
            'concurrent'      => 'competitor',
            'Competition'     => 'competitor',
            'Naar Concurrent' => 'competitor',
            'concurrent'      => 'competitor',
            // Add more mappings as discovered
        ];

        // Get all unique lost_reason values from database
        $existingValues = DB::table('leads')
            ->whereNotNull('lost_reason')
            ->where('lost_reason', '!=', '')
            ->distinct()
            ->pluck('lost_reason')
            ->toArray();

        $this->info('Found existing lost_reason values:');
        foreach ($existingValues as $value) {
            $this->line("- {$value}");
        }

        // Check which values need mapping
        $validEnumValues = array_map(fn ($case) => $case->value, LostReason::cases());
        $invalidValues = array_filter($existingValues, fn ($value) => ! in_array($value, $validEnumValues));

        if (empty($invalidValues)) {
            $this->info('All existing values are already valid enum values. No migration needed.');

            return 0;
        }

        $this->warn('Invalid values that need migration:');
        foreach ($invalidValues as $value) {
            $mappedValue = $mapping[$value] ?? null;
            if ($mappedValue) {
                $this->line("- '{$value}' -> '{$mappedValue}'");
            } else {
                $this->error("- '{$value}' -> NO MAPPING DEFINED");
            }
        }

        // Perform the migration
        $totalUpdated = 0;
        foreach ($mapping as $oldValue => $newValue) {
            if (! in_array($newValue, $validEnumValues)) {
                $this->error("Target value '{$newValue}' is not a valid enum value. Skipping '{$oldValue}'.");

                continue;
            }

            $count = DB::table('leads')->where('lost_reason', $oldValue)->count();

            if ($count > 0) {
                $this->info("Migrating {$count} records from '{$oldValue}' to '{$newValue}'");

                if (! $isDryRun) {
                    $updated = DB::table('leads')
                        ->where('lost_reason', $oldValue)
                        ->update(['lost_reason' => $newValue]);

                    $totalUpdated += $updated;
                    Log::info('Migrated lost_reason values', [
                        'from'  => $oldValue,
                        'to'    => $newValue,
                        'count' => $updated,
                    ]);
                }
            }
        }

        // Report unmapped values
        $unmappedValues = array_filter($invalidValues, fn ($value) => ! isset($mapping[$value]));
        if (! empty($unmappedValues)) {
            $this->error('The following values have no mapping and will remain as-is:');
            foreach ($unmappedValues as $value) {
                $this->line("- {$value}");
            }
            $this->warn('These records may cause errors when loading. Consider adding mappings or manually updating them.');
        }

        if ($isDryRun) {
            $this->info('Dry run completed. Use without --dry-run to perform actual migration.');
        } else {
            $this->info("Migration completed. Updated {$totalUpdated} records.");
        }

        return 0;
    }
}
