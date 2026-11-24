<?php

use App\Enums\ActivityType;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Enums\RoleCode;
use App\Models\AssessmentOutcome;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\ResourceType;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\Group;
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

function herniaStage(int $sortOrder = 1, bool $visibleForPortal = true): Stage
{
    return grantPortalRoles(salesStage(PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID, $sortOrder), $visibleForPortal);
}

function grantPortalRoles(Stage $stage, bool $grant = true): Stage
{
    if ($grant) {
        foreach (RoleCode::cases() as $code) {
            $role = Role::query()->where('code', $code->value)->first() ?? Role::factory()->create(['code' => $code->value]);
            $stage->roles()->attach($role->id);
        }
    }

    return $stage;
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

it('shows only stages granted to the role, none without role rows', function () {
    portalUser('kc-doc-1');
    herniaStage(2, false);
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
    $hidden = herniaStage(2, false);
    $visibleForDoctor = herniaStage(3, false);
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
    $hiddenStage = herniaStage(3, false);
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
    $hiddenStage = herniaStage(2, false);
    $sale = doctorSale($doctor, $from);

    foreach ([$hiddenStage->id, salesStage(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID)->id, 999999, 'abc', null] as $target) {
        moveStage('kc-doc-1', $sale->id, $target)->assertUnprocessable()->assertJsonValidationErrors('pipeline_stage_id');
    }

    expect($sale->fresh()->pipeline_stage_id)->toBe($from->id);
});

it('refuses won/lost targets and closed sales, ignoring forbidden fields', function () {
    $doctor = portalUser('kc-doc-1');
    $open = herniaStage(1);
    $won = grantPortalRoles(Stage::factory()->won()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value]));
    $lost = grantPortalRoles(Stage::factory()->lost()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value]));
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
    $needsOutcome = grantPortalRoles(Stage::factory()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value, 'code' => PipelineStage::SALES_CONFIRM_TREATMENT_HERNIA->value]));
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

function saleWithDepartment(User $doctor, Stage $stage, array $attributes = []): SalesLead
{
    $department = Department::factory()->create();
    Group::query()->create(['name' => 'Groep '.$department->id, 'description' => 'test', 'department_id' => $department->id]);

    return doctorSale($doctor, $stage, ['department_id' => $department->id, ...$attributes]);
}

function bearerGet(string $keycloakId, string $path)
{
    fakeKeycloakToken($keycloakId);

    return test()->getJson("/api/clinic/{$keycloakId}/{$path}", ['Authorization' => 'Bearer t']);
}

it('shows the detail of the doctor\'s own sale with the whitelisted fields only', function () {
    $doctor = portalUser('kc-doc-1');
    $outcome = AssessmentOutcome::factory()->create(['code' => 'uitkomst_x', 'label' => 'Uitkomst X']);
    $sale = doctorSale($doctor, herniaStage(), ['description' => '<p>Eerste &amp; tweede</p><p>Alinea 2 <script>alert(1)</script></p>', 'assessment_outcome' => 'uitkomst_x', 'additional_research_required' => true]);
    $sale->persons()->attach([
        Person::factory()->create(['first_name' => 'Jan', 'last_name' => 'Jansen', 'date_of_birth' => '1980-02-03'])->id,
        Person::factory()->create(['date_of_birth' => null])->id,
    ]);

    $response = $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.show', ['kc-doc-1', $sale->id]))
        ->assertOk()
        ->assertJsonPath('data.id', $sale->id)
        ->assertJsonPath('data.stage.id', $sale->pipeline_stage_id)
        ->assertJsonPath('data.doctor.id', $doctor->id)
        ->assertJsonPath('data.assessment_outcome', ['code' => 'uitkomst_x', 'label' => 'Uitkomst X'])
        ->assertJsonPath('data.additional_research_required', true)
        ->assertJsonCount(2, 'data.persons');

    expect($response->json('data.description'))->toBe("Eerste & tweede\n\nAlinea 2 alert(1)")
        ->and(collect($response->json('data.persons'))->pluck('date_of_birth')->filter()->all())->toBe(['1980-02-03'])
        ->and(array_keys($response->json('data.persons.0')))->toBe(['id', 'name', 'first_name', 'last_name', 'date_of_birth', 'counts'])
        ->and(array_keys($response->json('data.doctor')))->toBe(['id', 'name'])
        ->and(array_keys($response->json('data')))->not->toContain('email', 'phone', 'expected_close_date', 'lead_value', 'diagnosis', 'user_id');
});

