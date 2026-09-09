<?php

use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->user = User::factory()->active()->create();
});

test('a partial address is a validation error, not a logged error', function () {
    $lead = Lead::factory()->create(['user_id' => $this->user->id]);

    // The `sentry` log channel turns Log::error into a Bugsink issue - a bad form
    // submission must never reach it.
    Log::spy();

    $response = $this->actingAs($this->user, 'user')
        ->from(route('admin.leads.index'))
        ->put(route('admin.leads.update', $lead->id), [
            'first_name'    => 'Anne',
            'last_name'     => 'Vrolijk',
            'department_id' => $lead->department_id,
            'emails'        => [['value' => 'test@example.com', 'label' => 'eigen']],
            'address'       => [
                '_clear'       => '0',
                'postal_code'  => '2564CX',
                'house_number' => '174',
                'street'       => '',
                'city'         => '',
                'state'        => '',
                'country'      => '',
            ],
        ]);

    // The user is sent back with the field errors shown - not a 500 page.
    $response->assertRedirect(route('admin.leads.index'));
    $response->assertSessionHasErrors(['address.street', 'address.city', 'address.country']);

    // The `sentry` log channel turns Log::error into a Bugsink issue - a bad form
    // submission must never reach it.
    Log::shouldNotHaveReceived('error');
});
