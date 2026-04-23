<?php

namespace Tests\Feature\Sales;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use App\Models\SalesLeadRelation;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\User;

beforeEach(function (): void {
    $this->seed(TestSeeder::class);
    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('createPreventieSales creates preventie lead, sales and order and links them', function (): void {
    $user = User::factory()->create();
    $source = Source::firstOrCreate(['name' => 'Website']);
    $type = Type::firstOrCreate(['name' => 'New Lead']);

    $herniaDept = Department::firstOrCreate(['name' => 'Herniapoli']);
    $privatescanDept = Department::firstOrCreate(['name' => 'Privatescan']);

    $person = Person::factory()->create([
        'first_name' => 'Jan',
        'last_name'  => 'Smit',
        'emails'     => [['value' => 'jan@example.com', 'label' => 'work', 'is_default' => true]],
        'phones'     => [['value' => '+31612345678', 'label' => 'mobile', 'is_default' => true]],
    ]);

    // Create Herniapoli lead in WON stage to trigger SalesLead creation
    $herniaLead = new Lead([
        'lead_pipeline_id'       => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
        'lead_pipeline_stage_id' => PipelineStage::WON_HERNIA->id(),
        'status'                 => 1,
        'first_name'             => 'Jan',
        'last_name'              => 'Smit',
        'emails'                 => [['value' => 'jan@example.com', 'label' => 'work', 'is_default' => true]],
        'phones'                 => [['value' => '+31612345678', 'label' => 'mobile', 'is_default' => true]],
        'user_id'                => $user->id,
        'lead_source_id'         => $source->id,
        'lead_type_id'           => $type->id,
        'department_id'          => $herniaDept->id,
    ]);
    $herniaLead->save();
    $herniaLead->attachPersons([$person->id]);

    // Create Herniapoli SalesLead directly (simulating the won flow)
    $herniaSales = SalesLead::create([
        'name'              => 'Herniapoli Sales Jan Smit',
        'lead_id'           => $herniaLead->id,
        'pipeline_stage_id' => PipelineStage::SALES_ORDER_PREVENTIE_HERNIA->id(),
        'user_id'           => $user->id,
    ]);
    $herniaSales->attachPersons([$person->id]);

    // Act: call the createPreventieSales action
    $response = $this->post(route('admin.sales-leads.create-preventie-sales', $herniaSales->id));

    // Assert: a new Privatescan Lead was created
    $this->assertDatabaseHas('leads', [
        'department_id'          => $privatescanDept->id,
        'lead_pipeline_id'       => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
        'lead_pipeline_stage_id' => PipelineStage::WON->id(),
    ]);

    // Assert: a SalesLead was created for the Preventie lead
    $preventieLead = Lead::where('department_id', $privatescanDept->id)
        ->where('lead_pipeline_stage_id', PipelineStage::WON->id())
        ->latest()
        ->first();
    $this->assertNotNull($preventieLead);

    $preventieSales = SalesLead::where('lead_id', $preventieLead->id)->first();
    $this->assertNotNull($preventieSales, 'Preventie SalesLead was not created');

    // Assert: an Order was created for the Preventie sales
    $this->assertDatabaseHas('orders', ['sales_lead_id' => $preventieSales->id]);

    // Assert: a SalesLeadRelation links the two sales
    $this->assertDatabaseHas('saleslead_relations', [
        'source_saleslead_id' => $herniaSales->id,
        'target_saleslead_id' => $preventieSales->id,
        'relation_type'       => 'preventie_referral',
    ]);

    // Assert: redirect to the Preventie sales view
    $response->assertRedirect(route('admin.sales-leads.view', $preventieSales->id));
});

test('createPreventieSales returns error for non-hernia sales', function (): void {
    $user = User::factory()->create();
    $source = Source::firstOrCreate(['name' => 'Website']);
    $type = Type::firstOrCreate(['name' => 'New Lead']);
    $privatescanDept = Department::firstOrCreate(['name' => 'Privatescan']);

    $privatescanLead = new Lead([
        'lead_pipeline_id'       => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
        'lead_pipeline_stage_id' => PipelineStage::WON->id(),
        'status'                 => 1,
        'first_name'             => 'Anna',
        'last_name'              => 'Bakker',
        'emails'                 => [['value' => 'anna@example.com', 'label' => 'work', 'is_default' => true]],
        'phones'                 => [],
        'user_id'                => $user->id,
        'lead_source_id'         => $source->id,
        'lead_type_id'           => $type->id,
        'department_id'          => $privatescanDept->id,
    ]);
    $privatescanLead->save();

    $privatescanSales = SalesLead::create([
        'name'              => 'Privatescan Sales Anna Bakker',
        'lead_id'           => $privatescanLead->id,
        'pipeline_stage_id' => PipelineStage::SALES_IN_BEHANDELING->id(),
        'user_id'           => $user->id,
    ]);

    $response = $this->post(route('admin.sales-leads.create-preventie-sales', $privatescanSales->id));

    $response->assertRedirect();
    $this->assertDatabaseMissing('saleslead_relations', [
        'source_saleslead_id' => $privatescanSales->id,
    ]);
});
