<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\ResourceType;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductGroup;

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
        'name'                         => 'MRI Scan',
        'currency'                     => 'EUR',
        'sales_price'                  => 199.99,
        'active'                       => 1,
        'description'                  => 'Great partner product',
        'discount_info'                => 'Intro discount 10%',
        'resource_type_id'             => $resourceTypeId,
        'clinics'                      => [$clinicId],
        'clinic_description'           => 'Omschrijving kliniek',
        'duration'                     => 60,
        'purchase_price_misc'          => 10.50,
        'purchase_price_doctor'        => 25.00,
        'purchase_price_cardiology'    => 15.75,
        'purchase_price_clinic'        => 30.25,
        'purchase_price_royal_doctors' => 12.00,
        'purchase_price_radiology'     => 20.50,
    ];

    $response = $this->postJson(route('admin.settings.partner_products.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('partner_products', [
        'name' => 'MRI Scan',
    ]);

    $createdProduct = PartnerProduct::where('name', 'MRI Scan')->first();
    expect($createdProduct->purchase_price)->toBe('114.00')
        ->and($createdProduct->purchase_price_misc)->toBe('10.50')
        ->and($createdProduct->purchase_price_doctor)->toBe('25.00');
});

test('can update partner product', function () {
    $pp = PartnerProduct::factory()->create();

    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;

    $payload = [
        'name'                         => 'CT Scan',
        'currency'                     => 'EUR',
        'sales_price'                  => 299.95,
        'active'                       => 0,
        'description'                  => 'Updated description',
        'discount_info'                => null,
        'resource_type_id'             => $resourceTypeId,
        'clinic_description'           => 'Nieuwe omschrijving kliniek',
        'duration'                     => 45,
        'clinics'                      => [$clinicId],
        'purchase_price_misc'          => 5.00,
        'purchase_price_doctor'        => 50.00,
        'purchase_price_cardiology'    => 10.00,
        'purchase_price_clinic'        => 15.00,
        'purchase_price_royal_doctors' => 8.00,
        'purchase_price_radiology'     => 12.00,
        '_method'                      => 'put',
    ];

    $response = $this->postJson(route('admin.settings.partner_products.update', ['id' => $pp->id]), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'CT Scan');

    $this->assertDatabaseHas('partner_products', [
        'id'   => $pp->id,
        'name' => 'CT Scan',
    ]);

    $pp->refresh();
    expect($pp->purchase_price)->toBe('100.00')
        ->and($pp->purchase_price_misc)->toBe('5.00')
        ->and($pp->purchase_price_doctor)->toBe('50.00');
});

test('can delete partner product', function () {
    $pp = PartnerProduct::factory()->create();

    $response = $this->deleteJson(route('admin.settings.partner_products.delete', ['id' => $pp->id]));
    $response->assertOk();

    // Check that the partner product is soft deleted (deleted_at is set)
    $this->assertDatabaseHas('partner_products', [
        'id' => $pp->id,
        'deleted_at' => now(),
    ]);
    
    // Check that it's not returned in normal queries
    $this->assertDatabaseMissing('partner_products', [
        'id' => $pp->id,
        'deleted_at' => null,
    ]);
});

test('purchase price total is calculated correctly on create', function () {
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;

    $payload = [
        'name'                         => 'Test Product',
        'currency'                     => 'EUR',
        'sales_price'                  => 500.00,
        'active'                       => 1,
        'resource_type_id'             => $resourceTypeId,
        'clinics'                      => [$clinicId],
        'purchase_price_misc'          => 22.50,
        'purchase_price_doctor'        => 100.00,
        'purchase_price_cardiology'    => 50.25,
        'purchase_price_clinic'        => 75.75,
        'purchase_price_royal_doctors' => 30.00,
        'purchase_price_radiology'     => 45.50,
    ];

    $response = $this->postJson(route('admin.settings.partner_products.store'), $payload);
    $response->assertOk();

    $pp = PartnerProduct::where('name', 'Test Product')->first();

    // Use string comparison since decimal:2 cast returns strings
    expect($pp->purchase_price)->toBe('324.00')
        ->and($pp->purchase_price_misc)->toBe('22.50')
        ->and($pp->purchase_price_doctor)->toBe('100.00')
        ->and($pp->purchase_price_cardiology)->toBe('50.25')
        ->and($pp->purchase_price_clinic)->toBe('75.75')
        ->and($pp->purchase_price_royal_doctors)->toBe('30.00')
        ->and($pp->purchase_price_radiology)->toBe('45.50');
});