it('handles a sale without persons, doctor details, description and with an unknown outcome label', function () {
    $doctor = portalUser('kc-doc-1');
    $sale = doctorSale($doctor, herniaStage(), ['assessment_outcome' => 'onbekende_code', 'description' => null]);

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.show', ['kc-doc-1', $sale->id]))
        ->assertOk()
        ->assertJsonPath('data.persons', [])
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.assessment_outcome', ['code' => 'onbekende_code', 'label' => null])
        ->assertJsonPath('data.additional_research_required', false);
});

it('keeps a closed sale readable and does not grow queries per person', function () {
    $doctor = portalUser('kc-doc-1');
    $won = grantPortalRoles(Stage::factory()->won()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value]));
    $sale = doctorSale($doctor, $won, ['closed_at' => now()]);
    $count = function () use ($sale) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.show', ['kc-doc-1', $sale->id]))->assertOk();

        return count(DB::getQueryLog());
    };
    $sale->persons()->attach(Person::factory()->create()->id);
    $few = $count();
    $sale->persons()->attach(Person::factory()->count(4)->create()->pluck('id'));

    expect($count())->toBe($few);
});

it('does not leak the detail of sales outside the doctor\'s scope', function () {
    $doctor = portalUser('kc-doc-1');
    $other = portalUser('kc-doc-2');
    portalUser('kc-clinic-1', RoleCode::KLINIEK_MEDEWERKER);
    $hiddenStage = herniaStage(3, false);
    $foreign = doctorSale($other, herniaStage(1));
    $hidden = doctorSale($doctor, $hiddenStage);
    $otherPipeline = doctorSale($doctor, salesStage(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID));

    foreach ([$foreign->id, $hidden->id, $otherPipeline->id, 999999] as $id) {
        $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.show', ['kc-doc-1', $id]))->assertNotFound();
    }
    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.show', ['kc-clinic-1', $foreign->id]))->assertForbidden();
    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.show', ['kc-unknown', $foreign->id]))->assertNotFound();
    $this->withoutHeader('X-API-KEY')->getJson(route('api.clinic.sales-leads.show', ['kc-doc-1', $foreign->id]))->assertUnauthorized();
});

it('serves detail and notes with the doctor\'s own bearer token only', function () {
    $doctor = portalUser('kc-doc-1');
    portalUser('kc-doc-2');
    $sale = doctorSale($doctor, herniaStage());

    bearerGet('kc-doc-1', "sales-leads/{$sale->id}")->assertOk()->assertJsonPath('data.id', $sale->id);
    bearerGet('kc-doc-1', "sales-leads/{$sale->id}/notes")->assertOk();
});

it('forbids detail and notes with the bearer token of another subject', function () {
    $doctor = portalUser('kc-doc-1');
    portalUser('kc-doc-2');
    $sale = doctorSale($doctor, herniaStage());
    fakeKeycloakToken('kc-doc-2');
    $headers = ['Authorization' => 'Bearer t'];

    $this->getJson("/api/clinic/kc-doc-1/sales-leads/{$sale->id}", $headers)->assertForbidden();
    $this->getJson("/api/clinic/kc-doc-1/sales-leads/{$sale->id}/notes", $headers)->assertForbidden();
    $this->postJson("/api/clinic/kc-doc-1/sales-leads/{$sale->id}/notes", ['text' => 'x'], $headers)->assertForbidden();
    expect(Activity::where('type', 'note')->count())->toBe(0);
});

