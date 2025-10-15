<?php

namespace App\Repositories;

use App\Enums\Departments;
use App\Enums\OrderStatus;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

class SalesLeadRepository
{
    public function __construct(private readonly OrderRepository $orderRepository) {}

    /**
     * Create a SalesLead from a won Lead with appropriate workflow pipeline stage.
     */
    public function createFromWonLead(Lead $lead): ?SalesLead
    {
        try {
            // Check if SalesLead already exists for this lead
            $existingSalesLead = SalesLead::where('lead_id', $lead->id)->first();
            if ($existingSalesLead) {
                // Ensure an order exists; if not, create a default one
                $existingOrder = Order::where('sales_lead_id', $existingSalesLead->id)->first();
                if (! $existingOrder) {
                    Order::create([
                        'title'         => 'Order voor '.$lead->name,
                        'total_price'   => 0.00,
                        'status'        => OrderStatus::NIEUW,
                        'sales_lead_id' => $existingSalesLead->id,
                    ]);
                }

                return $existingSalesLead;
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

            return $salesLead;

        } catch (Throwable $e) {
            Log::error('Failed to create SalesLead/Order for won lead', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
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
            $personIds = $lead->persons()->pluck('persons.id')->toArray();
            if (! empty($personIds) && method_exists($salesLead, 'syncPersons')) {
                $salesLead->syncPersons($personIds);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to copy persons from lead to sales lead', [
                'lead_id'       => $lead->id,
                'sales_lead_id' => $salesLead->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
