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

test('soft deleted partner products are not returned in normal queries', function () {
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;
    
    // Create a partner product
    $partnerProduct = PartnerProduct::factory()->create([
        'name' => 'Test Product',
        'resource_type_id' => $resourceTypeId,
    ]);
    $partnerProduct->clinics()->sync([$clinicId]);
    
    // Verify it exists
    $this->assertDatabaseHas('partner_products', [
        'id' => $partnerProduct->id,
        'deleted_at' => null,
    ]);
    
    // Soft delete it
    $partnerProduct->delete();
    
    // Verify it's soft deleted
    $this->assertDatabaseHas('partner_products', [
        'id' => $partnerProduct->id,
        'deleted_at' => now(),
    ]);
    
    // Verify it's not returned in normal queries
    $this->assertDatabaseMissing('partner_products', [
        'id' => $partnerProduct->id,
        'deleted_at' => null,
    ]);
    
    // Verify it's not returned by the repository
    $repository = app(\App\Repositories\PartnerProductRepository::class);
    $products = $repository->searchFormatted('Test Product');
    expect($products)->toHaveCount(0);
});

test('soft deleted partner products are not returned in datagrid', function () {
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;
    
    // Create a partner product
    $partnerProduct = PartnerProduct::factory()->create([
        'name' => 'Test Product for Datagrid',
        'resource_type_id' => $resourceTypeId,
    ]);
    $partnerProduct->clinics()->sync([$clinicId]);
    
    // Verify it appears in the datagrid
    $response = $this->getJson(route('admin.settings.partner_products.index'));
    $response->assertOk();
    
    $ids = getDatagridIds($response);
    expect($ids)->toContain($partnerProduct->id);
    
    // Soft delete it
    $partnerProduct->delete();
    
    // Verify it doesn't appear in the datagrid anymore
    $response = $this->getJson(route('admin.settings.partner_products.index'));
    $response->assertOk();
    
    $ids = getDatagridIds($response);
    expect($ids)->not->toContain($partnerProduct->id);
});

test('soft deleted partner products are not returned in search', function () {
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;
    
    // Create a partner product
    $partnerProduct = PartnerProduct::factory()->create([
        'name' => 'Searchable Product',
        'resource_type_id' => $resourceTypeId,
    ]);
    $partnerProduct->clinics()->sync([$clinicId]);
    
    // Verify it appears in search
    $response = $this->getJson(route('admin.settings.partner_products.search', ['query' => 'Searchable']));
    $response->assertOk();
    
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['id'])->toBe($partnerProduct->id);
    
    // Soft delete it
    $partnerProduct->delete();
    
    // Verify it doesn't appear in search anymore
    $response = $this->getJson(route('admin.settings.partner_products.search', ['query' => 'Searchable']));
    $response->assertOk();
    
    $data = $response->json('data');
    expect($data)->toHaveCount(0);
});

test('soft deleted partner products are not returned in relationships', function () {
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinic = Clinic::factory()->create();
    
    // Create a partner product
    $partnerProduct = PartnerProduct::factory()->create([
        'name' => 'Relationship Test Product',
        'resource_type_id' => $resourceTypeId,
    ]);
    $partnerProduct->clinics()->sync([$clinic->id]);
    
    // Verify it appears in clinic's partner products
    $clinic->refresh();
    expect($clinic->partnerProducts)->toHaveCount(1);
    expect($clinic->partnerProducts->first()->id)->toBe($partnerProduct->id);
    
    // Soft delete it
    $partnerProduct->delete();
    
    // Verify it doesn't appear in clinic's partner products anymore
    $clinic->refresh();
    expect($clinic->partnerProducts)->toHaveCount(0);
});

test('soft deleted partner products are not returned in related products', function () {
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;
    
    // Create two partner products
    $product1 = PartnerProduct::factory()->create([
        'name' => 'Product 1',
        'resource_type_id' => $resourceTypeId,
    ]);
    $product1->clinics()->sync([$clinicId]);
    
    $product2 = PartnerProduct::factory()->create([
        'name' => 'Product 2',
        'resource_type_id' => $resourceTypeId,
    ]);
    $product2->clinics()->sync([$clinicId]);
    
    // Link them as related products
    $product1->relatedProducts()->sync([$product2->id]);
    
    // Verify the relationship works
    $product1->refresh();
    expect($product1->relatedProducts)->toHaveCount(1);
    expect($product1->relatedProducts->first()->id)->toBe($product2->id);
    
    // Soft delete product2
    $product2->delete();
    
    // Verify product2 doesn't appear in related products anymore
    $product1->refresh();
    expect($product1->relatedProducts)->toHaveCount(0);
});