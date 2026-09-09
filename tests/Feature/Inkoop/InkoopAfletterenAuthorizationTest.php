<?php

namespace Tests\Feature\Inkoop;

use App\Models\Clinic;
use App\Models\Inkoop\InkoopInvoice;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->clinic = Clinic::factory()->create();
    $this->invoice = InkoopInvoice::create([
        'clinic_id' => $this->clinic->id,
        'pdf_path'  => 'inkoop/test.pdf',
        'filename'  => 'test.pdf',
    ]);
});

function makeRoleUser(string $type, array $permissions = []): User
{
    $role = Role::factory()->create([
        'permission_type' => $type,
        'permissions'     => $type === 'all' ? null : $permissions,
    ]);

    return User::factory()->create([
        'role_id'         => $role->id,
        'view_permission' => 'global',
        'status'          => 1,
    ]);
}

test('custom role without inkoop-afletteren permission is blocked from inkoop routes', function () {
    $user = makeRoleUser('custom', ['settings.clinics', 'settings.clinics.view']);

    actingAs($user, 'user')
        ->get(route('admin.inkoop.step0', $this->invoice->id))
        ->assertStatus(401);

    actingAs($user, 'user')
        ->delete(route('admin.inkoop.delete', $this->invoice->id))
        ->assertStatus(401);
});

test('global admin can reach inkoop routes', function () {
    actingAs($user = makeRoleUser('all'), 'user');

    expect($this->get(route('admin.inkoop.step0', $this->invoice->id))->status())->not->toBe(401);
});

test('custom role granted inkoop-afletteren can reach inkoop routes', function () {
    $user = makeRoleUser('custom', ['settings.clinics.view', 'inkoop-afletteren']);

    expect(
        actingAs($user, 'user')->get(route('admin.inkoop.step0', $this->invoice->id))->status()
    )->not->toBe(401);
});

test('clinic view hides the Inkoop afletteren tab unless permitted', function () {
    $without = makeRoleUser('custom', ['settings.clinics', 'settings.clinics.view']);
    actingAs($without, 'user')
        ->get(route('admin.clinics.view', $this->clinic->id))
        ->assertOk()
        ->assertDontSee('Inkoop afletteren');

    actingAs(makeRoleUser('all'), 'user')
        ->get(route('admin.clinics.view', $this->clinic->id))
        ->assertOk()
        ->assertSee('Inkoop afletteren');
});
