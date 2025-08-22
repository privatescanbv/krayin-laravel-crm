<?php

namespace App\Console\Commands;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Models\Anamnesis;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

class ImportLeadsFromSugarCRM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:leads
                            {--connection=sugarcrm : Database connection name}
                            {--limit=100 : Number of records to import}
                            {--dry-run : Show what would be imported without actually importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import leads from SugarCRM database with anamnesis data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info('Starting lead import from SugarCRM...');
        $this->info("Connection: {$connection}");
        $this->info("Limit: {$limit}");
        $this->info('Dry run: '.($dryRun ? 'Yes' : 'No'));

        try {
            // Test connection
            $this->info('Testing database connection...');
            DB::connection($connection)->getPdo();
            $this->info('✓ Database connection successful');

            // Get records from SugarCRM
            $records = DB::connection($connection)
                ->table('leads as l')
                ->join('leads_cstm as lc', 'l.id', '=', 'lc.id_c')
                ->leftJoin('email_addr_bean_rel as eabr', function ($join) {
                    $join->on('eabr.bean_id', '=', 'l.id')
                        ->where('eabr.bean_module', '=', 'Leads')
                        ->where('eabr.deleted', '=', 0)
                        ->where('eabr.primary_address', '=', 1);
                })
                ->leftJoin('email_addresses as ea', function ($join) {
                    $join->on('ea.id', '=', 'eabr.email_address_id')
                        ->where('ea.deleted', '=', 0);
                })
                ->select([
                    'l.*',
                    'lc.gender_c',
                    'lc.hart_erfelijk_c',
                    'lc.vaat_erfelijk_c',
                    'lc.tumoren_erfelijk_c',
                    'lc.smoking_c',
                    'lc.heart_problems_c',
                    'lc.rugklachten_c',
                    'lc.diabetes_c',
                    'lc.spijverteringsklachten_c',
                    'lc.risico_hartinfarct_c',
                    'lc.workflow_status_c',
                    'lc.kanaal_c',
                    'lc.soort_aanvraag_c',
                    'lc.meisjesnaam_c',
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
                    'lc.dormicum_c',
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
                    'lc.huisnummer_c',
                    'lc.huisnr_toevoeging_c',
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
                    'lc.opm_operaties_c',
                    'lc.opm_advies_c',
                    'lc.ms_sinds_jaar_c',
                    'lc.rolstoel_c',
                    'lc.tillift_c',
                    'lc.metformine_c',
                    'lc.jodiumallergie_c',
                    'lc.nierproblemen_c',
                    'lc.schildklierprobl_c',
                    'lc.stents_c',
                    'lc.spasmen_c',
                    'lc.bloedverdunners_c',
                    'lc.opm_bloedverdunners_c',
                    'lc.decubitus_c',
                    'lc.opm_decubitus_c',
                    'lc.allergie_km_c',
                    'lc.narcose_problemen_c',
                    'lc.opm_metalen_c',
                    'lc.opm_medicijnen_c',
                    'lc.opm_glaucoom_c',
                    'lc.opm_erf_hart_c',
                    'lc.opm_erf_vaat_c',
                    'lc.opm_erf_tumoren_c',
                    'lc.opm_rugklachten_c',
                    'lc.opm_hartklachten_c',
                    'lc.opm_roken_c',
                    'lc.opm_diabetes_c',
                    'lc.opm_spijsverterering_c',
                    'ea.email_address as email',
                ])
                ->where('l.deleted', 0)
                ->orderBy('l.date_entered', 'desc') // Nieuwste eerst
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
            Log::error('SugarCRM lead import failed', [
                'error'      => $e->getMessage(),
                'connection' => $connection,
            ]);

            return 1;
        }
    }

    /**
     * Show dry run results
     */
    private function showDryRunResults($records): void
    {
        $this->info("\n=== DRY RUN RESULTS ===");

        $headers = ['External ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Status', 'Workflow Status', 'Stage ID', 'Department', 'Channel', 'Type', 'Source', 'Date Entered', 'Person Match'];
        $rows = [];

        foreach ($records as $record) {
            // Try to find matching person
            $person = $this->findMatchingPerson($record);
            $personMatch = $person ? "✓ {$person->name}" : '✗ Not found';

            $rows[] = [
                $record->id ?? 'N/A',
                $record->first_name ?? 'N/A',
                $record->last_name ?? 'N/A',
                $this->extractEmail($record) ?? 'N/A',
                $record->phone_work ?? 'N/A',
                $record->status ?? 'N/A',
                $record->workflow_status_c ?? 'N/A',
                $this->mapStage($record),
                $this->mapChannel($record),
                $this->mapType($record),
                $this->mapSource($record),
                $record->date_entered ?? 'N/A',
                $personMatch,
            ];
        }

        $this->table($headers, $rows);
        $this->info('Would import '.count($rows).' leads');
    }

    /**
     * Import records
     */
    private function importRecords($records): void
    {
        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $personNotFound = 0;

        foreach ($records as $record) {
            try {
                // Check if lead already exists by external_id
                $existingLead = Lead::where('external_id', $record->id)->first();
                if ($existingLead) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // Find matching person
                $person = $this->findMatchingPerson($record);
                if (! $person) {
                    $personNotFound++;
                    $this->error("\nPerson not found for lead: {$record->first_name} {$record->last_name} (ID: {$record->id})");
                    $bar->advance();

                    continue;
                }

                // Debug: Check person data
                $this->info("Creating lead for person: {$person->name} (ID: {$person->id})");

                // Create lead
                $lead = Lead::create([
                    'title'                  => $record->first_name.' '.$record->last_name,
                    'external_id'            => $record->id,
                    'description'            => $record->description ?? '',
                    'emails'                 => $this->formatEmails($record),
                    'phones'                 => $this->formatPhones($record),
                    'lead_value'             => 0,
                    'status'                 => $this->mapStatus($record->status),
                    // person_id removed - now using many-to-many relationship
                    'lead_pipeline_id'       => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
                    'lead_pipeline_stage_id' => $this->mapStage($record),
                    'salutation'             => $record->salutation,
                    'first_name'             => $record->first_name,
                    'last_name'              => $record->last_name,
                    'lastname_prefix'        => $record->tussenvoegsel_c ?? '',
                    'married_name'           => $record->meisjesnaam_c ?? '',
                    'initials'               => $record->voorletters_c ?? '',
                    'date_of_birth'          => $record->birthdate,
                    'gender'                 => $record->gender_c,
                    // skip departement mapping for now, will be done by business rules later
                    'lead_channel_id'        => $this->mapChannel($record),
                    'lead_type_id'           => $this->mapType($record),
                    'lead_source_id'         => $this->mapSource($record),
                    'created_at'             => $record->date_entered ?? now(),
                    'updated_at'             => $record->date_modified ?? now(),
                ]);

                // Attach person to lead using new many-to-many relationship
                if ($person && $person->id) {
                    $lead->attachPersons([$person->id]);
                }

                // Create anamnesis data
                $this->createAnamnesis($lead, $record);

                // Debug: Check if lead has persons relation
                $this->info('Lead created with person attached: '.($lead->persons->count() > 0 ? $lead->persons->first()->name : 'NONE'));

                $imported++;
                $bar->advance();

            } catch (Exception $e) {
                $errors++;
                Log::error('Failed to import lead', [
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
        $this->info("✗ Person not found: {$personNotFound}");
        $this->info("✗ Errors: {$errors}");
    }

    /**
     * Find matching person by email, phone or name
     */
    private function findMatchingPerson($record)
    {
        $email = $this->extractEmail($record);

        // First try to match by email
        if ($email) {
            $person = Person::where('emails', 'like', '%'.$email.'%')->first();
            if ($person) {
                return $person;
            }
        }

        // Then try to match by phone
        if ($record->phone_work) {
            $person = Person::where('phones', 'like', '%'.$record->phone_work.'%')
                ->orWhere('contact_numbers', 'like', '%'.$record->phone_work.'%')
                ->first();
            if ($person) {
                return $person;
            }
        }

        // Finally try to match by name
        if ($record->first_name && $record->last_name) {
            $person = Person::where('first_name', $record->first_name)
                ->where('last_name', $record->last_name)
                ->first();
            if ($person) {
                return $person;
            }
        }

        return null;
    }

    /**
     * Extract email from record (could be in different fields)
     */
    private function extractEmail($record)
    {
        // Check if there's an email field or we need to join with email tables
        return $record->email ?? null;
    }

    /**
     * Format emails for Lead model
     */
    private function formatEmails($record): array
    {
        $emails = [];
        $email = $this->extractEmail($record);

        if ($email) {
            $emails[] = [
                'label'      => 'work',
                'value'      => $email,
                'is_default' => true,
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
     * Create anamnesis data for the lead
     */
    private function createAnamnesis($lead, $record): Anamnesis
    {
        return Anamnesis::create([
            'id'                        => $record->id, // Use SugarCRM ID
            'name'                      => $lead->title,
            'description'               => $record->anamnese_c ?? '',
            'lead_id'                   => $lead->id,
            'height'                    => $record->lengte_c,
            'weight'                    => $record->gewicht_c,
            'metals'                    => (bool) $record->metalen_c,
            'metals_notes'              => $record->opm_metalen_c,
            'medications'               => (bool) $record->medicijnen_c,
            'medications_notes'         => $record->opm_medicijnen_c,
            'glaucoma'                  => (bool) $record->glaucoom_c,
            'glaucoma_notes'            => $record->opm_glaucoom_c,
            'claustrophobia'            => (bool) $record->claustrofobie_c,
            'dormicum'                  => (bool) $record->dormicum_c,
            'heart_surgery'             => (bool) $record->hart_operatie_c,
            'heart_surgery_notes'       => $record->opm_hart_operatie_c,
            'implant'                   => (bool) $record->implantaat_c,
            'implant_notes'             => $record->opm_implantaat_c,
            'surgeries'                 => (bool) $record->operaties_c,
            'surgeries_notes'           => $record->opm_operaties_c,
            'remarks'                   => $record->opmerking_c,
            'hereditary_heart'          => (bool) $record->hart_erfelijk_c,
            'hereditary_heart_notes'    => $record->opm_erf_hart_c,
            'hereditary_vascular'       => (bool) $record->vaat_erfelijk_c,
            'hereditary_vascular_notes' => $record->opm_erf_vaat_c,
            'hereditary_tumors'         => (bool) $record->tumoren_erfelijk_c,
            'hereditary_tumors_notes'   => $record->opm_erf_tumoren_c,
            'allergies'                 => (bool) $record->allergie_c,
            'allergies_notes'           => $record->opm_allergie_c,
            'back_problems'             => (bool) $record->rugklachten_c,
            'back_problems_notes'       => $record->opm_rugklachten_c,
            'heart_problems'            => (bool) $record->heart_problems_c,
            'heart_problems_notes'      => $record->opm_hartklachten_c,
            'smoking'                   => (bool) $record->smoking_c,
            'smoking_notes'             => $record->opm_roken_c,
            'diabetes'                  => (bool) $record->diabetes_c,
            'diabetes_notes'            => $record->opm_diabetes_c,
            'digestive_problems'        => (bool) $record->spijverteringsklachten_c,
            'digestive_problems_notes'  => $record->opm_spijsverterering_c,
            'heart_attack_risk'         => $record->risico_hartinfarct_c,
            'advice_notes'              => $record->opm_advies_c,
            'active'                    => true,
            'created_at'                => $record->date_entered ?? now(),
            'updated_at'                => $record->date_modified ?? now(),
        ]);
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
}
