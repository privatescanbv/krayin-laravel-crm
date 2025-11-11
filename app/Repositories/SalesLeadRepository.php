<?php

namespace App\Repositories;

use App\Enums\Departments;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Models\SalesLead;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

class SalesLeadRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'name',
        'description',
        'user_id',
        'user.first_name',
        'user.last_name',
        'pipeline_stage_id',
        'created_at',
        'closed_at',
    ];

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly ActivityRepository $activityRepository,
        \Illuminate\Container\Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Create a SalesLead from a won Lead with appropriate workflow pipeline stage.
     */
    public function createFromWonLead(Lead $lead): ?SalesLead
    {
        try {
            // Check if there's already a SalesLead for this lead
            $existingSalesLead = SalesLead::where('lead_id', $lead->id)->with('pipelineStage')->first();

            if ($existingSalesLead) {
                // Check if the existing SalesLead is in a non-won/lost stage
                if ($this->existsSalesLeadInNotWonOrLoss($lead->id)) {
                    Log::info('SalesLead already exists in a non-won/lost stage', [
                        'lead_id' => $lead->id,
                    ]);

                    // Don't create a new SalesLead if one already exists in a non-won/lost stage
                    return null;
                }
                // If an existing SalesLead is in won/lost, do not create an order here; creation is only for new SalesLeads
            }

            // Determine the appropriate workflow pipeline stage
            $pipelineStageId = $this->getWorkflowPipelineStageId($lead);

            // Create the SalesLead
            $salesLead = SalesLead::create([
                'name'              => $lead->name,
                'description'       => $lead->description,
                'pipeline_stage_id' => $pipelineStageId,
                'lead_id'           => $lead->id,
                'user_id'           => $lead->user_id,
            ]);

            // Copy persons from lead to sales lead
            $this->copyPersonsFromLead($salesLead, $lead);

            // Create initial order for this sales lead
            $this->orderRepository->createFromSalesLead($salesLead->id, $salesLead->name);

            // Add a system activity on the lead linking to this new sales lead view
            $this->activityRepository->createSystemActivitiesForSalesLeadCreation($lead, $salesLead);

            return $salesLead;

        } catch (Throwable $e) {
            Log::error('Failed to create SalesLead/Order for won lead', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function resolveEmailVariablesById($salesId): array
    {
        return $this->resolveEmailVariables($this->find($salesId));
    }

    public function model()
    {
        return SalesLead::class;
    }

    private function resolveEmailVariables(SalesLead $sales): array
    {
        $person = $sales->getContactPersonOrFirstPerson();

        return ['lastname' => $person->last_name];
    }

    private function existsSalesLeadInNotWonOrLoss(int $leadId): bool
    {
        return SalesLead::where('lead_id', $leadId)
            ->whereHas('pipelineStage', function ($query) {
                $query->where('is_won', false)
                    ->where('is_lost', false);
            })
            ->exists();
    }

    /**
     * Get the appropriate workflow pipeline stage ID based on the lead's department.
     */
    private function getWorkflowPipelineStageId(Lead $lead): int
    {
        $defaultStageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_WORKFLOW_ID->value;

        try {
            $department = $lead->department;
            if (! $department) {
                return $defaultStageId;
            }

            if ($department->name === Departments::PRIVATESCAN->value) {
                return $this->getFirstStageOfWorkflowPipeline(
                    PipelineDefaultKeys::PIPELINE_PRIVATESCAN_WORKFLOW_ID->value
                ) ?? $defaultStageId;
            }

            if ($department->name === Departments::HERNIA->value) {
                return $this->getFirstStageOfWorkflowPipeline(
                    PipelineDefaultKeys::PIPELINE_HERNIA_WORKFLOW_ID->value
                ) ?? $defaultStageId;
            }

        } catch (Throwable $e) {
            Log::warning('Error determining workflow pipeline stage', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return $defaultStageId;
    }

    /**
     * Get the first stage of a workflow pipeline.
     */
    private function getFirstStageOfWorkflowPipeline(int $pipelineId): ?int
    {
        $firstStage = Stage::where('lead_pipeline_id', $pipelineId)
            ->orderBy('sort_order')
            ->first();

        return $firstStage?->id;
    }

    /**
     * Copy persons from lead to sales lead.
     */
    private function copyPersonsFromLead(SalesLead $salesLead, Lead $lead): void
    {
        try {
            $salesLead->copyFromLead($lead);
        } catch (Throwable $e) {
            Log::error('Failed to copy persons from lead to sales', [
                'lead_id'       => $lead->id,
                'sales_lead_id' => $salesLead->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
