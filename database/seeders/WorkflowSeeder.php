<?php

namespace Webkul\Installer\Database\Seeders\Workflow;

use App\Enums\ActivityType;
use App\Enums\PipelineStage;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowSeeder extends Seeder
{

    public function __construct()
    {


    }

    /**
     * Generated default data
     * @return array{title: string, description: string}
     */
    private function createActivityTitle(PipelineStage $stage): array
    {
        $defaultDescription = 'Automatisch aangemaakt op basis van statuswijziging';
        return match ($stage) {
            PipelineStage::NIEUWE_AANVRAAG_KWALIFICEREN,
            PipelineStage::NIEUWE_AANVRAAG_KWALIFICEREN_HERNIA
            => ["Klant data bijwerken", $defaultDescription],
            PipelineStage::KLANT_ADVISEREN_START,
            PipelineStage::KLANT_ADVISEREN_START_HERNIA
            => ["Titel voor andere stage", $defaultDescription],
            PipelineStage::KLANT_ADVISEREN_OPVOLGEN =>['Klant bellen voor advies',$defaultDescription],
            PipelineStage::KLANT_ADVISEREN_WILL_MRI_HERNIA => ["MRI aanleveren", $defaultDescription],
            PipelineStage::KLANT_ADVISEREN_WACHTEN_OP_MRI_HERNIA => ['Klant levert MRI beelden aan, verwerken', $defaultDescription],
            PipelineStage::KLANT_ADVISEREN_MRI_BINNEN_HERNIA => ['Klant adviseren met MRI beelden', $defaultDescription],

            default => ["Auto-activity: {$stage->name()}", $defaultDescription],
        };
    }


    public function run($parameters = [])
    {
        DB::table('workflows')->delete();

        $now = Carbon::now();
        $activityType = ActivityType::TASK->value;

        $rows = [];
        $id = 1;

        foreach (PipelineStage::cases() as $stage) {
            $rows[] = $this->buildWorkflowRow(
                id: $id++,
                stage: $stage,
                activityType: $activityType,
                now: $now
            );
        }

        DB::table('workflows')->insert(array_filter($rows));
    }

    private function buildWorkflowRow(int $id, PipelineStage $stage, string $activityType, Carbon $now): array
    {
        if ($stage->isLost() || $stage->isWon() || $stage == PipelineStage::NO_PIPELINE) {
            // no auto activities
            return [];
        }
        [$title, $description] = $this->createActivityTitle($stage);
        $entityType = ($stage->isLead()) ? 'leads' : 'saleslead';
        $entityTypeEvent = ($stage->isLead()) ? 'lead' : 'sale';
        return [
            'id'             => $id,
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
                ]
            ]),
            'actions' => json_encode([
                [
                    'id'         => 'create_activity',
                    'attributes' => [
                        'title'       => $title,
                        'description' => $description,
                        'type'        => $activityType,
                    ],
                ],
            ]),

            'created_at'     => $now,
        ];
    }
}
