<?php

use App\Models\Anamnesis;
use App\Models\User;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->admin = User::factory()->create();
    actingAs($this->admin);
});

it('can show the sync anamnesis page with older anamneses', function () {
    // Arrange
    $person = Person::factory()->create();

    // Oudere anamnesis
    $leadOld = Lead::factory()->create();
    $leadOld->persons()->attach($person->id);

    $anamnesisOld = Anamnesis::factory()->create([
        'person_id'  => $person->id,
        'lead_id'    => $leadOld->id,
        'created_at' => now()->subDays(10),
        'height'     => 180,
        'weight'     => 80,
    ]);

    // Nieuwe anamnesis (current)
    $leadNew = Lead::factory()->create();
    $leadNew->persons()->attach($person->id);

    $anamnesisNew = Anamnesis::factory()->create([
        'person_id'  => $person->id,
        'lead_id'    => $leadNew->id,
        'created_at' => now(),
        'height'     => null, // Empty, to be filled
        'weight'     => 75, // Different value
    ]);

    // Act
    $response = get(route('admin.leads.sync-anamnesis-to-older-update', $anamnesisNew->person_id));

    // Assert
    $response->assertStatus(200);
    $response->assertViewIs('adminc::leads.sync-anamnesis');
    $response->assertViewHas('anamnesis', function ($viewAnamnesis) use ($anamnesisNew) {
        return $viewAnamnesis->id === $anamnesisNew->id;
    });
    $response->assertViewHas('olderAnamnises', function ($olderAnamnises) use ($anamnesisOld) {
        return $olderAnamnises->contains('id', $anamnesisOld->id);
    });
});

it('can sync specific fields from older anamnesis', function () {
    // Arrange
    $person = Person::factory()->create();

    // Oudere anamnesis
    $leadOld = Lead::factory()->create();
    $leadOld->persons()->attach($person->id);

    $anamnesisOld = Anamnesis::factory()->create([
        'person_id'  => $person->id,
        'lead_id'    => $leadOld->id,
        'created_at' => now()->subDay(),
        'height'     => 180,
        'weight'     => 80,
        'metals'     => 1,
    ]);

    // Nieuwe anamnesis (current)
    $leadNew = Lead::factory()->create();
    $leadNew->persons()->attach($person->id);

    $anamnesisNew = Anamnesis::factory()->create([
        'person_id'  => $person->id,
        'lead_id'    => $leadNew->id,
        'created_at' => now(),
        'height'     => 170, // To remain current
        'weight'     => 70,  // To be updated
        'metals'     => 0,   // To be updated
    ]);

    $data = [
        'choice' => [
            'height' => 'current',        // Keep 170
            'weight' => $anamnesisOld->id, // Take 80
            'metals' => $anamnesisOld->id, // Take 1
        ],
    ];

    $this->assertNotNull($anamnesisNew->person_id);
    // Act
    $response = postJson(route('admin.leads.sync-anamnesis-update', $anamnesisNew->person_id), $data);

    // Assert
    $response->assertStatus(200);
    $response->assertJson(['message' => 'Anamnesis succesvol bijgewerkt.']);

    $anamnesisNew->refresh();

    expect($anamnesisNew->height)->toBe(170)
        ->and($anamnesisNew->weight)->toBe(80)
        ->and($anamnesisNew->metals)->toBeTrue();
    // Casts to boolean in model
});