it('lists only the notes of this sale, newest first, paginated, plain text with author', function () {
    $doctor = portalUser('kc-doc-1');
    $sale = saleWithDepartment($doctor, herniaStage());
    $otherSale = doctorSale($doctor, herniaStage(2));
    $groupId = Group::query()->value('id');
    $mk = fn (SalesLead $s, string $type, string $comment, array $extra = []) => Activity::withoutEvents(fn () => Activity::create([
        'type' => $type, 'comment' => $comment, 'is_done' => 1, 'sales_lead_id' => $s->id, 'group_id' => $groupId, ...$extra,
    ]));
    $old = $mk($sale, 'note', '<p>Oud</p><p>Tweede <b>alinea</b></p>', ['created_by' => $doctor->id, 'created_at' => now()->subDay()]);
    $new = $mk($sale, 'note', 'Nieuw', ['created_by' => null, 'created_at' => now()]);
    $mk($sale, 'task', 'Taak');
    $mk($otherSale, 'note', 'Andere sale');

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.notes', ['kc-doc-1', $sale->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.0.author', null)
        ->assertJsonPath('data.1.text', "Oud\n\nTweede alinea")
        ->assertJsonPath('data.1.author', ['id' => $doctor->id, 'name' => $doctor->name])
        ->assertJsonMissingPath('data.0.user_id');

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.notes', ['keycloakUserId' => 'kc-doc-1', 'saleId' => $sale->id, 'per_page' => 1, 'page' => 2]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $old->id);
    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.notes', ['keycloakUserId' => 'kc-doc-1', 'saleId' => $sale->id, 'per_page' => 51]))->assertUnprocessable();
});

it('adds a note as the logged-in doctor under a bearer token, once, without other changes', function () {
    Mail::fake();
    Notification::fake();
    $doctor = portalUser('kc-doc-1');
    $other = portalUser('kc-doc-2');
    $stage = herniaStage();
    $sale = saleWithDepartment($doctor, $stage);
    fakeKeycloakToken('kc-doc-1');

    $response = $this->postJson("/api/clinic/kc-doc-1/sales-leads/{$sale->id}/notes", [
        'text' => "  Eerste regel <script>alert(1)</script>\n\nTweede alinea  ", 'user_id' => $other->id, 'created_by' => $other->id, 'type' => 'task', 'sales_lead_id' => 999, 'is_done' => 0,
    ], ['Authorization' => 'Bearer t'])
        ->assertCreated()
        ->assertJsonPath('data.text', "Eerste regel <script>alert(1)</script>\n\nTweede alinea")
        ->assertJsonPath('data.author', ['id' => $doctor->id, 'name' => $doctor->name]);

    $notes = Activity::where('type', 'note')->get();
    expect($notes)->toHaveCount(1)
        ->and($notes[0])->id->toBe($response->json('data.id'))->sales_lead_id->toBe($sale->id)->created_by->toBe($doctor->id)->user_id->toBe($doctor->id)
        ->and($notes[0]->comment)->not->toContain('<script>')->toContain('&lt;script&gt;')
        ->and($sale->fresh())->pipeline_stage_id->toBe($stage->id);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
    expect(DB::table('patient_messages')->count())->toBe(0);
});

it('validates note text', function (mixed $text) {
    $doctor = portalUser('kc-doc-1');
    $sale = saleWithDepartment($doctor, herniaStage());

    $this->withHeaders(apiKeyHeader())->postJson(route('api.clinic.sales-leads.notes.store', ['kc-doc-1', $sale->id]), ['text' => $text])
        ->assertUnprocessable()->assertJsonValidationErrors('text');

    expect(Activity::where('type', 'note')->count())->toBe(0);
})->with([null, '', '   ', str_repeat('a', 5001), [['a']]]);

it('refuses notes on sales outside the doctor\'s scope', function () {
    $doctor = portalUser('kc-doc-1');
    $other = portalUser('kc-doc-2');
    portalUser('kc-clinic-1', RoleCode::KLINIEK_MEDEWERKER);
    $hiddenStage = herniaStage(3, false);
    $foreign = saleWithDepartment($other, herniaStage(1));
    $hidden = saleWithDepartment($doctor, $hiddenStage);
    $otherPipeline = saleWithDepartment($doctor, salesStage(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID));

    foreach ([$foreign, $hidden, $otherPipeline] as $sale) {
        $this->withHeaders(apiKeyHeader())->postJson(route('api.clinic.sales-leads.notes.store', ['kc-doc-1', $sale->id]), ['text' => 'x'])->assertNotFound();
        $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.notes', ['kc-doc-1', $sale->id]))->assertNotFound();
    }
    $this->withHeaders(apiKeyHeader())->postJson(route('api.clinic.sales-leads.notes.store', ['kc-clinic-1', $foreign->id]), ['text' => 'x'])->assertForbidden();
    $this->withoutHeader('X-API-KEY')->postJson(route('api.clinic.sales-leads.notes.store', ['kc-doc-1', $foreign->id]), ['text' => 'x'])->assertUnauthorized();

    expect(Activity::where('type', 'note')->count())->toBe(0);
});

