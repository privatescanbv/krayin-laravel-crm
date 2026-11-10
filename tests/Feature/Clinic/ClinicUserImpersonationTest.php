<?php

use App\Enums\ActivityType;
use App\Enums\RoleCode;
use App\Models\Clinic;
use App\Models\Resource;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Http;
use Webkul\Activity\Models\Activity;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);

    config([
        'services.portal.clinic.web_url' => 'https://clinic.test',
        'services.portal.clinic.secret'  => 'clinic-secret',
    ]);
});

function clinicRole(): Role
{
    return Role::firstOrCreate(
        ['code' => RoleCode::KLINIEK_MEDEWERKER->value],
        ['name' => 'Kliniek medewerker', 'description' => 'x', 'permission_type' => 'custom', 'permissions' => []]
    );
}

function clinicStaffUser(array $attrs = []): User
{
    return makeUser(array_merge([
        'role_id'          => clinicRole()->id,
        'clinic_id'        => Clinic::factory()->create()->id,
        'keycloak_user_id' => 'kc-clinic-1',
    ], $attrs));
}

function adminWith(array $permissions): User
{
    $role = Role::factory()->create(['permission_type' => 'custom', 'permissions' => $permissions]);

    return User::factory()->create(['role_id' => $role->id, 'view_permission' => 'global', 'status' => 1]);
}

function fakeKeycloakExchange(): void
{
    Http::fake(['*/protocol/openid-connect/token' => Http::response(['access_token' => 'exchanged-token'])]);
}

test('impersonation requires the settings.user.users.impersonate permission', function () {
    $target = clinicStaffUser();

    $this->actingAs(adminWith(['settings', 'settings.user', 'settings.user.users', 'settings.user.users.view']), 'user')
        ->post(route('admin.settings.users.impersonate', $target->id))
        ->assertStatus(401);
});

test('impersonation is refused for users that cannot log in to the clinic portal', function (array $attrs) {
    if (($attrs['role_id'] ?? null) === 'other') {
        $attrs['role_id'] = Role::factory()->create(['code' => null])->id;
    }

    $target = clinicStaffUser($attrs);

    $this->actingAs(adminWith(['settings', 'settings.user', 'settings.user.users', 'settings.user.users.impersonate']), 'user')
        ->post(route('admin.settings.users.impersonate', $target->id))
        ->assertStatus(422);
})->with([
    'no keycloak account' => [['keycloak_user_id' => null]],
    'inactive'            => [['status' => 0]],
    'not a clinic role'   => [['role_id' => 'other']],
]);

test('impersonation exchanges a clinic-app token, redirects to the clinic portal and logs on the clinic', function () {
    fakeKeycloakExchange();
    $target = clinicStaffUser();
    $admin = adminWith(['settings', 'settings.user', 'settings.user.users', 'settings.user.users.impersonate']);

    $this->actingAs($admin, 'user')
        ->post(route('admin.settings.users.impersonate', $target->id))
        ->assertRedirect('https://clinic.test/auth/keycloak?token=exchanged-token')
        ->assertSessionHas('impersonating.type', 'clinic_user')
        ->assertSessionHas('impersonating.user_id', $target->id);

    Http::assertSent(fn ($request) => ($request['client_id'] ?? null) === 'clinic-app'
        && ($request['client_secret'] ?? null) === 'clinic-secret'
        && ($request['requested_subject'] ?? null) === 'kc-clinic-1');

    $activity = Activity::where('clinic_id', $target->clinic_id)->where('type', ActivityType::SYSTEM)->firstOrFail();
    expect($activity->title)->toBe('Kliniekportaal-sessie gestart')
        ->and($activity->user_id)->toBe($admin->id)
        ->and($activity->additional['clinic_user']['id'])->toBe($target->id)
        ->and($activity->additional['action'])->toBe('start');
});

