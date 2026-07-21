<?php

namespace App\Console\Commands;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\ContactLabel;
use App\Enums\Departments;
use App\Models\Department;
use App\Models\LeadAiFeedback;
use App\Models\LeadAiSummaryGeneration;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Ai\AiPromptConfig;
use App\Services\Ai\LeadAiContextService;
use App\Services\Ai\LeadAiSummaryService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\User;

/**
 * Builds a fixed, deliberately rich lead scenario (long history, heavy email
 * traffic, an explicit AI-feedback correction and a couple of "does the
 * model track recency correctly" traps) and runs it through the real
 * lead-summary pipeline (LeadAiContextService + LeadAiSummaryService) so the
 * actual prompt, payload size, token usage and LLM output can be reviewed by
 * hand. All synthetic records are created inside a transaction that is
 * always rolled back; nothing is left behind in the database.
 */
class TestLeadAiSummaryPrompt extends Command
{
    /** Gemeten op deze Nederlandse JSON-payload met de qwen-tokenizer; bytes/4 was ~75% te optimistisch. */
    private const BYTES_PER_TOKEN = 2.3;

    private const LOCK_KEY = 'ai:lead-summary:test';

    /** Ruim boven de langste LLM-timeout, zodat een trage run zijn eigen lock niet verliest. */
    private const LOCK_SECONDS = 1800;

    protected $signature = 'ai:lead-summary:test
        {--activities=30 : Aantal activiteiten in de synthetische case}
        {--emails=20 : Aantal e-mails in de synthetische case}
        {--historical-leads=5 : Aantal eerdere leads van dezelfde patient}
        {--skip-llm : Bouw en toon alleen de payload, roep de LLM niet aan}';

    protected $description = 'Bouw een vaste, uitgebreide lead-case en test de echte AI-samenvattingsprompt erop (payload, tokens, ruwe LLM-output), zonder iets in de database achter te laten.';

    private array $filler = [];

