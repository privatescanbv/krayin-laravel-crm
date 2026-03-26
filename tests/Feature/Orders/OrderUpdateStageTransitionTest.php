<?php

use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Stage as LeadStage;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('order stage transition succeeds when required field is provided in same request', function () {
    $salesLead = SalesLead::factory()->create();

    $fromStage = LeadStage::factory()->create(['code' => PipelineStage::ORDER_BEVESTIGD->value]);
    $toStage = LeadStage::factory()->create(['code' => PipelineStage::ORDER_WACHTEN_UITVOERING->value]);

    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => $fromStage->id,
        'first_examination_at' => null,
    ]);

    $response = $this->postJson(
        route('admin.orders.update', ['id' => $order->id]),
        [
            'title'                => $order->title,
            'sales_lead_id'        => $salesLead->id,
            'pipeline_stage_id'    => $toStage->id,
            'first_examination_at' => '2025-06-01 09:00:00',
            '_method'              => 'put',
        ]
    );

    $response->assertOk();

    expect(Order::find($order->id))
        ->pipeline_stage_id->toBe($toStage->id)
        ->first_examination_at->not->toBeNull();
});

test('order stage transition fails when required field is missing', function () {
    $salesLead = SalesLead::factory()->create();

    $fromStage = LeadStage::factory()->create(['code' => PipelineStage::ORDER_BEVESTIGD->value]);
    $toStage = LeadStage::factory()->create(['code' => PipelineStage::ORDER_WACHTEN_UITVOERING->value]);

    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => $fromStage->id,
        'first_examination_at' => null,
    ]);

    $response = $this->postJson(
        route('admin.orders.update', ['id' => $order->id]),
        [
            'title'             => $order->title,
            'sales_lead_id'     => $salesLead->id,
            'pipeline_stage_id' => $toStage->id,
            // first_examination_at intentionally omitted
            '_method'           => 'put',
        ]
    );

    $response->assertStatus(422)
        ->assertJson(fn (AssertableJson $json) => $json->has('errors.status_transition')->etc()
        );
});
