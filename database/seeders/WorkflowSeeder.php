<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\PipelineStage;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowSeeder extends Seeder
{
    // todo dynamisch type. bellen doen voor klant adviseren -> bellen
    public function __construct() {}

    public function run($parameters = [])
    {
        DB::table('workflows')->delete();

        $now = Carbon::now();

        $rows = [];
        $id = 1;

        foreach (PipelineStage::cases() as $stage) {
            $workflows = $this->buildWorkflowRow(
                id: $id,
                stage: $stage,
                now: $now
            );
            $id += count($workflows);
            $rows = array_merge($rows, $workflows);
        }

        DB::table('workflows')->insert($rows);
    }

    /**
     * Generated default data
     *
     * @return array<int, array{0: string, 1: string, 2: ActivityType, 3: int}> Each activity: [title, description, type, deadline_in_days].
     */
    private function createActivityTitle(PipelineStage $stage): array
    {
        $defaultDescription = 'Automatisch aangemaakt op basis van statuswijziging';

        return match ($stage) {
            PipelineStage::NIEUWE_AANVRAAG_KWALIFICEREN,
            PipelineStage::NIEUWE_AANVRAAG_KWALIFICEREN_HERNIA           => [['Klant data bijwerken', $defaultDescription, ActivityType::TASK, 1]],
            PipelineStage::KLANT_ADVISEREN_START                         => [['Nieuwe lead, bellen', $defaultDescription, ActivityType::CALL, 1]],
            PipelineStage::KLANT_ADVISEREN_START_HERNIA                  => [['Nieuwe lead, bellen - geen MRI / overig', $defaultDescription, ActivityType::CALL, 1]],
            PipelineStage::KLANT_ADVISEREN_OPVOLGEN                      => [],
            PipelineStage::KLANT_ADVISEREN_WILL_MRI_HERNIA               => [['Nieuwe lead, bellen - MRI', $defaultDescription, ActivityType::CALL, 1]],
            PipelineStage::KLANT_ADVISEREN_WACHTEN_OP_MRI_HERNIA         => [['Beelden binnen?', $defaultDescription, ActivityType::TASK, 10]],
            PipelineStage::KLANT_ADVISEREN_MRI_BINNEN_HERNIA             => [['Klant adviseren met MRI beelden', $defaultDescription, ActivityType::CALL, 1]],
            PipelineStage::SALES_ORDER_PREVENTIE_HERNIA                  => [['Onderzoek via Privatescan', $defaultDescription, ActivityType::TASK, 3]],
            PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA                => [['Casus beoordeeld door arts?', $defaultDescription, ActivityType::TASK, 3]],
            PipelineStage::SALES_ASSESSMENT_DONE_HERNIA                  => [['Beoordeling gereed: wat zegt de Arts?', $defaultDescription, ActivityType::TASK, 0]],
            PipelineStage::SALES_PATIENT_REFLECTION_TIME_HERNIA          => [['Wenst patient het advies?', $defaultDescription, ActivityType::TASK, 4]],
            PipelineStage::SALES_PLANNED_FOR_ADDITIONAL_RESEARCH_HERNIA  => [['Gepland voor aanvullend onderzoek', $defaultDescription, ActivityType::TASK, 3]],
            PipelineStage::SALES_CONFIRM_TREATMENT_HERNIA                => [['Behandeling bevestigen', $defaultDescription, ActivityType::TASK, 1]],
            PipelineStage::SALES_WAIT_HEALTH_INSURER_HERNIA              => [
                ['Artsbrief ontvangen?', $defaultDescription, ActivityType::TASK, 3],
                ['Kostenraming maken', $defaultDescription, ActivityType::TASK, 3],
                ['Kostenraming en artsbrief verzenden', $defaultDescription, ActivityType::TASK, 3],
                ['Reactie zorgverzekeraar?', $defaultDescription, ActivityType::TASK, 8],
            ],
            PipelineStage::SALES_TREATMENT_PLANNED_HERNIA                => [],
            PipelineStage::SALES_AFTER_TREATMENT_HERNIA                  => [],
            PipelineStage::SALES_AFTERCARE1_HERNIA                       => [],
            PipelineStage::SALES_AFTERCARE2_HERNIA                       => [],
            PipelineStage::SALES_PHYSICAL_CONSULTATION_HERNIA            => [['Hoe ging nacontrole afspraak?', $defaultDescription, ActivityType::TASK, 7]],
            PipelineStage::SALES_IN_BEHANDELING                          => [],
            PipelineStage::ORDER_CONFIRM,
            PipelineStage::ORDER_VOORBEREIDEN_HERNIA                => [['Inplannen en versturen', $defaultDescription, ActivityType::TASK, 0]],
            PipelineStage::ORDER_INGEPLAND,
            PipelineStage::ORDER_INGEPLAND_HERNIA                  => [
                ['Controle akkoord', $defaultDescription, ActivityType::TASK, 1],
            ],
            PipelineStage::ORDER_BEVESTIGD_HERNIA => [
                ['Betaling ontvangen?', $defaultDescription, ActivityType::TASK, 3],
                ['Narcoseformulier ontvangen?', $defaultDescription, ActivityType::TASK, 1],
                ['Aanmelden kliniek', $defaultDescription, ActivityType::TASK, 3],
                ['Artzbrief ontvangen?', $defaultDescription, ActivityType::TASK, 3],
                ['Kostenraming en patientmap aanmaken', $defaultDescription, ActivityType::TASK, 1],
                ['Kostenraming en artsbrief verzenden', $defaultDescription, ActivityType::TASK, 4],
            ],
            PipelineStage::ORDER_BEVESTIGD                  => [
                ['Betaling ontvangen?', $defaultDescription, ActivityType::TASK, 5],
                ['GVL ontvangen?', $defaultDescription, ActivityType::TASK, 5],
                ['AFB + GVL versturen naar Kliniek', $defaultDescription, ActivityType::TASK, 5],
            ],
            PipelineStage::ORDER_WACHTEN_UITVOERING,
            PipelineStage::ORDER_WACHTEN_UITVOERING_HERNIA         => [],
            PipelineStage::ORDER_UITGEVOERD                        => [
                ['Rapporten ontvangen? Verzenden klant', $defaultDescription, ActivityType::TASK, 5],
            ],
            PipelineStage::ORDER_UITGEVOERD_HERNIA => [
                ['Operatieverslag ontvangen? Verzenden klant', $defaultDescription, ActivityType::TASK, 5],
                ['1e nazorggesprek', $defaultDescription, ActivityType::CALL, 5],
                ['2e nazorggesprek', $defaultDescription, ActivityType::CALL, 30],
            ],
            PipelineStage::ORDER_RAPPORTEN_ONTVANGEN,
            PipelineStage::ORDER_RAPPORTEN_ONTVANGEN_HERNIA => [
                ['Laten vertalen', $defaultDescription, ActivityType::TASK, 5],
                ['Versturen naar klant', $defaultDescription, ActivityType::TASK, 10],
            ],

            default => [["Auto-activity: {$stage->name()}", $defaultDescription, ActivityType::TASK, 5]],
        };
    }

    private function buildWorkflowRow(int $id, PipelineStage $stage, Carbon $now): array
    {
        if ($stage->isLost() || $stage->isWon() || $stage == PipelineStage::NO_PIPELINE) {
            // no auto activities
            return [];
        }
        $activities = array_values($this->createActivityTitle($stage));

        return array_map(
            function ($activityVars, $offset) use ($id, $stage, $now) {

                [$title, $description, $type, $deadlineInDays] = $activityVars;
                $entityType = $stage->isOrder() ? 'orders' : ($stage->isLead() ? 'leads' : 'saleslead');
                $entityTypeEvent = $stage->isOrder() ? 'order' : ($stage->isLead() ? 'lead' : 'sale');

                return [
                    'id'             => $id + $offset,
                    'name'           => $title,
                    'description'    => $description,
                    'entity_type'    => $entityType,
                    'event'          => $entityTypeEvent.'.update_stage.after',
                    'condition_type' => 'and',
                    'conditions'     => json_encode([
                        [
                            'value'          => (string) $stage->id(),
                            'operator'       => '==',
                            'attribute'      => ($stage->isLead()) ? 'lead_pipeline_stage_id' : 'pipeline_stage_id',
                            'attribute_type' => 'select',
                        ],
                    ]),
                    'actions' => json_encode([
                        [
                            'id'         => 'create_activity',
                            'attributes' => [
                                'title'            => $title,
                                'description'      => $description,
                                'type'             => $type->value,
                                'deadline_in_days' => $deadlineInDays,
                            ],
                        ],
                    ]),

                    'created_at' => $now,
                ];
            }, $activities, array_keys($activities));
    }
}
