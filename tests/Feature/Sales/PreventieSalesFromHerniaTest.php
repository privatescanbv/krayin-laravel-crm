<?php

namespace Tests\Feature\Sales;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Models\Department;
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

test('createPreventieSales creates a preventie sales linked to the same lead and links them', function (): void {
    $user = User::factory()->create();
    $source = Source::firstOrCreate(['name' => 'Website']);
    $type = Type::firstOrCreate(['name' => 'New Lead']);

    $herniaDept = Department::firstOrCreate(['name' => 'Herniapoli']);

    $person = Person::factory()->create([
        'first_name' => 'Jan',
        'last_name'  => 'Smit',
        'emails'     => [['value' => 'jan@example.com', 'label' => 'work', 'is_default' => true]],
        'phones'     => [['value' => '+31612345678', 'label' => 'mobile', 'is_default' => true]],
    ]);

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

    $herniaSales = SalesLead::create([
        'name'              => 'Herniapoli Sales Jan Smit',
        'lead_id'           => $herniaLead->id,
        'pipeline_stage_id' => PipelineStage::SALES_ORDER_PREVENTIE_HERNIA->id(),
        'user_id'           => $user->id,
    ]);
    $herniaSales->attachPersons([$person->id]);

    $response = $this->post(route('admin.sales-leads.create-preventie-sales', $herniaSales->id));

    // Assert: a new SalesLead was created linked to the SAME Hernia lead
    $preventieSales = SalesLead::where('lead_id', $herniaLead->id)
        ->where('pipeline_stage_id', PipelineStage::SALES_IN_BEHANDELING->id())
        ->latest()
        ->first();
    $this->assertNotNull($preventieSales, 'Preventie SalesLead was not created');

    // Assert: no new Lead was created
    $this->assertDatabaseCount('leads', 1);

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
