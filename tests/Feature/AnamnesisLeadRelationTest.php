<?php

namespace Tests\Feature;

use App\Models\Anamnesis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Lead\Models\Lead;

uses(RefreshDatabase::class);

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
