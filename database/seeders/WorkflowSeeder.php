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
     * @return ?array{title: string, description: string, type: ActivityType}
     */
    private function createActivityTitle(PipelineStage $stage): array
    {
        $defaultDescription = 'Automatisch aangemaakt op basis van statuswijziging';

        return match ($stage) {
            PipelineStage::NIEUWE_AANVRAAG_KWALIFICEREN,
            PipelineStage::NIEUWE_AANVRAAG_KWALIFICEREN_HERNIA => [['Klant data bijwerken', $defaultDescription, ActivityType::TASK]],
            PipelineStage::KLANT_ADVISEREN_START,
            PipelineStage::KLANT_ADVISEREN_START_HERNIA             => [['Nieuwe lead bellen', $defaultDescription, ActivityType::CALL]],
            PipelineStage::KLANT_ADVISEREN_OPVOLGEN                 => [],
            PipelineStage::KLANT_ADVISEREN_WILL_MRI_HERNIA          => [['MRI aanleveren', $defaultDescription, ActivityType::TASK]],
            PipelineStage::KLANT_ADVISEREN_WACHTEN_OP_MRI_HERNIA    => [['Klant levert MRI beelden aan, verwerken', $defaultDescription, ActivityType::TASK]],
            PipelineStage::KLANT_ADVISEREN_MRI_BINNEN_HERNIA        => [['Klant adviseren met MRI beelden', $defaultDescription, ActivityType::CALL]],
            PipelineStage::SALES_DOC_COMPLETE_HERNIA                => [['4.1 consult met arts', $defaultDescription, ActivityType::TASK]],
            PipelineStage::SALES_MET_SUCCES_AFGEROND                => [['Test auto op sales entiteit', $defaultDescription, ActivityType::TASK]],
            PipelineStage::ORDER_INGEPLAND,
            PipelineStage::ORDER_INGEPLAND_HERNIA                  => [
                ['Inplannen', $defaultDescription, ActivityType::TASK],
            ],
            PipelineStage::ORDER_BEVESTIGD,
            PipelineStage::ORDER_BEVESTIGD_HERNIA                  => [
                ['Betaling ontvangen?', $defaultDescription, ActivityType::TASK],
                ['GVL ontvangen?', $defaultDescription, ActivityType::TASK],
                ['AFB + GVL versturen naar Kliniek', $defaultDescription, ActivityType::TASK],
            ],
            PipelineStage::ORDER_UITGEVOERD,
            PipelineStage::ORDER_UITGEVOERD_HERNIA => [
                ['Rapporten ontvangen en versturen', $defaultDescription, ActivityType::TASK],
            ],
            PipelineStage::ORDER_RAPPORTEN_ONTVANGEN,
            PipelineStage::ORDER_RAPPORTEN_ONTVANGEN_HERNIA => [
                ['Laten vertalen', $defaultDescription, ActivityType::TASK],
                ['Versturen naar klant', $defaultDescription, ActivityType::CALL],
            ],

            default => [["Auto-activity: {$stage->name()}", $defaultDescription, ActivityType::TASK]],
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

                [$title, $description, $type] = $activityVars;
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
                                'title'       => $title,
                                'description' => $description,
                                'type'        => $type->value,
                            ],
                        ],
                    ]),

                    'created_at' => $now,
                ];
            }, $activities, array_keys($activities));
    }
}