function closedHerniaStage(bool $won = true): Stage
{
    $stage = $won ? Stage::factory()->won() : Stage::factory()->lost();

    return grantPortalRoles($stage->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value]));
}

function salePerson(SalesLead $sale, string $first, string $last): Person
{
    $person = Person::factory()->create(['first_name' => $first, 'last_name' => $last]);
    $sale->persons()->attach($person->id);

    return $person;
}

it('lists only open sales by default and only closed sales with closed=1', function () {
    $doctor = portalUser('kc-doc-1');
    $open = doctorSale($doctor, herniaStage());
    $won = doctorSale($doctor, closedHerniaStage(true));
    $lost = doctorSale($doctor, closedHerniaStage(false));

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads', 'kc-doc-1'))
        ->assertOk()->assertJsonPath('data.*.id', [$open->id]);

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads', ['keycloakUserId' => 'kc-doc-1', 'closed' => 1]))
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonMissing(['id' => $open->id]);

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads', ['keycloakUserId' => 'kc-doc-1', 'closed' => 'x']))
        ->assertUnprocessable();
});

it('does not count or list closed sales of a stage the role may not see', function () {
    $doctor = portalUser('kc-doc-1');
    doctorSale($doctor, grantPortalRoles(Stage::factory()->won()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value]), false));

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads', ['keycloakUserId' => 'kc-doc-1', 'closed' => 1]))
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.counts', ['open' => 0, 'closed' => 0]);
});

it('searches on first and last name, partial and case-insensitive, within the own sales only', function () {
    $doctor = portalUser('kc-doc-1');
    $other = portalUser('kc-doc-2');
    $stage = herniaStage();
    $jan = doctorSale($doctor, $stage);
    salePerson($jan, 'Jan', 'Jansen');
    $piet = doctorSale($doctor, $stage);
    salePerson($piet, 'Piet', 'Pietersen');
    salePerson(doctorSale($other, $stage), 'Jan', 'Jansen');

    $search = fn (string $q) => $this->withHeaders(apiKeyHeader())
        ->getJson(route('api.clinic.sales-leads', ['keycloakUserId' => 'kc-doc-1', 'q' => $q]))
        ->assertOk()->json('data.*.id');

    expect($search('JANS'))->toBe([$jan->id])
        ->and($search('jan jans'))->toBe([$jan->id])
        ->and($search('jansen jan'))->toBe([$jan->id])
        ->and($search('pietersen'))->toBe([$piet->id])
        ->and($search('jansen pietersen'))->toBe([])
        ->and($search('nobody'))->toBe([])
        ->and($search('%'))->toBe([])
        ->and($search('  '))->toHaveCount(2);
});

it('reports open and closed counts that respect the search term but not the closed filter', function () {
    $doctor = portalUser('kc-doc-1');
    salePerson(doctorSale($doctor, herniaStage()), 'Jan', 'Jansen');
    salePerson(doctorSale($doctor, herniaStage(2)), 'Piet', 'Pietersen');
    salePerson(doctorSale($doctor, closedHerniaStage()), 'Jan', 'Bakker');
    salePerson(doctorSale($doctor, closedHerniaStage(false)), 'Klaas', 'Bakker');

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads', 'kc-doc-1'))
        ->assertOk()->assertJsonPath('meta.counts', ['open' => 2, 'closed' => 2])->assertJsonPath('meta.total', 2);

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads', ['keycloakUserId' => 'kc-doc-1', 'q' => 'jan', 'closed' => 1]))
        ->assertOk()->assertJsonPath('meta.counts', ['open' => 1, 'closed' => 1])->assertJsonPath('meta.total', 1);
});

function plannableType(bool $plannable = true): ResourceType
{
    return ResourceType::query()->firstOrCreate(['name' => $plannable ? 'MRI scanner' : 'Other']);
}