    public function handle(LeadAiContextService $contextService, LeadAiSummaryService $summaryService): int
    {
        $historicalLeadsCount = max(0, (int) $this->option('historical-leads'));
        $activityCount = max(0, (int) $this->option('activities'));
        $emailCount = max(0, (int) $this->option('emails'));

        // The synthetic case lives inside a transaction that stays open across the whole
        // LLM call, so two runs at once deadlock on the shared parent rows and die with
        // "Lock wait timeout exceeded" after 50s. Fail fast instead.
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->error('Er draait al een ai:lead-summary:test. Wacht tot die klaar is; gelijktijdig draaien geeft database lock timeouts.');
            $this->line('Draait er niets meer? Dan is een run afgebroken zonder zijn lock vrij te geven:');
            $this->line('  php artisan tinker --execute=\'Cache::lock("'.self::LOCK_KEY.'")->forceRelease();\'');

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $this->info('Synthetische case opbouwen (patient met veel historie en e-mailverkeer)...');

            $scenario = $this->buildScenario($historicalLeadsCount, $activityCount, $emailCount);
            $lead = $scenario['lead'];

            $context = $contextService->build($lead);
            $payload = $contextService->forLlm($context);
            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $compactJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $this->printPayloadStats($scenario, $context, $compactJson);

            $reportLines = [];
            $reportLines[] = "# AI lead-summary prompt test\n";
            $reportLines[] = 'Gegenereerd op: '.now()->toIso8601String()."\n";
            $reportLines[] = "## System prompt (config/ai_prompts.php -> lead_summary)\n";
            $reportLines[] = '```';
            $reportLines[] = (string) AiPromptConfig::prompt('lead_summary');
            $reportLines[] = '```';
            $reportLines[] = "\n## User payload (exact JSON dat naar de LLM gaat)\n";
            $reportLines[] = '```json';
            $reportLines[] = $payloadJson;
            $reportLines[] = '```';

            if ($this->option('skip-llm')) {
                $this->warn('--skip-llm: LLM wordt niet aangeroepen, alleen de payload is getoond.');
                $this->writeReport($reportLines);

                return self::SUCCESS;
            }

            $this->info('LLM aanroepen via de echte LeadAiSummaryService::generate() ...');

            try {
                $summary = $summaryService->generate($lead, 'manual-test-command');
            } catch (Throwable $exception) {
                $this->error('Generatie gooide een exception: '.$exception::class.' - '.$exception->getMessage());
                $this->writeReport($reportLines);

                return self::FAILURE;
            }

            $generation = LeadAiSummaryGeneration::query()
                ->where('lead_id', $lead->id)
                ->latest('id')
                ->first();

            $reportLines[] = "\n## Resultaat\n";
            $reportLines[] = '- Status: '.$summary->status;
            $reportLines[] = '- Model: '.$summary->model;
            $reportLines[] = '- Prompt version: '.$summary->prompt_version;

            if ($generation) {
                $reportLines[] = '- Tokens input (API): '.($generation->tokens_input ?? 'onbekend');
                $reportLines[] = '- Tokens output (API): '.($generation->tokens_output ?? 'onbekend');
                $reportLines[] = '- Duration: '.($generation->duration_ms ?? '?').' ms';
            }

            if ($summary->status === 'failed') {
                $reportLines[] = '- Fout: '.$summary->last_error;
            } else {
                $reportLines[] = "\n### Samenvatting\n".$summary->summary;
                $reportLines[] = "\n### Volgende actie\n".($summary->next_action_title ?: '(geen)')
                    .' — '.($summary->next_action_reason ?: '')
                    .' (prioriteit: '.($summary->priority ?? '-').')';
                $reportLines[] = "\n### Highlights\n".json_encode($summary->highlights, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $reportLines[] = "\n### Aandachtspunten\n".json_encode($summary->attention_points, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }

            if ($generation?->raw_response) {
                $reportLines[] = "\n## Ruwe LLM-response\n```\n".$generation->raw_response."\n```";
            }

            $this->printResult($summary, $generation);
            $reportPath = $this->writeReport($reportLines);

            $this->line('');
            $this->info("Volledig rapport (prompt + payload + ruwe response): {$reportPath}");

            return $summary->status === 'completed' ? self::SUCCESS : self::FAILURE;
        } finally {
            DB::rollBack();
            $lock->release();
            $this->line('');
            $this->comment('Alle synthetische testdata is teruggedraaid (transaction rollback), er blijft niets achter in de database.');
        }
    }

    /**
     * @return array{lead: Lead, historical_leads: int, activities_created: int, emails_created: int}
     */
    private function buildScenario(int $historicalLeadsCount, int $activityCount, int $emailCount): array
    {
        $user = User::query()->first() ?? User::factory()->create();
        $source = Source::first() ?? Source::create(['name' => 'Website']);
        $type = Type::first() ?? Type::create(['name' => 'Nieuwe lead']);
        $department = Department::where('name', Departments::PRIVATESCAN->value)->first()
            ?? Department::create(['name' => Departments::PRIVATESCAN->value]);

        $pipeline = Pipeline::first() ?? Pipeline::create([
            'name'        => 'Default Pipeline',
            'is_default'  => 1,
            'rotten_days' => 30,
        ]);

        $openStage = Stage::create([
            'name'             => 'In behandeling (test)',
            'code'             => 'test-open-'.uniqid(),
            'lead_pipeline_id' => $pipeline->id,
            'sort_order'       => 90,
            'is_won'           => false,
            'is_lost'          => false,
        ]);

        $wonStage = Stage::create([
            'name'             => 'Gewonnen (test)',
            'code'             => 'test-won-'.uniqid(),
            'lead_pipeline_id' => $pipeline->id,
            'sort_order'       => 91,
            'is_won'           => true,
            'is_lost'          => false,
        ]);

        $organization = Organization::factory()->create(['name' => 'Huisartsenpraktijk De Linde']);

        $person = Person::factory()->create([
            'first_name' => 'Emma',
            'last_name'  => 'de Groot',
            'job_title'  => null,
            'emails'     => [['value' => 'emma.degroot@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
            'phones'     => [['value' => '+31612345678', 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
        ]);
        $person->organization()->associate($organization);
        $person->save();

        $contactFields = [
            'first_name'         => 'Emma',
            'last_name'          => 'de Groot',
            'user_id'            => $user->id,
            'lead_source_id'     => $source->id,
            'lead_type_id'       => $type->id,
            'lead_pipeline_id'   => $pipeline->id,
            'department_id'      => $department->id,
            'organization_id'    => $organization->id,
            'contact_person_id'  => $person->id,
            'emails'             => [['value' => 'emma.degroot@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
            'phones'             => [['value' => '+31612345678', 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
        ];

        // Historical eras: oldest first. Each era gets its own lead + sales lead + order,
        // closed and won, spread out over the last ~16 months.
        $eras = [
            ['months_ago' => 16, 'desc' => 'Eerste MRI knie rechts na sportblessure (hardlopen). Traject volledig doorlopen, scan gemaakt en uitslag met patiente besproken door de radioloog. Patiente was tevreden over de snelheid van het traject.'],
            ['months_ago' => 9, 'desc' => 'Controle-MRI knie rechts op verzoek van orthopeed ter opvolging van eerdere bevindingen. Scan afgerond, verslag naar verwijzend huisarts gestuurd. Geen bijzonderheden bij de intake.'],
            ['months_ago' => 5, 'desc' => 'Informatieve aanvraag over MRI schouder links, patiente heeft uiteindelijk niet doorgezet omdat klachten vanzelf verminderden.'],
            ['months_ago' => 3, 'desc' => 'Aanvraag MRI onderrug in verband met terugkerende lage rugpijn. Traject afgerond, scan getoond geen afwijkingen van betekenis.'],
            ['months_ago' => 2, 'desc' => 'Kort telefonisch contact over vergoeding vanuit aanvullende verzekering voor een eerder onderzoek. Vraag is toen naar tevredenheid afgehandeld.'],
        ];

        $historicalLeads = [];
        $historicalSalesLeads = [];
        $historicalOrders = [];

        for ($i = 0; $i < $historicalLeadsCount; $i++) {
            $era = $eras[$i % count($eras)];
            $closedAt = now()->copy()->subMonths($era['months_ago']);

            $historicalLead = Lead::factory()->create(array_merge($contactFields, [
                'lead_pipeline_stage_id' => $wonStage->id,
                'description'            => $era['desc'],
                'status'                 => false,
                'closed_at'              => $closedAt,
            ]));
            $historicalLead->persons()->attach($person->id);
            $this->backdate($historicalLead, $closedAt->copy()->subDays(10));
            $historicalLeads[] = $historicalLead;

            $salesLead = SalesLead::factory()->create([
                'lead_id'           => $historicalLead->id,
                'user_id'           => $user->id,
                'department_id'     => $department->id,
                'contact_person_id' => $person->id,
                'name'              => 'Onderzoekstraject: '.$era['desc'],
                'description'       => $era['desc'],
                'pipeline_stage_id' => $wonStage->id,
                'closed_at'         => $closedAt,
            ]);
            $this->backdate($salesLead, $closedAt->copy()->subDays(9));
            $historicalSalesLeads[] = $salesLead;

            $order = Order::factory()->create([
                'sales_lead_id'         => $salesLead->id,
                'user_id'               => $user->id,
                'pipeline_stage_id'     => $wonStage->id,
                'invoice_number'        => sprintf('F%s-%04d', $closedAt->format('Y'), 1000 + $i),
                'title'                 => $era['desc'],
                'total_price'           => 425.00 + ($i * 65),
                'first_examination_at'  => $closedAt->copy()->subDays(3),
                'closed_at'             => $closedAt,
            ]);
            $this->backdate($order, $closedAt->copy()->subDays(9));
            $historicalOrders[] = $order;
        }

        // Current, open thread: a follow-up consult that is still being scheduled.
        $currentLead = Lead::factory()->create(array_merge($contactFields, [
            'lead_pipeline_stage_id' => $openStage->id,
            'description'            => 'Vervolgconsult aangevraagd na eerdere kniescan; patiente twijfelt aanvankelijk over vervolgtraject en wacht op akkoord van de zorgverzekeraar voor het vervolgonderzoek.',
            'status'                 => true,
            'closed_at'              => null,
        ]));
        $currentLead->persons()->attach($person->id);
        $this->backdate($currentLead, now()->copy()->subWeeks(8));

        $currentSalesLead = SalesLead::factory()->create([
            'lead_id'           => $currentLead->id,
            'user_id'           => $user->id,
            'department_id'     => $department->id,
            'contact_person_id' => $person->id,
            'name'              => 'Vervolgonderzoek knie rechts - nog in te plannen',
            'description'       => 'Vervolgonderzoek in afwachting van planning; wacht op akkoord verzekeraar, inmiddels binnen.',
            'pipeline_stage_id' => $openStage->id,
            'closed_at'         => null,
        ]);
        $this->backdate($currentSalesLead, now()->copy()->subWeeks(8));

        $currentOrder = Order::factory()->create([
            'sales_lead_id'         => $currentSalesLead->id,
            'user_id'               => $user->id,
            'pipeline_stage_id'     => $openStage->id,
            'title'                 => 'Vervolgonderzoek knie rechts (nog in te plannen)',
            'total_price'           => 0.0,
            'first_examination_at'  => null,
            'closed_at'             => null,
        ]);
        $this->backdate($currentOrder, now()->copy()->subWeeks(8));

        $activitiesCreated = $this->seedActivities(
            $currentLead,
            $currentSalesLead,
            $currentOrder,
            $historicalLeads,
            $historicalSalesLeads,
            $historicalOrders,
            $activityCount,
        );

        $emailsCreated = $this->seedEmails(
            $currentLead,
            $currentSalesLead,
            $historicalLeads,
            $emailCount,
        );

        $this->seedFeedback($currentLead);

        return [
            'lead'                => $currentLead,
            'historical_leads'    => count($historicalLeads),
            'activities_created'  => $activitiesCreated,
            'emails_created'      => $emailsCreated,
        ];
    }

    /**
     * @param  array<int, Lead>  $historicalLeads
     * @param  array<int, SalesLead>  $historicalSalesLeads
     * @param  array<int, Order>  $historicalOrders
     */
    private function seedActivities(
        Lead $currentLead,
        SalesLead $currentSalesLead,
        Order $currentOrder,
        array $historicalLeads,
        array $historicalSalesLeads,
        array $historicalOrders,
        int $totalCount,
    ): int {
        $created = 0;

        // A handful of routine activities scattered through the historical eras,
        // so the "historische data" isn't just bare lead/order records.
        foreach ($historicalLeads as $index => $historicalLead) {
            $anchor = $historicalOrders[$index]->closed_at ?? now()->copy()->subMonths(12);

            $created += $this->makeActivity($historicalLead->id, null, null, ActivityType::CALL, 'done',
                Carbon::parse($anchor)->subDays(12),
                'Patiente gebeld om afspraak te bevestigen en intakevragen door te nemen.');

            $created += $this->makeActivity($historicalLead->id, $historicalSalesLeads[$index]->id, $historicalOrders[$index]->id, ActivityType::NOTE, 'done',
                Carbon::parse($anchor)->addDays(1),
                'Scan uitgevoerd, uitslag besproken met patiente, geen bijzonderheden gemeld.');
        }

        // High-signal activities on the CURRENT, open thread. These are the ones that
        // should actually drive next_action/priority/attention_points, and they
        // deliberately include a recency trap: an earlier hesitation that is later
        // reversed, and an insurance rejection that is later overturned.
        $signals = [
            [-56, ActivityType::CALL, 'done', 'Patiente gebeld over vervolgonderzoek; geeft aan te twijfelen vanwege angst voor de MRI-scanner (claustrofobie), wil er nog over nadenken.'],
            [-49, ActivityType::TASK, 'done', 'Aanvraag vervolgvergoeding ingediend bij zorgverzekeraar voor vervolgonderzoek knie rechts.'],
            [-35, ActivityType::NOTE, 'done', 'Navraag bij verzekeraar: aanvraag is afgewezen wegens onvoldoende medische noodzaak, patiente hierover geinformeerd.'],
            [-30, ActivityType::CALL, 'done', 'Patiente teleurgesteld over afwijzing, gevraagd of bezwaar mogelijk is. Bezwaarprocedure gestart namens patiente.'],
            [-14, ActivityType::TASK, 'done', 'Bezwaar bij verzekeraar is toegewezen, akkoord voor vervolgonderzoek is binnen. Patiente hierover nog niet geinformeerd.'],
            [-10, ActivityType::CALL, 'done', 'Patiente gebeld met akkoord van verzekeraar. Patiente geeft nu aan toch door te willen gaan met het vervolgonderzoek, wil wel een rustige tijdssloot en uitleg vooraf i.v.m. eerder genoemde spanning voor de scanner.'],
            [-3, ActivityType::TASK, 'active', 'Actie: planning benaderen voor rustig tijdssloot vervolgonderzoek knie rechts, patiente heeft groen licht gegeven.'],
        ];

        foreach ($signals as [$daysOffset, $type, $status, $comment]) {
            $created += $this->makeActivity(
                $currentLead->id,
                $currentSalesLead->id,
                $currentOrder->id,
                $type,
                $status,
                now()->copy()->addDays($daysOffset),
                $comment,
            );
        }

        // Fill up to the requested total with lower-signal, routine filler activities
        // spread across the last ~8 weeks, so the payload realistically approaches
        // the configured activity_limit.
        $fillerTemplates = [
            'Automatische herinnering verstuurd voor openstaande actie rond vervolgonderzoek.',
            'Kort contactmoment: patiente bereikbaarheid gecheckt voor terugbelmoment.',
            'Interne notitie: dossier gecontroleerd op volledigheid voor planning.',
            'Telefonisch geen gehoor gekregen, voicemail ingesproken met verzoek terug te bellen.',
            'Afspraakherinnering ingepland te versturen richting patiente.',
            'Interne afstemming met collega over vervolgtraject van deze patiente.',
        ];

        $fillerIndex = 0;

        while ($created < $totalCount) {
            $daysOffset = -1 * (($fillerIndex % 55) + 1);
            $template = $fillerTemplates[$fillerIndex % count($fillerTemplates)];

            $created += $this->makeActivity(
                $currentLead->id,
                $currentSalesLead->id,
                null,
                ActivityType::NOTE,
                'done',
                now()->copy()->addDays($daysOffset),
                $template.' (dag '.abs($daysOffset).')',
            );

            $fillerIndex++;
        }

        return $created;
    }

    private function makeActivity(
        int $leadId,
        ?int $salesLeadId,
        ?int $orderId,
        ActivityType $type,
        string $statusKey,
        CarbonInterface $at,
        string $comment,
    ): int {
        $isDone = $statusKey === 'done';

        $activity = Activity::create([
            'type'          => $type->value,
            'title'         => $comment,
            'comment'       => $comment,
            'is_done'       => $isDone,
            'status'        => $isDone ? ActivityStatus::DONE->value : ActivityStatus::ACTIVE->value,
            'schedule_from' => $isDone ? null : $at,
            'completed_at'  => $isDone ? $at : null,
            'lead_id'       => $leadId,
            'sales_lead_id' => $salesLeadId,
            'order_id'      => $orderId,
        ]);

        $this->backdate($activity, $at);

        return 1;
    }

    /**
     * @param  array<int, Lead>  $historicalLeads
     */
    private function seedEmails(Lead $currentLead, SalesLead $currentSalesLead, array $historicalLeads, int $totalCount): int
    {
        $created = 0;
        $patient = ['name' => 'Emma de Groot', 'email' => 'emma.degroot@example.com'];
        $staff = ['name' => 'PrivateScan Klantenservice', 'email' => 'planning@privatescan.nl'];

        foreach ($historicalLeads as $historicalLead) {
            $created += $this->makeEmail($historicalLead->id, null, $staff,
                now()->copy()->subMonths(10), 'Bevestiging afspraak MRI-onderzoek',
                'Beste mevrouw De Groot, hierbij bevestigen wij uw afspraak voor het MRI-onderzoek. Wij verzoeken u 15 minuten van tevoren aanwezig te zijn. Met vriendelijke groet, PrivateScan.');
        }

        // High-signal emails mirroring/expanding the activity trail above: an
        // explicit rejection followed later by an explicit reversal, so we can
        // check whether the model correctly favours the most recent state.
        $signals = [
            [-36, $staff, 'patient', 'Uitslag aanvraag vervolgvergoeding',
                'Beste mevrouw De Groot, helaas heeft uw zorgverzekeraar de aanvraag voor het vervolgonderzoek afgewezen wegens onvoldoende medische noodzaak. Wij kunnen namens u bezwaar aantekenen indien gewenst.'],
            [-34, $patient, 'staff', 'RE: Uitslag aanvraag vervolgvergoeding',
                'Wat vervelend om te horen. Ja graag, wil jullie vragen om bezwaar te maken. Ik heb echt behoefte aan dit vervolgonderzoek.'],
            [-13, $staff, 'patient', 'Update bezwaar zorgverzekeraar: goedgekeurd',
                'Beste mevrouw De Groot, goed nieuws: na ons bezwaar heeft uw zorgverzekeraar alsnog akkoord gegeven voor het vervolgonderzoek. We nemen binnenkort contact op om een afspraak in te plannen.'],
            [-9, $patient, 'staff', 'RE: Update bezwaar zorgverzekeraar: goedgekeurd',
                'Fijn om te horen! Ik wil inderdaad graag door laten gaan. Ik ben wel wat gespannen voor de scanner, dus als er een rustig moment mogelijk is met wat extra uitleg vooraf zou ik dat fijn vinden.'],
            [-2, $patient, 'staff', 'Vraag over planning vervolgonderzoek',
                'Ik hoor nog niets over een datum voor het vervolgonderzoek, kunnen jullie me laten weten wanneer dit gepland kan worden?'],
        ];

        foreach ($signals as [$daysOffset, $from, $direction, $subject, $body]) {
            $created += $this->makeEmail(
                $currentLead->id,
                $currentSalesLead->id,
                $from,
                now()->copy()->addDays($daysOffset),
                $subject,
                $body,
            );
        }

        $fillerSubjects = [
            'Herinnering: openstaande vraag over planning',
            'Bevestiging ontvangst bericht',
            'Automatische statusupdate dossier',
            'Vraag over bereikbaarheid voor terugbelafspraak',
        ];
        $fillerBody = 'Dit is een routinematig bericht in het kader van het lopende traject rond het vervolgonderzoek. Geen actie vereist tenzij anders aangegeven.';

        $fillerIndex = 0;

        while ($created < $totalCount) {
            $daysOffset = -1 * (($fillerIndex % 55) + 1);

            $created += $this->makeEmail(
                $currentLead->id,
                $currentSalesLead->id,
                $fillerIndex % 2 === 0 ? $staff : $patient,
                now()->copy()->addDays($daysOffset),
                $fillerSubjects[$fillerIndex % count($fillerSubjects)].' ('.abs($daysOffset).')',
                $fillerBody,
            );

            $fillerIndex++;
        }

        return $created;
    }

    /**
     * @param  array{name: string, email: string}  $from
     */
    private function makeEmail(int $leadId, ?int $salesLeadId, array $from, CarbonInterface $at, string $subject, string $body): int
    {
        $email = Email::create([
            'lead_id'       => $leadId,
            'sales_lead_id' => $salesLeadId,
            'subject'       => $subject,
            'from'          => $from,
            'reply'         => $body,
        ]);

        $this->backdate($email, $at);

        return 1;
    }

    private function seedFeedback(Lead $currentLead): void
    {
        $feedback = LeadAiFeedback::create([
            'lead_id'   => $currentLead->id,
            'user_id'   => User::query()->first()?->id ?? User::factory()->create()->id,
            'feedback'  => 'Correctie: in een oud dossier stond genoteerd dat patiente allergisch zou zijn voor jodiumhoudende contrastvloeistof. Dit is een verwisseling met een andere patiente en inmiddels door de radioloog weerlegd. Er is GEEN contrastallergie bij deze patiente.',
            'is_active' => true,
        ]);

        $this->backdate($feedback, now()->copy()->subDays(4));
    }

    private function backdate(mixed $model, CarbonInterface $at): void
    {
        $model->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
    }

    /**
     * @param  array{lead: Lead, historical_leads: int, activities_created: int, emails_created: int}  $scenario
     * @param  array<string, mixed>  $context
     */
    private function printPayloadStats(array $scenario, array $context, string $compactJson): void
    {
        $systemPrompt = (string) AiPromptConfig::prompt('lead_summary');
        $bytes = strlen($compactJson);
        $activityLimit = (int) config('services.llm.lead_summary.activity_limit', 12);
        $emailLimit = (int) config('services.llm.lead_summary.email_limit', 6);
        $ctxWindow = 8192;
        $payload = json_decode($compactJson, true) ?: [];

        // The system prompt counts against the window too, so measure the whole request.
        $exactTokens = $this->countTokens($systemPrompt.$compactJson);
        $tokens = $exactTokens ?? (int) round(($bytes + strlen($systemPrompt)) / self::BYTES_PER_TOKEN);
        $tokenLabel = $exactTokens !== null
            ? 'Tokens in request (server /tokenize)'
            : 'Geschat aantal tokens (bytes/'.self::BYTES_PER_TOKEN.')';

        $this->line('');
        $this->info('=== Payload statistieken ===');
        $this->table(['Metric', 'Waarde'], [
            ['Lead ID (huidig)', $scenario['lead']->id],
            ['Historische leads aangemaakt', $scenario['historical_leads']],
            ['Activiteiten aangemaakt', $scenario['activities_created']],
            ['E-mails aangemaakt', $scenario['emails_created']],
            ['Timeline-items in LLM-payload', count($payload['timeline'] ?? [])],
            ['History-items in LLM-payload', count($payload['history'] ?? [])],
            ['Feedback-items in LLM-payload', count($payload['feedback'] ?? [])],
            ['Activity-selectielimiet (config)', $activityLimit],
            ['E-mail-selectielimiet (config)', $emailLimit],
            ['Interne sources-catalogus (niet naar LLM)', count($context['sources'] ?? [])],
            ['System prompt (bytes)', number_format(strlen($systemPrompt), 0, ',', '.')],
            ['User payload (bytes)', number_format($bytes, 0, ',', '.')],
            [$tokenLabel, number_format($tokens, 0, ',', '.')],
            ['Model context window (server --ctx-size)', $ctxWindow],
            ['% van context window', round(($tokens / $ctxWindow) * 100, 1).'%'],
        ]);

        if ($tokens > $ctxWindow) {
            $this->error(
                'De request past niet in het context window ('.number_format($tokens, 0, ',', '.').' > '.$ctxWindow.' tokens); '
                .'de server antwoordt met HTTP 400 exceed_context_size_error. Verlaag --activities/--emails/--historical-leads '
                .'of verhoog --ctx-size op de LLM-server.'
            );
        } elseif ($tokens > $ctxWindow * 0.75) {
            $this->warn('Let op: de request zit dicht tegen het context window van '.$ctxWindow.' tokens aan.');
        }
    }

    /**
     * Ask the llama.cpp server for the real token count; null when it is unreachable
     * or does not expose /tokenize, so the caller falls back to a byte estimate.
     */
    private function countTokens(string $content): ?int
    {
        $baseUrl = rtrim(AiPromptConfig::baseUrl('lead_summary'), '/');

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->post(preg_replace('#/v1$#', '', $baseUrl).'/tokenize', ['content' => $content]);
        } catch (Throwable) {
            return null;
        }

        $tokens = $response->successful() ? $response->json('tokens') : null;

        return is_array($tokens) ? count($tokens) : null;
    }

    private function printResult(mixed $summary, mixed $generation): void
    {
        $this->line('');
        $this->info('=== LLM resultaat ===');
        $this->table(['Veld', 'Waarde'], [
            ['Status', $summary->status],
            ['Tokens input (API)', $generation?->tokens_input ?? '-'],
            ['Tokens output (API)', $generation?->tokens_output ?? '-'],
            ['Duration (ms)', $generation?->duration_ms ?? '-'],
        ]);

        if ($summary->status === 'failed') {
            $this->error('Fout: '.$summary->last_error);

            return;
        }

        $this->line('');
        $this->comment('Samenvatting:');
        $this->line($summary->summary ?? '(leeg)');

        $this->line('');
        $this->comment('Volgende actie:');
        $this->line(($summary->next_action_title ?: '(geen)').' — '.($summary->next_action_reason ?: '').' [prioriteit: '.($summary->priority ?? '-').']');

        $this->line('');
        $this->comment('Aandachtspunten:');
        foreach ($summary->attention_points ?? [] as $point) {
            $this->line('- '.($point['text'] ?? '').' (bron: '.($point['source']['ref'] ?? '?').')');
        }
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function writeReport(array $lines): string
    {
        $disk = Storage::disk('local');
        $relativePath = 'ai-lead-summary-test/report-'.now()->format('Y-m-d_H-i-s').'.md';
        $disk->put($relativePath, implode("\n", $lines));

        return $disk->path($relativePath);
    }
}
