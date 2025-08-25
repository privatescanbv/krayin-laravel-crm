<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Models\Person;

class ImportPersonsFromSugarCRM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:persons
                            {--connection=sugarcrm : Database connection name}
                            {--table=contacts : Source table name}
                            {--limit=100 : Number of records to import}
                            {--dry-run : Show what would be imported without actually importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import persons from SugarCRM database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $table = $this->option('table');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info('Starting import from SugarCRM...');
        $this->info("Connection: {$connection}");
        $this->info("Table: {$table}");
        $this->info("Limit: {$limit}");
        $this->info('Dry run: '.($dryRun ? 'Yes' : 'No'));

        try {
            // Test connection
            $this->info('Testing database connection...');
            DB::connection($connection)->getPdo();
            $this->info('✓ Database connection successful');

            // Get records from SugarCRM
            $records = DB::connection($connection)
                ->table($table.' as c')
                ->join('contacts_cstm as cm', 'c.id', '=', 'cm.id_c')
                ->leftJoin('email_addr_bean_rel as eabr', function ($join) {
                    $join->on('eabr.bean_id', '=', 'c.id')
                        ->where('eabr.bean_module', '=', 'Contacts')
                        ->where('eabr.deleted', '=', 0)
                        ->where('eabr.primary_address', '=', 1);
                })
                ->leftJoin('email_addresses as ea', function ($join) {
                    $join->on('ea.id', '=', 'eabr.email_address_id')
                        ->where('ea.deleted', '=', 0);
                })
                ->select([
                    'c.*',
                    'cm.gender_c',
                    'cm.meisjesnaam_c',
                    'cm.roepnaam_c',
                    'cm.voorletters_c',
                    'ea.email_address as email',
                ])
                ->where('c.deleted', 0)
                ->orderBy('c.date_entered', 'desc') // Nieuwste eerst
                ->limit($limit)
                ->get();

            $this->info('Found '.$records->count().' records to import');

            if ($dryRun) {
                $this->showDryRunResults($records);

                return;
            }

            $this->importRecords($records);

        } catch (Exception $e) {
            $this->error('Error: '.$e->getMessage());
            Log::error('SugarCRM import failed', [
                'error'      => $e->getMessage(),
                'connection' => $connection,
                'table'      => $table,
            ]);

            return 1;
        }
    }

    /**
     * Show dry run results
     */
    private function showDryRunResults($records)
    {
        $this->info("\n=== DRY RUN RESULTS ===");

        $headers = ['External ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Gender', 'Meisjesnaam', 'Roepnaam', 'Voorletters'];
        $rows = [];

        foreach ($records as $record) {
            $rows[] = [
                $record->id ?? 'N/A',
                $record->first_name ?? 'N/A',
                $record->last_name ?? 'N/A',
                $record->email ?? 'N/A',
                $record->phone_work ?? 'N/A',
                $record->gender_c ?? 'N/A',
                $record->meisjesnaam_c ?? 'N/A',
                $record->roepnaam_c ?? 'N/A',
                $record->voorletters_c ?? 'N/A',
            ];
        }

        $this->table($headers, $rows);
        $this->info('Would import '.count($rows).' persons');
    }

    /**
     * Import records
     */
    private function importRecords($records)
    {
        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        $attributeValueRepo = app(AttributeValueRepository::class);
        foreach ($records as $record) {
            try {
                // Check if person already exists by external_id
                $existingPerson = Person::where('external_id', $record->id)->first();
                if ($existingPerson) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }
                $person = Person::create([
                    'external_id'     => $record->id,
                    'emails'          => [['label' => 'work', 'value' => $record->email ?? '']],
                    'phones'          => [['label' => 'work', 'value' => $record->phone_work ?? '']],
                    'initials'        => $record->voorletters_c ?? '',
                    'first_name'      => $record->first_name ?? '',
                    'last_name'       => $record->last_name ?? '',
                    'lastname_prefix' => $record->tussenvoegsel_c ?? '',
                    'maiden_name'     => $record->meisjesnaam_c ?? '',
                    'created_at'      => $record->date_entered ?? now(),
                    'updated_at'      => $record->date_modified ?? now(),
                ]);
                $attributeValueRepo->save([
                    'entity_type' => 'persons',
                    'entity_id'   => $person->id,
                    // Use PersonAttributeKeys enum values for all person attributes:
                    //                    PersonAttributeKeys::NICKNAME->value         => $record->roepnaam_c ?? '',
                    //                    PersonAttributeKeys::GENDER->value           => $record->gender_c ?? '',
                ]);
                $imported++;
                $bar->advance();
            } catch (Exception $e) {
                $errors++;
                Log::error('Failed to import person', [
                    'record_id' => $record->id ?? 'unknown',
                    'error'     => $e->getMessage(),
                ]);
                $bar->advance();
            }
        }
        $bar->finish();
        $this->newLine(2);
        $this->info('Import completed!');
        $this->info("✓ Imported: {$imported}");
        $this->info("⚠ Skipped: {$skipped}");
        $this->info("✗ Errors: {$errors}");
    }
}