function personItem(SalesLead $sale, Person $person, array $attributes = [], ?Order $order = null, ?Carbon $from = null, bool $plannable = true): OrderItem
{
    $order ??= Order::factory()->create(['sales_lead_id' => $sale->id, 'first_examination_at' => null]);
    $item = OrderItem::factory()->create([
        'order_id'         => $order->id,
        'person_id'        => $person->id,
        'resource_type_id' => plannableType($plannable)->id,
        ...$attributes,
    ]);

    if ($from) {
        ResourceOrderItem::factory()->create([
            'orderitem_id' => $item->id,
            'resource_id'  => Resource::factory()->create()->id,
            'from'         => $from,
            'to'           => $from->copy()->addHour(),
        ]);
    }

    return $item;
}

function personAppointments(string $keycloakId, SalesLead $sale, Person $person)
{
    return test()->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.appointments', [$keycloakId, $sale->id, $person->id]));
}

it('lists all items of one person across all orders of the sale and never those of the other person', function () {
    $doctor = portalUser('kc-doc-1');
    $sale = doctorSale($doctor, herniaStage());
    $anna = salePerson($sale, 'Anna', 'A');
    $bert = salePerson($sale, 'Bert', 'B');
    $shared = Order::factory()->create(['sales_lead_id' => $sale->id, 'first_examination_at' => null]);

    $annaOne = personItem($sale, $anna, from: now()->addDays(2), order: $shared);
    $bertOne = personItem($sale, $bert, from: now()->addDays(3), order: $shared);
    $annaTwo = personItem($sale, $anna, from: now()->addDays(9));
    $bertTwo = personItem($sale, $bert, from: now()->addDays(4));
    personItem(doctorSale($doctor, herniaStage(2)), $anna, from: now()->addDay()); // andere sale

    $response = personAppointments('kc-doc-1', $sale, $anna)->assertOk();
    expect(collect($response->json('data'))->pluck('order_item_id')->all())->toBe([$annaOne->id, $annaTwo->id]);

    expect(collect(personAppointments('kc-doc-1', $sale, $bert)->assertOk()->json('data'))->pluck('order_item_id')->all())
        ->toBe([$bertOne->id, $bertTwo->id]);
});

it('shows planned, not yet planned and not-applicable items with status, ordering and counts, without prices', function () {
    $doctor = portalUser('kc-doc-1');
    $sale = doctorSale($doctor, herniaStage());
    $person = salePerson($sale, 'Anna', 'A');
    $past = personItem($sale, $person, from: now()->subDays(3));
    $older = personItem($sale, $person, from: now()->subDays(10));
    $later = personItem($sale, $person, from: now()->addDays(5));
    $soon = personItem($sale, $person, from: now()->addDay());
    $dated = Order::factory()->create(['sales_lead_id' => $sale->id, 'first_examination_at' => now()->addDays(20)->toDateString()]);
    $unplanned = personItem($sale, $person, order: $dated);
    $nothingToPlan = personItem($sale, $person, plannable: false);

    $response = personAppointments('kc-doc-1', $sale, $person)->assertOk()
        ->assertJsonPath('data.*.order_item_id', [$soon->id, $later->id, $past->id, $older->id, $unplanned->id, $nothingToPlan->id])
        ->assertJsonPath('data.*.status', ['scheduled', 'scheduled', 'scheduled', 'scheduled', 'unscheduled', 'not_applicable'])
        ->assertJsonPath('meta.counts', ['scheduled' => 4, 'unscheduled' => 1, 'not_applicable' => 1]);

    $booking = $soon->resourceOrderItems()->first();
    expect($response->json('data.0'))->toMatchArray(['id' => (string) $booking->id, 'start_at' => $booking->from->toIso8601String(), 'end_at' => $booking->to->toIso8601String(), 'expected_date' => null])
        ->and($response->json('data.4'))->toMatchArray(['id' => null, 'start_at' => null, 'end_at' => null, 'clinic' => null, 'expected_date' => now()->addDays(20)->toDateString()])
        ->and($response->json('data.5'))->toMatchArray(['id' => null, 'status' => 'not_applicable', 'expected_date' => null])
        ->and(array_keys($response->json('data.0')))->toBe(['id', 'order_item_id', 'order_id', 'label', 'type', 'status', 'start_at', 'end_at', 'expected_date', 'clinic'])
        ->and(json_encode($response->json()))->not->toMatch('/price|prijs|cost|inkoop/i');

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.appointments.show', ['kc-doc-1', $booking->id]))->assertOk();
});

