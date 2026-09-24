<?php

use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\Admin\DataGrids\Contact\PersonDataGrid;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);
    $this->user = makeUser(['view_permission' => 'global']);
    $this->actingAs($this->user, 'user');
});

test('name fields are filter-only columns', function () {
    $grid = app(PersonDataGrid::class);
    $grid->prepareColumns();

    $columns = collect($grid->getColumns())->keyBy(fn ($c) => $c->getIndex());

    foreach (['first_name', 'lastname_prefix', 'last_name', 'married_name_prefix', 'married_name'] as $field) {
        expect($columns[$field]->getFilterable())->toBeTrue()
            ->and($columns[$field]->getVisibility())->toBeFalse();
    }
});

test('datagrid filters persons by individual name fields', function (string $field, string $value) {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requires MySQL: PersonDataGrid uses CONCAT_WS');
    }

    $target = Person::factory()->create([$field => $value]);
    $other = Person::factory()->create([$field => 'zzzonbekend']);

    $response = test()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.contacts.persons.index', ['filters' => [$field => [$value]]]));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($target->id)
        ->and($ids)->not->toContain($other->id);
})->with([
    ['first_name', 'Xanthippe'],
    ['lastname_prefix', 'vander'],
    ['last_name', 'Qwertyson'],
    ['married_name_prefix', 'terre'],
    ['married_name', 'Plofmans'],
]);
