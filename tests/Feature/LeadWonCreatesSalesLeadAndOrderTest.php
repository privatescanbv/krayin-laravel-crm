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
            'is_won'           => false,
            'is_lost'          => false,
        ]);
        $stageWon = Stage::create([
            'name'             => 'Won',
            'lead_pipeline_id' => $pipeline->id,
            'code'             => 'won',
            'sort_order'       => 100,
            'is_won'           => true,
            'is_lost'          => false,
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
            'is_won'           => false,
            'is_lost'          => false,
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

    public function test_lead_won_does_not_create_sales_lead_when_one_exists_in_non_won_lost_stage(): void
    {
        // Arrange: create a pipeline with a won stage at the end
        $pipeline = Pipeline::factory()->create();
        $stageNew = Stage::create([
            'name'             => 'New',
            'lead_pipeline_id' => $pipeline->id,
            'code'             => 'new',
            'sort_order'       => 1,
            'is_won'           => false,
            'is_lost'          => false,
        ]);
        $stageWon = Stage::create([
            'name'             => 'Won',
            'lead_pipeline_id' => $pipeline->id,
            'code'             => 'won',
            'sort_order'       => 100,
            'is_won'           => true,
            'is_lost'          => false,
        ]);

        // Create workflow pipeline and its stages for SalesLead
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
            'is_won'           => false,
            'is_lost'          => false,
        ]);
        $workflowWonStage = Stage::create([
            'name'             => 'Order Won',
            'lead_pipeline_id' => $workflowPipeline->id,
            'code'             => 'order-won',
            'sort_order'       => 2,
            'is_won'           => true,
            'is_lost'          => false,
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

        // Create an existing SalesLead in a non-won/lost stage
        $existingSalesLead = SalesLead::create([
            'name'              => $lead->name,
            'description'       => $lead->description,
            'pipeline_stage_id' => $workflowFirstStage->id, // Non-won/lost stage
            'lead_id'           => $lead->id,
            'user_id'           => $lead->user_id,
        ]);

        // Act: transition lead to won stage (should NOT create a new SalesLead)
        $lead->update([
            'lead_pipeline_stage_id' => $stageWon->id,
        ]);

        // Assert: only one SalesLead exists (the existing one) - no new one was created
        $salesLeads = SalesLead::where('lead_id', $lead->id)->get();
        $this->assertCount(1, $salesLeads, 'Should only have one SalesLead');
        $this->assertEquals($existingSalesLead->id, $salesLeads->first()->id, 'Should be the existing SalesLead');

        // Assert: the existing SalesLead is still in the non-won/lost stage
        $this->assertEquals($workflowFirstStage->id, $salesLeads->first()->pipeline_stage_id, 'SalesLead should still be in the non-won/lost stage');

        // Assert: no new order was created (since no new SalesLead was created)
        $orders = Order::where('sales_lead_id', $existingSalesLead->id)->get();
        $this->assertCount(0, $orders, 'No new order should be created when SalesLead already exists in non-won/lost stage');
    }

    public function test_lead_won_creates_sales_lead_when_existing_one_is_in_won_lost_stage(): void
    {
        // Arrange: create a pipeline with a won stage at the end
        $pipeline = Pipeline::factory()->create();
        $stageNew = Stage::create([
            'name'             => 'New',
            'lead_pipeline_id' => $pipeline->id,
            'code'             => 'new',
            'sort_order'       => 1,
            'is_won'           => false,
            'is_lost'          => false,
        ]);
        $stageWon = Stage::create([
            'name'             => 'Won',
            'lead_pipeline_id' => $pipeline->id,
            'code'             => 'won',
            'sort_order'       => 100,
            'is_won'           => true,
            'is_lost'          => false,
        ]);

        // Create workflow pipeline and its stages for SalesLead
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
            'is_won'           => false,
            'is_lost'          => false,
        ]);
        $workflowWonStage = Stage::create([
            'name'             => 'Order Won',
            'lead_pipeline_id' => $workflowPipeline->id,
            'code'             => 'order-won',
            'sort_order'       => 2,
            'is_won'           => true,
            'is_lost'          => false,
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

        // Create an existing SalesLead in a won stage
        $existingSalesLead = SalesLead::create([
            'name'              => $lead->name,
            'description'       => $lead->description,
            'pipeline_stage_id' => $workflowWonStage->id, // Won stage
            'lead_id'           => $lead->id,
            'user_id'           => $lead->user_id,
        ]);

        // Act: transition lead to won stage (should create a new SalesLead)
        $lead->update([
            'lead_pipeline_stage_id' => $stageWon->id,
        ]);

        // Assert: two SalesLeads exist (the existing won one and a new one)
        $salesLeads = SalesLead::where('lead_id', $lead->id)->get();
        $this->assertCount(2, $salesLeads, 'Should have two SalesLeads');

        // Assert: the new SalesLead is in the first stage of the workflow pipeline
        $newSalesLead = $salesLeads->where('id', '!=', $existingSalesLead->id)->first();
        $this->assertEquals($workflowFirstStage->id, $newSalesLead->pipeline_stage_id, 'New SalesLead should be in the first stage of the workflow pipeline');

        // Assert: an order was created for the existing SalesLead (since it was in won/lost stage)
        $existingOrder = Order::where('sales_lead_id', $existingSalesLead->id)->first();
        $this->assertNotNull($existingOrder, 'Order should be created for existing SalesLead in won/lost stage');

        // Assert: an order was created for the new SalesLead
        $newOrder = Order::where('sales_lead_id', $newSalesLead->id)->first();
        $this->assertNotNull($newOrder, 'Order should be created for new SalesLead');
    }
}
