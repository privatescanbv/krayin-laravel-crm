<?php

use App\Enums\ActivityType;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');

    $this->pipeline = Pipeline::first();
    $this->stage = Stage::first();
});

test('activity edit page shows a clickable phone number for a directly linked person', function () {
    $person = Person::factory()->create([
        'phones' => [['value' => '0612345678', 'is_default' => true]],
    ]);

    $activity = Activity::create([
        'title'       => 'Call activity',
        'type'        => ActivityType::TASK->value,
        'schedule_to' => now()->addDay(),
        'is_done'     => false,
        'person_id'   => $person->id,
        'user_id'     => $this->user->id,
    ]);

    $this->get(route('admin.activities.edit', $activity->id))
        ->assertOk()
        ->assertSee('href="tel:0612345678"', false);
});

test('activity edit page shows a clickable phone number for the lead contact person', function () {
    $person = Person::factory()->create([
        'phones' => [['value' => '0687654321', 'is_default' => true]],
    ]);

    $lead = Lead::factory()->create([
        'contact_person_id'      => $person->id,
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    $activity = Activity::create([
        'title'       => 'Lead call activity',
        'type'        => ActivityType::TASK->value,
        'schedule_to' => now()->addDay(),
        'is_done'     => false,
        'lead_id'     => $lead->id,
        'user_id'     => $this->user->id,
    ]);

    $this->get(route('admin.activities.edit', $activity->id))
        ->assertOk()
        ->assertSee('href="tel:0687654321"', false);
});

test('activity edit page shows a clickable phone number for the sales lead contact person', function () {
    $person = Person::factory()->create([
        'phones' => [['value' => '0611223344', 'is_default' => true]],
    ]);

    $salesLead = SalesLead::factory()->create([
        'contact_person_id' => $person->id,
        'pipeline_stage_id' => $this->stage->id,
        'user_id'           => $this->user->id,
    ]);

    $activity = Activity::create([
        'title'         => 'Sales call activity',
        'type'          => ActivityType::TASK->value,
        'schedule_to'   => now()->addDay(),
        'is_done'       => false,
        'sales_lead_id' => $salesLead->id,
        'user_id'       => $this->user->id,
    ]);

    $this->get(route('admin.activities.edit', $activity->id))
        ->assertOk()
        ->assertSee('href="tel:0611223344"', false);
});