it('gives every booking of an item its own line', function () {
    $doctor = portalUser('kc-doc-1');
    $sale = doctorSale($doctor, herniaStage());
    $person = salePerson($sale, 'Anna', 'A');
    $item = personItem($sale, $person, from: now()->addDays(2));
    ResourceOrderItem::factory()->create(['orderitem_id' => $item->id, 'resource_id' => Resource::factory()->create()->id, 'from' => now()->addDays(1), 'to' => now()->addDays(1)->addHour()]);

    personAppointments('kc-doc-1', $sale, $person)->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.*.order_item_id', [$item->id, $item->id])
        ->assertJsonPath('meta.counts.scheduled', 2);
});

it('hides lost and incorrect items', function () {
    $doctor = portalUser('kc-doc-1');
    $sale = doctorSale($doctor, herniaStage());
    $person = salePerson($sale, 'Anna', 'A');
    $visible = personItem($sale, $person, from: now()->addDay());
    // Status na het inplannen zetten: een boeking maakt van de regel zelf weer "ingepland".
    personItem($sale, $person, from: now()->addDay())->update(['status' => 'lost']);
    personItem($sale, $person, from: now()->addDay())->update(['status' => 'incorrect']);

    personAppointments('kc-doc-1', $sale, $person)->assertOk()->assertJsonPath('data.*.order_item_id', [$visible->id]);
});

it('refuses persons that are not on the sale, other doctors\' sales and non-doctors', function () {
    $doctor = portalUser('kc-doc-1');
    $other = portalUser('kc-doc-2');
    portalUser('kc-clinic-1', RoleCode::KLINIEK_MEDEWERKER);
    $sale = doctorSale($doctor, herniaStage());
    $person = salePerson($sale, 'Anna', 'A');
    $stranger = Person::factory()->create();
    personItem($sale, $stranger, from: now()->addDay());
    $foreign = doctorSale($other, herniaStage(2));
    $foreignPerson = salePerson($foreign, 'Piet', 'P');

    personAppointments('kc-doc-1', $sale, $stranger)->assertNotFound();
    personAppointments('kc-doc-1', $sale, $foreignPerson)->assertNotFound();
    personAppointments('kc-doc-1', $foreign, $foreignPerson)->assertNotFound();
    personAppointments('kc-doc-2', $sale, $person)->assertNotFound();
    personAppointments('kc-clinic-1', $sale, $person)->assertForbidden();
    $this->withoutHeader('X-API-KEY')->getJson(route('api.clinic.sales-leads.appointments', ['kc-doc-1', $sale->id, $person->id]))->assertUnauthorized();
});

it('reports per person how many appointments are planned and not yet planned in the sale detail', function () {
    $doctor = portalUser('kc-doc-1');
    $sale = doctorSale($doctor, herniaStage());
    $anna = salePerson($sale, 'Anna', 'A');
    $bert = salePerson($sale, 'Bert', 'B');
    personItem($sale, $anna, from: now()->addDay());
    personItem($sale, $anna, from: now()->addDays(2));
    personItem($sale, $anna);
    personItem($sale, $bert);

    $response = $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.show', ['kc-doc-1', $sale->id]))->assertOk();
    $counts = collect($response->json('data.persons'))->mapWithKeys(fn ($p) => [$p['id'] => $p['counts']]);

    expect($counts[$anna->id])->toBe(['scheduled' => 2, 'unscheduled' => 1])
        ->and($counts[$bert->id])->toBe(['scheduled' => 0, 'unscheduled' => 1])
        ->and(array_keys($response->json('data')))->not->toContain('appointments')
        ->and($response->json('data.counts'))->toBe(['files' => 0]);
});

function saleFile(SalesLead|Order $owner, ?DocumentType $type, string $name = 'verslag.pdf', string $content = 'pdf-bytes'): ActivityFile
{
    $activity = Activity::query()->create([
        'title'            => 'Titel '.$name,
        'type'             => 'file',
        'schedule_from'    => now(),
        'schedule_to'      => now(),
        'is_done'          => 1,
        ...($owner instanceof Order ? ['order_id' => $owner->id] : ['sales_lead_id' => $owner->id]),
        'document_type_id' => $type?->id,
    ]);
    $path = 'activities/'.$activity->id.'/'.$name;
    Storage::disk(config('filesystems.default'))->put($path, $content);

    return ActivityFile::query()->create(['name' => $name, 'path' => $path, 'activity_id' => $activity->id]);
}

