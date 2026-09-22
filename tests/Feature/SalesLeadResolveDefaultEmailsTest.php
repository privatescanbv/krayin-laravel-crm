<?php

use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create();
    $this->pipeline = Pipeline::first();
    $this->stage = Stage::first();
});

function defaultSalesLeadEmail(array $emails): ?string
{
    foreach ($emails as $email) {
        if (! empty($email['is_default'])) {
            return $email['value'] ?? null;
        }
    }

    return $emails[0]['value'] ?? null;
}

test('resolveDefaultEmails uses contact person emails when set', function () {
    $contactPerson = Person::factory()->create([
        'emails' => [['value' => 'contact@example.com', 'is_default' => true]],
    ]);

    $salesLead = SalesLead::factory()->create([
        'contact_person_id' => $contactPerson->id,
        'pipeline_stage_id' => $this->stage->id,
        'user_id'           => $this->user->id,
    ]);

    expect(defaultSalesLeadEmail($salesLead->resolveDefaultEmails()))->toBe('contact@example.com');
});

test('resolveDefaultEmails uses linked person emails when no contact person is set', function () {
    $salesLead = SalesLead::factory()->create([
        'contact_person_id' => null,
        'pipeline_stage_id' => $this->stage->id,
        'user_id'           => $this->user->id,
    ]);

    $person = Person::factory()->create([
        'emails' => [['value' => 'linked@example.com', 'is_default' => true]],
    ]);

    $salesLead->attachPersons([$person->id]);

    expect(defaultSalesLeadEmail($salesLead->resolveDefaultEmails()))->toBe('linked@example.com');
});

test('resolveDefaultEmails returns empty array when no person is linked', function () {
    $salesLead = SalesLead::factory()->create([
        'contact_person_id' => null,
        'pipeline_stage_id' => $this->stage->id,
        'user_id'           => $this->user->id,
    ]);

    expect($salesLead->resolveDefaultEmails())->toBe([]);
});
