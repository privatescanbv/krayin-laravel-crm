<?php

namespace Tests\Feature;

use App\Models\PartnerProduct;
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

test('products index page loads with ajax returns json', function () {
    $p1 = Product::factory()->create();
    $p2 = Product::factory()->create();

    $response = $this->get(route('admin.products.index'), ['HTTP_X-Requested-With' => 'XMLHttpRequest']);
    $response->assertOk();
});

test('can create product', function () {
    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;

    $payload = [
        'name'              => 'MRI Scan Basic',
        'currency'          => 'EUR',
        'description'       => 'Basic MRI scan product',
        'price'             => 299.99,
        'costs'             => 150.50,
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => $resourceTypeId,
    ];

    $response = $this->post(route('admin.products.store'), $payload);
    $response->assertRedirect(route('admin.products.index'));

    $this->assertDatabaseHas('products', [
        'name' => 'MRI Scan Basic',
    ]);

    $createdProduct = Product::where('name', 'MRI Scan Basic')->first();
    expect($createdProduct->price)->toBe('299.99')
        ->and($createdProduct->costs)->toBe('150.50')
        ->and($createdProduct->currency)->toBe('EUR');
});

test('can update product', function () {
    $product = Product::factory()->create();

    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;

    $payload = [
        'name'              => 'Updated Product Name',
        'currency'          => 'EUR',
        'description'       => 'Updated description',
        'price'             => 499.95,
        'costs'             => 250.00,
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => $resourceTypeId,
    ];

    $response = $this->put(route('admin.products.update', ['id' => $product->id]), $payload);
    $response->assertRedirect(route('admin.products.index'));

    $this->assertDatabaseHas('products', [
        'id'   => $product->id,
        'name' => 'Updated Product Name',
    ]);

    $product->refresh();
    expect($product->price)->toBe('499.95')
        ->and($product->costs)->toBe('250.00')
        ->and($product->description)->toBe('Updated description');
});

