<?php

use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Resource;
use Database\Seeders\TestSeeder;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->actingAs(getDefaultAdmin(), 'user');
});

test('clinic view lists artsen and medewerkers of this clinic only', function () {
    $clinic = Clinic::factory()->create();
    $other = Clinic::factory()->create();
    $department = ClinicDepartment::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Cardiologie']);
    $otherDepartment = ClinicDepartment::factory()->create(['clinic_id' => $other->id]);

    makeUser(['first_name' => 'Dr Jansen', 'last_name' => 'Test', 'resource_id' => Resource::factory()->create(['clinic_department_id' => $department->id])->id]);
    makeUser(['first_name' => 'Mevr Medewerker', 'last_name' => 'Test', 'clinic_id' => $clinic->id]);
    makeUser(['first_name' => 'Dr Elders', 'last_name' => 'Test', 'resource_id' => Resource::factory()->create(['clinic_department_id' => $otherDepartment->id])->id]);
    makeUser(['first_name' => 'Medewerker Elders', 'last_name' => 'Test', 'clinic_id' => $other->id]);

    $this->get(route('admin.clinics.view', $clinic->id))
        ->assertOk()
        ->assertSee('Personeel')
        ->assertSee('Dr Jansen')
        ->assertSee('Cardiologie')
        ->assertSee('Mevr Medewerker')
        ->assertViewHas('staff', fn ($staff) => $staff->pluck('name')->sort()->values()->all() === ['Dr Jansen Test', 'Mevr Medewerker Test']);
});

test('user view page shows the user', function () {
    $user = makeUser(['first_name' => 'Zoe', 'last_name' => 'Zicht']);

    $this->get(route('admin.settings.users.view', $user->id))
        ->assertOk()
        ->assertSee('Zoe Zicht')
        ->assertSee($user->email);
});

test('users overview offers a view action per user', function () {
    $user = makeUser(['first_name' => 'Zoe', 'last_name' => 'Zicht']);

    // The users grid has its own row template: it must render the view action too.
    $this->get(route('admin.settings.users.index'))->assertOk()->assertSee("action.index === 'view'", false);

    $records = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->getJson(route('admin.settings.users.index'))->assertOk()->json('records');
    $view = collect(collect($records)->firstWhere('id', $user->id)['actions'])->firstWhere('index', 'view');

    expect($view['url'])->toBe(route('admin.settings.users.view', $user->id));
});
