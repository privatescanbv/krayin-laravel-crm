<?php

use App\Enums\ActivityType;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Enums\RoleCode;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Webkul\Activity\Models\Activity;
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

it('returns the card shape with person id, name and date of birth, without medical fields', function () {
    $doctor = portalUser('kc-doc-1');
    $sale = doctorSale($doctor, herniaStage());
    $person = Person::factory()->create(['first_name' => 'Jan', 'last_name' => 'Jansen', 'date_of_birth' => '1980-04-02']);
    $sale->persons()->attach($person->id);

    $response = $this->withHeaders(apiKeyHeader())
        ->getJson(route('api.clinic.sales-leads', 'kc-doc-1'))
        ->assertOk();

    expect(array_keys($response->json('data.0')))->toBe(['id', 'name', 'pipeline_stage_id', 'created_at', 'updated_at', 'closed_at', 'stage_entered_at', 'persons'])
        ->and($response->json('data.0.persons'))->toBe([['id' => $person->id, 'name' => $person->name, 'date_of_birth' => '1980-04-02']]);
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

function moveStage(string $keycloakId, int $saleId, mixed $stageId, array $extra = [])
{
    return test()->withHeaders(apiKeyHeader())
        ->putJson(route('api.clinic.sales-leads.stage', [$keycloakId, $saleId]), ['pipeline_stage_id' => $stageId, ...$extra]);
}

it('moves the doctor\'s own sale to another visible Herniapoli stage', function () {
    $doctor = portalUser('kc-doc-1');
    $from = herniaStage(1);
    $to = herniaStage(2);
    $sale = doctorSale($doctor, $from);

    moveStage('kc-doc-1', $sale->id, $to->id)
        ->assertOk()
        ->assertJsonPath('data.id', $sale->id)
        ->assertJsonPath('data.pipeline_stage_id', $to->id)
        ->assertJsonMissingPath('data.assessment_outcome');

    expect($sale->fresh()->pipeline_stage_id)->toBe($to->id);
});

it('refuses other doctors, non-doctors, unknown users and missing authentication', function () {
    $doctor = portalUser('kc-doc-1');
    portalUser('kc-doc-2');
    portalUser('kc-clinic-1', RoleCode::KLINIEK_MEDEWERKER);
    $to = herniaStage(2);
    $sale = doctorSale($doctor, herniaStage(1));

    moveStage('kc-doc-2', $sale->id, $to->id)->assertNotFound();
    moveStage('kc-clinic-1', $sale->id, $to->id)->assertForbidden();
    moveStage('kc-unknown', $sale->id, $to->id)->assertNotFound();
    $this->withoutHeader('X-API-KEY')->putJson(route('api.clinic.sales-leads.stage', ['kc-doc-1', $sale->id]), ['pipeline_stage_id' => $to->id])->assertUnauthorized();

    expect($sale->fresh()->pipeline_stage_id)->not->toBe($to->id);
});

it('forbids moving a sale with the bearer token of another subject', function () {
    $doctor = portalUser('kc-doc-1');
    $to = herniaStage(2);
    $sale = doctorSale($doctor, herniaStage(1));
    fakeKeycloakToken('kc-doc-2');

    $this->putJson(route('api.clinic.sales-leads.stage', ['kc-doc-1', $sale->id]), ['pipeline_stage_id' => $to->id], ['Authorization' => 'Bearer t'])->assertForbidden();
    expect($sale->fresh()->pipeline_stage_id)->not->toBe($to->id);
});

it('moves a sale with the bearer token of the doctor itself', function () {
    $doctor = portalUser('kc-doc-1');
    $to = herniaStage(2);
    $sale = doctorSale($doctor, herniaStage(1));
    fakeKeycloakToken('kc-doc-1');

    $this->putJson(route('api.clinic.sales-leads.stage', ['kc-doc-1', $sale->id]), ['pipeline_stage_id' => $to->id], ['Authorization' => 'Bearer t'])
        ->assertOk()
        ->assertJsonPath('data.pipeline_stage_id', $to->id);
});

it('does not touch sales in a hidden source stage, another pipeline or of another doctor', function () {
    $doctor = portalUser('kc-doc-1');
    $other = portalUser('kc-doc-2');
    $to = herniaStage(2);
    $hiddenStage = herniaStage(3);
    $hiddenStage->roles()->attach(Role::factory()->create()->id);
    $hidden = doctorSale($doctor, $hiddenStage);
    $otherPipeline = doctorSale($doctor, salesStage(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID));
    $foreign = doctorSale($other, herniaStage(1));

    foreach ([$hidden, $otherPipeline, $foreign] as $sale) {
        $stageBefore = $sale->pipeline_stage_id;
        moveStage('kc-doc-1', $sale->id, $to->id)->assertNotFound();
        expect($sale->fresh()->pipeline_stage_id)->toBe($stageBefore);
    }
});

it('rejects hidden, foreign-pipeline, unknown and badly typed target stages', function () {
    $doctor = portalUser('kc-doc-1');
    $from = herniaStage(1);
    $hiddenStage = herniaStage(2);
    $hiddenStage->roles()->attach(Role::factory()->create()->id);
    $sale = doctorSale($doctor, $from);

    foreach ([$hiddenStage->id, salesStage(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID)->id, 999999, 'abc', null] as $target) {
        moveStage('kc-doc-1', $sale->id, $target)->assertUnprocessable()->assertJsonValidationErrors('pipeline_stage_id');
    }

    expect($sale->fresh()->pipeline_stage_id)->toBe($from->id);
});

it('refuses won/lost targets and closed sales, ignoring forbidden fields', function () {
    $doctor = portalUser('kc-doc-1');
    $open = herniaStage(1);
    $won = Stage::factory()->won()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value]);
    $lost = Stage::factory()->lost()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value]);
    $sale = doctorSale($doctor, $open);
    $closed = doctorSale($doctor, $won, ['closed_at' => now()]);

    moveStage('kc-doc-1', $sale->id, $won->id)->assertUnprocessable();
    moveStage('kc-doc-1', $sale->id, $lost->id, ['lost_reason' => 'x', 'closed_at' => now()->toDateString()])->assertUnprocessable();
    moveStage('kc-doc-1', $closed->id, $open->id)->assertUnprocessable();

    expect($sale->fresh())->pipeline_stage_id->toBe($open->id)->closed_at->toBeNull()
        ->and($closed->fresh()->pipeline_stage_id)->toBe($won->id);
});