test('stopping a clinic impersonation logs it, logs out Keycloak and clears the session', function () {
    fakeKeycloakExchange();
    $target = clinicStaffUser();
    $admin = adminWith(['settings', 'settings.user', 'settings.user.users', 'settings.user.users.impersonate']);

    $this->actingAs($admin, 'user')
        ->withSession(['impersonating' => [
            'type'             => 'clinic_user',
            'user_id'          => $target->id,
            'person_name'      => $target->name,
            'keycloak_user_id' => 'kc-clinic-1',
            'started_at'       => now()->subMinutes(5)->toISOString(),
            'expires_at'       => now()->addMinutes(100)->toISOString(),
        ]])
        ->post(route('admin.contacts.impersonation.stop'))
        ->assertSessionMissing('impersonating');

    $activity = Activity::where('clinic_id', $target->clinic_id)->where('title', 'Kliniekportaal-sessie gestopt')->firstOrFail();
    expect($activity->additional['duration_seconds'])->toBeGreaterThan(0);
});

test('user view page only offers the clinic impersonation button with the permission for clinic users', function (array $permissions, bool $clinicUser, bool $visible) {
    $target = $clinicUser ? clinicStaffUser() : makeUser();

    $html = $this->actingAs(adminWith($permissions), 'user')
        ->get(route('admin.settings.users.view', $target->id))
        ->assertOk()
        ->getContent();

    expect(str_contains($html, route('admin.settings.users.impersonate', $target->id)))->toBe($visible);
})->with([
    'permission, clinic user'    => [['settings', 'settings.user', 'settings.user.users', 'settings.user.users.view', 'settings.user.users.impersonate'], true, true],
    'permission, other user'     => [['settings', 'settings.user', 'settings.user.users', 'settings.user.users.view', 'settings.user.users.impersonate'], false, false],
    'no permission, clinic user' => [['settings', 'settings.user', 'settings.user.users', 'settings.user.users.view'], true, false],
]);

test('user activities endpoint only returns the impersonation log of that user', function () {
    $clinic = Clinic::factory()->create();
    $a = clinicStaffUser(['clinic_id' => $clinic->id]);
    $b = clinicStaffUser(['clinic_id' => $clinic->id, 'keycloak_user_id' => 'kc-clinic-2']);
    $admin = adminWith(['settings', 'settings.user', 'settings.user.users', 'settings.user.users.view']);

    foreach ([$a, $b] as $target) {
        Activity::create([
            'type'       => ActivityType::SYSTEM,
            'title'      => 'Kliniekportaal-sessie gestart',
            'is_done'    => 1,
            'clinic_id'  => $clinic->id,
            'user_id'    => $admin->id,
            'additional' => ['action' => 'start', 'clinic_user' => ['id' => $target->id], 'admin' => ['name' => 'Beheerder']],
        ]);
    }

    $this->actingAs($admin, 'user')
        ->getJson(route('admin.settings.users.activities.index', $a->id))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.additional.clinic_user.id', $a->id);

    $this->actingAs(adminWith(['settings']), 'user')
        ->getJson(route('admin.settings.users.activities.index', $a->id))
        ->assertForbidden();
});

test('user activities endpoint keeps showing impersonations from a previous clinic', function () {
    $oldClinic = Clinic::factory()->create();
    $user = clinicStaffUser(['clinic_id' => $oldClinic->id]);
    $admin = adminWith(['settings', 'settings.user', 'settings.user.users', 'settings.user.users.view']);

    Activity::create([
        'type'       => ActivityType::SYSTEM,
        'title'      => 'Kliniekportaal-sessie gestart',
        'is_done'    => 1,
        'clinic_id'  => $oldClinic->id,
        'user_id'    => $admin->id,
        'additional' => ['action' => 'start', 'clinic_user' => ['id' => $user->id]],
    ]);

    $user->update(['clinic_id' => Clinic::factory()->create()->id]);

    $this->actingAs($admin, 'user')
        ->getJson(route('admin.settings.users.activities.index', $user->id))
        ->assertOk()
        ->assertJsonCount(2, 'data') // old impersonation + the clinic change itself
        ->assertJsonFragment(['title' => 'Kliniekportaal-sessie gestart']);
});

function viewerAdmin(): User
{
    return adminWith(['settings', 'settings.user', 'settings.user.users', 'settings.user.users.view', 'settings.user.users.impersonate']);
}

