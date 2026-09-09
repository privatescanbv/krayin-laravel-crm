<?php

use App\Enums\ContactLabel;
use App\Services\PersonDuplicateCacheService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

use function Pest\Laravel\get;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);

    $role = Role::factory()->create([
        'permission_type' => 'all',
        'permissions'     => null,
    ]);

    $this->user = User::factory()->create([
        'role_id'         => $role->id,
        'view_permission' => 'global',
        'status'          => 1,
    ]);

    $this->actingAs($this->user, 'user');
});

test('werkbakken shows a duplicates banner with a link to the persons filter', function () {
    $p1 = Person::factory()->create([
        'emails' => [['value' => 'banner.dup@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);
    Person::factory()->create([
        'emails' => [['value' => 'banner.dup@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    app(PersonDuplicateCacheService::class)->getCachedDuplicates($p1->id);

    get(route('admin.operational-dashboard.index'))
        ->assertOk()
        ->assertSee('personen met mogelijke duplicaten', false)
        ->assertSee('Bekijk & opruimen', false)
        ->assertSee('filters', false)
        ->assertSee('has_duplicates', false);
});

test('werkbakken hides the duplicates banner when there are none', function () {
    Person::factory()->create();

    get(route('admin.operational-dashboard.index'))
        ->assertOk()
        ->assertDontSee('mogelijke duplicaten', false)
        ->assertDontSee('Bekijk & opruimen', false);
});

test('persons index shows the duplicates banner', function () {
    $p1 = Person::factory()->create([
        'emails' => [['value' => 'persons.banner@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);
    Person::factory()->create([
        'emails' => [['value' => 'persons.banner@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    app(PersonDuplicateCacheService::class)->getCachedDuplicates($p1->id);

    get(route('admin.contacts.persons.index'))
        ->assertOk()
        ->assertSee('personen met mogelijke duplicaten', false)
        ->assertSee('Bekijk & opruimen', false);
});

test('persons datagrid can filter to duplicate persons', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requires MySQL: PersonDataGrid uses CONCAT_WS');
    }

    $duplicate = Person::factory()->create([
        'emails' => [['value' => 'grid.dup@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);
    $other = Person::factory()->create();

    Person::factory()->create([
        'emails' => [['value' => 'grid.dup@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    app(PersonDuplicateCacheService::class)->getCachedDuplicates($duplicate->id);

    $response = test()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.contacts.persons.index', [
            'filters' => ['has_duplicates' => ['1']],
        ]));

    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($duplicate->id)
        ->and($ids)->not->toContain($other->id);
});

test('opening the persons list does not change the duplicate flags', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requires MySQL: PersonDataGrid uses CONCAT_WS');
    }

    $a = Person::factory()->create([
        'emails' => [['value' => 'list.readonly@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);
    $b = Person::factory()->create([
        'emails' => [['value' => 'list.readonly@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    // Flags deliberately left off: the list must render from the column, not detect (and write).
    Person::withoutTimestamps(fn () => Person::whereIn('id', [$a->id, $b->id])->update(['has_duplicates' => false]));

    test()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.contacts.persons.index', ['filters' => ['has_duplicates' => ['0']]]))
        ->assertOk();

    expect((bool) $a->fresh()->has_duplicates)->toBeFalse()
        ->and((bool) $b->fresh()->has_duplicates)->toBeFalse();
});
