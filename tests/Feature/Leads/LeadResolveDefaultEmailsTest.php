<?php

use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create();
    $this->pipeline = Pipeline::first();
    $this->stage = Stage::first();

    if (! $this->pipeline || ! $this->stage) {
        throw new Exception('Pipeline or Stage not found. Ensure TestSeeder provisions them.');
    }
});

function defaultEmailFromResolved(array $emails): ?string
{
    foreach ($emails as $email) {
        if (! empty($email['is_default'])) {
            return $email['value'] ?? null;
        }
    }

    return $emails[0]['value'] ?? null;
}

test('resolveDefaultEmails uses contact person when no linked persons', function () {
    $contactPerson = Person::factory()->create([
        'emails' => [['value' => 'contact@example.com', 'is_default' => true]],
    ]);

    $lead = Lead::factory()->create([
        'contact_person_id'      => $contactPerson->id,
        'emails'                 => [['value' => 'lead@example.com', 'is_default' => true]],
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    expect(defaultEmailFromResolved($lead->resolveDefaultEmails()))->toBe('contact@example.com');
});

test('resolveDefaultEmails uses lead email when no linked persons and no contact person', function () {
    $lead = Lead::factory()->create([
        'contact_person_id'      => null,
        'emails'                 => [['value' => 'lead@example.com', 'is_default' => true]],
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    expect(defaultEmailFromResolved($lead->resolveDefaultEmails()))->toBe('lead@example.com');
});

test('resolveDefaultEmails uses highest match score person when linked persons exist without contact person', function () {
    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'lastname_prefix'        => 'van',
        'contact_person_id'      => null,
        'emails'                 => [['value' => 'lead@example.com', 'is_default' => true]],
        'phones'                 => [['value' => '0612345678', 'is_default' => true]],
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    $bestMatchPerson = Person::factory()->create([
        'first_name'      => 'John',
        'last_name'       => 'Doe',
        'lastname_prefix' => 'van',
        'emails'          => [['value' => 'best-match@example.com', 'is_default' => true]],
        'phones'          => [['value' => '0612345678', 'is_default' => true]],
    ]);

    $partialMatchPerson = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'partial-match@example.com', 'is_default' => true]],
    ]);

    $lead->attachPersons([$partialMatchPerson->id, $bestMatchPerson->id]);

    expect(defaultEmailFromResolved($lead->resolveDefaultEmails()))->toBe('best-match@example.com');
});

test('resolveDefaultEmails prefers contact person over linked persons', function () {
    $contactPerson = Person::factory()->create([
        'emails' => [['value' => 'contact@example.com', 'is_default' => true]],
    ]);

    $linkedPerson = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'linked@example.com', 'is_default' => true]],
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'contact_person_id'      => $contactPerson->id,
        'emails'                 => [['value' => 'lead@example.com', 'is_default' => true]],
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    $lead->attachPersons([$linkedPerson->id]);

    expect(defaultEmailFromResolved($lead->resolveDefaultEmails()))->toBe('contact@example.com');
});
