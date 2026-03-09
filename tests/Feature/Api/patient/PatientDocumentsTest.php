<?php

use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

it('returns paginated documents for a patient', function () {
    config(['api.keys' => ['test-api-key']]);

    $keycloakUserId = (string) Str::uuid();

    /** @var Person $person */
    $person = Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'is_active'        => true,
    ]);

    /** @var SalesLead $salesLead */
    $salesLead = SalesLead::factory()->create();
    // Attach directly via relation (avoid extra side effects like anamnesis creation).
    $salesLead->persons()->attach($person->id);

    /** @var Order $order */
    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
    ]);

    // should not be visible: not publish_to_portal
    /** @var Activity $activity */
    $activity1 = Activity::query()->create([
        'title'             => 'MRI uitslag knie',
        'type'              => 'file',
        'comment'           => null,
        'schedule_from'     => now(),
        'schedule_to'       => now(),
        'is_done'           => 1,
        'publish_to_portal' => false,
        'order_id'          => $order->id,
        'additional'        => [
            'document_type' => 'report',
        ],
    ]);

    /** @var Activity $activity */
    $activity = Activity::query()->create([
        'title'             => 'MRI uitslag knie',
        'type'              => 'file',
        'comment'           => null,
        'schedule_from'     => now(),
        'schedule_to'       => now(),
        'is_done'           => 1,
        'publish_to_portal' => true,
        'order_id'          => $order->id,
        'additional'        => [
            'document_type' => 'report',
        ],
    ]);

    /** @var ActivityFile $file */
    $file = ActivityFile::query()->create([
        'name'        => 'mri-knie-uitslag.pdf',
        'path'        => 'activities/'.$activity->id.'/mri-knie-uitslag.pdf',
        'activity_id' => $activity->id,
    ]);

    $response = $this->getJson(
        "/api/patient/{$keycloakUserId}/documents",
        ['X-API-KEY' => 'test-api-key']
    );

    $response->assertOk();

    $response->assertJsonStructure([
        'data' => [
            [
                'id',
                'patient_id',
                'type',
                'title',
                'file_name',
                'mime_type',
                'size',
                'download_url',
                'created_at',
            ],
        ],
        'meta' => [
            'current_page',
            'per_page',
            'total',
        ],
    ]);

    $response->assertJsonPath('meta.current_page', 1);
    $response->assertJsonPath('meta.total', 1);
    $response->assertJsonPath('data.0.id', $file->id);
    $response->assertJsonPath('data.0.patient_id', $person->id);
    $response->assertJsonPath('data.0.type', 'report');
    $response->assertJsonPath('data.0.title', 'MRI uitslag knie');
    $response->assertJsonPath('data.0.file_name', 'mri-knie-uitslag.pdf');

    expect($response->json('data.0.download_url'))
        ->toContain("/api/patient/{$keycloakUserId}/documents/{$file->id}/download");
});

it('forbids accessing another patient documents with a keycloak token', function () {
    // Ensure the middleware uses Keycloak (not API key).
    config(['api.keys' => []]);

    $tokenSubject = (string) Str::uuid();
    $otherPatientKeycloakId = (string) Str::uuid();

    $socialiteUser = new SocialiteUser;
    $socialiteUser->setRaw(['sub' => $tokenSubject]);
    $socialiteUser->map(['id' => $tokenSubject]);

    $provider = Mockery::mock();
    $provider->shouldReceive('userFromToken')->once()->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('keycloak')->once()->andReturn($provider);

    // The patient in the URL is NOT the token subject.
    Person::factory()->create([
        'keycloak_user_id' => $otherPatientKeycloakId,
        'is_active'        => true,
    ]);

    $response = $this->getJson(
        "/api/patient/{$otherPatientKeycloakId}/documents",
        ['Authorization' => 'Bearer test-token']
    );

    $response->assertForbidden();
});

// ─── FILE activity via person relations ───────────────────────────────────

function makeFileActivity(array $attrs = []): Activity
{
    return Activity::query()->create(array_merge([
        'title'             => 'Test Document',
        'type'              => 'file',
        'schedule_from'     => now(),
        'schedule_to'       => now(),
        'is_done'           => 1,
        'publish_to_portal' => true,
        'additional'        => ['document_type' => 'report'],
    ], $attrs));
}

function makeFileRecord(Activity $activity, string $name = 'doc.pdf'): ActivityFile
{
    return ActivityFile::query()->create([
        'name'        => $name,
        'path'        => 'activities/'.$activity->id.'/'.$name,
        'activity_id' => $activity->id,
    ]);
}

