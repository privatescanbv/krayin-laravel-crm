<?php

use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\Contact\Models\Person;

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

    /** @var Activity $activity */
    $activity = Activity::query()->create([
        'title'         => 'MRI uitslag knie',
        'type'          => 'file',
        'comment'       => null,
        'schedule_from' => now(),
        'schedule_to'   => now(),
        'is_done'       => 1,
        'order_id'      => $order->id,
        'additional'    => [
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
