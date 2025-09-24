<?php

namespace App\Console\Commands;

use App\Enums\LostReason;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Models\Address;
use App\Models\Anamnesis;
use App\Models\Department;
use App\Services\Importers\SugarCRM\ActivityImporter;
use App\Services\Importers\SugarCRM\AttachmentImporter;
use App\Services\Importers\SugarCRM\MeetingImporter;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

/**
 * Import leads from SugarCRM database with anamnesis data, call activities, email activities, meeting activities and email attachments
 *
 * This command imports leads and their associated anamnesis data from SugarCRM,
 * as well as call activities, email activities, meeting activities and email attachments related to those leads.
 * It uses the following relationships:
 * - leads_contacts_c: Links leads to persons
 * - leads_pcrm_anamnesepreventie_1_c: Links leads to anamnesis
 * - pcrm_anamnetie_contacts_c: Links anamnesis to persons
 * - calls table: Contains call activities where parent_type = 'Leads'
 * - meetings table: Contains meeting activities where parent_type = 'Leads'
 * - emails, emails_text, emails_beans: Contains emails linked to leads via bean_module = 'Leads'
 * - notes table: Contains email attachments linked to emails via parent_id where parent_type = 'Emails'
 */
class ImportLeadsFromSugarCRM extends AbstractSugarCRMImport
{
    protected ActivityImporter $activityImporter;

    protected AttachmentImporter $attachmentImporter;

    protected MeetingImporter $meetingImporter;

