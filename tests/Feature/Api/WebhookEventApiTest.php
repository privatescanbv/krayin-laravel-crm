<?php

namespace Tests\Feature;

use App\Enums\FormType;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Webkul\Contact\Models\Person;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    config(['api.keys' => ['valid-api-key-123']]);
    config(['mail.send_only_accept' => '*@example.com']);
    Mail::fake();
});

test('webhooks event endpoint logs payload and returns ok', function () {
    Log::spy();

    // Create a person for the email
    $person = Person::factory()->create([
        'emails' => [['value' => 'test@example.com', 'is_default' => true]],
    ]);

    $payload = [
        'entity_type' => 'forms',
        'id'          => 123,
        'action'      => 'STATUS_UPDATE',
        'status'      => 'completed',
        'url'         => 'https://example.test/leads/123',
        'person_id'   => $person->id,
        'form_type'   => FormType::PrivateScan->value,
    ];

    $response = $this->withHeaders([
        'X-API-KEY' => 'valid-api-key-123',
        'Accept'    => 'application/json',
    ])->putJson(route('api.webhooks.event'), $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
        ]);

    // Verify the specific log call from EventWebhookController
    Log::shouldHaveReceived('info')
        ->with('Application webhook event received', Mockery::on(function ($context) {
            return isset($context['entity_type'])
                && $context['entity_type'] === 'forms'
                && isset($context['entity_id'])
                && $context['entity_id'] === 123
                && isset($context['action'])
                && $context['action'] === 'STATUS_UPDATE';
        }))
        ->atLeast()
        ->once();
});
