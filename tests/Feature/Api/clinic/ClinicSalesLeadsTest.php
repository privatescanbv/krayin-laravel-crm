<?php

use App\Enums\ActivityType;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Enums\RoleCode;
use App\Models\AssessmentOutcome;
use App\Models\Department;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Webkul\Activity\Models\Activity;
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
        ->and(array_keys($response->json('data.persons.0')))->toBe(['id', 'name', 'first_name', 'last_name', 'date_of_birth'])
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
    $won = Stage::factory()->won()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value]);
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
    $hiddenStage = herniaStage(3);
    $hiddenStage->roles()->attach(Role::factory()->create()->id);
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
    $hiddenStage = herniaStage(3);
    $hiddenStage->roles()->attach(Role::factory()->create()->id);
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
