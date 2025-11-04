<?php

use App\Enums\ContactLabel;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Authenticate on admin guard
    $this->user = User::factory()->create(['name' => 'Admin Tester']);
    $this->actingAs($this->user, 'user');
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    $this->withoutMiddleware(Authenticate::class);
});

test('person search by name fields works', function () {
    $p = Person::factory()->create([
        'first_name' => 'Eva',
        'last_name'  => 'Kuijer',
        'user_id'    => $this->user->id,
    ]);

    $resp = $this->getJson(route('admin.contacts.persons.search', [
        'search'       => 'name:Kuijer;',
        'searchFields' => 'first_name:like;last_name:like;married_name:like;',
    ]));

    $resp->assertOk();
    $ids = collect($resp->json('data'))->pluck('id');
    expect($ids)->toContain($p->id);
});

test('person search by phone token works', function () {
    $p = Person::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Tester',
        'phones'     => [
            ['value' => '0687654321', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'user_id'    => $this->user->id,
    ]);

    // Use the convenience token path ("phone:") to ensure normalization is exercised
    $resp = $this->getJson(route('admin.contacts.persons.search', [
        'search' => 'phone:0687654321;',
    ]));

    $resp->assertOk();
    $ids = collect($resp->json('data'))->pluck('id');
    expect($ids)->toContain($p->id);
});

test('person search by firstname and lastname tokens works', function () {
    $p = Person::factory()->create([
        'first_name' => 'EvaToken',
        'last_name'  => 'KuyperToken',
        'user_id'    => $this->user->id,
    ]);

    $resp = $this->getJson(route('admin.contacts.persons.search', [
        'search' => 'firstname:EvaToken;lastname:KuyperToken;',
    ]));

    $resp->assertOk();
    $ids = collect($resp->json('data'))->pluck('id');
    expect($ids)->toContain($p->id);
});

test('person search by lastname token also matches married_name', function () {
    $p = Person::factory()->create([
        'first_name'      => 'Anna',
        'last_name'       => 'Maas',
        'married_name'    => 'De Bruin',
        'user_id'         => $this->user->id,
    ]);

    // Should match on married_name using lastname: token
    $resp = $this->getJson(route('admin.contacts.persons.search', [
        'search' => 'lastname:De Bruin;',
    ]));

    $resp->assertOk();
    $ids = collect($resp->json('data'))->pluck('id');
    expect($ids)->toContain($p->id);
});

test('person search accepts plain email via query param', function () {
    $email = 'jane.query@example.com';
    $p = Person::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Query',
        'emails'     => [
            ['value' => $email, 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'user_id'    => $this->user->id,
    ]);

    // Send as plain query so backend's email-normalization path is exercised
    $resp = $this->getJson(route('admin.contacts.persons.search', [
        'query' => $email,
    ]));

    $resp->assertOk();
    $ids = collect($resp->json('data'))->pluck('id');
    expect($ids)->toContain($p->id);
});

test('person search accepts plain name via query param', function () {
    $p = Person::factory()->create([
        'first_name' => 'Karel',
        'last_name'  => 'Zoeker',
        'user_id'    => $this->user->id,
    ]);

    $resp = $this->getJson(route('admin.contacts.persons.search', [
        'query' => 'Karel Zoek',
    ]));

    $resp->assertOk();
    $ids = collect($resp->json('data'))->pluck('id');
    expect($ids)->toContain($p->id);
});

test('person search matches partial email via query param', function () {
    $email = 'harmkevdmeer@outlook.com';
    $p = Person::factory()->create([
        'first_name' => 'Harmke',
        'last_name'  => 'vd Meer',
        'emails'     => [
            ['value' => $email, 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'user_id'    => $this->user->id,
    ]);

    // Search by partial local-part of the email (as users often do)
    $resp = $this->getJson(route('admin.contacts.persons.search', [
        'query' => 'harmkev',
    ]));

    $resp->assertOk();
    $ids = collect($resp->json('data'))->pluck('id');
    expect($ids)->toContain($p->id);
});

test('person search by organization.name works', function () {
    $org = Organization::factory()->create(['name' => 'Kuijer BV']);
    $p = Person::factory()->create([
        'first_name'      => 'Piet',
        'last_name'       => 'Jansen',
        'organization_id' => $org->id,
        'user_id'         => $this->user->id,
    ]);

    $resp = $this->getJson(route('admin.contacts.persons.search', [
        'search'       => 'organization.name:Kuijer;',
        'searchFields' => 'organization.name:like;',
    ]));

    $resp->assertOk();
    $ids = collect($resp->json('data'))->pluck('id');
    expect($ids)->toContain($p->id);
});

test('person search by email and phone works', function () {
    $p = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [
            ['value' => 'john.doe@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'phones' => [
            ['value' => '0687654321', 'label' => ContactLabel::Relatie->value, 'is_default' => true],
        ],
        'user_id'    => $this->user->id,
    ]);

    // Email
    $respEmail = $this->getJson(route('admin.contacts.persons.search', [
        'search'       => 'emails:john.doe@example.com;',
        'searchFields' => 'emails:like;',
    ]));
    $respEmail->assertOk();
    $idsEmail = collect($respEmail->json('data'))->pluck('id');
    expect($idsEmail)->toContain($p->id);

    // Phone
    $respPhone = $this->getJson(route('admin.contacts.persons.search', [
        'search'       => 'phones:0687654321;',
        'searchFields' => 'phones:like;',
    ]));
    $respPhone->assertOk();
    $idsPhone = collect($respPhone->json('data'))->pluck('id');
    expect($idsPhone)->toContain($p->id);
});

test('person search by phone works', function () {
    $p = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [
            ['value' => 'john.doe@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'phones' => [
            ['value' => '0687654321', 'label' => ContactLabel::Relatie->value, 'is_default' => true],
        ],
        'user_id'    => $this->user->id,
    ]);

    // Email
    $respPhone = $this->getJson(route('admin.contacts.persons.search', [
        'query'       => '6876543',
    ]));
    $respPhone->assertOk();
    $personIdsFound = collect($respPhone->json('data'))->pluck('id');
    expect($personIdsFound)->toContain($p->id);
});

test('person search returns 400 for invalid field', function () {
    $resp = $this->getJson(route('admin.contacts.persons.search', [
        'search'       => 'foo:bar;',
        'searchFields' => 'foo:like;',
    ]));

    // Some controllers may not enforce 400; if not, relax to 200 OK and empty/ignored
    $status = $resp->getStatusCode();
    expect($status == 400)->toBeTrue();
});