test('validates resources belong to selected clinics when creating', function () {
    $resourceType = ResourceType::factory()->create();
    $clinic = Clinic::factory()->create();
    $resource = Resource::factory()->create([
        'clinic_id'        => $clinic->id,
        'resource_type_id' => $resourceType->id,
    ]);

    $payload = [
        'name'               => 'Valid Product',
        'currency'           => 'EUR',
        'sales_price'        => 100.00,
        'active'             => 1,
        'resource_type_id'   => $resourceType->id,
        'clinics'            => [$clinic->id],
        'resources'          => [$resource->id],
        'partner_name'       => 'Test'.time().rand(1000, 9999),
        'duration'           => 30,
    ];

    $response = $this->postJson(route('admin.settings.partner_products.store'), $payload);
    $response->assertOk();

    // Just verify relationships exist
    $createdId = $response->json('data.id');

    $this->assertDatabaseHas('clinic_partner_product', [
        'partner_product_id' => $createdId,
        'clinic_id'          => $clinic->id,
    ]);

    $this->assertDatabaseHas('partner_product_resource', [
        'partner_product_id' => $createdId,
        'resource_id'        => $resource->id,
    ]);
});

test('cannot create partner product with resources from different clinics', function () {
    $resourceType = ResourceType::factory()->create();
    $clinicA = Clinic::factory()->create(['name' => 'Clinic A']);
    $clinicB = Clinic::factory()->create(['name' => 'Clinic B']);

    $resourceFromClinicB = Resource::factory()->create([
        'clinic_id'        => $clinicB->id,
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

    $resourceFromClinicA = Resource::factory()->create([
        'clinic_id'        => $clinicA->id,
        'resource_type_id' => $resourceType->id,
    ]);

    $resourceFromClinicB = Resource::factory()->create([
        'clinic_id'        => $clinicB->id,
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

    $resourceFromClinicA = Resource::factory()->create([
        'clinic_id'        => $clinicA->id,
        'resource_type_id' => $resourceType->id,
    ]);

    $resourceFromClinicB = Resource::factory()->create([
        'clinic_id'        => $clinicB->id,
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
    expect($pp->clinics->pluck('id')->toArray())->toContain($clinicA->id, $clinicB->id)
        ->and($pp->resources->pluck('id')->toArray())->toContain($resourceFromClinicA->id, $resourceFromClinicB->id);
});

test('can create partner product with template product', function () {
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;

    // Create a template product
    $templateProduct = Product::factory()->create([
        'name'             => 'Template Product',
        'description'      => 'Template description',
        'currency'         => 'EUR',
        'price'            => 150.00,
        'resource_type_id' => $resourceTypeId,
    ]);

    $payload = [
        'name'                         => 'Partner Product from Template',
        'currency'                     => 'EUR',
        'sales_price'                  => 199.99,
        'active'                       => 1,
        'description'                  => 'Partner product description',
        'discount_info'                => 'Intro discount 10%',
        'resource_type_id'             => $resourceTypeId,
        'product_id'                   => $templateProduct->id,
        'clinics'                      => [$clinicId],
        'clinic_description'           => 'Omschrijving kliniek',
        'duration'                     => 60,
        'purchase_price_misc'          => 10.50,
        'purchase_price_doctor'        => 25.00,
        'purchase_price_cardiology'    => 15.75,
        'purchase_price_clinic'        => 30.25,
        'purchase_price_royal_doctors' => 12.00,
        'purchase_price_radiology'     => 20.50,
    ];

    $response = $this->postJson(route('admin.settings.partner_products.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('partner_products', [
        'name'       => 'Partner Product from Template',
        'product_id' => $templateProduct->id,
    ]);

    $createdProduct = PartnerProduct::where('name', 'Partner Product from Template')->first();
    expect($createdProduct->product_id)->toBe($templateProduct->id);
});

test('can get template products for selection', function () {
    $product1 = Product::factory()->create(['name' => 'Product 1', 'active' => true, 'product_group_id' => null]);
    $product2 = Product::factory()->create(['name' => 'Product 2', 'active' => true, 'product_group_id' => null]);
    $product3 = Product::factory()->create(['name' => 'Product 3', 'active' => false]); // Inactive

    $response = $this->getJson(route('admin.settings.partner_products.template_products'));
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(2); // Only active products
    expect(collect($data)->pluck('name'))->toContain('Product 1', 'Product 2');
    expect(collect($data)->pluck('name'))->not->toContain('Product 3');

    // Check that name_with_path is present and matches name when no product group
    expect(collect($data)->pluck('name_with_path'))->toContain('Product 1', 'Product 2');
});

test('can get specific template product details', function () {
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $templateProduct = Product::factory()->create([
        'name'             => 'Template Product',
        'description'      => 'Template description',
        'currency'         => 'EUR',
        'price'            => 150.00,
        'costs'            => 100.00,
        'resource_type_id' => $resourceTypeId,
        'product_group_id' => null, // Explicitly set to null
    ]);

    $response = $this->getJson(route('admin.settings.partner_products.template_product', ['id' => $templateProduct->id]));
    $response->assertOk();

    $data = $response->json('data');
    expect($data['id'])->toBe($templateProduct->id);
    expect($data['name'])->toBe('Template Product');
    expect($data['name_with_path'])->toBe('Template Product'); // No product group, so same as name
    expect($data['description'])->toBe('Template description');
    expect($data['currency'])->toBe('EUR');
    expect($data['price'])->toBe('150.00');
    expect($data['costs'])->toBe('100.00');
    expect($data['resource_type_id'])->toBe($resourceTypeId);
});

test('template products show product group path when available', function () {
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;

    // Create a product group hierarchy
    $parentGroup = ProductGroup::factory()->create(['name' => 'Parent Group']);
    $childGroup = ProductGroup::factory()->create([
        'name'      => 'Child Group',
        'parent_id' => $parentGroup->id,
    ]);

    $templateProduct = Product::factory()->create([
        'name'             => 'Template Product',
        'active'           => true,
        'product_group_id' => $childGroup->id,
        'resource_type_id' => $resourceTypeId,
    ]);

    $response = $this->getJson(route('admin.settings.partner_products.template_products'));
    $response->assertOk();

    $data = $response->json('data');
    $product = collect($data)->firstWhere('id', $templateProduct->id);

    expect($product)->not->toBeNull();
    expect($product['name'])->toBe('Template Product');
    expect($product['name_with_path'])->toBe('Parent Group > Child Group > Template Product');
});

test('edit partner product shows linked product with full path', function () {
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;

    // Create a product group hierarchy
    $parentGroup = ProductGroup::factory()->create(['name' => 'Parent Group']);
    $childGroup = ProductGroup::factory()->create([
        'name'      => 'Child Group',
        'parent_id' => $parentGroup->id,
    ]);

    $templateProduct = Product::factory()->create([
        'name'             => 'Template Product',
        'active'           => true,
        'product_group_id' => $childGroup->id,
        'resource_type_id' => $resourceTypeId,
    ]);

    // Create a partner product linked to the template product
    $partnerProduct = PartnerProduct::factory()->create([
        'name'             => 'Partner Product',
        'product_id'       => $templateProduct->id,
        'resource_type_id' => $resourceTypeId,
    ]);

    // Test the edit view
    $response = $this->get(route('admin.settings.partner_products.edit', $partnerProduct->id));
    $response->assertOk();

    // The view should contain the linked product with full path
    $response->assertSee('Parent Group > Child Group > Template Product');
});
