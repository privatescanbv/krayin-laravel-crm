<?php

use Tests\Feature\Concerns\ControllerSearchTestHelpers;
use Webkul\Product\Models\Product;

uses(ControllerSearchTestHelpers::class);

beforeEach(function () {
    $this->setUpSearchTest();
});

test('product search with query parameter filters by name', function () {
    $productMatch = Product::factory()->create(['name' => 'MRI Scan']);
    $productPartial = Product::factory()->create(['name' => 'MRI Scan with Contrast']);
    $productNoMatch = Product::factory()->create(['name' => 'CT Scan']);

    $response = $this->performSearch('admin.products.search', ['query' => 'MRI']);

    $this->assertEntityFound($response, $productMatch->id);
    $this->assertEntityFound($response, $productPartial->id);
    $this->assertEntityNotFound($response, $productNoMatch->id);
});

test('product search returns empty array when no matches', function () {
    Product::factory()->create(['name' => 'MRI Scan']);
    Product::factory()->create(['name' => 'CT Scan']);

    $response = $this->performSearch('admin.products.search', ['query' => 'NonExistent']);

    $this->assertSearchEmpty($response);
});

test('product search returns all when query is empty', function () {
    $product1 = Product::factory()->create(['name' => 'MRI Scan']);
    $product2 = Product::factory()->create(['name' => 'CT Scan']);

    $response = $this->performSearch('admin.products.search');

    $this->assertSearchReturnsAll($response, [$product1->id, $product2->id]);
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
    $response = $this->performSearch('admin.products.search', ['query' => 'mri']);
    $this->assertEntityFound($response, $product1->id);
    $this->assertEntityFound($response, $product2->id);

    // Search with uppercase
    $response = $this->performSearch('admin.products.search', ['query' => 'MRI']);
    $this->assertEntityFound($response, $product1->id);
    $this->assertEntityFound($response, $product2->id);
});