test('start and stop through the impersonation flow both show up in the user log with audit details', function () {
    fakeKeycloakExchange();
    $target = clinicStaffUser();
    $admin = viewerAdmin();

    $this->actingAs($admin, 'user')
        ->withHeader('User-Agent', 'PestBrowser/1.0')
        ->post(route('admin.settings.users.impersonate', $target->id))
        ->assertSessionHas('impersonating');

    $this->travel(5)->minutes();

    $this->actingAs($admin, 'user')
        ->post(route('admin.contacts.impersonation.stop'))
        ->assertSessionMissing('impersonating');

    $rows = collect($this->actingAs($admin, 'user')
        ->getJson(route('admin.settings.users.activities.index', $target->id))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data'))->keyBy('title');

    expect($rows->keys()->all())->toEqualCanonicalizing(['Kliniekportaal-sessie gestart', 'Kliniekportaal-sessie gestopt']);

    $start = $rows['Kliniekportaal-sessie gestart']['additional'];
    expect($start['admin']['name'])->toBe($admin->name)
        ->and($start['clinic_user']['id'])->toBe($target->id)
        ->and($start['network']['ip_address'])->not->toBeEmpty()
        ->and($start['client']['user_agent'])->toBe('PestBrowser/1.0')
        ->and($start['session_id'])->not->toBeEmpty();

    expect($rows['Kliniekportaal-sessie gestopt']['additional']['duration_seconds'])->toBeGreaterThan(0);
});

test('user log falls back to the clinic of the department when the user has no clinic_id', function () {
    fakeKeycloakExchange();
    $clinic = Clinic::factory()->create();
    $resource = Resource::factory()->create(['clinic_id' => $clinic->id]);
    $target = clinicStaffUser(['clinic_id' => null, 'resource_id' => $resource->id]);
    $admin = viewerAdmin();

    $this->actingAs($admin, 'user')->post(route('admin.settings.users.impersonate', $target->id))->assertRedirect();

    expect(Activity::where('clinic_id', $clinic->id)->count())->toBe(1);

    $this->actingAs($admin, 'user')
        ->getJson(route('admin.settings.users.activities.index', $target->id))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('user log is paginated', function () {
    $target = clinicStaffUser();
    $admin = viewerAdmin();

    foreach (range(1, 25) as $i) {
        Activity::create([
            'type'       => ActivityType::SYSTEM,
            'title'      => "Kliniekportaal-sessie gestart $i",
            'is_done'    => 1,
            'clinic_id'  => $target->clinic_id,
            'user_id'    => $admin->id,
            'additional' => ['action' => 'start', 'clinic_user' => ['id' => $target->id]],
        ]);
    }

    $this->actingAs($admin, 'user')
        ->getJson(route('admin.settings.users.activities.index', $target->id))
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.last_page', 2);

    $this->actingAs($admin, 'user')
        ->getJson(route('admin.settings.users.activities.index', [$target->id, 'page' => 2]))
        ->assertJsonCount(5, 'data');
});

test('user view page renders the log tab and the expandable audit details', function () {
    $target = clinicStaffUser();

    $html = $this->actingAs(viewerAdmin(), 'user')
        ->get(route('admin.settings.users.view', $target->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(route('admin.settings.users.activities.index', $target->id))
        ->toContain('hasAudit(activity)')
        ->toContain('auditSections(activity)');
});

test('field changes of a user end up in his log with old and new value, without password hashes', function () {
    $target = clinicStaffUser(['first_name' => 'Jan']);
    $admin = viewerAdmin();

    $this->actingAs($admin, 'user');
    $target->update(['first_name' => 'Piet', 'password' => 'NieuwWachtwoord123!']);
    $target->update(['first_name' => 'Piet']); // unchanged: no extra row

    $rows = collect($this->getJson(route('admin.settings.users.activities.index', $target->id))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data'))->keyBy(fn ($r) => $r['additional']['attribute']);

    expect($rows['Voornaam']['additional']['old']['label'])->toBe('Jan')
        ->and($rows['Voornaam']['additional']['new']['label'])->toBe('Piet')
        ->and($rows['Voornaam']['user_id'] ?? $admin->id)->toBe($admin->id)
        ->and($rows['Wachtwoord']['title'])->toBe('Wachtwoord gewijzigd')
        ->and($rows['Wachtwoord']['additional'])->not->toHaveKeys(['old', 'new']);
});