it('ignores client overrides of doctor, closed_at and pipeline on a valid move', function () {
    $doctor = portalUser('kc-doc-1');
    $other = portalUser('kc-doc-2');
    $to = herniaStage(2);
    $sale = doctorSale($doctor, herniaStage(1));

    moveStage('kc-doc-1', $sale->id, $to->id, ['doctor_user_id' => $other->id, 'closed_at' => now()->toDateString(), 'lead_pipeline_id' => 1])->assertOk();

    expect($sale->fresh())->doctor_user_id->toBe($doctor->id)->closed_at->toBeNull();
});

it('requires an assessment outcome for stages that need one, and keeps the old stage', function () {
    $doctor = portalUser('kc-doc-1');
    $from = herniaStage(1);
    $needsOutcome = Stage::factory()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value, 'code' => PipelineStage::SALES_CONFIRM_TREATMENT_HERNIA->value]);
    $sale = doctorSale($doctor, $from);

    moveStage('kc-doc-1', $sale->id, $needsOutcome->id, ['assessment_outcome' => 'made-up'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assessment_outcome');
    expect($sale->fresh()->pipeline_stage_id)->toBe($from->id);

    $sale->update(['assessment_outcome' => 'existing-outcome']);
    moveStage('kc-doc-1', $sale->id, $needsOutcome->id)->assertOk();
    expect($sale->fresh())->pipeline_stage_id->toBe($needsOutcome->id)->additional_research_required->toBeFalse();
});

it('treats a move to the current stage as a no-op without writing', function () {
    $doctor = portalUser('kc-doc-1');
    $stage = herniaStage(1);
    $sale = doctorSale($doctor, $stage);
    $updatedAt = $sale->fresh()->updated_at;
    $this->travel(5)->minutes();

    moveStage('kc-doc-1', $sale->id, $stage->id)->assertOk()->assertJsonPath('data.pipeline_stage_id', $stage->id);

    expect($sale->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('reports a null date of birth and no stage change time for a sale that was never moved', function () {
    $doctor = portalUser('kc-doc-1');
    $sale = doctorSale($doctor, herniaStage());
    $sale->persons()->attach(Person::factory()->create(['date_of_birth' => null])->id);

    $this->withHeaders(apiKeyHeader())
        ->getJson(route('api.clinic.sales-leads', 'kc-doc-1'))
        ->assertOk()
        ->assertJsonPath('data.0.persons.0.date_of_birth', null)
        ->assertJsonPath('data.0.stage_entered_at', null);
});

it('reports when the sale entered its current stage, not the last edit', function () {
    $doctor = portalUser('kc-doc-1');
    $from = herniaStage(1);
    $to = herniaStage(2);
    $sale = doctorSale($doctor, $to);

    $activity = function (int $stageId, string $at) use ($sale, $doctor) {
        $row = Activity::query()->create([
            'type'          => ActivityType::SYSTEM, 'title' => 'Status gewijzigd', 'is_done' => 1, 'user_id' => $doctor->id,
            'sales_lead_id' => $sale->id,
            'additional'    => ['attribute' => 'Status', 'new' => ['value' => $stageId, 'label' => 'x'], 'old' => ['value' => null, 'label' => '-']],
        ]);
        DB::table('activities')->where('id', $row->id)->update(['created_at' => $at]);
    };
    $activity($to->id, '2026-11-01 10:00:00');   // eerdere keer in deze stage
    $activity($from->id, '2026-11-03 10:00:00'); // terug
    $activity($to->id, '2026-11-05 10:00:00');   // nu weer: dit telt
    $sale->update(['name' => 'Aangepast']); // dossierwijziging, wijzigt updated_at

    $response = $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads', 'kc-doc-1'))->assertOk();

    expect(Carbon::parse($response->json('data.0.stage_entered_at'))->toDateTimeString())->toBe('2026-11-05 10:00:00');
});
