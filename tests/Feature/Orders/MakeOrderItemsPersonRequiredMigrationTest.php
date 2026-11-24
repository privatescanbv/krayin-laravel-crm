<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesLead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

uses(RefreshDatabase::class);

function personRequiredMigration(): Migration
{
    return require database_path('migrations/2026_11_24_000000_make_order_items_person_id_required.php');
}

/**
 * The database is already migrated, so go back to the nullable state to test the data fix.
 */
function itemWithoutPerson(?SalesLead $sale): int
{
    $order = Order::factory()->create(['sales_lead_id' => $sale?->id]);
    $item = OrderItem::factory()->create(['order_id' => $order->id]);
    DB::table('order_items')->where('id', $item->id)->update(['person_id' => null]);

    return $item->id;
}

beforeEach(function () {
    personRequiredMigration()->down();
});

it('fills items from the only person of the sale, the first person of a sale with several, or else the lead', function () {
    $single = SalesLead::factory()->create();
    $only = Person::factory()->create();
    $single->persons()->attach($only->id);

    $several = SalesLead::factory()->create();
    [$first, $second] = Person::factory()->count(2)->create();
    $several->persons()->attach($first->id);
    $several->persons()->attach($second->id);

    $lead = Lead::factory()->create();
    $viaLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $leadPerson = Person::factory()->create();
    DB::table('lead_persons')->insert(['lead_id' => $lead->id, 'person_id' => $leadPerson->id]);

    $a = itemWithoutPerson($single);
    $b = itemWithoutPerson($several);
    $c = itemWithoutPerson($viaLead);
    $keep = OrderItem::factory()->create(['person_id' => $second->id]);

    personRequiredMigration()->up();

    $person = fn (int $id) => DB::table('order_items')->where('id', $id)->value('person_id');

    expect($person($a))->toBe($only->id)
        ->and($person($b))->toBe($first->id)
        ->and($person($c))->toBe($leadPerson->id)
        ->and($person($keep->id))->toBe($second->id);
});

it('stops and changes nothing when an item has no person anywhere', function () {
    $sale = SalesLead::factory()->create();
    $person = Person::factory()->create();
    $sale->persons()->attach($person->id);
    $fixable = itemWithoutPerson($sale);
    $lost = itemWithoutPerson(SalesLead::factory()->create());

    expect(fn () => personRequiredMigration()->up())->toThrow(RuntimeException::class, "item {$lost}");

    expect(DB::table('order_items')->where('id', $fixable)->value('person_id'))->toBeNull();
});

it('makes the column required and refuses to hard delete a person with order items', function () {
    personRequiredMigration()->up();

    $person = Person::factory()->create();
    $item = OrderItem::factory()->create(['person_id' => $person->id]);

    expect(fn () => DB::table('order_items')->insert([
        'order_id' => $item->order_id, 'quantity' => 1, 'total_price' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    DB::statement('PRAGMA foreign_keys = ON');
    expect(fn () => DB::table('persons')->where('id', $person->id)->delete())->toThrow(QueryException::class);
});