test('can delete product', function () {
    $product = Product::factory()->create();

    $response = $this->deleteJson(route('admin.products.delete', ['id' => $product->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('products', [
        'id' => $product->id,
    ]);
});

test('costs field is optional when creating product', function () {
    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;

    $payload = [
        'name'              => 'Product Without Costs',
        'currency'          => 'EUR',
        'price'             => 199.99,
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => $resourceTypeId,
    ];

    $response = $this->post(route('admin.products.store'), $payload);
    $response->assertRedirect(route('admin.products.index'));

    $this->assertDatabaseHas('products', [
        'name' => 'Product Without Costs',
    ]);

    $createdProduct = Product::where('name', 'Product Without Costs')->first();
    expect($createdProduct->costs)->toBeNull();
});

test('can update product costs from null to value', function () {
    $product = Product::factory()->create(['costs' => null]);

    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;

    $payload = [
        'name'              => $product->name,
        'currency'          => 'EUR',
        'price'             => 299.99,
        'costs'             => 125.75,
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => $resourceTypeId,
    ];

    $response = $this->put(route('admin.products.update', ['id' => $product->id]), $payload);
    $response->assertRedirect(route('admin.products.index'));

    $product->refresh();
    expect($product->costs)->toBe('125.75');
});

test('can update product costs from value to different value', function () {
    $product = Product::factory()->create(['costs' => 100.00]);

    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;

    $payload = [
        'name'              => $product->name,
        'currency'          => 'EUR',
        'price'             => 299.99,
        'costs'             => 175.50,
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => $resourceTypeId,
    ];

    $response = $this->put(route('admin.products.update', ['id' => $product->id]), $payload);
    $response->assertRedirect(route('admin.products.index'));

    $product->refresh();
    expect($product->costs)->toBe('175.50');
});

test('price normalization works with comma separator', function () {
    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;

    $payload = [
        'name'              => 'Product With Comma Price',
        'currency'          => 'EUR',
        'price'             => '1.234,56',  // European format
        'costs'             => '567,89',     // European format
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => $resourceTypeId,
    ];

    $response = $this->post(route('admin.products.store'), $payload);
    $response->assertRedirect(route('admin.products.index'));

    $createdProduct = Product::where('name', 'Product With Comma Price')->first();
    expect($createdProduct->price)->toBe('1234.56')
        ->and($createdProduct->costs)->toBe('567.89');
});

test('can link partner products to product', function () {
    $product = Product::factory()->create();
    $partnerProduct1 = PartnerProduct::factory()->create(['product_id' => null]);
    $partnerProduct2 = PartnerProduct::factory()->create(['product_id' => null]);

    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;

    $payload = [
        'name'              => $product->name,
        'currency'          => 'EUR',
        'price'             => 299.99,
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => $resourceTypeId,
        'partner_products'  => [$partnerProduct1->id, $partnerProduct2->id],
    ];

    $response = $this->put(route('admin.products.update', ['id' => $product->id]), $payload);
    $response->assertRedirect(route('admin.products.index'));

    $partnerProduct1->refresh();
    $partnerProduct2->refresh();

    expect($partnerProduct1->product_id)->toBe($product->id)
        ->and($partnerProduct2->product_id)->toBe($product->id);
});

test('can unlink partner products from product', function () {
    $product = Product::factory()->create();
    $partnerProduct1 = PartnerProduct::factory()->create(['product_id' => $product->id]);
    $partnerProduct2 = PartnerProduct::factory()->create(['product_id' => $product->id]);

    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;

    // Update product without partner_products (should unlink all)
    $payload = [
        'name'              => $product->name,
        'currency'          => 'EUR',
        'price'             => 299.99,
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => $resourceTypeId,
        'partner_products'  => [],
    ];

    $response = $this->put(route('admin.products.update', ['id' => $product->id]), $payload);
    $response->assertRedirect(route('admin.products.index'));

    $partnerProduct1->refresh();
    $partnerProduct2->refresh();

    expect($partnerProduct1->product_id)->toBeNull()
        ->and($partnerProduct2->product_id)->toBeNull();
});

test('can change partner product selection', function () {
    $product = Product::factory()->create();
    $partnerProduct1 = PartnerProduct::factory()->create(['product_id' => $product->id]);
    $partnerProduct2 = PartnerProduct::factory()->create(['product_id' => null]);

    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;
    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;

    // Change from partnerProduct1 to partnerProduct2
    $payload = [
        'name'              => $product->name,
        'currency'          => 'EUR',
        'price'             => 299.99,
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => $resourceTypeId,
        'partner_products'  => [$partnerProduct2->id],
    ];

    $response = $this->put(route('admin.products.update', ['id' => $product->id]), $payload);
    $response->assertRedirect(route('admin.products.index'));

    $partnerProduct1->refresh();
    $partnerProduct2->refresh();

    expect($partnerProduct1->product_id)->toBeNull()
        ->and($partnerProduct2->product_id)->toBe($product->id);
});

test('validation fails when partner product has different resource type', function () {
    $resourceType1 = ResourceType::factory()->create();
    $resourceType2 = ResourceType::factory()->create();

    $product = Product::factory()->create(['resource_type_id' => $resourceType1->id]);
    $partnerProduct = PartnerProduct::factory()->create([
        'resource_type_id' => $resourceType2->id,
        'product_id'       => null,
    ]);

    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;

    $payload = [
        'name'              => $product->name,
        'currency'          => 'EUR',
        'price'             => 299.99,
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => $resourceType1->id,
        'partner_products'  => [$partnerProduct->id],
    ];

    $response = $this->put(route('admin.products.update', ['id' => $product->id]), $payload);
    $response->assertSessionHasErrors('partner_products');
});

test('validation passes when partner product has same resource type', function () {
    $resourceType = ResourceType::factory()->create();

    $product = Product::factory()->create(['resource_type_id' => $resourceType->id]);
    $partnerProduct = PartnerProduct::factory()->create([
        'resource_type_id' => $resourceType->id,
        'product_id'       => null,
    ]);

    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;

    $payload = [
        'name'              => $product->name,
        'currency'          => 'EUR',
        'price'             => 299.99,
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => $resourceType->id,
        'partner_products'  => [$partnerProduct->id],
    ];

    $response = $this->put(route('admin.products.update', ['id' => $product->id]), $payload);
    $response->assertRedirect(route('admin.products.index'));

    $partnerProduct->refresh();
    expect($partnerProduct->product_id)->toBe($product->id);
});

test('validation fails when resource type is not set but partner products are selected', function () {
    $partnerProduct = PartnerProduct::factory()->create();
    $productGroupId = ProductGroup::query()->value('id') ?? ProductGroup::factory()->create()->id;

    $payload = [
        'name'              => 'Test Product',
        'currency'          => 'EUR',
        'price'             => 299.99,
        'product_group_id'  => $productGroupId,
        'resource_type_id'  => null,
        'partner_products'  => [$partnerProduct->id],
    ];

    $response = $this->post(route('admin.products.store'), $payload);
    $response->assertSessionHasErrors('partner_products');
});
