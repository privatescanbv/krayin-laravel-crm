<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\PartnerProduct;
use App\Models\ResourceType;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('partner products index returns datagrid json', function () {
    $p1 = PartnerProduct::factory()->create();
    $p2 = PartnerProduct::factory()->create();

    $response = $this->getJson(route('admin.settings.partner_products.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($p1->id, $p2->id);
});

test('can create partner product', function () {

    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;
    $payload = [
        'name'               => 'MRI Scan',
        'currency'           => 'EUR',
        'sales_price'        => 199.99,
        'active'             => 1,
        'description'        => 'Great partner product',
        'discount_info'      => 'Intro discount 10%',
        'resource_type_id'   => $resourceTypeId,
        'clinics'            => [$clinicId],
        'partner_name'       => 'Acme Partner',
        'clinic_description' => 'Omschrijving kliniek',
        'duration'           => 60,
    ];

    $response = $this->postJson(route('admin.settings.partner_products.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('partner_products', [
        'partner_name' => 'Acme Partner',
    ]);
});

test('can update partner product', function () {
    $pp = PartnerProduct::factory()->create();

    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;

    $payload = [
        'name'               => 'CT Scan',
        'currency'           => 'EUR',
        'sales_price'        => 299.95,
        'active'             => 0,
        'description'        => 'Updated description',
        'discount_info'      => null,
        'resource_type_id'   => $resourceTypeId,
        'partner_name'       => 'Updated Partner Name',
        'clinic_description' => 'Nieuwe omschrijving kliniek',
        'duration'           => 45,
        'clinics'            => [$clinicId],
        '_method'            => 'put',
    ];

    $response = $this->postJson(route('admin.settings.partner_products.update', ['id' => $pp->id]), $payload);
    $response->assertOk()->assertJsonPath('data.partner_name', 'Updated Partner Name');

    $this->assertDatabaseHas('partner_products', [
        'id'           => $pp->id,
        'partner_name' => 'Updated Partner Name',
    ]);
});

test('can delete partner product', function () {
    $pp = PartnerProduct::factory()->create();

    $response = $this->deleteJson(route('admin.settings.partner_products.delete', ['id' => $pp->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('partner_products', [
        'id' => $pp->id,
    ]);
});

test('can create partner product with resources from selected clinics', function () {
    $resourceType = ResourceType::factory()->create();
    $clinic = Clinic::factory()->create();
    $resource = \App\Models\Resource::factory()->create([
        'clinic_id' => $clinic->id,
        'resource_type_id' => $resourceType->id,
    ]);

    $uniquePartnerName = 'ResourceTest' . uniqid();
    
    $payload = [
        'name'               => 'CT Scan with Resource',
        'currency'           => 'EUR',
        'sales_price'        => 299.99,
        'active'             => 1,
        'description'        => 'Partner product with valid resource',
        'resource_type_id'   => $resourceType->id,
        'clinics'            => [$clinic->id],
        'resources'          => [$resource->id],
        'partner_name'       => $uniquePartnerName,
        'duration'           => 45,
    ];

    $response = $this->postJson(route('admin.settings.partner_products.store'), $payload);
    
    if ($response->status() !== 200) {
        dump('Response status: ' . $response->status());
        dump('Response body:', $response->json());
    }
    
    $response->assertOk();

    // Verify the partner product was created
    $partnerProduct = PartnerProduct::latest()->first();
    expect($partnerProduct)->not->toBeNull();
    expect($partnerProduct->partner_name)->toBe($uniquePartnerName);
    
    // Verify the resource relationship
    expect($partnerProduct->resources->pluck('id')->toArray())->toContain($resource->id);
});

test('cannot create partner product with resources from different clinics', function () {
    $resourceType = ResourceType::factory()->create();
    $clinicA = Clinic::factory()->create(['name' => 'Clinic A']);
    $clinicB = Clinic::factory()->create(['name' => 'Clinic B']);
    
    $resourceFromClinicB = \App\Models\Resource::factory()->create([
        'clinic_id' => $clinicB->id,
        'resource_type_id' => $resourceType->id,
    ]);

    $payload = [
        'name'               => 'Invalid Partner Product',
        'currency'           => 'EUR',
        'sales_price'        => 199.99,
        'active'             => 1,
        'resource_type_id'   => $resourceType->id,
        'clinics'            => [$clinicA->id], // Clinic A selected
        'resources'          => [$resourceFromClinicB->id], // Resource from Clinic B
        'partner_name'       => 'Mismatch Test',
        'duration'           => 30,
    ];

    $response = $this->postJson(route('admin.settings.partner_products.store'), $payload);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors('resources');
    
    expect($response->json('errors.resources.0'))
        ->toContain('Gekozen resource(s) horen niet bij de geselecteerde kliniek(en)');
});

test('cannot update partner product with resources from different clinics', function () {
    $resourceType = ResourceType::factory()->create();
    $clinicA = Clinic::factory()->create(['name' => 'Clinic A']);
    $clinicB = Clinic::factory()->create(['name' => 'Clinic B']);
    
    $resourceFromClinicA = \App\Models\Resource::factory()->create([
        'clinic_id' => $clinicA->id,
        'resource_type_id' => $resourceType->id,
    ]);
    
    $resourceFromClinicB = \App\Models\Resource::factory()->create([
        'clinic_id' => $clinicB->id,
        'resource_type_id' => $resourceType->id,
    ]);

    $pp = PartnerProduct::factory()->create();
    $pp->clinics()->sync([$clinicA->id]);
    $pp->resources()->sync([$resourceFromClinicA->id]);

    $payload = [
        'name'               => $pp->name,
        'currency'           => 'EUR',
        'sales_price'        => 199.99,
        'active'             => 1,
        'resource_type_id'   => $resourceType->id,
        'clinics'            => [$clinicA->id], // Still Clinic A
        'resources'          => [$resourceFromClinicB->id], // Try to switch to resource from Clinic B
        'partner_name'       => $pp->partner_name,
        'duration'           => 30,
        '_method'            => 'put',
    ];

    $response = $this->postJson(route('admin.settings.partner_products.update', ['id' => $pp->id]), $payload);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors('resources');
});

test('can update partner product with resources from multiple selected clinics', function () {
    $resourceType = ResourceType::factory()->create();
    $clinicA = Clinic::factory()->create(['name' => 'Clinic A']);
    $clinicB = Clinic::factory()->create(['name' => 'Clinic B']);
    
    $resourceFromClinicA = \App\Models\Resource::factory()->create([
        'clinic_id' => $clinicA->id,
        'resource_type_id' => $resourceType->id,
    ]);
    
    $resourceFromClinicB = \App\Models\Resource::factory()->create([
        'clinic_id' => $clinicB->id,
        'resource_type_id' => $resourceType->id,
    ]);

    $pp = PartnerProduct::factory()->create();

    $payload = [
        'name'               => 'Multi-Clinic Product',
        'currency'           => 'EUR',
        'sales_price'        => 199.99,
        'active'             => 1,
        'resource_type_id'   => $resourceType->id,
        'clinics'            => [$clinicA->id, $clinicB->id], // Both clinics
        'resources'          => [$resourceFromClinicA->id, $resourceFromClinicB->id], // Resources from both
        'partner_name'       => $pp->partner_name,
        'duration'           => 30,
        '_method'            => 'put',
    ];

    $response = $this->postJson(route('admin.settings.partner_products.update', ['id' => $pp->id]), $payload);
    $response->assertOk();

    $pp->refresh();
    expect($pp->clinics->pluck('id')->toArray())->toContain($clinicA->id, $clinicB->id);
    expect($pp->resources->pluck('id')->toArray())->toContain($resourceFromClinicA->id, $resourceFromClinicB->id);
});
