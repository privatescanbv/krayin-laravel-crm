<?php

namespace Tests\Feature;

use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->actingAs(makeUser(), 'user');
});

test('lead naar gewonnen zonder toegewezen persoon geeft 422', function () {
    $lead = Lead::factory()->create();
    $won = Stage::where('is_won', true)->firstOrFail();

    $this->putJson(route('admin.leads.stage.update', $lead->id), [
        'lead_pipeline_stage_id' => $won->id,
        'user_id'                => '',
    ])->assertStatus(422)->assertJsonValidationErrors('user_id');
});
