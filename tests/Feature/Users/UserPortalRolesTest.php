<?php

use App\Enums\ResourceType as ResourceTypeEnum;
use App\Enums\RoleCode;
use App\Models\Clinic;
use App\Models\Resource;
use App\Models\ResourceType;
use Database\Seeders\TestSeeder;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    actingAs(User::factory()->create(), 'user');

    $this->artsRole = Role::where('code', RoleCode::ARTS->value)->firstOrFail();
    $this->kliniekRole = Role::where('code', RoleCode::KLINIEK_MEDEWERKER->value)->firstOrFail();
    $this->otherRole = Role::whereNull('code')->firstOrFail();

    $artsenType = ResourceType::where('name', ResourceTypeEnum::ARTSEN->label())->firstOrFail();
    $mriType = ResourceType::where('name', ResourceTypeEnum::MRI_SCANNER->label())->firstOrFail();

    $this->artsenTypeId = $artsenType->id;
    $this->activeClinic = Clinic::factory()->create(['is_active' => true]);
    $this->artsResource = Resource::factory()->create(['resource_type_id' => $artsenType->id, 'clinic_id' => $this->activeClinic->id]);
    $this->mriResource = Resource::factory()->create(['resource_type_id' => $mriType->id, 'clinic_id' => $this->activeClinic->id]);
});

function artsPayload(array $overrides = []): array
{
    return array_merge([
        'email'            => 'arts@example.com',
        'first_name'       => 'Dokter',
        'last_name'        => 'Test',
        'view_permission'  => 'global',
        'groups'           => [],
        'password'         => 'Secret123!',
        'confirm_password' => 'Secret123!',
    ], $overrides);
}

it('creates an arts user linked to an ARTSEN resource and its clinic', function () {
    $this->postJson(route('admin.settings.users.store'), artsPayload([
        'role_id'     => $this->artsRole->id,
        'resource_id' => $this->artsResource->id,
    ]))->assertOk();

    $user = User::where('email', 'arts@example.com')->firstOrFail();

    expect($user->resource_id)->toBe($this->artsResource->id)
        ->and($user->resource->clinic->id)->toBe($this->artsResource->clinicDepartment->clinic_id);
});

it('requires a resource for the arts role', function () {
    $this->postJson(route('admin.settings.users.store'), artsPayload(['role_id' => $this->artsRole->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('resource_id');
});

it('rejects a resource that is not of type ARTSEN', function () {
    $this->postJson(route('admin.settings.users.store'), artsPayload([
        'role_id'     => $this->artsRole->id,
        'resource_id' => $this->mriResource->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors('resource_id');
});

it('rejects a resource already linked to another user', function () {
    User::factory()->create(['role_id' => $this->artsRole->id, 'resource_id' => $this->artsResource->id]);

    $this->postJson(route('admin.settings.users.store'), artsPayload([
        'role_id'     => $this->artsRole->id,
        'resource_id' => $this->artsResource->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors('resource_id');
});

it('clears the resource when the role changes away from arts', function () {
    $user = User::factory()->create([
        'email'       => 'arts@example.com',
        'role_id'     => $this->artsRole->id,
        'resource_id' => $this->artsResource->id,
    ]);

    $this->putJson(route('admin.settings.users.update', ['id' => $user->id]), artsPayload([
        'role_id'     => $this->otherRole->id,
        'resource_id' => $this->artsResource->id,
        'status'      => 1,
    ]))->assertOk();

    expect($user->fresh()->resource_id)->toBeNull();
});

it('creates a kliniek medewerker linked to exactly one clinic', function () {
    $clinic = Clinic::factory()->create();

    $this->postJson(route('admin.settings.users.store'), artsPayload([
        'role_id'   => $this->kliniekRole->id,
        'clinic_id' => $clinic->id,
    ]))->assertOk();

    expect(User::where('email', 'arts@example.com')->firstOrFail()->clinic->id)->toBe($clinic->id);
});

it('requires a clinic for the kliniek medewerker role', function () {
    $this->postJson(route('admin.settings.users.store'), artsPayload(['role_id' => $this->kliniekRole->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('clinic_id');
});

it('rejects a non-existing clinic', function () {
    $this->postJson(route('admin.settings.users.store'), artsPayload([
        'role_id'   => $this->kliniekRole->id,
        'clinic_id' => 999999,
    ]))->assertUnprocessable()->assertJsonValidationErrors('clinic_id');
});

it('clears the clinic when the role changes away from kliniek medewerker', function () {
    $user = User::factory()->create([
        'email'     => 'arts@example.com',
        'role_id'   => $this->kliniekRole->id,
        'clinic_id' => Clinic::factory()->create()->id,
    ]);

    $this->putJson(route('admin.settings.users.update', ['id' => $user->id]), artsPayload([
        'role_id'   => $this->otherRole->id,
        'clinic_id' => $user->clinic_id,
        'status'    => 1,
    ]))->assertOk();

    expect($user->fresh()->clinic_id)->toBeNull();
});

it('shows the resource and clinic fields only for their role on the user form', function () {
    $this->withoutVite()
        ->get(route('admin.settings.users.create'))
        ->assertOk()
        ->assertSee('v-if="['.$this->artsRole->id.'].includes(Number(values.role_id))"', false)
        ->assertSee('v-if="['.$this->kliniekRole->id.'].includes(Number(values.role_id))"', false)
        ->assertSee('v-slot="{ meta, errors, setValues, values }"', false);
});

it('labels arts resources with their clinic on the user form', function () {
    $clinicName = $this->artsResource->clinicDepartment->clinic->name;

    $this->withoutVite()
        ->get(route('admin.settings.users.create'))
        ->assertOk()
        ->assertSee(e($this->artsResource->name).' | '.e($clinicName), false);
});

it('rejects and hides arts resources of an inactive clinic or inactive resources', function () {
    $inactiveClinic = Clinic::factory()->create(['is_active' => false]);
    $ofInactiveClinic = Resource::factory()->create(['resource_type_id' => $this->artsenTypeId, 'clinic_id' => $inactiveClinic->id, 'name' => 'Dr. Inactieve Kliniek']);
    $inactiveResource = Resource::factory()->create(['resource_type_id' => $this->artsenTypeId, 'clinic_id' => $this->activeClinic->id, 'is_active' => false, 'name' => 'Dr. Inactief']);

    foreach ([$ofInactiveClinic, $inactiveResource] as $resource) {
        $this->postJson(route('admin.settings.users.store'), artsPayload([
            'role_id'     => $this->artsRole->id,
            'resource_id' => $resource->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('resource_id');
    }

    $this->withoutVite()
        ->get(route('admin.settings.users.create'))
        ->assertDontSee('Dr. Inactieve Kliniek')
        ->assertDontSee('Dr. Inactief');
});

it('rejects an inactive clinic for the kliniek medewerker role', function () {
    $this->postJson(route('admin.settings.users.store'), artsPayload([
        'role_id'   => $this->kliniekRole->id,
        'clinic_id' => Clinic::factory()->create(['is_active' => false])->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors('clinic_id');
});
