<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ProductHelper;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductGroup;

test('formatNameWithPath returns just name when no product group', function () {
    $product = Product::factory()->create([
        'name'             => 'Test Product',
        'product_group_id' => null,
    ]);

    $result = ProductHelper::formatNameWithPath($product);

    expect($result)->toBe('Test Product');
});

test('formatNameWithPath returns name with path when product group exists', function () {
    $parentGroup = ProductGroup::factory()->create(['name' => 'Parent Group']);
    $childGroup = ProductGroup::factory()->create([
        'name'      => 'Child Group',
        'parent_id' => $parentGroup->id,
    ]);

    $product = Product::factory()->create([
        'name'             => 'Test Product',
        'product_group_id' => $childGroup->id,
    ]);

    // Load the relationship
    $product->load('productGroup');

    $result = ProductHelper::formatNameWithPath($product);

    expect($result)->toBe('Test Product (Parent Group > Child Group)');
});

test('formatNameWithPathLazy loads product group relationship', function () {
    $parentGroup = ProductGroup::factory()->create(['name' => 'Parent Group']);
    $childGroup = ProductGroup::factory()->create([
        'name'      => 'Child Group',
        'parent_id' => $parentGroup->id,
    ]);

    $product = Product::factory()->create([
        'name'             => 'Test Product',
        'product_group_id' => $childGroup->id,
    ]);

    // Don't load the relationship beforehand
    $result = ProductHelper::formatNameWithPathLazy($product);

    expect($result)->toBe('Test Product (Parent Group > Child Group)');
});

test('formatCollectionWithPaths formats multiple products', function () {
    $parentGroup = ProductGroup::factory()->create(['name' => 'Parent Group']);
    $childGroup = ProductGroup::factory()->create([
        'name'      => 'Child Group',
        'parent_id' => $parentGroup->id,
    ]);

    $product1 = Product::factory()->create([
        'name'             => 'Product 1',
        'product_group_id' => $childGroup->id,
    ]);

    $product2 = Product::factory()->create([
        'name'             => 'Product 2',
        'product_group_id' => null,
    ]);

    $products = collect([$product1, $product2]);

    $result = ProductHelper::formatCollectionWithPaths($products);

    expect($result)->toHaveCount(2);
    expect($result[0]['name'])->toBe('Product 1');
    expect($result[0]['name_with_path'])->toBe('Product 1 (Parent Group > Child Group)');
    expect($result[1]['name'])->toBe('Product 2');
    expect($result[1]['name_with_path'])->toBe('Product 2');
});