it('returns file activity linked directly via person_id FK', function () {
    config(['api.keys' => ['test-api-key']]);

    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $activity = makeFileActivity(['person_id' => $person->id]);
    $file = makeFileRecord($activity);

    $response = $this->getJson(
        "/api/patient/{$keycloakUserId}/documents",
        ['X-API-KEY' => 'test-api-key']
    );

    $response->assertOk();
    $response->assertJsonPath('meta.total', 1);
    $response->assertJsonPath('data.0.id', $file->id);
    $response->assertJsonPath('data.0.group', null); // no order linked
});

it('returns file activity linked via sales_lead persons', function () {
    config(['api.keys' => ['test-api-key']]);

    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $activity = makeFileActivity(['sales_lead_id' => $salesLead->id]);
    $file = makeFileRecord($activity, 'saleslead-doc.pdf');

    $response = $this->getJson(
        "/api/patient/{$keycloakUserId}/documents",
        ['X-API-KEY' => 'test-api-key']
    );

    $response->assertOk();
    $response->assertJsonPath('meta.total', 1);
    $response->assertJsonPath('data.0.id', $file->id);
});

it('returns file activity linked via lead persons', function () {
    config(['api.keys' => ['test-api-key']]);

    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $lead = Lead::factory()->create();
    $lead->persons()->attach($person->id);

    $activity = makeFileActivity(['lead_id' => $lead->id]);
    $file = makeFileRecord($activity, 'lead-doc.pdf');

    $response = $this->getJson(
        "/api/patient/{$keycloakUserId}/documents",
        ['X-API-KEY' => 'test-api-key']
    );

    $response->assertOk();
    $response->assertJsonPath('meta.total', 1);
    $response->assertJsonPath('data.0.id', $file->id);
});

it('excludes file activity with publish_to_portal false', function () {
    config(['api.keys' => ['test-api-key']]);

    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $activity = makeFileActivity(['publish_to_portal' => false, 'person_id' => $person->id]);
    makeFileRecord($activity);

    $response = $this->getJson(
        "/api/patient/{$keycloakUserId}/documents",
        ['X-API-KEY' => 'test-api-key']
    );

    $response->assertOk();
    $response->assertJsonPath('meta.total', 0);
});

it('order_id filter scopes documents to that order only', function () {
    config(['api.keys' => ['test-api-key']]);

    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $orderA = Order::factory()->create(['sales_lead_id' => $salesLead->id]);
    $orderB = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $activityA = makeFileActivity(['order_id' => $orderA->id]);
    $fileA = makeFileRecord($activityA, 'order-a.pdf');

    $activityB = makeFileActivity(['order_id' => $orderB->id]);
    makeFileRecord($activityB, 'order-b.pdf');

    $response = $this->getJson(
        "/api/patient/{$keycloakUserId}/documents?order_id={$orderA->id}",
        ['X-API-KEY' => 'test-api-key']
    );

    $response->assertOk();
    $response->assertJsonPath('meta.total', 1);
    $response->assertJsonPath('data.0.id', $fileA->id);
});

it('group label uses order title when activity has an order_id', function () {
    config(['api.keys' => ['test-api-key']]);

    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'title'         => 'MRI Knie',
    ]);

    $activity = makeFileActivity(['order_id' => $order->id]);
    makeFileRecord($activity);

    $response = $this->getJson(
        "/api/patient/{$keycloakUserId}/documents",
        ['X-API-KEY' => 'test-api-key']
    );

    $response->assertOk();
    $response->assertJsonPath('data.0.group', 'Order MRI Knie');
});

it('download works for file activity linked via person_id FK', function () {
    config(['api.keys' => ['test-api-key']]);
    Storage::fake('local');

    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $activity = makeFileActivity(['person_id' => $person->id]);
    $file = makeFileRecord($activity);

    Storage::disk('local')->put($file->path, 'fake-content');

    $response = $this->getJson(
        "/api/patient/{$keycloakUserId}/documents/{$file->id}/download",
        ['X-API-KEY' => 'test-api-key']
    );

    $response->assertOk();
});

it('download returns 404 for file activity not linked to patient', function () {
    config(['api.keys' => ['test-api-key']]);

    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    // Activity belongs to a different sales lead / person — not linked to our patient.
    $otherSalesLead = SalesLead::factory()->create();
    $activity = makeFileActivity(['sales_lead_id' => $otherSalesLead->id]);
    $file = makeFileRecord($activity);

    $response = $this->getJson(
        "/api/patient/{$keycloakUserId}/documents/{$file->id}/download",
        ['X-API-KEY' => 'test-api-key']
    );

    $response->assertNotFound();
});
