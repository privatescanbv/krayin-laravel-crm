<?php

use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);
    $this->user = makeUser(['view_permission' => 'global']);
    $this->actingAs($this->user, 'user');
});

/**
 * Hit the persons datagrid with AJAX headers (required for JSON response).
 */
function personsDatagridRequest(array $filters): TestResponse
{
    return test()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.contacts.persons.index', ['filters' => $filters]));
}

// These tests require MySQL — PersonDataGrid uses CONCAT_WS which SQLite does not support.
// The filter auto-fill and date format logic is covered by the unit tests in PersonDataGridTest.php.

test('datagrid finds person when filtering by exact birthday using single from date', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requires MySQL: PersonDataGrid uses CONCAT_WS');
    }
    $target = Person::factory()->create(['date_of_birth' => '2008-05-16']);
    $other = Person::factory()->create(['date_of_birth' => '1990-01-01']);

    $response = personsDatagridRequest(['date_of_birth' => [['2008-05-16', '']]]);
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($target->id)
        ->and($ids)->not->toContain($other->id);
});

test('datagrid finds person when filtering by exact birthday using single to date', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requires MySQL: PersonDataGrid uses CONCAT_WS');
    }

    $target = Person::factory()->create(['date_of_birth' => '2008-05-16']);
    $other = Person::factory()->create(['date_of_birth' => '1990-01-01']);

    $response = personsDatagridRequest(['date_of_birth' => [['', '2008-05-16']]]);
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($target->id)
        ->and($ids)->not->toContain($other->id);
});

test('datagrid finds all persons within a birthday date range', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requires MySQL: PersonDataGrid uses CONCAT_WS');
    }

    $inRange1 = Person::factory()->create(['date_of_birth' => '1985-03-01']);
    $inRange2 = Person::factory()->create(['date_of_birth' => '1985-03-31']);
    $outside = Person::factory()->create(['date_of_birth' => '1990-06-15']);

    $response = personsDatagridRequest(['date_of_birth' => [['1985-03-01', '1985-03-31']]]);
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($inRange1->id)
        ->toContain($inRange2->id)
        ->not->toContain($outside->id);
});
