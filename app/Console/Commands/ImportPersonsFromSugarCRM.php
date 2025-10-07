<?php

namespace App\Console\Commands;

use App\Enums\ContactLabel;
use App\Models\Address;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Models\Person;

class ImportPersonsFromSugarCRM extends AbstractSugarCRMImport
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:persons
                            {--connection=sugarcrm : Database connection name}
                            {--table=contacts : Source table name}
                            {--limit=-1 : Number of records to import}
                            {--person-ids=* : Specific person IDs to import (ignores limit)}
                            {--dry-run : Show what would be imported without actually importing}
                            {--list-invalid-phones : List only persons failing phone validation and exit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import persons from SugarCRM database with addresses and contact information';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $table = $this->option('table');
        $limit = (int) $this->option('limit');
        $personIds = $this->option('person-ids');
        $dryRun = $this->option('dry-run');
        $listInvalidPhones = (bool) $this->option('list-invalid-phones');

        $this->info('Starting import from SugarCRM...');
        $this->infoV("Connection: {$connection}");
        $this->infoV("Table: {$table}");
        if (! empty($personIds)) {
            $this->infoV('Person IDs: '.(is_array($personIds) ? implode(', ', $personIds) : $personIds));
        } else {
            $this->infoV("Limit: {$limit}");
        }
        $this->infoV('Dry run: '.($dryRun ? 'Yes' : 'No'));

        // Start import run tracking
        if (! $dryRun) {
            $this->startImportRun('persons');
        }

        try {
            return $this->executeImport($dryRun, function () use ($connection, $table, $limit, $personIds, $dryRun, $listInvalidPhones) {
                // Test connection
                $this->testConnection($connection);

                // Get records from SugarCRM
                $sql = DB::connection($connection)
                    ->table($table.' as c')
                    ->join('contacts_cstm as cm', 'c.id', '=', 'cm.id_c')
                    ->leftJoin('email_addr_bean_rel as eabr', function ($join) {
                        $join->on('eabr.bean_id', '=', 'c.id')
                            ->where('eabr.bean_module', '=', 'Contacts')
                            ->where('eabr.deleted', '=', 0);
                    })
                    ->leftJoin('email_addresses as ea', function ($join) {
                        $join->on('ea.id', '=', 'eabr.email_address_id')
                            ->where('ea.deleted', '=', 0);
                    })
                    ->select([
                        'c.id',
                        'c.first_name',
                        'c.last_name',
                        'c.phone_work',
                        'c.phone_mobile',
                        'c.phone_home',
                        'c.phone_other',
                        'c.birthdate',
                        'c.date_entered',
                        'c.date_modified',
                        // Primary address fields from contacts
                        'c.primary_address_street',
                        'c.primary_address_city',
                        'c.primary_address_state',
                        'c.primary_address_postalcode',
                        'c.primary_address_country',
                        'cm.gender_c',
                        'cm.meisjesnaam_c',
                        'cm.roepnaam_c',
                        'cm.voorletters_c',
                        'cm.tussenvoegsel_c',
                        'cm.aang_tussenv_c',
                        'cm.primary_huisnr_c',
                        'cm.primary_huisnr_toevoeging_c',
                        DB::raw('MAX(CASE WHEN eabr.primary_address = 1 THEN ea.email_address END) as email_primary'),
                        DB::raw('MIN(CASE WHEN eabr.primary_address = 0 THEN ea.email_address END) as email_any'),
                    ])
                    ->where('c.deleted', 0)
                    ->whereNotNull('c.id')
                    ->where('c.id', '!=', '');

                // If specific person IDs are provided, filter by them and ignore limit
                if (! empty($personIds)) {
                    // Normalize IDs: support repeated --person-ids options and a single
                    // quoted value with space/comma separated IDs
                    if (is_array($personIds)) {
                        $personIds = implode(' ', $personIds);
                    }
                    $normalizedIds = preg_split('/[\s,]+/', (string) $personIds, -1, PREG_SPLIT_NO_EMPTY);

                    $sql = $sql->whereIn('c.id', $normalizedIds)
                        ->groupBy('c.id');
                } else {
                    $sql = $sql->groupBy('c.id')
                        ->orderBy('c.date_entered', 'desc'); // Nieuwste eerst
                    if ($limit > 0) {
                        $sql = $sql->limit($limit);
                    }
                }
                $this->infoVV($sql->toRawSql());
                // Fail fast if the query execution errors
                try {
                    $records = $sql->get();
                } catch (Exception $e) {
                    $this->error('Query failed: '.$e->getMessage());
                    throw $e; // crash the script as requested
                }

                $this->info('Found '.$records->count().' records to import');

                if ($listInvalidPhones) {
                    $this->showInvalidPhoneRecords($records);

                    return;
                }

                if ($dryRun) {
                    $this->showDryRunResults($records);

                    return;
                }

                $this->importRecords($records);
            });
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

        $headers = ['External ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Gender', 'Meisjesnaam', 'Roepnaam', 'Voorletters', 'Valid'];
        $rows = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($records as $record) {
            $isValid = ! empty($record->id) && ! empty($record->last_name);
            if ($isValid) {
                $validCount++;
            } else {
                $invalidCount++;
            }

            $rows[] = [
                $record->id ?? 'N/A',
                $record->first_name ?? 'N/A',
                $record->last_name ?? 'N/A',
                $record->email_primary ?? $record->email_any ?? 'N/A',
                $record->phone_work ?? 'N/A',
                $record->gender_c ?? 'N/A',
                $record->meisjesnaam_c ?? 'N/A',
                $record->roepnaam_c ?? 'N/A',
                $record->voorletters_c ?? 'N/A',
                $isValid ? '✓' : '✗',
            ];
        }

        $this->table($headers, $rows);
        $this->info("Would import {$validCount} valid persons");
        if ($invalidCount > 0) {
            $this->warn("Would skip {$invalidCount} invalid persons (missing required fields)");
        }
    }

    /**
     * Show only persons that fail phone validation
     */
    private function showInvalidPhoneRecords($records)
    {
        $this->info("\n=== INVALID PHONE RECORDS ===");

        $headers = ['External ID', 'First Name', 'Last Name', 'Field', 'Raw', 'Error'];
        $rows = [];
        $count = 0;

        foreach ($records as $record) {
            $fields = ['phone_work', 'phone_mobile', 'phone_home', 'phone_other'];
            foreach ($fields as $field) {
                $raw = $record->{$field} ?? null;
                if (empty($raw)) {
                    continue;
                }
                try {
                    // Reuse the same sanitization/validation logic used during import
                    $this->sanitizePhoneAndInferLabel($raw, ContactLabel::Eigen->value);
                } catch (Exception $e) {
                    $rows[] = [
                        $record->id ?? 'N/A',
                        $record->first_name ?? 'N/A',
                        $record->last_name ?? 'N/A',
                        $field,
                        $raw,
                        $e->getMessage(),
                    ];
                    $count++;
                    // List each person once (first failing field is enough)
                    break;
                }
            }
        }

        if (empty($rows)) {
            $this->info('No invalid phone records found.');

            return;
        }

        $this->table($headers, $rows);
        $this->info("Total persons with invalid phones: {$count}");
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
        $skippedMissingRequired = 0;
        $skippedAlreadyExisting = 0;
        $firstErrors = [];

        $attributeValueRepo = app(AttributeValueRepository::class);
        foreach ($records as $record) {
            try {
                // Validate required fields (lot first names are empty in SugarCRM)
                if (empty($record->id) || empty($record->last_name)) {
                    $this->warn("Skipping record with missing required fields: ID={$record->id}, First Name={$record->first_name}, Last Name={$record->last_name}");
                    $skipped++;
                    $skippedMissingRequired++;
                    $bar->advance();

                    continue;
                }

                // Check if person already exists by external_id
                $existingPerson = Person::where('external_id', $record->id)->first();
                if ($existingPerson) {
                    $skipped++;
                    $skippedAlreadyExisting++;
                    $this->infoV("Skipping existing person with external_id={$record->id} (already imported as #{$existingPerson->id})");
                    $bar->advance();

                    continue;
                }
                // Build phones array from available SugarCRM fields with sanitization
                $phones = [];
                if (! empty($record->phone_work)) {
                    [$label, $value] = $this->sanitizePhoneAndInferLabel($record->phone_work, ContactLabel::Eigen->value);
                    if ($value !== '') {
                        $phones[] = ['label' => ContactLabel::fromOld($label)->value, 'value' => $value, 'is_default' => true];
                    }
                }
                if (! empty($record->phone_mobile)) {
                    [$label, $value] = $this->sanitizePhoneAndInferLabel($record->phone_mobile, ContactLabel::Eigen->value);
                    if ($value !== '') {
                        $phones[] = ['label' => ContactLabel::fromOld($label)->value, 'value' => $value, 'is_default' => empty($phones)];
                    }
                }
                if (! empty($record->phone_home)) {
                    [$label, $value] = $this->sanitizePhoneAndInferLabel($record->phone_home, ContactLabel::Eigen->value);
                    if ($value !== '') {
                        $phones[] = ['label' => ContactLabel::fromOld($label)->value, 'value' => $value, 'is_default' => empty($phones)];
                    }
                }
                if (! empty($record->phone_other)) {
                    [$label, $value] = $this->sanitizePhoneAndInferLabel($record->phone_other, ContactLabel::Eigen->value);
                    if ($value !== '') {
                        $phones[] = ['label' => ContactLabel::fromOld($label)->value, 'value' => $value, 'is_default' => empty($phones)];
                    }
                }

                $emails = [];
                if (! empty($record->email_primary)) {
                    $this->validateEmailOrFail($record->email_primary, 'primary');
                    $emails[] = ['label' => ContactLabel::Eigen->value, 'value' => $record->email_primary, 'is_default' => true];
                } elseif (! empty($record->email_any)) {
                    $this->validateEmailOrFail($record->email_any, 'secundair');
                    $emails[] = ['label' => ContactLabel::Eigen->value, 'value' => $record->email_any, 'is_default' => true];
                }

                $gender = $this->mapGenderFromSugar($record->gender_c ?? null);

                $person = $this->createEntityWithTimestamps(Person::class, [
                    'external_id'         => $record->id,
                    'emails'              => $emails,
                    'phones'              => $phones,
                    'initials'            => $record->voorletters_c ?? '',
                    'salutation'          => $this->mapSalutationFromGender($gender),
                    'first_name'          => $record->first_name ?? '',
                    'last_name'           => $record->last_name ?? '',
                    'lastname_prefix'     => $record->tussenvoegsel_c ?? '',
                    'married_name'        => $record->meisjesnaam_c ?? '',
                    'married_name_prefix' => $record->aang_tussenv_c ?? null,
                    'gender'              => $gender,
                    'date_of_birth'       => $record->birthdate ?? null,
                ], [
                    'created_at' => $this->parseSugarDate($record->date_entered),
                    'updated_at' => $this->parseSugarDate($record->date_modified),
                ]);

                // Create/update primary address for person if present
                if ($record->primary_huisnr_c && $record->primary_address_postalcode) {
                    Address::create([
                        'person_id'           => $person->id,
                        'street'              => $record->primary_address_street ?? null,
                        'house_number'        => $record->primary_huisnr_c,
                        'house_number_suffix' => $record->primary_huisnr_toevoeging_c ?? null,
                        'postal_code'         => $record->primary_address_postalcode,
                        'state'               => $record->primary_address_state ?? null,
                        'city'                => $record->primary_address_city ?? null,
                        'country'             => $record->primary_address_country ?? null,
                    ]);
                }
                // Note: PersonAttributeKeys enum values can be used here for additional attributes
                // $attributeValueRepo->save([
                //     'entity_type' => 'persons',
                //     'entity_id'   => $person->id,
                //     PersonAttributeKeys::NICKNAME->value => $record->roepnaam_c ?? '',
                //     PersonAttributeKeys::GENDER->value   => $record->gender_c ?? '',
                // ]);
                $imported++;
                $bar->advance();
            } catch (Exception $e) {
                $errors++;
                $this->logImportError('Failed to import person', [
                    'record_id' => $record->id ?? 'unknown',
                    'error'     => $e->getMessage(),
                ]);
                if (count($firstErrors) < 5) {
                    $firstErrors[] = [
                        'id'      => $record->id ?? 'unknown',
                        'message' => $e->getMessage(),
                    ];
                }
                $bar->advance();
            }
        }
        $bar->finish();
        $this->newLine(2);
        $this->info('Import completed!');
        $this->info("✓ Imported: {$imported}");
        $this->info("⚠ Skipped: {$skipped}");
        $this->info("✗ Errors: {$errors}");

        // Complete import run tracking
        $this->completeImportRun([
            'processed' => $imported + $skipped + $errors,
            'imported'  => $imported,
            'skipped'   => $skipped,
            'errored'   => $errors,
        ]);

        $this->line('');
        $this->info('Skip breakdown:');
        $this->info("- Missing required fields: {$skippedMissingRequired}");
        $this->info("- Already existing (external_id present): {$skippedAlreadyExisting}");
        if (! empty($firstErrors)) {
            $this->line('');
            $this->warn('First errors:');
            foreach ($firstErrors as $err) {
                $this->warn("  - ID={$err['id']}: {$err['message']}");
            }
        }
    }

    // mapping now provided by AbstractSugarCRMImport: mapGenderFromSugar, mapSalutationFromGender
}
