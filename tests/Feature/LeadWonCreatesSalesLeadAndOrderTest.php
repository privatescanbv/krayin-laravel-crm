<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PipelineDefaultKeys;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\User;

class LeadWonCreatesSalesLeadAndOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_won_creates_sales_lead_and_order(): void
    {
        // Arrange: create a pipeline with a won stage at the end
        $pipeline = Pipeline::factory()->create();
        $stageNew = Stage::create([
            'name'             => 'New',
            'lead_pipeline_id' => $pipeline->id,
            'code'             => 'new',
            'sort_order'       => 1,
        ]);
        $stageWon = Stage::create([
            'name'             => 'Won',
            'lead_pipeline_id' => $pipeline->id,
            'code'             => 'won',
            'sort_order'       => 100,
        ]);

        // Create workflow pipeline and its first stage for SalesLead
        $workflowPipeline = Pipeline::factory()->create([
            'id'   => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_WORKFLOW_ID->value,
            'name' => 'Privatescan Workflow',
            'type' => 'workflow',
        ]);
        $workflowFirstStage = Stage::create([
            'name'             => 'Bestelling voorbereiden',
            'lead_pipeline_id' => $workflowPipeline->id,
            'code'             => 'bestelling-voorbereiden',
            'sort_order'       => 1,
        ]);

        // Create required related models for the lead
        $user = User::factory()->create();
        $source = Source::create(['name' => 'Website']);
        $type = Type::create(['name' => 'New Lead']);
        $department = Department::create(['name' => 'Privatescan']);

        // Create a person first with specific data for 100% match score
        $person = Person::factory()->create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'emails'     => [['value' => 'john.doe@example.com', 'label' => 'work', 'is_default' => true]],
            'phones'     => [['value' => '+31612345678', 'label' => 'mobile', 'is_default' => true]],
        ]);

        // Create lead manually to avoid factory stage creation issues
        $lead = new Lead([
            'lead_pipeline_id'       => $pipeline->id,
            'lead_pipeline_stage_id' => $stageNew->id,
            'status'                 => 1,
            'first_name'             => 'John',
            'last_name'              => 'Doe',
            'emails'                 => [['value' => 'john.doe@example.com', 'label' => 'work', 'is_default' => true]],
            'phones'                 => [['value' => '+31612345678', 'label' => 'mobile', 'is_default' => true]],
            'description'            => 'Test lead',
            'user_id'                => $user->id,
            'lead_source_id'         => $source->id,
            'lead_type_id'           => $type->id,
            'department_id'          => $department->id,
            'combine_order'          => true,
        ]);
        $lead->save();

        // Attach the person to the lead (required for won transition)
        $lead->attachPersons([$person->id]);

        // Act: transition lead to won stage (triggers LeadObserver@updated)
        $lead->update([
            'lead_pipeline_stage_id' => $stageWon->id,
        ]);

        // Assert: a SalesLead exists and is linked to the Lead
        $this->assertDatabaseHas('salesleads', [
            'lead_id' => $lead->id,
        ]);

        $salesLead = SalesLead::where('lead_id', $lead->id)->first();
        $this->assertNotNull($salesLead, 'SalesLead not created for won lead');

        // Assert: SalesLead has the correct pipeline stage ID (first stage of workflow pipeline)
        $this->assertEquals($workflowFirstStage->id, $salesLead->pipeline_stage_id, 'SalesLead should have the first stage of the Privatescan workflow pipeline');

        // Assert: an Order exists for the SalesLead with default values
        $this->assertDatabaseHas('orders', [
            'sales_lead_id' => $salesLead->id,
        ]);

        $order = Order::where('sales_lead_id', $salesLead->id)->first();
        $this->assertNotNull($order, 'Order not created for new SalesLead');
        $this->assertSame(OrderStatus::NIEUW, $order->status);
        $this->assertSame(0.00, (float) $order->total_price);
    }
}
