<?php

namespace App\Console\Commands;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Models\Address;
use App\Models\Anamnesis;
use App\Models\Department;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

/**
 * Import leads from SugarCRM database with anamnesis data and call activities
 *
 * This command imports leads and their associated anamnesis data from SugarCRM,
 * as well as call activities related to those leads.
 * It uses the following relationships:
 * - leads_contacts_c: Links leads to persons
 * - leads_pcrm_anamnesepreventie_1_c: Links leads to anamnesis
 * - pcrm_anamnetie_contacts_c: Links anamnesis to persons
 * - calls table: Contains call activities where parent_type = 'Leads'
 */
class ImportLeadsFromSugarCRM extends AbstractSugarCRMImport
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:leads
                            {--connection=sugarcrm : Database connection name}
                            {--limit=100 : Number of records to import}
                            {--lead-ids=* : Specific lead IDs to import (ignores limit)}
                            {--dry-run : Show what would be imported without actually importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import leads from SugarCRM database with anamnesis data and call activities';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $limit = (int) $this->option('limit');
        $leadIds = $this->option('lead-ids');
        $dryRun = $this->option('dry-run');

        $this->info('Starting lead import from SugarCRM...');
        $this->info("Connection: {$connection}");
        if (! empty($leadIds)) {
            $this->info('Lead IDs: '.implode(', ', $leadIds));
        } else {
            $this->info("Limit: {$limit}");
        }
        $this->info('Dry run: '.($dryRun ? 'Yes' : 'No'));

        // user import needs to be run first
        $this->ensureUserImportRan();

        return $this->executeImport($dryRun, function () use ($connection, $limit, $leadIds, $dryRun) {
            // Test connection
            $this->testConnection($connection);

            // Get records from SugarCRM
            $sql = DB::connection($connection)
                ->table('leads as l')
                ->join('leads_cstm as lc', 'l.id', '=', 'lc.id_c')
                ->leftJoin('email_addr_bean_rel as eabr', function ($join) {
                    $join->on('eabr.bean_id', '=', 'l.id')
                        ->where('eabr.bean_module', '=', 'Leads')
                        ->where('eabr.deleted', '=', 0);
                })
                ->leftJoin('email_addresses as ea', function ($join) {
                    $join->on('ea.id', '=', 'eabr.email_address_id')
                        ->where('ea.deleted', '=', 0);
                })
                ->select([
                    'l.*',
                    DB::raw('MAX(l.date_entered) as lead_date_entered'),
                    DB::raw('MAX(l.date_modified) as lead_date_modified'),
                    'lc.gender_c',
                    'lc.workflow_status_c',
                    'lc.kanaal_c',
                    'lc.soort_aanvraag_c',
                    'lc.meisjesnaam_c',
                    'lc.aang_tussenv_c',
                    'lc.partner_birthdate_c',
                    'lc.partner_gender_c',
                    'lc.lengte_c',
                    'lc.gewicht_c',
                    'lc.op_een_factuur_c',
                    'lc.anamnese_c',
                    'lc.partner_anamnese_c',
                    'lc.metalen_c',
                    'lc.medicijnen_c',
                    'lc.glaucoom_c',
                    'lc.claustrofobie_c',
                    'lc.opmerking_c',
                    'lc.partner_medicijnen_c',
                    'lc.partner_smoking_c',
                    'lc.partner_diabetes_c',
                    'lc.partner_vaat_erfelijk_c',
                    'lc.partner_gewicht_c',
                    'lc.partner_metalen_c',
                    'lc.partner_tumoren_erfelijk_c',
                    'lc.partner_glaucoom_c',
                    'lc.partner_meisjesnaam_c',
                    'lc.partner_lengte_c',
                    'lc.partner_first_name_c',
                    'lc.partner_heart_problems_c',
                    'lc.partner_rugklachten_c',
                    'lc.partner_last_name_c',
                    'lc.partner_dormicum_c',
                    'lc.partner_claustrofobie_c',
                    'lc.partner_spijsverteringsklach_c',
                    'lc.partner_hart_erfelijk_c',
                    'lc.partner_salutation_c',
                    'lc.partner_opmerking_c',
                    'lc.partner_risico_hartinfarct_c',
                    'lc.ms_sinds_c',
                    'lc.ms_type_c',
                    'lc.spreektalen_c',
                    'lc.straat_c',
                    'lc.primary_huisnr_c',
                    'lc.primary_huisnr_toevoeging_c',
                    'lc.reden_afvoeren_c',
                    'lc.nieuwsbrief_vraag_c',
                    'lc.reset_wfl_status_c',
                    'lc.particulier_c',
                    'lc.roepnaam_c',
                    'lc.voorletters_c',
                    'lc.leeftijd_c',
                    'lc.allergie_c',
                    'lc.opm_allergie_c',
                    'lc.hart_operatie_c',
                    'lc.opm_hart_operatie_c',
                    'lc.implantaat_c',
                    'lc.opm_implantaat_c',
                    'lc.tussenvoegsel_c',
                    'lc.interes_info_c',
                    'lc.operaties_c',
                    'lc.reden_afvoeren_c',
                    'lc.opm_erf_tumoren_c',
                    DB::raw('MAX(CASE WHEN eabr.primary_address = 1 THEN ea.email_address END) as email_primary'),
                    DB::raw('MIN(CASE WHEN eabr.primary_address = 0 THEN ea.email_address END) as email_any'),
                ])
                ->where('l.deleted', 0)
                ->where('soort_aanvraag_c', '!=', 'ccsvi'); // Exclude 'ccsvi'

            // If specific lead IDs are provided, filter by them and ignore limit
            if (! empty($leadIds)) {
                $sql->whereIn('l.id', $leadIds);
            } else {
                $sql->groupBy('l.id')
                    ->orderBy('l.date_entered', 'desc') // Nieuwste eerst
                    ->limit($limit);
            }

            $this->info($sql->toRawSql());
            $records = $sql->get();

            $this->info('Found '.$records->count().' records to import');

            $leadByPersons = $this->extractPerson($records);
            $leadByPersonsByAnamnesis = $this->extractAnamenesis($leadByPersons);

            // Extract call activities for the leads
            $callActivities = $this->extractCallActivities($records);

            if ($dryRun) {
                $this->showDryRunResults($records, $leadByPersonsByAnamnesis, $callActivities);

                return;
            }

            $this->importRecords($records, $leadByPersonsByAnamnesis, $callActivities);
        });
    }

    /**
     * Show dry run results
     */
    private function showDryRunResults($records, $leadByPersonsByAnamnesis, $callActivities = []): void
    {
        $this->info("\n=== DRY RUN RESULTS ===");

        $headers = [
            'External ID',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Status',
            'Workflow Status',
            'Stage ID',
            'Department',
            'Channel',
            'Type',
            'Source',
            'Date Entered',
            'Rel Person IDs',
            'Person Match',
            'Rel Anamnesis IDs',
            'Rel Anamnesis Count',
            'Call Activities',
        ];
        $rows = [];

        foreach ($records as $record) {
            // Extract related persons and anamnesis from mapping built earlier
            $related = $leadByPersonsByAnamnesis[$record->id] ?? [];
            $relatedPersonIds = array_keys($related);

            // Try to find matching person using first related person ID
            $person = null;
            $personMatch = '✗ Not found';
            if (! empty($relatedPersonIds)) {
                $firstPersonId = $relatedPersonIds[0];
                $person = Person::where('external_id', '=', $firstPersonId)->first();
                $personMatch = $person ? "✓ {$person->name}" : "✗ Person {$firstPersonId} not found";
            }

            // Flatten anamnesis ids per related person
            $relatedAnamnesisIds = [];
            $relatedAnamnesisCount = 0;
            foreach ($related as $perPersonAnamneses) {
                $relatedAnamnesisIds = array_merge($relatedAnamnesisIds, array_keys($perPersonAnamneses));
                $relatedAnamnesisCount += count($perPersonAnamneses);
            }

            // Get call activities for this lead
            $leadCallActivities = $callActivities[$record->id] ?? [];
            $callActivitiesInfo = empty($leadCallActivities) ? '—' : count($leadCallActivities).' calls';

            $rows[] = [
                $record->id ?? 'N/A',
                $record->first_name ?? 'N/A',
                $record->last_name ?? 'N/A',
                $this->extractEmail($record) ?? 'N/A',
                $record->phone_work ?? 'N/A',
                $record->status ?? 'N/A',
                $record->workflow_status_c ?? 'N/A',
                $this->mapStage($record),
                $this->mapDepartment($record),
                $this->mapChannel($record),
                $this->mapType($record),
                $this->mapSource($record),
                $record->date_entered ?? 'N/A',
                empty($relatedPersonIds) ? '—' : implode(',', $relatedPersonIds),
                $personMatch,
                empty($relatedAnamnesisIds) ? '—' : implode(',', $relatedAnamnesisIds),
                $relatedAnamnesisCount,
                $callActivitiesInfo,
            ];
        }

        $this->table($headers, $rows);
        $this->info('Would import '.count($rows).' leads');

        // Show call activities summary
        if (! empty($callActivities)) {
            $totalCallActivities = array_sum(array_map('count', $callActivities));
            $this->line('');
            $this->info('Call Activities Summary:');
            $this->info("Total call activities found: {$totalCallActivities}");

            if ($totalCallActivities > 0) {
                $this->info('Call activities per lead:');
                foreach ($callActivities as $leadId => $calls) {
                    $leadRecord = collect($records)->firstWhere('id', $leadId);
                    $leadName = $leadRecord ? "{$leadRecord->first_name} {$leadRecord->last_name}" : "Lead {$leadId}";
                    $this->info("  - {$leadName}: ".count($calls).' calls');
                }
            }
        } else {
            $this->line('');
            $this->info('Call Activities Summary: No call activities found or calls table not available');
        }
    }

    /**
     * Import records
     */
    private function importRecords($records, $leadByPersonsByAnamnesis, $callActivities = []): void
    {
        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $personNotFound = 0;
        $skippedAlreadyExisting = 0;
        $skippedNoRelatedPersons = 0;
        $skippedNotAllPersonsFound = 0;
        $callActivitiesImported = 0;
        $callActivitiesSkipped = 0;

        foreach ($records as $record) {
            try {
                // Check if lead already exists by external_id
                $existingLead = Lead::where('external_id', $record->id)->first();
                if ($existingLead) {
                    $skipped++;
                    $skippedAlreadyExisting++;
                    $this->info("Skipping existing lead with external_id={$record->id} (already imported as #{$existingLead->id})");
                    $bar->advance();

                    continue;
                }

                // Find matching persons
                $persons = $this->findMatchingPerson($record, $leadByPersonsByAnamnesis);
                $related = $leadByPersonsByAnamnesis[$record->id] ?? [];
                $expectedPersonIds = array_keys($related);

                if (empty($persons)) {
                    $personNotFound++;
                    $skipped++;
                    $skippedNoRelatedPersons++;
                    $this->error("\nPerson not found for lead: {$record->first_name} {$record->last_name} (LEAD ID: {$record->id}) (person ids: ".(empty($expectedPersonIds) ? 'none' : implode(',', $expectedPersonIds)).')');
                    $bar->advance();

                    continue;
                }

                // Check if all expected persons were found
                $foundPersonIds = array_map(fn ($p) => $p->external_id, $persons);
                $missingPersonIds = array_diff($expectedPersonIds, $foundPersonIds);

                if (! empty($missingPersonIds)) {
                    $personNotFound++;
                    $skipped++;
                    $skippedNotAllPersonsFound++;
                    $this->error("\nNot all persons found for lead: {$record->first_name} {$record->last_name} (LEAD ID: {$record->id})");
                    $this->error('Expected persons: '.implode(', ', $expectedPersonIds));
                    $this->error('Found persons: '.implode(', ', $foundPersonIds));
                    $this->error('Missing persons: '.implode(', ', $missingPersonIds));
                    $this->error('Skipping lead import - all persons must be found first.');
                    $bar->advance();

                    continue;
                }

                // Debug: Check person data
                $this->info('Creating lead for persons: '.implode(', ', array_map(fn ($p) => $p->name.' (#'.$p->id.')', $persons)));

                // Use database transaction to ensure all-or-nothing import
                DB::transaction(function () use ($record, $persons, $leadByPersonsByAnamnesis, &$lead) {
                    // Create lead with proper timestamps
                    $lead = $this->createEntityWithTimestamps(Lead::class, [
                        'external_id'            => $record->id,
                        'description'            => $record->description ?? '',
                        'emails'                 => $this->formatEmails($record),
                        'phones'                 => $this->formatPhones($record),
                        'status'                 => $this->mapStatus($record->status),
                        'lead_pipeline_id'       => $this->mapPipeline($record, $this->mapDepartment($record)),
                        'lead_pipeline_stage_id' => $this->mapStage($record),
                        'salutation'             => $this->mapSalutationFromGender($this->mapGenderFromSugar($record->gender_c ?? null)),
                        'first_name'             => $record->first_name,
                        'last_name'              => $record->last_name,
                        'lastname_prefix'        => $record->tussenvoegsel_c ?? '',
                        'married_name'           => $record->meisjesnaam_c ?? '',
                        'married_name_prefix'    => $record->aang_tussenv_c ?? null,
                        'initials'               => $record->voorletters_c ?? '',
                        'date_of_birth'          => $record->birthdate,
                        'gender'                 => $this->mapGenderFromSugar($record->gender_c ?? null),
                        'department_id'          => $this->mapDepartment($record),
                        'lead_channel_id'        => $this->mapChannel($record),
                        'lead_type_id'           => $this->mapType($record),
                        'lead_source_id'         => $this->mapSource($record),
                        'lost_reason'            => $record->reden_afvoeren_c ?? null,
                    ], [
                        'created_at' => $this->parseSugarDate($record->lead_date_entered ?? $record->date_entered),
                        'updated_at' => $this->parseSugarDate($record->lead_date_modified ?? $record->date_modified),
                    ]);

                    // primariy key huisnummer

                    // Create address for lead if present in SugarCRM
                    if (! empty($record->primary_address_postalcode) && ! empty($record->primary_huisnr_c)) {
                        Address::create([
                            'lead_id'             => $lead->id,
                            'street'              => $record->primary_address_street ?? null,
                            'house_number'        => $record->primary_huisnr_c,
                            'house_number_suffix' => $record->primary_huisnr_toevoeging_c ?? null,
                            'postal_code'         => $record->primary_address_postalcode ?? null,
                            'state'               => $record->primary_address_state ?? null,
                            'city'                => $record->primary_address_city ?? null,
                            'country'             => $record->primary_address_country ?? null,
                        ]);
                    }

                    // Attach persons to lead using many-to-many relationship
                    $personIdsToAttach = array_values(array_filter(array_map(fn ($p) => $p->id ?? null, $persons)));
                    if (! empty($personIdsToAttach)) {
                        $this->info('Attached persons ['.implode(', ', $personIdsToAttach).'] to lead ID '.$lead->id);
                        $lead->attachPersons($personIdsToAttach);

                        // Update anamnesis per person (anamnesis created by attachPersons)
                        foreach ($persons as $p) {
                            $perPersonAnamneses = $leadByPersonsByAnamnesis[$lead->external_id][$p->external_id] ?? [];
                            // Pick the first anamnesis object if available
                            $anamnesisValues = array_values($perPersonAnamneses);
                            $firstAnamnesisObj = $anamnesisValues[0] ?? null;
                            if ($firstAnamnesisObj) {
                                $this->updateAnamnesis($lead, $record, $p->id, $firstAnamnesisObj);
                            }
                        }
                    }
                });

                // Import call activities for this lead (outside main transaction)
                try {
                    $callStats = $this->importCallActivities($lead, $callActivities);
                    $callActivitiesImported += $callStats['imported'];
                    $callActivitiesSkipped += $callStats['skipped'];
                } catch (Exception $e) {
                    $this->error("Failed to import call activities for lead {$lead->external_id}: ".$e->getMessage());
                    // Continue with next lead
                }

                $imported++;
                $bar->advance();

            } catch (Exception $e) {
                $errors++;
                $this->error("\nFailed to import lead {$record->id}: ".$e->getMessage());
                Log::error('Failed to import lead', [
                    'record_id' => $record->id ?? 'unknown',
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                ]);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Import completed!');
        $this->info("✓ Imported: {$imported}");
        $this->info("⚠ Skipped: {$skipped}");
        $this->info("✗ Person not found: {$personNotFound}");
        $this->info("✗ Errors: {$errors}");
        $this->line('');
        $this->info('Call Activities:');
        $this->info("✓ Call activities imported: {$callActivitiesImported}");
        $this->info("⚠ Call activities skipped: {$callActivitiesSkipped}");

        // Detailed skip breakdown
        $this->line('');
        $this->info('Skip breakdown:');
        $this->info("- Already existing (external_id present): {$skippedAlreadyExisting}");
        $this->info("- No related persons found: {$skippedNoRelatedPersons}");
        $this->info("- Not all related persons found: {$skippedNotAllPersonsFound}");
    }

    /**
     * Find matching persons for a lead (may be multiple).
     *
     * @return Person[]
     */
    private function findMatchingPerson($record, array $leadByPersonsByAnamnesis): array
    {
        $related = $leadByPersonsByAnamnesis[$record->id] ?? [];
        $relatedPersonIds = array_keys($related);

        if (empty($relatedPersonIds)) {
            return [];
        }

        $this->info('Looking for persons with external_ids: '.implode(', ', $relatedPersonIds));

        $persons = Person::whereIn('external_id', $relatedPersonIds)->get()->all();

        $this->info('Found '.count($persons).' persons: '.implode(', ', array_map(fn ($p) => $p->name.' (ext_id: '.$p->external_id.')', $persons)));

        // Check if any persons were not found and warn
        $foundPersonIds = array_map(fn ($p) => $p->external_id, $persons);
        $missingPersonIds = array_diff($relatedPersonIds, $foundPersonIds);

        if (! empty($missingPersonIds)) {
            $this->warn('⚠ Warning: The following persons were not found in the database and will be skipped: '.implode(', ', $missingPersonIds));
        }

        return $persons;
    }

    /**
     * Extract email from record (could be in different fields)
     */
    private function extractEmail($record)
    {
        // Prefer primary email; fallback to any non-primary if present
        return $record->email_primary ?? $record->email_any ?? null;
    }

    /**
     * Format emails for Lead model
     */
    private function formatEmails($record): array
    {
        $emails = [];

        $primary = $record->email_primary ?? null;
        $any = $record->email_any ?? null;

        if ($primary) {
            $emails[] = [
                'label'      => 'work',
                'value'      => $primary,
                'is_default' => true,
            ];
        }

        if ($any && $any !== $primary) {
            $emails[] = [
                'label'      => 'work',
                'value'      => $any,
                'is_default' => false,
            ];
        }

        return $emails;
    }

    /**
     * Format phones for Lead model
     */
    private function formatPhones($record): array
    {
        $phones = [];

        if ($record->phone_work) {
            $phones[] = [
                'label'      => 'work',
                'value'      => $record->phone_work,
                'is_default' => true,
            ];
        }

        if ($record->phone_mobile) {
            $phones[] = [
                'label'      => 'mobile',
                'value'      => $record->phone_mobile,
                'is_default' => false,
            ];
        }

        if ($record->phone_home) {
            $phones[] = [
                'label'      => 'home',
                'value'      => $record->phone_home,
                'is_default' => false,
            ];
        }

        return $phones;
    }

    /**
     * Map SugarCRM status to our system
     */
    private function mapStatus($status): string
    {
        $statusMap = [
            'New'        => 'new',
            'Assigned'   => 'assigned',
            'In Process' => 'in_process',
            'Converted'  => 'converted',
            'Recycled'   => 'recycled',
            'Dead'       => 'dead',
        ];

        return $statusMap[$status] ?? 'new';
    }

    /**
     * Map SugarCRM workflow status to pipeline stage
     */
    private function mapStage($record): int
    {
        // Get workflow status from Sugar CRM
        $workflowStatus = $record->workflow_status_c ?? 'nieuweaanvraag';
        $leadStatus = $record->status ?? '';

        // Map Sugar CRM workflow statuses to pipeline stages
        $stageMap = [
            'nieuweaanvraag'     => 1, // nieuwe-aanvraag-kwalificeren
            'nieuwe-aanvraag'    => 1, // nieuwe-aanvraag-kwalificeren
            'kwalificeren'       => 1, // nieuwe-aanvraag-kwalificeren
            'adviseren'          => 2, // klant-adviseren-start
            'klant-adviseren'    => 2, // klant-adviseren-start
            'adviseren-start'    => 2, // klant-adviseren-start
            'opvolgen'           => 3, // klant-adviseren-opvolgen
            'adviseren-opvolgen' => 3, // klant-adviseren-opvolgen
            'won'                => 4, // won
            'converted'          => 4, // won
            'lost'               => 5, // lost
            'dead'               => 5, // lost
        ];

        // Check lead status first (higher priority)
        if ($leadStatus) {
            $leadStatusLower = strtolower($leadStatus);
            if ($leadStatusLower === 'converted') {
                return 4; // won stage
            }
            if (in_array($leadStatusLower, ['dead', 'recycled'])) {
                return 5; // lost stage
            }
        }

        // Map workflow status
        $workflowStatusLower = strtolower($workflowStatus);

        // Return mapped stage or default to first stage
        return $stageMap[$workflowStatusLower] ?? PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value;
    }

    /**
     * Create/update anamnesis data for the lead-person using SugarCRM anamnesis object
     */
    private function updateAnamnesis($lead, $record, int $personId, object $amamnesisData): void
    {
        // Find the anamnesis created by attachPersons
        $anamnesis = Anamnesis::where('lead_id', $lead->id)
            ->where('person_id', $personId)
            ->firstOrFail();

        // Update with SugarCRM data and proper timestamps
        $updateData = [
            'description'                => $amamnesisData->description ?? '',
            'height'                     => $amamnesisData->lengte ?? null,
            'weight'                     => $amamnesisData->gewicht ?? null,
            'metals'                     => (bool) ($amamnesisData->metalen ?? false),
            'metals_notes'               => $amamnesisData->opm_metalen_c ?? null,
            'medications'                => (bool) ($amamnesisData->medicijnen ?? false),
            'medications_notes'          => $amamnesisData->opm_medicijnen_c ?? null,
            'glaucoma'                   => (bool) ($amamnesisData->glaucoom ?? false),
            'glaucoma_notes'             => $amamnesisData->opm_glaucoom_c ?? null,
            'claustrophobia'             => (bool) ($amamnesisData->claustrofobie ?? false),
            'dormicum'                   => (bool) ($amamnesisData->dormicum ?? false),
            'heart_surgery'              => (bool) ($amamnesisData->hart_operatie_c ?? false),
            'heart_surgery_notes'        => $amamnesisData->opm_hart_operatie_c ?? null,
            'implant'                    => (bool) ($amamnesisData->implantaat_c ?? false),
            'implant_notes'              => $amamnesisData->opm_implantaat_c ?? null,
            'surgeries'                  => (bool) ($amamnesisData->operaties_c ?? false),
            'surgeries_notes'            => $amamnesisData->opm_operaties_c ?? null,
            'remarks'                    => $amamnesisData->opmerking ?? null,
            'hereditary_heart'           => (bool) ($amamnesisData->hart_erfelijk ?? false),
            'hereditary_heart_notes'     => $amamnesisData->opm_erf_hart_c ?? null,
            'hereditary_vascular'        => (bool) ($amamnesisData->vaat_erfelijk ?? false),
            'hereditary_vascular_notes'  => $amamnesisData->opm_erf_vaat_c ?? null,
            'hereditary_tumors'          => (bool) ($amamnesisData->tumoren_erfelijk ?? false),
            'hereditary_tumors_notes'    => $amamnesisData->opm_erf_tumoren_c ?? null,
            'allergies'                  => (bool) ($amamnesisData->allergie_c ?? false),
            'allergies_notes'            => $amamnesisData->opm_allergie_c ?? null,
            'back_problems'              => (bool) ($amamnesisData->rugklachten ?? false),
            'back_problems_notes'        => $amamnesisData->opm_rugklachten_c ?? null,
            'heart_problems'             => (bool) ($amamnesisData->heart_problems ?? false),
            'heart_problems_notes'       => $amamnesisData->opm_hartklachten_c ?? null,
            'smoking'                    => (bool) ($amamnesisData->smoking ?? false),
            'smoking_notes'              => $amamnesisData->opm_roken_c ?? null,
            'diabetes'                   => (bool) ($amamnesisData->diabetes ?? false),
            'diabetes_notes'             => $amamnesisData->opm_diabetes_c ?? null,
            'digestive_problems'         => (bool) ($amamnesisData->spijverteringsklachten_c ?? false),
            'digestive_problems_notes'   => $amamnesisData->opm_spijsverterering_c ?? null,
            'heart_attack_risk'          => $amamnesisData->risico_hartinfarct ?? null,
            'advice_notes'               => $amamnesisData->opm_advies_c ?? null,
            'active'                     => true,
            'digestive_complaints'       => (bool) ($anamnesis->spijsverteringsklachten ?? false),
            'digestive_complaints_notes' => $anamnesis->opm_spijsvertering_c ?? null,
            'comment_clinic'             => $amamnesisData->anamnese ?? null,
        ];

        // Update with proper timestamps
        $anamnesis->timestamps = false;
        $anamnesis->fill($updateData);

        // Set custom timestamps
        $createdAtParsed = $this->parseSugarDate($amamnesisData->date_entered);
        $updatedAtParsed = $this->parseSugarDate($amamnesisData->date_modified);

        if ($createdAtParsed) {
            $anamnesis->setAttribute('created_at', $createdAtParsed);
        }
        if ($updatedAtParsed) {
            $anamnesis->setAttribute('updated_at', $updatedAtParsed);
        }

        $anamnesis->saveQuietly();
        $anamnesis->timestamps = true;
    }

    /**
     * Map channel based on SugarCRM kanaal field
     */
    private function mapChannel($record): int
    {
        $kanaal = $record->kanaal_c ?? '';

        // Map SugarCRM channels to our channel IDs
        $channelMap = [
            'telefoon'     => 1, // Telefoon
            'website'      => 2, // Website
            'email'        => 3, // E-mail
            'tel-en-tel'   => 4, // Tel-en-Tel
            'agenten'      => 5, // Agenten
            'partners'     => 6, // Partners
            'social media' => 7, // Social media
            'webshop'      => 8, // Webshop
            'campagne'     => 9, // Campagne
        ];

        $kanaalLower = strtolower(trim($kanaal));

        return $channelMap[$kanaalLower] ?? 2; // Default to Website (ID: 2)
    }

    /**
     * Map type based on SugarCRM soort_aanvraag field
     */
    private function mapType($record): int
    {
        $soortAanvraag = $record->soort_aanvraag_c ?? '';

        // Map SugarCRM types to our type IDs
        $typeMap = [
            'preventie' => 1, // Preventie
            'gericht'   => 2, // Gericht
            'operatie'  => 3, // Operatie
            'overig'    => 4, // Overig
        ];

        $soortAanvraagLower = strtolower(trim($soortAanvraag));

        return $typeMap[$soortAanvraagLower] ?? 4; // Default to Overig (ID: 4)
    }

    /**
     * Map department based on SugarCRM kanaal or soort_aanvraag
     */
    private function mapDepartment($record): int
    {
        $kanaal = $record->kanaal_c ?? '';
        $soortAanvraag = $record->soort_aanvraag_c ?? '';

        // Map based on channel/type to determine department
        $kanaalLower = strtolower(trim($kanaal));
        $soortLower = strtolower(trim($soortAanvraag));

        // Hernia department indicators
        $herniaIndicators = ['operatie'];
        foreach ($herniaIndicators as $indicator) {
            if (str_contains($kanaalLower, $indicator) || str_contains($soortLower, $indicator)) {
                return Department::findHerniaId();
            }
        }

        // Default to Privatescan department
        return Department::findPrivateScanId();
    }

    /**
     * Map source based on SugarCRM lead source or other criteria
     */
    private function mapSource($record): int
    {
        $leadSource = $record->lead_source ?? '';

        // Map SugarCRM sources to our source IDs
        $sourceMap = [
            'bodyscan.nl'                  => 1,
            'privatescan.nl'               => 2,
            'mri-scan.nl'                  => 3,
            'ccsvi-online.nl'              => 4,
            'ccsvi-online.com'             => 5,
            'google zoeken'                => 6,
            'adwords'                      => 7,
            'krant telegraaf'              => 8,
            'krant spits'                  => 9,
            'krant regionaal'              => 10,
            'krant overige dagbladen'      => 11,
            'krant redactioneel'           => 12,
            'magazine dito'                => 13,
            'magazine humo belgie'         => 14,
            'dokterdokter.nl'              => 15,
            'vrouw.nl'                     => 16,
            'dito-magazine.nl'             => 17,
            'groupdeal.nl'                 => 18,
            'marktplaats'                  => 19,
            'zorgplanet.nl'                => 20,
            'linkpartner'                  => 21,
            'youtube'                      => 22,
            'linkedin'                     => 23,
            'twitter'                      => 24,
            'facebook'                     => 25,
            'rtl business class'           => 26,
            'nieuwsbrief'                  => 27,
            'bestaande klant'              => 28,
            'zakenrelatie'                 => 29,
            'vrienden, familie, kennissen' => 30,
            'collega'                      => 31,
            'anders'                       => 32,
            'wegener webshop'              => 33,
            'herniapoli.nl'                => 34,
        ];

        $leadSourceLower = strtolower(trim($leadSource));

        return $sourceMap[$leadSourceLower] ?? 32; // Default to Anders (ID: 32)
    }

    private function mapGender(?string $sugarGender): ?string
    {
        if (! $sugarGender) {
            return null;
        }
        $g = strtolower(trim($sugarGender));

        return match ($g) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            default => null,
        };
    }

    private function extractPerson(mixed $records): array
    {
        // Bulk fetch person IDs for given lead IDs from SugarCRM relation table
        $leadIds = collect($records)->pluck('id')->all();

        if (empty($leadIds)) {
            return [];
        }

        $connection = $this->option('connection');

        $sql = DB::connection($connection)
            ->table('leads_contacts_c')
            ->select('leads_c7104eads_ida as lead_id', 'leads_cbb5dacts_idb as person_id')
            ->whereIn('leads_c7104eads_ida', $leadIds)
            ->where('deleted', 0);
        $this->info($sql->toRawSql());
        $relations = $sql->get();

        $this->info('extractPerson: Found '.$relations->count().' relations');

        // Map lead_id => [person_id1, person_id2, ...] to support multiple persons per lead
        $map = [];
        foreach ($relations as $rel) {
            if (! isset($map[$rel->lead_id])) {
                $map[$rel->lead_id] = [];
            }
            // Only add if this person_id is not already in the array
            if (! in_array($rel->person_id, $map[$rel->lead_id])) {
                $map[$rel->lead_id][] = $rel->person_id;
            }
        }

        // Debug: Show unique person counts per lead
        foreach ($map as $leadId => $personIds) {
            $uniquePersonIds = array_unique($personIds);
            $this->info("Lead {$leadId}: ".count($personIds).' total relations, '.count($uniquePersonIds).' unique persons: '.implode(', ', $uniquePersonIds));
        }

        return $map;
    }

    /**
     * @param  array  $leadByPersons  [lead_id => [person_id1, person_id2, ...]]
     * @return array [lead_id => [person_id => anamnesis_data]]
     */
    private function extractAnamenesis(array $leadByPersons): array
    {
        if (empty($leadByPersons)) {
            return [];
        }

        $connection = $this->option('connection');
        $leadIds = array_keys($leadByPersons);
        $personIds = array_merge(...array_values($leadByPersons));

        // Fetch anamnesis relations for given lead and person IDs
        $sql = DB::connection($connection)
            ->table('leads_pcrm_anamnesepreventie_1_c as lead_anamnesis')
            ->join('pcrm_anamnetie_contacts_c as anamnesis_person', function ($join) use ($personIds) {
                $join->on(
                    'anamnesis_person.pcrm_anamn171deventie_idb',
                    '=',
                    'lead_anamnesis.leads_pcrm_anamnesepreventie_1pcrm_anamnesepreventie_idb'
                )
                    ->where('anamnesis_person.deleted', '=', 0)
                    ->whereIn('anamnesis_person.pcrm_anamn0b6eontacts_ida', $personIds);
            })
            ->join('pcrm_anamnesepreventie as anamnesis', 'anamnesis.id', '=', 'lead_anamnesis.leads_pcrm_anamnesepreventie_1pcrm_anamnesepreventie_idb')
            ->join('pcrm_anamnesepreventie_cstm as anamnesis_cstm', 'anamnesis_cstm.id_c', '=', 'anamnesis.id')
            ->select([
                'lead_anamnesis.leads_pcrm_anamnesepreventie_1leads_ida as lead_id',
                'anamnesis_person.pcrm_anamn0b6eontacts_ida as person_id',
                'anamnesis.id as anamnesis_id',
                // Add all relevant anamnesis properties below
                'anamnesis.name',
                'anamnesis.date_entered',
                'anamnesis.date_modified',
                'anamnesis.modified_user_id',
                'anamnesis.created_by',
                'anamnesis.description',
                'anamnesis.deleted',
                'anamnesis.team_id',
                'anamnesis.team_set_id',
                'anamnesis.assigned_user_id',
                'anamnesis.anamnese',
                'anamnesis.lengte',
                'anamnesis.gewicht',
                'anamnesis.metalen',
                'anamnesis.medicijnen',
                'anamnesis.glaucoom',
                'anamnesis.claustrofobie',
                'anamnesis.dormicum',
                'anamnesis.opmerking',
                'anamnesis.hart_erfelijk',
                'anamnesis.vaat_erfelijk',
                'anamnesis.tumoren_erfelijk',
                'anamnesis.rugklachten',
                'anamnesis.heart_problems',
                'anamnesis.smoking',
                'anamnesis.diabetes',
                'anamnesis.spijsverteringsklachten',
                'anamnesis.risico_hartinfarct',
                'anamnesis.anamnese_datum',
                'anamnesis_cstm.opm_metalen_c',
                'anamnesis_cstm.opm_medicijnen_c',
                'anamnesis_cstm.opm_glaucoom_c',
                'anamnesis_cstm.opm_erf_hart_c',
                'anamnesis_cstm.opm_erf_vaat_c',
                'anamnesis_cstm.opm_erf_tumor_c',
                'anamnesis_cstm.opm_rugklachten_c',
                'anamnesis_cstm.opm_hartklachten_c',
                'anamnesis_cstm.opm_roken_c',
                'anamnesis_cstm.opm_diabetes_c',
                'anamnesis_cstm.opm_spijsvertering_c',
                'anamnesis_cstm.operaties_c',
                'anamnesis_cstm.opm_operaties_c',
                'anamnesis_cstm.opm_advies_c',
                'anamnesis_cstm.hart_operatie_c',
                'anamnesis_cstm.allergie_c',
                'anamnesis_cstm.implantaat_c',
                'anamnesis_cstm.opm_allergie_c',
                'anamnesis_cstm.opm_hart_operatie_c',
                'anamnesis_cstm.opm_implantaat_c',
                'anamnesis_cstm.aos_products_id_c',
                'anamnesis_cstm.diagnose_c',
                'anamnesis_cstm.operatieopname_c',
            ])
            ->whereIn('lead_anamnesis.leads_pcrm_anamnesepreventie_1leads_ida', $leadIds)
            ->where('anamnesis.status', '=', 'active');

        $this->info($sql->toRawSql());
        $relations = $sql->get();

        $this->info('extractAnamenesis: Found '.$relations->count().' relations');

        $result = [];
        foreach ($relations as $rel) {
            if (! isset($result[$rel->lead_id])) {
                $result[$rel->lead_id] = [];
            }
            if (! isset($result[$rel->lead_id][$rel->person_id])) {
                $result[$rel->lead_id][$rel->person_id] = [];
            }
            // Only add if this anamnesis_id doesn't already exist for this person
            if (! isset($result[$rel->lead_id][$rel->person_id][$rel->anamnesis_id])) {
                $result[$rel->lead_id][$rel->person_id][$rel->anamnesis_id] = $rel;
            }
        }

        $this->info(
            'extractAnamenesis: Found '.$relations->count().' relations, returning '.count($result).' unique lead-person-anamnesis mappings. '.
            'Unique persons per lead: '.implode(', ', array_map(
                fn ($persons) => count($persons),
                $result
            )).'. '.
            'Anamnesis counts per person: '.implode(', ', array_map(
                fn ($persons) => implode('|', array_map(fn ($anamneses) => count($anamneses), $persons)),
                $result
            ))
        );

        return $result;
    }

    private function mapPipeline($record, int $departmentId): int
    {
        if ($departmentId == Department::findHerniaId()) {
            return PipelineDefaultKeys::PIPELINE_HERNIA_ID->value;
        }

        return PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value;
    }

    /**
     * Extract call activities from SugarCRM for the given leads
     *
     * @param  mixed  $records  The lead records
     * @return array [lead_id => [call_data1, call_data2, ...]]
     */
    private function extractCallActivities($records): array
    {
        $leadIds = collect($records)->pluck('id')->all();

        if (empty($leadIds)) {
            return [];
        }

        $connection = $this->option('connection');

        try {
            // Check if calls tables exist
            if (! Schema::connection($connection)->hasTable('calls')) {
                $this->info('Calls table does not exist in SugarCRM database, skipping call activities import');

                return [];
            }
            if (! Schema::connection($connection)->hasTable('calls_cstm')) {
                $this->info('Calls_cstm table does not exist in SugarCRM database, skipping call activities import');

                return [];
            }

            $sql = DB::connection($connection)
                ->table('calls as c')
                ->join('calls_cstm as cc', 'c.id', '=', 'cc.id_c')
                ->select([
                    'c.id',
                    'c.name',
                    'c.date_entered',
                    'c.date_modified',
                    'c.modified_user_id',
                    'c.created_by',
                    'c.description',
                    'c.deleted',
                    'c.assigned_user_id',
                    'c.date_start',
                    'c.date_end',
                    'c.parent_type',
                    'c.status',
                    'c.direction',
                    'c.parent_id',
                    'cc.belgroep_c',
                ])
                ->whereIn('c.parent_id', $leadIds)
                ->where('c.parent_type', '=', 'Leads')
                ->where('c.deleted', '=', 0)
                ->orderBy('c.date_entered', 'asc');

            $this->info('Extracting call activities: '.$sql->toRawSql());
            $calls = $sql->get();

            $this->info('Found '.$calls->count().' call activities');

            // Group calls by parent_id (lead_id)
            $result = [];
            foreach ($calls as $call) {
                if (! isset($result[$call->parent_id])) {
                    $result[$call->parent_id] = [];
                }
                $result[$call->parent_id][] = $call;
            }

            return $result;
        } catch (Exception $e) {
            $this->error('Failed to extract call activities: '.$e->getMessage());
            $this->info('Continuing import without call activities');

            return [];
        }
    }

    /**
     * Import call activities for a lead
     *
     * @param  Lead  $lead  The lead to import activities for
     * @param  array  $callActivities  All call activities grouped by lead ID
     * @return array Statistics about imported and skipped activities
     */
    private function importCallActivities(Lead $lead, array $callActivities): array
    {
        $imported = 0;
        $skipped = 0;

        try {
            $leadCallActivities = $callActivities[$lead->external_id] ?? [];

            if (empty($leadCallActivities)) {
                return ['imported' => $imported, 'skipped' => $skipped];
            }

            $this->info('Importing '.count($leadCallActivities)." call activities for lead {$lead->external_id}");

            foreach ($leadCallActivities as $callData) {
                try {
                    // Check if activity already exists by external reference
                    $existingActivity = Activity::where('external_id', $callData->id)->first();
                    if ($existingActivity) {
                        $this->info("Skipping existing call activity with external_id={$callData->id}");
                        $skipped++;

                        continue;
                    }

                    // Get group_id from lead's department (will throw exception if invalid)
                    $groupId = Department::getGroupIdForLead($lead);

                    // Create the activity
                    $activityData = [
                        'title'       => $callData->name ?? 'Bel activiteit',
                        'type'        => 'call',
                        'comment'     => $callData->description ?? '',
                        'external_id' => $callData->id,
                        'additional'  => [
                            'direction'   => $callData->direction,
                            'status'      => $callData->status,
                            'belgroep'    => $callData->belgroep_c,
                        ],
                        'schedule_from' => $this->parseSugarDate($callData->date_start),
                        'schedule_to'   => $this->parseSugarDate($callData->date_end),
                        'is_done'       => $this->mapCallStatus($callData->status),
                        'user_id'       => $this->mapAssignedUser($callData->assigned_user_id),
                        'lead_id'       => $lead->id,
                        'group_id'      => $groupId,
                    ];

                    $timestamps = [
                        'created_at' => $this->parseSugarDate($callData->date_entered),
                        'updated_at' => $this->parseSugarDate($callData->date_modified),
                    ];

                    $activity = $this->createEntityWithTimestamps(Activity::class, $activityData, $timestamps);

                    $this->info("✓ Imported call activity: {$callData->name} for lead {$lead->external_id}");
                    $imported++;
                } catch (Exception $e) {
                    $this->error("Failed to import call activity {$callData->id}: ".$e->getMessage());
                    // Continue with next call activity
                }
            }
        } catch (Exception $e) {
            $this->error("Failed to import call activities for lead {$lead->external_id}: ".$e->getMessage());
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Map call status to is_done boolean
     */
    private function mapCallStatus(?string $status): bool
    {
        if (! $status) {
            return false;
        }

        $completedStatuses = ['held', 'completed', 'done', 'finished'];

        return in_array(strtolower(trim($status)), $completedStatuses);
    }

    /**
     * Map assigned user ID from SugarCRM to existing user by external_id
     *
     * @param  string|null  $assignedUserId  The SugarCRM user ID
     * @return int|null The user ID to assign
     *
     * @throws Exception when user could not be found by external_id
     */
    private function mapAssignedUser(?string $assignedUserId): ?int
    {
        if (empty($assignedUserId)) {
            return null;
        }
        // Look up user by external_id
        $user = User::where('external_id', $assignedUserId)->first();
        if (is_null($user)) {
            throw new Exception('User not found by external_id: '.$assignedUserId);
        }

        $this->info("Mapped assigned user {$assignedUserId} to user: {$user->name} (ID: {$user->id})");

        return $user->id;
    }

    /**
     * @throws Exception when external users are missing
     */
    private function ensureUserImportRan(): void
    {
        User::whereNotNull('external_id')->count() > 0 or throw new Exception('No users with external_id found, please run the user import first');
    }
}
