<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use Webkul\Activity\Models\Activity;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('document types index returns datagrid json', function () {
    $d1 = DocumentType::factory()->create();
    $d2 = DocumentType::factory()->create();

    $response = $this->getJson(route('admin.settings.document_types.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($d1->id, $d2->id);
});

test('can create document type', function () {
    $payload = [
        'name'                    => 'Operatieverslagen',
        'description'             => 'MRI, CT, foto\'s',
        'show_in_clinic_portal'   => '1',
        'show_in_patient_portal'  => '1',
        'sort_order'              => 5,
    ];

    $response = $this->postJson(route('admin.settings.document_types.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('document_types', [
        'name'                    => 'Operatieverslagen',
        'show_in_clinic_portal'   => true,
        'show_in_patient_portal'  => true,
        'sort_order'              => 5,
        'key'                     => null,
    ]);
});

test('creating a document type ignores a submitted key', function () {
    $response = $this->postJson(route('admin.settings.document_types.store'), [
        'name' => 'Nieuw type',
        'key'  => 'prescription',
    ]);
    $response->assertOk();

    $this->assertDatabaseHas('document_types', [
        'name' => 'Nieuw type',
        'key'  => null,
    ]);
});

test('can update document type', function () {
    $documentType = DocumentType::factory()->create(['show_in_clinic_portal' => false]);

    $payload = [
        'name'                    => 'Updated naam',
        'description'             => 'Nieuwe omschrijving',
        'show_in_clinic_portal'   => '1',
        'show_in_patient_portal'  => '0',
        'sort_order'              => 9,
    ];

    $response = $this->putJson(route('admin.settings.document_types.update', ['id' => $documentType->id]), $payload);
    $response->assertOk();

    $documentType->refresh();
    expect($documentType->name)->toBe('Updated naam');
    expect($documentType->show_in_clinic_portal)->toBeTrue();
    expect($documentType->show_in_patient_portal)->toBeFalse();
    expect($documentType->sort_order)->toBe(9);
});

test('updating a document type does not change its key', function () {
    $documentType = DocumentType::findByKey(DocumentType::KEY_FINANCIAL);

    $response = $this->putJson(route('admin.settings.document_types.update', ['id' => $documentType->id]), [
        'name' => 'Financieel hernoemd',
        'key'  => 'something_else',
    ]);
    $response->assertOk();

    $documentType->refresh();
    expect($documentType->name)->toBe('Financieel hernoemd');
    expect($documentType->key)->toBe(DocumentType::KEY_FINANCIAL);
});

test('can delete a document type without documents', function () {
    $documentType = DocumentType::factory()->create();

    $response = $this->deleteJson(route('admin.settings.document_types.delete', ['id' => $documentType->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('document_types', [
        'id' => $documentType->id,
    ]);
});

test('cannot delete a document type that has documents', function () {
    $documentType = DocumentType::factory()->create();

    Activity::query()->create([
        'title'            => 'Bestand',
        'type'             => 'file',
        'schedule_from'    => now(),
        'schedule_to'      => now(),
        'is_done'          => 1,
        'document_type_id' => $documentType->id,
    ]);

    $response = $this->deleteJson(route('admin.settings.document_types.delete', ['id' => $documentType->id]));
    $response->assertStatus(400);

    $this->assertDatabaseHas('document_types', [
        'id' => $documentType->id,
    ]);
});

test('cannot delete a system document type even without documents', function () {
    $documentType = DocumentType::findByKey(DocumentType::KEY_OWN_UPLOAD);

    $response = $this->deleteJson(route('admin.settings.document_types.delete', ['id' => $documentType->id]));
    $response->assertStatus(400);

    $this->assertDatabaseHas('document_types', [
        'id' => $documentType->id,
    ]);
});
