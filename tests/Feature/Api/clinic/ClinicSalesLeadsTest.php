<?php

use App\Enums\PipelineDefaultKeys;
use App\Enums\RoleCode;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123']]);
    Http::fake(['*' => Http::response(['access_token' => 'test-admin-token'], 200)]);

    // De testdatabase kent de vaste productie-pipelines niet; de leads-observer verwacht pipeline 1.
    foreach ([PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID, PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID, PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID] as $pipeline) {
        DB::table('lead_pipelines')->insertOrIgnore(['id' => $pipeline->value, 'name' => 'Pipeline '.$pipeline->value, 'is_default' => 0, 'rotten_days' => 30]);
    }
});

function portalUser(string $keycloakId, RoleCode $roleCode = RoleCode::ARTS): User
{
    $role = Role::query()->where('code', $roleCode->value)->first() ?? Role::factory()->create(['code' => $roleCode->value]);

    return User::withoutEvents(fn () => User::factory()->create([
        'status'           => 1,
        'keycloak_user_id' => $keycloakId,
        'role_id'          => $role->id,
    ]));
}

function salesStage(PipelineDefaultKeys $pipeline, int $sortOrder = 1): Stage
{
    return Stage::factory()->create(['lead_pipeline_id' => $pipeline->value, 'sort_order' => $sortOrder]);
}

function herniaStage(int $sortOrder = 1): Stage
{
    return salesStage(PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID, $sortOrder);
}

function doctorSale(User $doctor, Stage $stage, array $attributes = []): SalesLead
{
    return SalesLead::factory()->create([
        'doctor_user_id'    => $doctor->id,
        'pipeline_stage_id' => $stage->id,
        ...$attributes,
    ]);
}

function apiKeyHeader(): array
{
    return ['X-API-KEY' => 'valid-api-key-123'];
}

it('lists only the doctor\'s own sales in the Herniapoli pipeline', function () {
    $doctor = portalUser('kc-doc-1');
    $other = portalUser('kc-doc-2');
    $stage = herniaStage();
    $mine = doctorSale($doctor, $stage);
    doctorSale($other, $stage);
    doctorSale($doctor, salesStage(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID));

    $this->withHeaders(apiKeyHeader())
        ->getJson(route('api.clinic.sales-leads', 'kc-doc-1'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

it('returns the minimal card shape with person id and name only', function () {
    $doctor = portalUser('kc-doc-1');
    $sale = doctorSale($doctor, herniaStage());
    $person = Person::factory()->create(['first_name' => 'Jan', 'last_name' => 'Jansen']);
    $sale->persons()->attach($person->id);

    $response = $this->withHeaders(apiKeyHeader())
        ->getJson(route('api.clinic.sales-leads', 'kc-doc-1'))
        ->assertOk();

    expect(array_keys($response->json('data.0')))->toBe(['id', 'name', 'pipeline_stage_id', 'created_at', 'updated_at', 'closed_at', 'persons'])
        ->and($response->json('data.0.persons'))->toBe([['id' => $person->id, 'name' => $person->name]]);
});

it('shows stages without role restriction to every role', function () {
    portalUser('kc-doc-1');
    $stage = herniaStage();

    $this->withHeaders(apiKeyHeader())
        ->getJson(route('api.clinic.sales-stages', 'kc-doc-1'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $stage->id)
        ->assertJsonStructure(['data' => [['id', 'code', 'name', 'sort_order', 'is_won', 'is_lost']]]);
});

it('hides stages and their sales when restricted to another role, and shows them for the own role', function () {
    $doctor = portalUser('kc-doc-1');
    $open = herniaStage(1);
    $hidden = herniaStage(2);
    $visibleForDoctor = herniaStage(3);
    $hidden->roles()->attach(Role::factory()->create()->id);
    $visibleForDoctor->roles()->attach($doctor->role_id);
    doctorSale($doctor, $open);
    doctorSale($doctor, $hidden);
    doctorSale($doctor, $visibleForDoctor);

    $this->withHeaders(apiKeyHeader())
        ->getJson(route('api.clinic.sales-stages', 'kc-doc-1'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$open->id, $visibleForDoctor->id]);

    $this->withHeaders(apiKeyHeader())
        ->getJson(route('api.clinic.sales-leads', 'kc-doc-1'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissing(['pipeline_stage_id' => $hidden->id]);

    $this->withHeaders(apiKeyHeader())
        ->getJson(route('api.clinic.sales-leads', ['keycloakUserId' => 'kc-doc-1', 'stage_id' => $hidden->id]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('filters by stage and paginates within the limit', function () {
    $doctor = portalUser('kc-doc-1');
    $a = herniaStage(1);
    $b = herniaStage(2);
    doctorSale($doctor, $a);
    doctorSale($doctor, $a);
    doctorSale($doctor, $b);

    $this->withHeaders(apiKeyHeader())
        ->getJson(route('api.clinic.sales-leads', ['keycloakUserId' => 'kc-doc-1', 'stage_id' => $a->id, 'per_page' => 1]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2);

    $this->withHeaders(apiKeyHeader())
        ->getJson(route('api.clinic.sales-leads', ['keycloakUserId' => 'kc-doc-1', 'per_page' => 101]))
        ->assertUnprocessable();
});

it('does not run extra queries per sale', function () {
    $doctor = portalUser('kc-doc-1');
    $stage = herniaStage();
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads', 'kc-doc-1'))->assertOk();

        return count(DB::getQueryLog());
    };

    foreach (range(1, 2) as $i) {
        doctorSale($doctor, $stage)->persons()->attach(Person::factory()->create()->id);
    }
    $few = $count();
    foreach (range(1, 5) as $i) {
        doctorSale($doctor, $stage)->persons()->attach(Person::factory()->create()->id);
    }

    expect($count())->toBe($few);
});

it('refuses non-doctors, unknown users and unauthenticated calls', function () {
    portalUser('kc-clinic-1', RoleCode::KLINIEK_MEDEWERKER);

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads', 'kc-clinic-1'))->assertForbidden();
    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-stages', 'kc-clinic-1'))->assertForbidden();
    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads', 'kc-unknown'))->assertNotFound();
    $this->withoutHeader('X-API-KEY')->getJson(route('api.clinic.sales-leads', 'kc-clinic-1'))->assertUnauthorized();
});

function fakeKeycloakToken(string $subject): void
{
    config(['api.keys' => []]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->setRaw(['sub' => $subject]);
    $socialiteUser->map(['id' => $subject]);

    $provider = Mockery::mock();
    $provider->shouldReceive('userFromToken')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
}

it('accepts a keycloak bearer token of the doctor itself', function () {
    $doctor = portalUser('kc-doc-1');
    $sale = doctorSale($doctor, herniaStage());
    fakeKeycloakToken('kc-doc-1');

    $this->getJson(route('api.clinic.sales-leads', 'kc-doc-1'), ['Authorization' => 'Bearer test-token'])
        ->assertOk()
        ->assertJsonPath('data.0.id', $sale->id);
});

it('forbids reading another doctor\'s sales with a keycloak bearer token', function () {
    $doctor = portalUser('kc-doc-1');
    portalUser('kc-doc-2');
    doctorSale($doctor, herniaStage());
    fakeKeycloakToken('kc-doc-2');

    $this->getJson(route('api.clinic.sales-leads', 'kc-doc-1'), ['Authorization' => 'Bearer test-token'])->assertForbidden();
    $this->getJson(route('api.clinic.sales-stages', 'kc-doc-1'), ['Authorization' => 'Bearer test-token'])->assertForbidden();
});
