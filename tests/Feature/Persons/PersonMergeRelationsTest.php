<?php

namespace Tests\Feature\Persons;

use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();
    $this->personRepository = app(PersonRepository::class);
});

test('merging persons transfers lead_persons pivot to primary person', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $lead = Lead::factory()->create();

    DB::table('lead_persons')->insert([
        'lead_id'   => $lead->id,
        'person_id' => $duplicate->id,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(DB::table('lead_persons')->where('lead_id', $lead->id)->where('person_id', $primary->id)->exists())->toBeTrue();
    expect(DB::table('lead_persons')->where('lead_id', $lead->id)->where('person_id', $duplicate->id)->exists())->toBeFalse();
    expect(Person::withTrashed()->find($duplicate->id)->trashed())->toBeTrue();
});

test('merging persons transfers saleslead_persons pivot to primary person', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $salesLead = SalesLead::factory()->create();

    DB::table('saleslead_persons')->insert([
        'saleslead_id' => $salesLead->id,
        'person_id'    => $duplicate->id,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(DB::table('saleslead_persons')->where('saleslead_id', $salesLead->id)->where('person_id', $primary->id)->exists())->toBeTrue();
    expect(DB::table('saleslead_persons')->where('saleslead_id', $salesLead->id)->where('person_id', $duplicate->id)->exists())->toBeFalse();
});

test('merging persons transfers contact_person_id on leads', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $lead = Lead::factory()->create(['contact_person_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($lead->fresh()->contact_person_id)->toBe($primary->id);
});

test('merging persons transfers contact_person_id on salesleads', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $salesLead = SalesLead::factory()->create(['contact_person_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($salesLead->fresh()->contact_person_id)->toBe($primary->id);
});

test('merging persons does not create duplicate lead_persons when both already linked', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $lead = Lead::factory()->create();

    DB::table('lead_persons')->insert(['lead_id' => $lead->id, 'person_id' => $primary->id]);
    DB::table('lead_persons')->insert(['lead_id' => $lead->id, 'person_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    $count = DB::table('lead_persons')->where('lead_id', $lead->id)->where('person_id', $primary->id)->count();
    expect($count)->toBe(1);
});
