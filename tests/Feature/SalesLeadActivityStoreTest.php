<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

test('creating activity on sales lead stores sales_lead_id and appears as planned', function () {
    $this->seed(TestSeeder::class);

    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $pipeline = Pipeline::first() ?? Pipeline::factory()->create();
    $stage = Stage::first() ?? Stage::factory()->create(['lead_pipeline_id' => $pipeline->id]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
        'user_id'                => $user->id,
    ]);

    $salesLead = SalesLead::create([
        'name'              => 'WL for Activity',
        'description'       => 'desc',
        'pipeline_stage_id' => $stage->id,
        'lead_id'           => $lead->id,
        'user_id'           => $user->id,
    ]);

    $now = now();
    $payload = [
        'type'          => 'task',
        'title'         => 'My Planned Task',
        'description'   => 'Test',
        'schedule_from' => $now->format('Y-m-d H:i:s'),
        'schedule_to'   => $now->copy()->addHour()->format('Y-m-d H:i:s'),
    ];

    $response = $this->postJson(route('admin.sales-leads.activities.store', ['id' => $salesLead->id]), $payload);
    $response->assertOk();

    // Assert saved with sales_lead_id and not done
    $this->assertDatabaseHas('activities', [
        'title'         => 'My Planned Task',
        'sales_lead_id' => $salesLead->id,
        'is_done'       => 0,
    ]);

    // Fetch activities endpoint and ensure the item is returned
    $get = $this->getJson(route('admin.sales-leads.activities.index', ['id' => $salesLead->id]));
    $get->assertOk();
    $json = $get->json('data');
    $titles = collect($json)->pluck('title');
    expect($titles)->toContain('My Planned Task');
});
