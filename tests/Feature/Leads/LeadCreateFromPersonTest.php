<?php

use App\Models\Address;
use App\Models\Order;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->actingAs(makeUser(), 'user');
});

test('create lead page prefills the person from person_id', function () {
    $person = Person::factory()->create();

    $this->get(route('admin.leads.create', ['person_id' => $person->id]))
        ->assertOk()
        ->assertSee($person->name);
});

test('person view has a new lead link for that person', function () {
    $person = Person::factory()->create();

    $this->get(route('admin.contacts.persons.view', $person->id))
        ->assertOk()
        ->assertSee('person_id='.$person->id, false);
});

test('order with one person links directly, with several persons lists them all', function () {
    $order = Order::factory()->create();
    [$a, $b] = Person::factory()->count(2)->create();

    $order->salesLead->persons()->attach($a->id);
    $this->get(route('admin.orders.view', $order->id))
        ->assertOk()
        ->assertSee('person_id='.$a->id, false)
        ->assertDontSee('person_id='.$b->id, false);

    $order->salesLead->persons()->attach($b->id);
    $this->get(route('admin.orders.view', $order->id))
        ->assertOk()
        ->assertSee('person_id='.$a->id, false)
        ->assertSee('person_id='.$b->id, false);
});

test('a person from person_id is linked immediately without picking a suggestion', function () {
    $person = Person::factory()->create();

    $this->get(route('admin.leads.create', ['person_id' => $person->id]))
        ->assertOk()
        ->assertSee('this.selectedPersonId = p.id;', false)
        ->assertSee('Lead wordt gekoppeld aan:', false);
});

test('the address of the person from person_id is passed to the form', function () {
    $person = Person::factory()->create();
    $person->address()->associate(Address::create([
        'street' => 'Teststraat', 'house_number' => '12', 'postal_code' => '1234AB', 'city' => 'Utrecht',
    ]))->save();

    $this->get(route('admin.leads.create', ['person_id' => $person->id]))
        ->assertOk()
        ->assertSee('Teststraat', false)
        ->assertSee('1234AB', false);
});
