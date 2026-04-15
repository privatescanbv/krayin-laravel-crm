<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Enums\PipelineStageDefaultKeys;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\User;

beforeEach(function (): void {
    $this->seed(TestSeeder::class);
    // Order::firstOrderStageId() returns hardcoded stage IDs from PipelineStage enum.
    // Create the minimum required: orders pipeline (ID 6) + ORDER_CONFIRM stage (ID 30).
});

test('lead won creates sales lead and order', function (): void {
    // Arrange: create a pipeline with a won stage at the end

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
        'lead_pipeline_id'       => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
        'lead_pipeline_stage_id' => PipelineStage::NIEUWE_AANVRAAG_KWALIFICEREN->id(),
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
    ]);
    $lead->save();

    // Attach the person to the lead (required for won transition)
    $lead->attachPersons([$person->id]);

    // Act: transition lead to won stage (triggers LeadObserver@updated)
    $lead->update([
        'lead_pipeline_stage_id' => PipelineStage::WON->id(),
    ]);

    // Assert: a SalesLead exists and is linked to the Lead
    $this->assertDatabaseHas('salesleads', [
        'lead_id' => $lead->id,
    ]);

    $salesLead = SalesLead::where('lead_id', $lead->id)->first();
    $this->assertNotNull($salesLead, 'SalesLead not created for won lead');

    // Assert: SalesLead has the correct pipeline stage ID (first stage of workflow pipeline)
    $this->assertEquals(PipelineStage::SALES_IN_BEHANDELING->id(), $salesLead->pipeline_stage_id, 'SalesLead should have the first stage of the Privatescan workflow pipeline');

    // Assert: an Order exists for the SalesLead with default values
    $this->assertDatabaseHas('orders', [
        'sales_lead_id' => $salesLead->id,
    ]);

    $order = Order::where('sales_lead_id', $salesLead->id)->first();
    $this->assertNotNull($order, 'Order not created for new SalesLead');
    $this->assertNotNull($order->pipeline_stage_id, 'Order should have a pipeline stage assigned');
    $this->assertSame(0.00, (float) $order->total_price);

    // Assert: a system activity was created on the lead linking to the new sales lead
    $SalesActivity = Activity::where('sales_lead_id', $salesLead->id)
        ->where('type', ActivityType::SYSTEM)
        ->first();

    // Debug: check all activities for this lead
    $allActivities = Activity::where('lead_id', $lead->id)->get();
    $this->assertGreaterThan(0, $allActivities->count(), 'No activities found for lead. Found: '.$allActivities->toJson());

    $this->assertNotNull($SalesActivity, 'System activity not created for ales creation. All activities: '.$allActivities->toJson());
    $this->assertSame('Sales aangemaakt vanuit lead', $SalesActivity->title);
    $this->assertSame(1, (int) $SalesActivity->is_done);
    $this->assertIsArray($SalesActivity->additional);
    $this->assertSame(route('admin.leads.view', $lead->id), $SalesActivity->additional['link'] ?? null);
});

test('lead won does not create sales lead when one exists in non-won/lost stage', function (): void {
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
    ]);
    $lead->save();

    // Attach the person to the lead (required for won transition)
    $lead->attachPersons([$person->id]);

    // Create an existing SalesLead in a non-won/lost stage
    $existingSalesLead = SalesLead::create([
        'name'              => $lead->name,
        'description'       => $lead->description,
        'pipeline_stage_id' => PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id(), // Non-won/lost stage
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
    $this->assertEquals(PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id(), $salesLeads->first()->pipeline_stage_id, 'SalesLead should still be in the non-won/lost stage');

    // Assert: no new order was created (since no new SalesLead was created)
    $orders = Order::where('sales_lead_id', $existingSalesLead->id)->get();
    $this->assertCount(0, $orders, 'No new order should be created when SalesLead already exists in non-won/lost stage');
});

test('lead won creates sales lead when existing one is in won/lost stage', function (): void {

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
        'lead_pipeline_id'       => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
        'lead_pipeline_stage_id' => PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value,
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
    ]);
    $lead->save();

    // Attach the person to the lead (required for won transition)
    $lead->attachPersons([$person->id]);

    // Create an existing SalesLead in a won stage
    $existingSalesLead = SalesLead::create([
        'name'              => $lead->name,
        'description'       => $lead->description,
        'pipeline_stage_id' => PipelineStage::SALES_MET_SUCCES_AFGEROND->id(), // Won stage
        'lead_id'           => $lead->id,
        'user_id'           => $lead->user_id,
    ]);

    // Act: transition lead to won stage (should create a new SalesLead)
    $lead->update([
        'lead_pipeline_stage_id' => PipelineStage::WON->id(),
    ]);

    // Assert: two SalesLeads exist (the existing won one and a new one)
    $salesLeads = SalesLead::where('lead_id', $lead->id)->get();
    $this->assertCount(2, $salesLeads, 'Should have two SalesLeads');

    // Assert: the new SalesLead is in the first stage of the workflow pipeline
    $newSalesLead = $salesLeads->where('id', '!=', $existingSalesLead->id)->first();
    $this->assertEquals(PipelineStage::SALES_IN_BEHANDELING->id(), $newSalesLead->pipeline_stage_id, 'New SalesLead should be in the first stage of the workflow pipeline');

    // Assert: no order is created for the existing SalesLead in won/lost stage (order creation only happens for newly created SalesLeads)
    $existingOrder = Order::where('sales_lead_id', $existingSalesLead->id)->first();
    $this->assertNull($existingOrder, 'No order should be created for existing SalesLead in won/lost stage');

    // Assert: an order was created for the new SalesLead
    $newOrder = Order::where('sales_lead_id', $newSalesLead->id)->first();
    $this->assertNotNull($newOrder, 'Order should be created for new SalesLead');
});