    // Totals across all chunks
    private int $totalImported = 0;
    private int $totalSkipped = 0;
    private int $totalErrors = 0;
    private int $totalPersonNotFound = 0;
    private int $totalSkippedAlreadyExisting = 0;
    private int $totalSkippedNoRelatedPersons = 0;
    private int $totalSkippedNotAllPersonsFound = 0;
    private int $totalCallActivitiesImported = 0;
    private int $totalCallActivitiesSkipped = 0;
    private int $totalEmailActivitiesImported = 0;
    private int $totalEmailActivitiesSkipped = 0;
    private int $totalMeetingActivitiesImported = 0;
    private int $totalMeetingActivitiesSkipped = 0;
    private int $totalEmailAttachmentsImported = 0;
    private int $totalEmailAttachmentsSkipped = 0;
    private ProgressBar $bar;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:leads
                            {--connection=sugarcrm : Database connection name}
                            {--limit=-1 : Number of records to import (optional, defaults to all)}
                            {--lead-ids=* : Specific lead IDs to import (ignores limit)}
                            {--dry-run : Show what would be imported without actually importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import leads from SugarCRM database with anamnesis data, call activities, email activities, meeting activities and email attachments';

    /**
     * Map SugarCRM reden_afvoeren_c (free text/code) to our LostReason enum value
     */
    private static function mapLostReason(?string $sugarReason): ?string
    {
        if (! $sugarReason) {
            return null;
        }

        $normalized = strtolower(trim($sugarReason));

        $map = [
            'geen mri'                    => LostReason::NoMRI->value,
            'geenmri'                     => LostReason::NoMRI->value,
            'afval'                       => LostReason::Waste->value,
            'prijs'                       => LostReason::Price->value,
            'geen vergoeding'             => LostReason::NoInsuranceCoverage->value,
            'geen vergoeding verzekeraar' => LostReason::NoInsuranceCoverage->value,
            'afstand'                     => LostReason::Distance->value,
            'informatief'                 => LostReason::Informative->value,
            'prescan'                     => LostReason::Prescan->value,
            'ziek'                        => LostReason::Sick->value,
            'concurrent'                  => LostReason::Competitor->value,
            'niet planbaar'               => LostReason::NotSchedulable->value,
            'geen vervoer'                => LostReason::NoTransport->value,
            'partner niet akkoord'        => LostReason::PartnerDisagrees->value,
            'niet uitvoerbaar'            => LostReason::NotFeasible->value,
            'negatief advies'             => LostReason::NegativeAdvice->value,
            'geen reactie'                => LostReason::NoResponse->value,
            'betaalt niet'                => LostReason::DoesNotPay->value,
            'verslapen'                   => LostReason::Overslept->value,
            'te laat'                     => LostReason::TooLate->value,
            'angst'                       => LostReason::Fear->value,
            'ontevreden'                  => LostReason::Dissatisfied->value,
            'elders nl standaard'         => LostReason::StandardCareNL->value,
            'kan in nl zorg terecht'      => LostReason::StandardCareNL->value,
            'uitstel door omstandigheden' => LostReason::PostponedCircumstances->value,
            'nieuwsbrief'                 => LostReason::NewsletterOnly->value,
            'foutief'                     => LostReason::Incorrect->value,
            'datainvoer'                  => LostReason::DataEntry->value,
            'geen reden'                  => LostReason::NoReason->value,
            'spoort niet'                 => LostReason::NotMatching->value,
        ];

        // direct match by map
        if (array_key_exists($normalized, $map)) {
            return $map[$normalized];
        }

        // attempt to match any enum value or label partially
        foreach (LostReason::cases() as $case) {
            if ($normalized === strtolower($case->value) || $normalized === strtolower($case->label())) {
                return $case->value;
            }
        }

        return null; // fallback to null if unknown
    }

    /**
     * Execute the console command.
     * @throws Exception
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $limit = (int) $this->option('limit');
        $leadIds = $this->option('lead-ids');
        $dryRun = $this->option('dry-run');

        $this->info('Starting lead import from SugarCRM...');
        $this->infoV("Connection: {$connection}");
        if (! empty($leadIds)) {
            $this->info('Lead IDs: '.implode(', ', $leadIds));
        } else {
            $this->info("Limit: {$limit}");
        }
        $this->infoV('Dry run: '.($dryRun ? 'Yes' : 'No'));

        // Initialize importers
        $this->activityImporter = new ActivityImporter($this, $connection);
        $this->attachmentImporter = new AttachmentImporter($this, $connection);
        $this->meetingImporter = new MeetingImporter($this, $connection);

        // user import needs to be run first
        $this->ensureUserImportRan();

        return $this->executeImport($dryRun, function () use ($connection, $limit, $leadIds, $dryRun) {
            // Test connection
            $this->testConnection($connection);

            // Reduce memory usage for large imports
            DB::disableQueryLog();
            DB::connection($connection)->disableQueryLog();

            // Prepare an ID-only query for safe chunking
            $idQuery = DB::connection($connection)
                ->table('leads as l')
                ->join('leads_cstm as lc', 'l.id', '=', 'lc.id_c')
                ->where('l.deleted', 0)
                ->where('soort_aanvraag_c', '!=', 'ccsvi');

            if (! empty($leadIds)) {
                $idQuery= $idQuery->whereIn('l.id', $leadIds);
            }
            $this->info('Total leads to process: '.$idQuery->count());
            $this->infoVV($idQuery->toRawSql());
            $batchSize = 1000;
            $processed = 0;

            $this->bar = $this->output->createProgressBar($idQuery->count());
            $this->bar->start();

            $idQuery->select('l.id')->chunkById($batchSize, function ($rows) use ($limit, $connection, $dryRun, &$processed) {
                if ($rows->isEmpty()) {
                    $this->info('No more leads to process, exiting chunk loop');
                    return false;
                }
                if ($limit > 0) {
                    $remaining = $limit - $processed;
                    if ($remaining <= 0) {
                        return false; // al klaar
                    }
                    $leadIdsBatch = $rows->pluck('id')->take($remaining)->all();
                } else {
                    $leadIdsBatch = $rows->pluck('id')->all();
                }
                $this->infoV('Running next batch, number of leads: '.count($leadIdsBatch));
//                $this->info(print_r($leadIdsBatch, true));

                // Build the full select for this batch
                $sqlBatch = DB::connection($connection)
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
                    ->whereIn('l.id', $leadIdsBatch)
                    ->groupBy('l.id');
                $records = $sqlBatch->get();
                $this->infoV('Fetched '.($records ? $records->count() : 0).' lead records');
                $leadByPersons = $this->extractPerson($records);
                $leadByPersonsByAnamnesis = $this->extractAnamenesis($leadByPersons);

                $callActivities = $this->activityImporter->extractCallActivities($leadIdsBatch);
                $emailActivities = $this->activityImporter->extractEmailActivities($leadIdsBatch);
                $meetingActivities = $this->meetingImporter->extractMeetingActivities($leadIdsBatch);
                $emailAttachments = $this->attachmentImporter->extractEmailAttachments($leadIdsBatch);

                if ($dryRun) {
                    $this->showDryRunResults($records, $leadByPersonsByAnamnesis, $callActivities, $emailActivities, $meetingActivities, $emailAttachments);
                } else {
                    $this->importRecords($records, $leadByPersonsByAnamnesis, $callActivities, $emailActivities, $meetingActivities, $emailAttachments);
                }

                $processed += $records->count();
                unset($records);
                gc_collect_cycles();
            });

            $this->bar->finish();
            // After processing all chunks, print a single consolidated summary
            if (! $dryRun) {
                $this->printImportSummary();
            }
        });
    }

    /**
     * Show dry run results
     */
    private function showDryRunResults($records, $leadByPersonsByAnamnesis, $callActivities = [], $emailActivities = [], $meetingActivities = [], $emailAttachments = []): void
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
            'Email Activities',
            'Meeting Activities',
            'Email Attachments',
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

            // Get email activities for this lead
            $leadEmailActivities = $emailActivities[$record->id] ?? [];
            $emailActivitiesInfo = empty($leadEmailActivities) ? '—' : count($leadEmailActivities).' emails';

            // Get meeting activities for this lead
            $leadMeetingActivities = $meetingActivities[$record->id] ?? [];
            $meetingActivitiesInfo = empty($leadMeetingActivities) ? '—' : count($leadMeetingActivities).' meetings';

            // Get email attachments for this lead
            $leadEmailAttachments = $emailAttachments[$record->id] ?? [];
            $emailAttachmentsInfo = empty($leadEmailAttachments) ? '—' : count($leadEmailAttachments).' files';

            $mappedDepartementId = $this->mapDepartment($record);
            $mappedPipelineId = $this->mapPipeline($record, $mappedDepartementId);
            $mappedStatus = $this->mapStage($record, $mappedPipelineId);
            $rows[] = [
                $record->id ?? 'N/A',
                $record->first_name ?? 'N/A',
                $record->last_name ?? 'N/A',
                $this->extractEmail($record) ?? 'N/A',
                $record->phone_work ?? 'N/A',
                $record->status ?? 'N/A',
                $record->workflow_status_c ?? 'N/A',
                $mappedStatus,
                $mappedDepartementId,
                $this->mapChannel($record),
                $this->mapType($record),
                $this->mapSource($record),
                $record->date_entered ?? 'N/A',
                empty($relatedPersonIds) ? '—' : implode(',', $relatedPersonIds),
                $personMatch,
                empty($relatedAnamnesisIds) ? '—' : implode(',', $relatedAnamnesisIds),
                $relatedAnamnesisCount,
                $callActivitiesInfo,
                $emailActivitiesInfo,
                $meetingActivitiesInfo,
                $emailAttachmentsInfo,
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

        // Show email activities summary
        if (! empty($emailActivities)) {
            $totalEmailActivities = array_sum(array_map('count', $emailActivities));
            $this->line('');
            $this->info('Email Activities Summary:');
            $this->info("Total email activities found: {$totalEmailActivities}");

            if ($totalEmailActivities > 0) {
                $this->info('Email activities per lead:');
                foreach ($emailActivities as $leadId => $emails) {
                    $leadRecord = collect($records)->firstWhere('id', $leadId);
                    $leadName = $leadRecord ? "{$leadRecord->first_name} {$leadRecord->last_name}" : "Lead {$leadId}";
                    $this->info("  - {$leadName}: ".count($emails).' emails');
                }
            }
        } else {
            $this->line('');
            $this->info('Email Activities Summary: No email activities found or emails table not available');
        }

        // Show meeting activities summary
        if (! empty($meetingActivities)) {
            $totalMeetingActivities = array_sum(array_map('count', $meetingActivities));
            $this->line('');
            $this->info('Meeting Activities Summary:');
            $this->info("Total meeting activities found: {$totalMeetingActivities}");

            if ($totalMeetingActivities > 0) {
                $this->info('Meeting activities per lead:');
                foreach ($meetingActivities as $leadId => $meetings) {
                    $leadRecord = collect($records)->firstWhere('id', $leadId);
                    $leadName = $leadRecord ? "{$leadRecord->first_name} {$leadRecord->last_name}" : "Lead {$leadId}";
                    $this->info("  - {$leadName}: ".count($meetings).' meetings');
                }
            }
        } else {
            $this->line('');
            $this->info('Meeting Activities Summary: No meeting activities found or meetings table not available');
        }

        // Show email attachments summary
        if (! empty($emailAttachments)) {
            $totalEmailAttachments = array_sum(array_map('count', $emailAttachments));
            $this->line('');
            $this->info('Email Attachments Summary:');
            $this->info("Total email attachments found: {$totalEmailAttachments}");

            if ($totalEmailAttachments > 0) {
                $this->info('Email attachments per lead:');
                foreach ($emailAttachments as $leadId => $attachments) {
                    $leadRecord = collect($records)->firstWhere('id', $leadId);
                    $leadName = $leadRecord ? "{$leadRecord->first_name} {$leadRecord->last_name}" : "Lead {$leadId}";
                    $this->info("  - {$leadName}: ".count($attachments).' files');
                }
            }
        } else {
            $this->line('');
            $this->info('Email Attachments Summary: No email attachments found or notes table not available');
        }
    }

    /**
     * Import records
     */
    private function importRecords($records, $leadByPersonsByAnamnesis, $callActivities = [], $emailActivities = [], $meetingActivities = [], $emailAttachments = []): void
    {


        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $personNotFound = 0;
        $skippedAlreadyExisting = 0;
        $skippedNoRelatedPersons = 0;
        $skippedNotAllPersonsFound = 0;
        $callActivitiesImported = 0;
        $callActivitiesSkipped = 0;
        $emailActivitiesImported = 0;
        $emailActivitiesSkipped = 0;
        $meetingActivitiesImported = 0;
        $meetingActivitiesSkipped = 0;
        $emailAttachmentsImported = 0;
        $emailAttachmentsSkipped = 0;

        foreach ($records as $record) {
            try {
                // Check if lead already exists by external_id
                $existingLead = Lead::where('external_id', $record->id)->first();
                if ($existingLead) {
                    $skipped++;
                    $skippedAlreadyExisting++;
                    //                    $this->info("Skipping existing lead with external_id={$record->id} (already imported as #{$existingLead->id})");
                    $this->bar->advance();

                    continue;
                }

                // Find matching persons
                $this->infoVV('leadByPersonsByAnamnesis = '.print_r($leadByPersonsByAnamnesis, true));
                $persons = $this->findMatchingPerson($record, $leadByPersonsByAnamnesis);
                $related = $leadByPersonsByAnamnesis[$record->id] ?? [];
                $expectedPersonIds = array_keys($related);

                // If SugarCRM shows no related persons for this lead, we still import the lead
                // If SugarCRM shows related persons, then all of them must be found to proceed
                if (! empty($expectedPersonIds) && empty($persons)) {
                    $personNotFound++;
                    $skipped++;
                    $skippedNoRelatedPersons++;
                    $this->error("\nPerson not found for lead: {$record->first_name} {$record->last_name} (LEAD ID: {$record->id}) (person ids: ".implode(',', $expectedPersonIds).')');
                    $this->bar->advance();

                    continue;
                }

                // Check if all expected persons were found
                $foundPersonIds = array_map(fn ($p) => $p->external_id, $persons);
                $missingPersonIds = array_diff($expectedPersonIds, $foundPersonIds);

                if (! empty($expectedPersonIds) && ! empty($missingPersonIds)) {
                    $personNotFound++;
                    $skipped++;
                    $skippedNotAllPersonsFound++;
                    $this->error("\nNot all persons found for lead: {$record->first_name} {$record->last_name} (LEAD ID: {$record->id})");
                    $this->error('Expected persons: '.implode(', ', $expectedPersonIds));
                    $this->error('Found persons: '.implode(', ', $foundPersonIds));
                    $this->error('Missing persons: '.implode(', ', $missingPersonIds));
                    $this->error('Skipping lead import - all persons must be found first.');
                    $this->bar->advance();

                    continue;
                }

                // Debug: Check person data
                if (! empty($persons)) {
                    $this->infoV('Creating lead for persons: '.implode(', ', array_map(fn ($p) => $p->name.' (#'.$p->id.')', $persons)));
                }

                // Use database transaction to ensure all-or-nothing import
                DB::transaction(function () use ($record, $persons, $leadByPersonsByAnamnesis, &$lead) {
                    // Create lead with proper timestamps
                    $mappedDepartmentId = $this->mapDepartment($record);
                    $departmentId = $mappedDepartmentId;
                    $mappedPipelineId = $this->mapPipeline($record, $mappedDepartmentId);
                    $mappedStageId = $this->mapStage($record, $mappedPipelineId);
                    $mapLostReason = self::mapLostReason($record->reden_afvoeren_c ?? null);
                    $lostStageId = Stage::where('lead_pipeline_id', $mappedPipelineId)
                        ->where('code', 'like', '%lost%')
                        ->firstOrFail()->id;
                    if ($lostStageId == $mappedStageId && is_null($mapLostReason)) {
                        $this->infoV('Mapping lost reason to NoReason for lost stage (default)');
                        $mapLostReason = LostReason::NoReason->value;
                    }
                    $lead = $this->createEntityWithTimestamps(Lead::class, [
                        'external_id'            => $record->id,
                        'description'            => $record->description ?? '',
                        'emails'                 => $this->formatEmails($record),
                        'phones'                 => $this->formatPhones($record),
                        'status'                 => $this->mapStatus($record->status),
                        'lead_pipeline_id'       => $this->mapPipeline($record, $departmentId),
                        'lead_pipeline_stage_id' => $mappedStageId,
                        'salutation'             => $this->mapSalutationFromGender($this->mapGenderFromSugar($record->gender_c ?? null)),
                        'first_name'             => $record->first_name,
                        'last_name'              => $record->last_name,
                        'lastname_prefix'        => $record->tussenvoegsel_c ?? '',
                        'married_name'           => $record->meisjesnaam_c ?? '',
                        'married_name_prefix'    => $record->aang_tussenv_c ?? null,
                        'initials'               => $record->voorletters_c ?? '',
                        'date_of_birth'          => $record->birthdate,
                        'gender'                 => $this->mapGenderFromSugar($record->gender_c ?? null),
                        'department_id'          => $departmentId,
                        'lead_channel_id'        => $this->mapChannel($record),
                        'lead_type_id'           => $this->mapType($record),
                        'lead_source_id'         => $this->mapSource($record),
                        'lost_reason'            => $mapLostReason,
                        'user_id'                => $this->mapUser($record),
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
                        $this->infoV('Attached persons ['.implode(', ', $personIdsToAttach).'] to lead ID '.$lead->id);
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
                    } else {
                        $this->infoV('No related persons for lead '.$record->id.'; lead imported without person attachments.');
                    }
                });

                // Import call activities for this lead (outside main transaction)
                try {
                    $callStats = $this->activityImporter->importCallActivities($lead, $callActivities);
                    $callActivitiesImported += $callStats['imported'];
                    $callActivitiesSkipped += $callStats['skipped'];
                } catch (Exception $e) {
                    $this->error("Failed to import call activities for lead {$lead->external_id}: ".$e->getMessage());
                    // Continue with next lead
                }

                // Import emails as Email records for this lead (outside main transaction)
                try {
                    $emailStats = $this->activityImporter->importEmailsAsEmailRecords($lead, $emailActivities);
                    $emailActivitiesImported += $emailStats['imported'];
                    $emailActivitiesSkipped += $emailStats['skipped'];

                    // Import email attachments for imported emails
                    if (! empty($emailStats['email_ids'])) {
                        $attachmentStats = $this->attachmentImporter->importEmailAttachmentsAsEmailAttachments($lead, $emailAttachments, $emailStats['email_ids']);
                        $emailAttachmentsImported += $attachmentStats['imported'];
                        $emailAttachmentsSkipped += $attachmentStats['skipped'];
                    }
                } catch (Exception $e) {
                    $this->error("Failed to import emails/attachments for lead {$lead->external_id}: ".$e->getMessage());
                    // Continue with next lead
                }

                // Import meeting activities for this lead (outside main transaction)
                try {
                    $meetingStats = $this->meetingImporter->importMeetingActivities($lead, $meetingActivities);
                    $meetingActivitiesImported += $meetingStats['imported'];
                    $meetingActivitiesSkipped += $meetingStats['skipped'];
                } catch (Exception $e) {
                    $this->error("Failed to import meeting activities for lead {$lead->external_id}: ".$e->getMessage());
                    // Continue with next lead
                }

                $imported++;
                $this->bar->advance();

            } catch (Exception $e) {
                $errors++;
                $this->error("\nFailed to import lead {$record->id}: ".$e->getMessage());
                Log::error('Failed to import lead', [
                    'record_id' => $record->id ?? 'unknown',
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                ]);
                $this->bar->advance();
            }
        }

        // Accumulate totals across all chunks
        $this->totalImported += $imported;
        $this->totalSkipped += $skipped;
        $this->totalErrors += $errors;
        $this->totalPersonNotFound += $personNotFound;
        $this->totalSkippedAlreadyExisting += $skippedAlreadyExisting;
        $this->totalSkippedNoRelatedPersons += $skippedNoRelatedPersons;
        $this->totalSkippedNotAllPersonsFound += $skippedNotAllPersonsFound;
        $this->totalCallActivitiesImported += $callActivitiesImported;
        $this->totalCallActivitiesSkipped += $callActivitiesSkipped;
        $this->totalEmailActivitiesImported += $emailActivitiesImported;
        $this->totalEmailActivitiesSkipped += $emailActivitiesSkipped;
        $this->totalMeetingActivitiesImported += $meetingActivitiesImported;
        $this->totalMeetingActivitiesSkipped += $meetingActivitiesSkipped;
        $this->totalEmailAttachmentsImported += $emailAttachmentsImported;
        $this->totalEmailAttachmentsSkipped += $emailAttachmentsSkipped;
    }

    /**
     * Print a consolidated import summary across all chunks
     */
    private function printImportSummary(): void
    {
        $this->newLine(2);
        $this->info('Import completed!');
        $this->info("✓ Imported: {$this->totalImported}");
        $this->info("⚠ Skipped: {$this->totalSkipped}");
        $this->info("✗ Person not found: {$this->totalPersonNotFound}");
        $this->info("✗ Errors: {$this->totalErrors}");
        $this->line('');
        $this->info('Call Activities:');
        $this->info("✓ Call activities imported: {$this->totalCallActivitiesImported}");
        $this->info("⚠ Call activities skipped: {$this->totalCallActivitiesSkipped}");

        $this->line('');
        $this->info('Email Activities:');
        $this->info("✓ Email activities imported: {$this->totalEmailActivitiesImported}");
        $this->info("⚠ Email activities skipped: {$this->totalEmailActivitiesSkipped}");

        $this->line('');
        $this->info('Meeting Activities:');
        $this->info("✓ Meeting activities imported: {$this->totalMeetingActivitiesImported}");
        $this->info("⚠ Meeting activities skipped: {$this->totalMeetingActivitiesSkipped}");

        $this->line('');
        $this->info('Email Attachments:');
        $this->info("✓ Email attachments imported: {$this->totalEmailAttachmentsImported}");
        $this->info("⚠ Email attachments skipped: {$this->totalEmailAttachmentsSkipped}");

        // Detailed skip breakdown
        $this->line('');
        $this->info('Skip breakdown:');
        $this->info("- Already existing (external_id present): {$this->totalSkippedAlreadyExisting}");
        $this->info("- No related persons found: {$this->totalSkippedNoRelatedPersons}");
        $this->info("- Not all related persons found: {$this->totalSkippedNotAllPersonsFound}");
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
        if ($this->output->isVerbose()) {
            $this->info('Looking for persons with external_ids: '.implode(', ', $relatedPersonIds));
        }
        $persons = Person::whereIn('external_id', $relatedPersonIds)->get()->all();

        if ($this->output->isVerbose()) {
            $this->info('Found '.count($persons).' persons: '.implode(', ', array_map(fn ($p) => $p->name.' (ext_id: '.$p->external_id.')', $persons)));
        }
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
                'label'      => \App\Enums\ContactLabel::Eigen->value,
                'value'      => $primary,
                'is_default' => true,
            ];
        }

        if ($any && $any !== $primary) {
            $emails[] = [
                'label'      => \App\Enums\ContactLabel::Eigen->value,
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
            [$label, $value] = $this->sanitizePhoneAndInferLabel($record->phone_work, \App\Enums\ContactLabel::Eigen->value);
            if ($value !== '') {
                $phones[] = [
                    'label'      => \App\Enums\ContactLabel::fromOld($label)->value,
                    'value'      => $value,
                    'is_default' => true,
                ];
            }
        }

        if ($record->phone_mobile) {
            [$label, $value] = $this->sanitizePhoneAndInferLabel($record->phone_mobile, \App\Enums\ContactLabel::Eigen->value);
            if ($value !== '') {
                $phones[] = [
                    'label'      => \App\Enums\ContactLabel::fromOld($label)->value,
                    'value'      => $value,
                    'is_default' => empty($phones),
                ];
            }
        }

        if ($record->phone_home) {
            [$label, $value] = $this->sanitizePhoneAndInferLabel($record->phone_home, \App\Enums\ContactLabel::Eigen->value);
            if ($value !== '') {
                $phones[] = [
                    'label'      => \App\Enums\ContactLabel::fromOld($label)->value,
                    'value'      => $value,
                    'is_default' => empty($phones),
                ];
            }
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
    private function mapStage($record, int $pipelineId): int
    {
        // Get workflow status from Sugar CRM
        $workflowStatus = strtolower($record->workflow_status_c ?? 'nieuweaanvraag');
        $leadStatus = strtolower($record->status ?? '');

        // $workflowStatus is afvoeren -> lost
        // status die zijn doorgezet zijn en behandeling zijn.
        // workflow status -> nieuweaanvraag, in behandeling, afgehandeld, dead, recycled, converted

        // Determine the correct first stage based on pipeline
        $firstStageId = $this->getFirstStageForPipeline($pipelineId);

        if ($workflowStatus) {
            if ($workflowStatus == 'nieuweaanvraag') {
                return $firstStageId;
            } elseif ($workflowStatus == 'afvoeren') {
                $lostReason = self::mapLostReason($record->reden_afvoeren_c ?? null);

                return Stage::where('lead_pipeline_id', $pipelineId)
                    ->where('code', 'like', '%lost%')
                    ->firstOrFail()->id;
            } elseif ($workflowStatus == 'verkoopadvies') {
                //                if ($leadStatus === 'in process') {
                return $firstStageId + 1;
            } elseif ($workflowStatus === 'opvolgen') {
                // $leadStatus === 'in process'
                if ($pipelineId === PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value) {
                    return $firstStageId + 2;
                }

                return $firstStageId + 1;
            } elseif ($workflowStatus === 'orderbevestigen') {
                return Stage::where('lead_pipeline_id', $pipelineId)
                    ->where('code', 'like', '%won%')
                    ->firstOrFail()->id;

            } else {
                $this->warn('Unknown workflow status for lead ID '.$record->id.': '.$workflowStatus.'. Defaulting to first stage of pipeline.');
            }
            // TODO wachtakkoord, uitgevoerdrapport
        } elseif (in_array($leadStatus, ['dead', 'recycled'])) {
            // Find lost stage for this pipeline
            return Stage::where('lead_pipeline_id', $pipelineId)
                ->where('code', 'like', '%lost%')
                ->firstOrFail()->id;
        }

        $this->error('Unknown or missing workflow status for lead ID '.$record->id.'. Lead stastus = '.$leadStatus.', workflow status = '.$workflowStatus.'. Defaulting to first stage of pipeline.');

        return $firstStageId;
    }

    /**
     * Get the first stage ID for a given pipeline
     */
    private function getFirstStageForPipeline(int $pipelineId): int
    {
        switch ($pipelineId) {
            case PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value:
                return PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value;
            case PipelineDefaultKeys::PIPELINE_HERNIA_ID->value:
                return PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_HERNIA_ID->value;
            default:
                // For other pipelines, find the first stage
                $firstStage = Stage::where('lead_pipeline_id', $pipelineId)
                    ->orderBy('sort_order')
                    ->first();

                return $firstStage ? $firstStage->id : PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value;
        }
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

    /**
     * @return array [lead_id] => [person_id1, person_id2, ...]
     */
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
        $relations = $sql->get();

        if ($this->output->isVeryVerbose()) {
            $this->info($sql->toRawSql());
        }

        if ($this->output->isVerbose()) {
            $this->info('extractPerson: Found '.$relations->count().' relations');
        }

        // Initialize map for all leads to ensure presence even if no relations exist
        $map = [];
        foreach ($leadIds as $leadId) {
            $map[$leadId] = [];
        }

        // Map lead_id => [person_id1, person_id2, ...] to support multiple persons per lead
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
        if ($this->output->isVeryVerbose()) {
            foreach ($map as $leadId => $personIds) {
                $uniquePersonIds = array_unique($personIds);
                $this->info("Lead {$leadId}: ".count($personIds).' total relations, '.count($uniquePersonIds).' unique persons: '.implode(', ', $uniquePersonIds));
            }
        }

        return $map;
    }

    /**
     * @param  array  $leadByPersons  [lead_id => [person_id1, person_id2, ...]]
     * @return array [lead_id => [person_id => anamnesis_data]]
     *
     * @throws Exception if argument is empty
     */
    private function extractAnamenesis(array $leadByPersons): array
    {
        if (empty($leadByPersons)) {
            throw new Exception('No lead-person relations provided for anamnesis extraction.');
        }

        $connection = $this->option('connection');
        $leadIds = array_keys($leadByPersons);
        $personIdLists = array_values($leadByPersons);
        $nonEmptyPersonLists = array_values(array_filter($personIdLists, fn ($lst) => ! empty($lst)));
        $personIds = ! empty($nonEmptyPersonLists) ? array_merge(...$nonEmptyPersonLists) : [];

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
                    ->when(! empty($personIds), function ($q) use ($personIds) {
                        $q->whereIn('anamnesis_person.pcrm_anamn0b6eontacts_ida', $personIds);
                    })
                    ->when(empty($personIds), function ($q) {
                        // Force empty result when no person IDs are provided
                        $q->whereRaw('1 = 0');
                    });
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

        if ($this->output->isVeryVerbose()) {
            $this->info($sql->toRawSql());
        }
        $relations = $sql->get();

        if ($this->output->isVerbose()) {
            $this->info('extractAnamenesis: Found '.$relations->count().' relations');
        }

        // Initialize result with all leads and their related person ids from leadByPersons
        // so that we also return person mappings even when anamnesis is missing.
        $result = [];
        foreach ($leadByPersons as $leadId => $persons) {
            // Ensure array structure lead_id => [person_id => []]
            foreach ($persons as $personId) {
                $result[$leadId][$personId] = [];
            }
        }

        // Merge in actual anamnesis relations (if any)
        foreach ($relations as $rel) {
            //            if (! isset($result[$rel->lead_id][$rel->person_id][$rel->anamnesis_id])) {
            $result[$rel->lead_id][$rel->person_id][$rel->anamnesis_id] = $rel;
            //            }
        }

        //        $this->info(
        //            'extractAnamenesis: Found '.$relations->count().' relations, returning '.count($result).' unique lead-person-anamnesis mappings. '.
        //            'Unique persons per lead: '.implode(', ', array_map(
        //                fn ($persons) => count($persons),
        //                $result
        //            )).'. '.
        //            'Anamnesis counts per person: '.implode(', ', array_map(
        //                fn ($persons) => implode('|', array_map(fn ($anamneses) => count($anamneses), $persons)),
        //                $result
        //            ))
        //        );
        // $this->info('extractAnamenesis: Detailed mapping: '.json_encode($result));
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
     * @throws Exception when external users are missing
     */
    private function ensureUserImportRan(): void
    {
        User::whereNotNull('external_id')->count() > 0 or throw new Exception('No users with external_id found, please run the user import first');
    }

    /**
     * Map department to appropriate user_id for lead assignment
     */
    private function mapUser($record): ?int
    {
        if (! empty($record->assigned_user_id)) {
            $user = User::where('external_id', $record->assigned_user_id)->first();
            if ($user) {
                return $user->id;
            }
        }

        return null;
    }
}
