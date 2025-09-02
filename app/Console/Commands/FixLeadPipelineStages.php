<?php

namespace App\Console\Commands;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use Illuminate\Console\Command;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

class FixLeadPipelineStages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:fix-pipeline-stages {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix leads with incorrect pipeline stage assignments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made');
        }

        // Find leads with mismatched pipeline and stage
        $leads = Lead::with(['stage', 'pipeline'])
            ->whereHas('stage', function($query) {
                $query->whereColumn('lead_pipeline_stages.lead_pipeline_id', '!=', 'leads.lead_pipeline_id');
            })
            ->get();

        $this->info("Found {$leads->count()} leads with mismatched pipeline/stage");

        if ($leads->isEmpty()) {
            $this->info('No leads need pipeline stage fixes');
            return;
        }

        $fixedCount = 0;
        $errors = [];

        foreach ($leads as $lead) {
            try {
                $correctStageId = $this->getCorrectStageForPipeline($lead->lead_pipeline_id);
                
                if ($dryRun) {
                    $this->line("Would fix lead {$lead->id}: pipeline={$lead->lead_pipeline_id}, current_stage={$lead->lead_pipeline_stage_id} -> correct_stage={$correctStageId}");
                } else {
                    $lead->update(['lead_pipeline_stage_id' => $correctStageId]);
                    $this->line("Fixed lead {$lead->id}: updated stage from {$lead->lead_pipeline_stage_id} to {$correctStageId}");
                }
                
                $fixedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to fix lead {$lead->id}: " . $e->getMessage();
            }
        }

        if (!$dryRun) {
            $this->info("Successfully fixed {$fixedCount} leads");
        } else {
            $this->info("Would fix {$fixedCount} leads");
        }

        if (!empty($errors)) {
            $this->error('Errors encountered:');
            foreach ($errors as $error) {
                $this->error($error);
            }
        }
    }

    /**
     * Get the correct first stage ID for a given pipeline
     */
    private function getCorrectStageForPipeline(int $pipelineId): int
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
                
                if (!$firstStage) {
                    throw new \Exception("No stages found for pipeline {$pipelineId}");
                }
                
                return $firstStage->id;
        }
    }
}