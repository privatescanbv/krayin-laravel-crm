<?php

use App\Enums\ContactLabel;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Lead\Models\Lead;

test('lead duplicates merge view loads when primary lead has phone numbers', function () {
    $this->seed(TestSeeder::class);

    Lead::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);

    $lead = Lead::factory()->create([
        'phones' => [
            ['value' => '+31651441908', 'label' => ContactLabel::Eigen->value],
        ],
    ]);

    $response = $this->get(route('admin.leads.duplicates.index', $lead->id));

    $response->assertOk();
});
