<?php

use App\Enums\RoleCode;
use Database\Seeders\TestSeeder;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\User\Models\Role;
use Webkul\User\Repositories\UserRepository;

beforeEach(function (): void {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);
    $this->actingAs(getDefaultAdmin(), 'user');

    $this->artsRole = Role::where('code', RoleCode::ARTS->value)->firstOrFail();
    $this->kliniekRole = Role::where('code', RoleCode::KLINIEK_MEDEWERKER->value)->firstOrFail();
});

test('allActiveUsers excludes arts and kliniek medewerker users', function (): void {
    $arts = makeUser(['role_id' => $this->artsRole->id, 'first_name' => 'Arts', 'last_name' => 'Gebruiker']);
    $kliniek = makeUser(['role_id' => $this->kliniekRole->id, 'first_name' => 'Kliniek', 'last_name' => 'Medewerker']);
    $regular = makeUser(['first_name' => 'Gewone', 'last_name' => 'Gebruiker']);

    $ids = app(UserRepository::class)->allActiveUsers()->pluck('id');

    expect($ids)->not->toContain($arts->id)
        ->not->toContain($kliniek->id)
        ->toContain($regular->id);
});

test('allAsOptions excludes arts and kliniek medewerker users', function (): void {
    $arts = makeUser(['role_id' => $this->artsRole->id]);
    $kliniek = makeUser(['role_id' => $this->kliniekRole->id]);
    $regular = makeUser();

    $ids = collect(app(UserRepository::class)->allAsOptions())->pluck('id');

    expect($ids)->not->toContain($arts->id)
        ->not->toContain($kliniek->id)
        ->toContain($regular->id);
});

test('the user search endpoint used by the activity "assigned to" filter excludes arts and kliniek medewerker users', function (): void {
    $arts = makeUser(['role_id' => $this->artsRole->id, 'first_name' => 'ArtsZoek']);
    $kliniek = makeUser(['role_id' => $this->kliniekRole->id, 'first_name' => 'KliniekZoek']);
    $regular = makeUser(['first_name' => 'RegulierZoek']);

    $response = $this->getJson(route('admin.settings.users.search'))->assertOk();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->not->toContain($arts->id)
        ->not->toContain($kliniek->id)
        ->toContain($regular->id);
});

test('excludingClinicRoles filters out arts and kliniek medewerker when chained like the datagrid lookup does', function (): void {
    // Mirrors the query shape DataGridController::lookUp builds for a searchable_dropdown
    // filter backed by UserRepository (e.g. the lead "Sales Person" filter).
    $arts = makeUser(['role_id' => $this->artsRole->id, 'first_name' => 'ArtsLookup']);
    $kliniek = makeUser(['role_id' => $this->kliniekRole->id, 'first_name' => 'KliniekLookup']);
    $regular = makeUser(['first_name' => 'RegulierLookup']);

    $repository = app(UserRepository::class);

    $query = $repository
        ->select(['first_name as label', 'first_name as value'])
        ->where('first_name', 'LIKE', '%Lookup%');

    $names = $repository->excludingClinicRoles($query)->get()->pluck('label');

    expect($names)->not->toContain($arts->first_name)
        ->not->toContain($kliniek->first_name)
        ->toContain($regular->first_name);
});
