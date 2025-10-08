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

test('person search returns 400 for invalid field', function () {
    $resp = $this->getJson(route('admin.contacts.persons.search', [
        'search'       => 'foo:bar;',
        'searchFields' => 'foo:like;',
    ]));

    // Some controllers may not enforce 400; if not, relax to 200 OK and empty/ignored
    $status = $resp->getStatusCode();
    expect($status == 400)->toBeTrue();
});
