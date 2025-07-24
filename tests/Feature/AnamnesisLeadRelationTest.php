<?php

namespace Tests\Feature;

use App\Models\Anamnesis;
use Webkul\Lead\Models\Lead;

test('it can create anamnesis with lead and retrieve relation', function () {
    $lead = Lead::factory()->create();
    $anamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id]);

    $this->assertDatabaseHas('anamnesis', [
        'id'      => $anamnesis->id,
        'lead_id' => $lead->id,
    ]);

    $this->assertInstanceOf(Lead::class, $anamnesis->lead);
    $this->assertEquals($lead->id, $anamnesis->lead->id);

});

test('it maps dutch field names to english database columns', function () {
    $lead = Lead::factory()->create();
    $anamnesis = Anamnesis::factory()->create([
        'lead_id' => $lead->id,
        'height' => 180,
        'weight' => 75,
        'metals' => 1,
        'medications' => 0,
        'glaucoma' => 1,
        'claustrophobia' => 0,
        'metals_notes' => 'Test metalen opmerking',
        'medications_notes' => 'Test medicijnen opmerking',
    ]);

    // Test Dutch field name accessors
    $this->assertEquals(180, $anamnesis->lengte);
    $this->assertEquals(75, $anamnesis->gewicht);
    $this->assertEquals(1, $anamnesis->metalen);
    $this->assertEquals(0, $anamnesis->medicijnen);
    $this->assertEquals(1, $anamnesis->glaucoom);
    $this->assertEquals(0, $anamnesis->claustrofobie);
    $this->assertEquals('Test metalen opmerking', $anamnesis->opm_metalen_c);
    $this->assertEquals('Test medicijnen opmerking', $anamnesis->opm_medicijnen_c);
    
    // Test that English field names still work
    $this->assertEquals(180, $anamnesis->height);
    $this->assertEquals(75, $anamnesis->weight);
    $this->assertEquals(1, $anamnesis->metals);
    $this->assertEquals(0, $anamnesis->medications);
});

test('controller can update anamnesis with dutch field names', function () {
    $lead = Lead::factory()->create();
    $anamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id]);

    // Simulate form data with Dutch field names
    $dutchFormData = [
        'name' => 'Test Anamnesis',
        'description' => 'Test beschrijving',
        'lengte' => 175,
        'gewicht' => 70,
        'metalen' => 1,
        'medicijnen' => 0,
        'glaucoom' => 1,
        'claustrofobie' => 0,
        'dormicum' => 1,
        'hart_operatie_c' => 0,
        'implantaat_c' => 1,
        'operaties_c' => 0,
        'hart_erfelijk' => 1,
        'vaat_erfelijk' => 0,
        'tumoren_erfelijk' => 1,
        'allergie_c' => 0,
        'rugklachten' => 1,
        'heart_problems' => 0,
        'smoking' => 1,
        'diabetes' => 0,
        'spijsverteringsklachten' => 1,
        'actief' => 1,
        'opm_metalen_c' => 'Test metalen opmerking',
        'opm_medicijnen_c' => 'Test medicijnen opmerking',
    ];

    $response = $this->put(route('admin.anamnesis.update', $anamnesis->id), $dutchFormData);

    // Check if redirect is successful
    $response->assertRedirect(route('admin.leads.view', $anamnesis->lead_id));

    // Verify data was saved correctly with English field names in database
    $anamnesis->refresh();
    $this->assertEquals('Test Anamnesis', $anamnesis->name);
    $this->assertEquals('Test beschrijving', $anamnesis->description);
    $this->assertEquals(175, $anamnesis->height);
    $this->assertEquals(70, $anamnesis->weight);
    $this->assertEquals(1, $anamnesis->metals);
    $this->assertEquals(0, $anamnesis->medications);
    $this->assertEquals(1, $anamnesis->glaucoma);
    $this->assertEquals(0, $anamnesis->claustrophobia);
    $this->assertEquals('Test metalen opmerking', $anamnesis->metals_notes);
    $this->assertEquals('Test medicijnen opmerking', $anamnesis->medications_notes);
    
    // Verify Dutch accessors still work
    $this->assertEquals(175, $anamnesis->lengte);
    $this->assertEquals(70, $anamnesis->gewicht);
    $this->assertEquals(1, $anamnesis->metalen);
    $this->assertEquals(0, $anamnesis->medicijnen);
});
