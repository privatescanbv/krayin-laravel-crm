<?php

use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Product\Models\Product;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Create and authenticate a back-office user
    $this->user = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Tester']);
    $this->actingAs($this->user, 'user');
    $this->withoutMiddleware(Authenticate::class);
});

test('product search with query parameter filters by name', function () {
    // Create products that should be found
    $productMatch = Product::factory()->create(['name' => 'MRI Scan']);
    $productPartial = Product::factory()->create(['name' => 'MRI Scan with Contrast']);

    // Create product that should NOT be found
    $productNoMatch = Product::factory()->create(['name' => 'CT Scan']);

    // Search with query parameter
    $response = $this->getJson(route('admin.products.search', [
        'query' => 'MRI',
    ]));

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeArray();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($productMatch->id)
        ->and($ids)->toContain($productPartial->id)
        ->and($ids)->not->toContain($productNoMatch->id);
});

test('product search returns empty array when no matches', function () {
    Product::factory()->create(['name' => 'MRI Scan']);
    Product::factory()->create(['name' => 'CT Scan']);

    $response = $this->getJson(route('admin.products.search', [
        'query' => 'NonExistent',
    ]));

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeArray()
        ->and($data)->toBeEmpty();
});

test('product search returns all when query is empty', function () {
    $product1 = Product::factory()->create(['name' => 'MRI Scan']);
    $product2 = Product::factory()->create(['name' => 'CT Scan']);

    $response = $this->getJson(route('admin.products.search'));

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeArray();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($product1->id)
        ->and($ids)->toContain($product2->id);
});

test('product search response has correct structure', function () {
    $product = Product::factory()->create(['name' => 'MRI Scan']);

    $response = $this->getJson(route('admin.products.search', [
        'query' => 'MRI',
    ]));

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeArray()
        ->and($data)->not->toBeEmpty();

    $firstItem = $data[0];
    expect($firstItem)->toHaveKey('id')
        ->and($firstItem)->toHaveKey('name')
        ->and($firstItem['name'])->toBe($product->name);
});

test('product search is case insensitive', function () {
    $product1 = Product::factory()->create(['name' => 'MRI Scan']);
    $product2 = Product::factory()->create(['name' => 'mri scan with contrast']);

    // Search with lowercase
    $response = $this->getJson(route('admin.products.search', [
        'query' => 'mri',
    ]));

    $response->assertOk();
    $data = $response->json('data');
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($product1->id)
        ->and($ids)->toContain($product2->id);

    // Search with uppercase
    $response = $this->getJson(route('admin.products.search', [
        'query' => 'MRI',
    ]));

    $response->assertOk();
    $data = $response->json('data');
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($product1->id)
        ->and($ids)->toContain($product2->id);
});
