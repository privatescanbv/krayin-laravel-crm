<?php

use App\Jobs\LinkInboundEmailViaLlmJob;
use App\Services\Mail\EmailLlmLinkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;

uses(RefreshDatabase::class);

test('links email using llm extracted sender addresses', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $email = Email::create([
        'subject' => 'FW: Patient vraag',
        'from'    => ['name' => 'Medewerker', 'email' => 'medewerker@privatescan.nl'],
        'reply'   => '<p>Doorgestuurd bericht</p><p>Van: Jan Jansen &lt;patient@example.com&gt;</p>',
    ]);

    $service = Mockery::mock(EmailLlmLinkingService::class);
    $service->shouldReceive('extractAndLink')
        ->once()
        ->with(
            Mockery::on(fn ($model) => $model->id === $email->id),
            null,
            true,
            'automatic',
        )
        ->andReturn([
            'status'   => 'linked',
            'senders'  => [['email' => 'patient@example.com', 'name' => 'Jan', 'confidence' => 0.9, 'role' => 'original_sender']],
            'links'    => ['person_id' => $person->id],
            'metadata' => ['status' => 'linked'],
        ]);

    $job = new LinkInboundEmailViaLlmJob($email->id);
    $job->handle($service);
});

test('skips when email is already linked', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $email = Email::create([
        'subject'   => 'FW: Patient vraag',
        'from'      => ['name' => 'Medewerker', 'email' => 'medewerker@privatescan.nl'],
        'reply'     => 'Doorgestuurd bericht',
        'person_id' => $person->id,
    ]);

    $service = Mockery::mock(EmailLlmLinkingService::class);
    $service->shouldNotReceive('extractAndLink');

    $job = new LinkInboundEmailViaLlmJob($email->id);
    $job->handle($service);
});
