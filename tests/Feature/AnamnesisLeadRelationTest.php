<?php

namespace Tests\Feature;

use App\Models\Anamnesis;
use Database\Seeders\LeadChannelSeeder;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

test('it can create anamnesis with lead and retrieve relation', function () {
    $this->seed(TestSeeder::class);
    $stage = \Webkul\Lead\Models\Stage::first();
    $lead = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);
    $anamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id]);

    $this->assertDatabaseHas('anamnesis', [
        'id'      => $anamnesis->id,
        'lead_id' => $lead->id,
    ]);

    $this->assertInstanceOf(Lead::class, $anamnesis->lead);
    $this->assertEquals($lead->id, $anamnesis->lead->id);

});

test('anamnesis model uses english field names', function () {
    $this->seed(TestSeeder::class);
    $stage = \Webkul\Lead\Models\Stage::first();
    $lead = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);
    $anamnesis = Anamnesis::factory()->create([
        'lead_id'           => $lead->id,
        'height'            => 180,
        'weight'            => 75,
        'metals'            => 1,
        'medications'       => 0,
        'glaucoma'          => 1,
        'claustrophobia'    => 0,
        'metals_notes'      => 'Test metalen opmerking',
        'medications_notes' => 'Test medicijnen opmerking',
    ]);

    // Test that English field names work directly
    $this->assertEquals(180, $anamnesis->height);
    $this->assertEquals(75, $anamnesis->weight);
    $this->assertEquals(1, $anamnesis->metals);
    $this->assertEquals(0, $anamnesis->medications);
    $this->assertEquals(1, $anamnesis->glaucoma);
    $this->assertEquals(0, $anamnesis->claustrophobia);
    $this->assertEquals('Test metalen opmerking', $anamnesis->metals_notes);
    $this->assertEquals('Test medicijnen opmerking', $anamnesis->medications_notes);
});

test('it prevents duplicate anamnesis creation when attaching same person multiple times', function () {
    $this->seed(TestSeeder::class);
    $this->artisan('db:seed', ['--class' => LeadChannelSeeder::class]);

    $stage = \Webkul\Lead\Models\Stage::first();
    $lead = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);
    $person = Person::factory()->create();

    // Attach the same person multiple times (simulating concurrent requests)
    $lead->attachPersons([$person->id]);
    $lead->attachPersons([$person->id]);
    $lead->attachPersons([$person->id]);

    // Only one anamnesis should exist
    $anamnesisCount = Anamnesis::where('lead_id', $lead->id)
        ->where('person_id', $person->id)
        ->count();

    expect($anamnesisCount)->toBe(1);
});

test('database constraint prevents duplicate anamnesis insertion', function () {
    $this->seed(TestSeeder::class);
    $stage = \Webkul\Lead\Models\Stage::first();
    $lead = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);
    $person = Person::factory()->create();

    // Create first anamnesis
    $anamnesis1 = Anamnesis::create([
        'id'        => Str::uuid(),
        'lead_id'   => $lead->id,
        'person_id' => $person->id,
        'name'      => 'First anamnesis',
    ]);

    expect($anamnesis1)->toBeInstanceOf(Anamnesis::class)
        ->and(fn () => Anamnesis::create([
            'id'        => Str::uuid(),
            'lead_id'   => $lead->id,
            'person_id' => $person->id,
            'name'      => 'Duplicate anamnesis',
        ]))->toThrow(\Illuminate\Database\QueryException::class);

    // Second creation with same lead_id + person_id should fail
});

test('it allows multiple anamnesis for different lead-person combinations', function () {
    $this->seed(TestSeeder::class);
    $this->artisan('db:seed', ['--class' => LeadChannelSeeder::class]);

    // Get the first stage from the seeded data
    $stage = \Webkul\Lead\Models\Stage::first();
    expect($stage)->not->toBeNull();

    $lead1 = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);
    $lead2 = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);
    $person1 = Person::factory()->create();
    $person2 = Person::factory()->create();

    // Create anamnesis for different combinations
    $lead1->attachPersons([$person1->id]);
    $lead1->attachPersons([$person2->id]);
    $lead2->attachPersons([$person1->id]);
    $lead2->attachPersons([$person2->id]);

    // Check specific anamnesis counts for these leads only
    $lead1AnamnesisCount = Anamnesis::where('lead_id', $lead1->id)->count();
    $lead2AnamnesisCount = Anamnesis::where('lead_id', $lead2->id)->count();
    $totalAnamnesisForTheseLeads = $lead1AnamnesisCount + $lead2AnamnesisCount;

    expect($totalAnamnesisForTheseLeads)->toBe(4)
        ->and(Anamnesis::where('lead_id', $lead1->id)->where('person_id', $person1->id)->count())->toBe(1)
        ->and(Anamnesis::where('lead_id', $lead1->id)->where('person_id', $person2->id)->count())->toBe(1)
        ->and(Anamnesis::where('lead_id', $lead2->id)->where('person_id', $person1->id)->count())->toBe(1)
        ->and(Anamnesis::where('lead_id', $lead2->id)->where('person_id', $person2->id)->count())->toBe(1);

    // Each combination should have exactly one anamnesis
});
