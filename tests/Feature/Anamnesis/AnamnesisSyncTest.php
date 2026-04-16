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

    // Oudere anamnesis — attach triggert pivot model dat de anamnesis aanmaakt
    $leadOld = Lead::factory()->create();
    $leadOld->attachPersons([$person->id]);
    $anamnesisOld = Anamnesis::where('lead_id', $leadOld->id)->where('person_id', $person->id)->firstOrFail();
    $anamnesisOld->height = 180;
    $anamnesisOld->weight = 80;
    $anamnesisOld->created_at = now()->subDays(10);
    $anamnesisOld->save();

    // Nieuwe anamnesis (current)
    $leadNew = Lead::factory()->create();
    $leadNew->attachPersons([$person->id]);
    $anamnesisNew = Anamnesis::where('lead_id', $leadNew->id)->where('person_id', $person->id)->firstOrFail();
    $anamnesisNew->height = null;
    $anamnesisNew->weight = 75;
    $anamnesisNew->created_at = now();
    $anamnesisNew->save();

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
    $leadOld->attachPersons([$person->id]);
    $anamnesisOld = Anamnesis::where('lead_id', $leadOld->id)->where('person_id', $person->id)->firstOrFail();
    $anamnesisOld->height = 180;
    $anamnesisOld->weight = 80;
    $anamnesisOld->metals = 1;
    $anamnesisOld->created_at = now()->subDay();
    $anamnesisOld->save();

    // Nieuwe anamnesis (current)
    $leadNew = Lead::factory()->create();
    $leadNew->attachPersons([$person->id]);
    $anamnesisNew = Anamnesis::where('lead_id', $leadNew->id)->where('person_id', $person->id)->firstOrFail();
    $anamnesisNew->height = 170;
    $anamnesisNew->weight = 70;
    $anamnesisNew->metals = 0;
    $anamnesisNew->created_at = now();
    $anamnesisNew->save();

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
