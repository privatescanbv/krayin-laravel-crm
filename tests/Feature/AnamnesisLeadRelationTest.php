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

test('anamnesis model uses english field names', function () {
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
