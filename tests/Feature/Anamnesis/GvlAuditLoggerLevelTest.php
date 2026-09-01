<?php

namespace Tests\Feature\Anamnesis;

use App\Enums\ActivityType;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\Order;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

uses(RefreshDatabase::class);

test('GVL audit log attaches to the lead the anamnesis is coupled to, not an unrelated open order', function () {
    $this->seed(TestSeeder::class);

    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $openStage = Stage::factory()->create(['is_won' => false, 'is_lost' => false]);
    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $openStage->id,
    ]);

    $anamnesis = Anamnesis::factory()->create([
        'lead_id'   => $lead->id,
        'sales_id'  => null,
        'order_id'  => null,
        'person_id' => $person->id,
    ]);

    $form = AnamnesisGvlForm::create(['anamnesis_id' => $anamnesis->id, 'gvl_form_id' => 'form-audit-1']);

    $created = Activity::where('type', ActivityType::SYSTEM->value)->latest('id')->first();
    expect($created->lead_id)->toBe($lead->id)
        ->and($created->order_id)->toBeNull()
        ->and($created->sales_lead_id)->toBeNull();

    $form->delete();

    $deleted = Activity::where('type', ActivityType::SYSTEM->value)->latest('id')->first();
    expect($deleted->id)->not->toBe($created->id)
        ->and($deleted->lead_id)->toBe($lead->id)
        ->and($deleted->order_id)->toBeNull();
});
