<?php

namespace Tests\Feature\Sales;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
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

test('createHerniaSales creates a hernia sales linked to the same lead and links them', function (): void {
    $user = User::factory()->create();
    $source = Source::firstOrCreate(['name' => 'Website']);
    $type = Type::firstOrCreate(['name' => 'New Lead']);

    $privatescanDept = Department::firstOrCreate(['name' => 'Privatescan']);

    $person = Person::factory()->create([
        'first_name' => 'Sanne',
        'last_name'  => 'de Vries',
        'emails'     => [['value' => 'sanne@example.com', 'label' => 'work', 'is_default' => true]],
        'phones'     => [['value' => '+31687654321', 'label' => 'mobile', 'is_default' => true]],
    ]);

    $privatescanLead = new Lead([
        'lead_pipeline_id'       => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
        'lead_pipeline_stage_id' => PipelineStage::WON->id(),
        'status'                 => 1,
        'first_name'             => 'Sanne',
        'last_name'              => 'de Vries',
        'emails'                 => [['value' => 'sanne@example.com', 'label' => 'work', 'is_default' => true]],
        'phones'                 => [['value' => '+31687654321', 'label' => 'mobile', 'is_default' => true]],
        'user_id'                => $user->id,
        'lead_source_id'         => $source->id,
        'lead_type_id'           => $type->id,
        'department_id'          => $privatescanDept->id,
    ]);
    $privatescanLead->save();

    $privatescanSales = SalesLead::create([
        'name'              => 'Privatescan Sales Sanne de Vries',
        'lead_id'           => $privatescanLead->id,
        'pipeline_stage_id' => PipelineStage::SALES_IN_BEHANDELING->id(),
        'user_id'           => $user->id,
    ]);
    $privatescanSales->attachPersons([$person->id]);

    $response = $this->post(route('admin.sales-leads.create-hernia-sales', $privatescanSales->id));

    // Assert: a new SalesLead was created linked to the SAME Privatescan lead
    $herniaSales = SalesLead::where('lead_id', $privatescanLead->id)
        ->where('pipeline_stage_id', PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA->id())
        ->latest()
        ->first();
    $this->assertNotNull($herniaSales, 'Hernia SalesLead was not created');

    // Assert: no new Lead was created
    $this->assertDatabaseCount('leads', 1);

    // Assert: an Order was created for the Hernia sales
    $this->assertDatabaseHas('orders', ['sales_lead_id' => $herniaSales->id]);

    // Assert: a SalesLeadRelation links the two sales
    $this->assertDatabaseHas('saleslead_relations', [
        'source_saleslead_id' => $privatescanSales->id,
        'target_saleslead_id' => $herniaSales->id,
        'relation_type'       => 'hernia_referral',
    ]);

    // Assert: redirect to the Hernia sales view
    $response->assertRedirect(route('admin.sales-leads.view', $herniaSales->id));

    // Assert: SalesLead has Hernia department_id (not Privatescan)
    $herniaDept = Department::firstOrCreate(['name' => 'Herniapoli']);
    $this->assertEquals($herniaDept->id, $herniaSales->department_id);

    // Assert: Order is on Hernia orders pipeline (ORDER_VOORBEREIDEN_HERNIA), not Privatescan
    $order = Order::where('sales_lead_id', $herniaSales->id)->first();
    $this->assertNotNull($order);
    $this->assertEquals(PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id(), $order->pipeline_stage_id);

    // Assert: isHerniaPoli returns true for the Hernia SalesLead
    $this->assertTrue(SalesLead::isHerniaPoli($herniaSales->id));
});

test('createHerniaSales returns error for non-privatescan sales', function (): void {
    $user = User::factory()->create();
    $source = Source::firstOrCreate(['name' => 'Website']);
    $type = Type::firstOrCreate(['name' => 'New Lead']);
    $herniaDept = Department::firstOrCreate(['name' => 'Herniapoli']);

    $herniaLead = new Lead([
        'lead_pipeline_id'       => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
        'lead_pipeline_stage_id' => PipelineStage::WON_HERNIA->id(),
        'status'                 => 1,
        'first_name'             => 'Bram',
        'last_name'              => 'Visser',
        'emails'                 => [['value' => 'bram@example.com', 'label' => 'work', 'is_default' => true]],
        'phones'                 => [],
        'user_id'                => $user->id,
        'lead_source_id'         => $source->id,
        'lead_type_id'           => $type->id,
        'department_id'          => $herniaDept->id,
    ]);
    $herniaLead->save();

    $herniaSales = SalesLead::create([
        'name'              => 'Herniapoli Sales Bram Visser',
        'lead_id'           => $herniaLead->id,
        'pipeline_stage_id' => PipelineStage::SALES_ORDER_PREVENTIE_HERNIA->id(),
        'user_id'           => $user->id,
    ]);

    $response = $this->post(route('admin.sales-leads.create-hernia-sales', $herniaSales->id));

    $response->assertRedirect();
    $this->assertDatabaseMissing('saleslead_relations', [
        'source_saleslead_id' => $herniaSales->id,
    ]);
});