it('lists the documents of the sale and its orders, only of types shown in the clinic portal', function () {
    Storage::fake(config('filesystems.default'));
    $doctor = portalUser('kc-doc-1');
    $sale = doctorSale($doctor, herniaStage());
    $order = Order::factory()->create(['sales_lead_id' => $sale->id]);
    $visible = DocumentType::factory()->create(['name' => 'Testverslagen', 'show_in_clinic_portal' => true]);
    $hidden = DocumentType::factory()->create(['show_in_clinic_portal' => false]);

    $onSale = saleFile($sale, $visible, 'a.pdf', '12345');
    $onOrder = saleFile($order, $visible, 'b.pdf');
    saleFile($sale, $hidden, 'hidden.pdf');
    saleFile(Order::factory()->create(['sales_lead_id' => doctorSale($doctor, herniaStage(2))->id]), $visible, 'other-sale.pdf');

    $response = $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.documents', ['kc-doc-1', $sale->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())->toBe([$onSale->id, $onOrder->id])
        ->and(collect($response->json('data'))->firstWhere('id', $onSale->id))->toMatchArray([
            'file_name' => 'a.pdf', 'type' => ['id' => $visible->id, 'label' => 'Testverslagen'], 'size' => 5,
        ]);

    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.show', ['kc-doc-1', $sale->id]))
        ->assertOk()->assertJsonPath('data.counts.files', 2);
});

it('downloads a document of the own sale and refuses hidden types, other sales and other doctors', function () {
    Storage::fake(config('filesystems.default'));
    $doctor = portalUser('kc-doc-1');
    portalUser('kc-doc-2');
    $sale = doctorSale($doctor, herniaStage());
    $visible = DocumentType::factory()->create(['show_in_clinic_portal' => true]);
    $hidden = DocumentType::factory()->create(['show_in_clinic_portal' => false]);
    $file = saleFile($sale, $visible, 'ok.pdf', 'inhoud');
    $hiddenFile = saleFile($sale, $hidden, 'hidden.pdf');
    $otherSale = doctorSale($doctor, herniaStage(2));
    $otherFile = saleFile($otherSale, $visible, 'other.pdf');

    $download = fn (string $kc, SalesLead $s, ActivityFile $f) => $this->withHeaders(apiKeyHeader())
        ->get(route('api.clinic.sales-leads.documents.download', [$kc, $s->id, $f->id]));

    expect($download('kc-doc-1', $sale, $file)->assertOk()->streamedContent())->toBe('inhoud');
    $download('kc-doc-1', $sale, $hiddenFile)->assertNotFound();
    $download('kc-doc-1', $sale, $otherFile)->assertNotFound();
    $download('kc-doc-2', $sale, $file)->assertNotFound();
    $this->withHeaders(apiKeyHeader())->getJson(route('api.clinic.sales-leads.documents', ['kc-doc-2', $sale->id]))->assertNotFound();
});

function tabUrls(): array
{
    $sale = doctorSale(User::query()->where('keycloak_user_id', 'kc-doc-1')->first(), herniaStage());
    $file = saleFile($sale, DocumentType::factory()->create(['show_in_clinic_portal' => true]));
    $person = salePerson($sale, 'Anna', 'A');

    return [
        route('api.clinic.sales-leads.appointments', ['kc-doc-1', $sale->id, $person->id]),
        route('api.clinic.sales-leads.documents', ['kc-doc-1', $sale->id]),
        route('api.clinic.sales-leads.documents.download', ['kc-doc-1', $sale->id, $file->id]),
    ];
}

it('serves the appointment and document tabs to the bearer token of the doctor itself', function () {
    Storage::fake(config('filesystems.default'));
    portalUser('kc-doc-1');
    $urls = tabUrls();
    fakeKeycloakToken('kc-doc-1');

    foreach ($urls as $url) {
        $this->get($url, ['Authorization' => 'Bearer t', 'Accept' => 'application/json'])->assertOk();
    }
});

it('forbids the appointment and document tabs to the bearer token of another subject', function () {
    Storage::fake(config('filesystems.default'));
    portalUser('kc-doc-1');
    $urls = tabUrls();
    fakeKeycloakToken('kc-doc-2');

    foreach ($urls as $url) {
        $this->get($url, ['Authorization' => 'Bearer t', 'Accept' => 'application/json'])->assertForbidden();
    }
});
