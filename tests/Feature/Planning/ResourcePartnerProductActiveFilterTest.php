<?php

namespace Tests\Feature\Planning;

use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->actingAs(makeUser(), 'user');
});

/**
 * Create a resource with a shift covering the current week so it passes
 * the getFilteredResources date filter.
 */
function resourceWithShift(): Resource
{
    $resource = Resource::factory()->create();
    Shift::factory()->create([
        'resource_id'  => $resource->id,
        'period_start' => Carbon::now()->startOfWeek(),
        'period_end'   => Carbon::now()->endOfWeek()->addDays(7),
    ]);

    return $resource;
}

function availabilityResponseResources(): array
{
    $response = test()->getJson(route('admin.planning.monitor.availability'));
    $response->assertOk();

    return $response->json('resources') ?? [];
}

function findResourceInResponse(array $resources, int $resourceId): ?array
{
    foreach ($resources as $r) {
        if ($r['id'] === $resourceId) {
            return $r;
        }
    }

    return null;
}

test('resource with active partner product includes product_id in active_product_ids', function () {
    $product = Product::factory()->create();
    $resource = resourceWithShift();

    $partnerProduct = PartnerProduct::factory()->create([
        'product_id' => $product->id,
        'active'     => true,
    ]);

    DB::table('partner_product_resource')->insert([
        'partner_product_id' => $partnerProduct->id,
        'resource_id'        => $resource->id,
    ]);

    $resources = availabilityResponseResources();
    $found = findResourceInResponse($resources, $resource->id);

    expect($found)->not->toBeNull()
        ->and($found['active_product_ids'])->toContain($product->id)
        ->and($found['restricted_product_ids'])->toContain($product->id);
});

test('resource with inactive partner product excludes product_id from active_product_ids', function () {
    $product = Product::factory()->create();
    $resource = resourceWithShift();

    $partnerProduct = PartnerProduct::factory()->create([
        'product_id' => $product->id,
        'active'     => false,
    ]);

    DB::table('partner_product_resource')->insert([
        'partner_product_id' => $partnerProduct->id,
        'resource_id'        => $resource->id,
    ]);

    $resources = availabilityResponseResources();
    $found = findResourceInResponse($resources, $resource->id);

    expect($found)->not->toBeNull()
        ->and($found['active_product_ids'])->not->toContain($product->id)
        ->and($found['restricted_product_ids'])->toContain($product->id);
});

test('resource with no partner products has empty active and restricted product ids', function () {
    $resource = resourceWithShift();

    $resources = availabilityResponseResources();
    $found = findResourceInResponse($resources, $resource->id);

    expect($found)->not->toBeNull()
        ->and($found['active_product_ids'])->toBeEmpty()
        ->and($found['restricted_product_ids'])->toBeEmpty();
});

test('resource with no partner products for a product has empty active_product_ids so it is excluded from booking options', function () {
    $productWithoutPartner = Product::factory()->create();
    $resource = resourceWithShift();
    // resource has no partner product link to this product at all

    $resources = availabilityResponseResources();
    $found = findResourceInResponse($resources, $resource->id);

    expect($found)->not->toBeNull()
        ->and($found['active_product_ids'])->not->toContain($productWithoutPartner->id)
        // restricted_product_ids is also empty — the frontend filter active.includes(productId)
        // will return false for this resource, so no resources are shown
        ->and($found['restricted_product_ids'])->not->toContain($productWithoutPartner->id);
});

test('resource with mixed active and inactive partner products only includes active product ids', function () {
    $activeProduct = Product::factory()->create();
    $inactiveProduct = Product::factory()->create();
    $resource = resourceWithShift();

    $activePP = PartnerProduct::factory()->create([
        'product_id' => $activeProduct->id,
        'active'     => true,
    ]);
    $inactivePP = PartnerProduct::factory()->create([
        'product_id' => $inactiveProduct->id,
        'active'     => false,
    ]);

    DB::table('partner_product_resource')->insert([
        ['partner_product_id' => $activePP->id, 'resource_id' => $resource->id],
        ['partner_product_id' => $inactivePP->id, 'resource_id' => $resource->id],
    ]);

    $resources = availabilityResponseResources();
    $found = findResourceInResponse($resources, $resource->id);

    expect($found)->not->toBeNull()
        ->and($found['active_product_ids'])->toContain($activeProduct->id)
        ->and($found['active_product_ids'])->not->toContain($inactiveProduct->id)
        ->and($found['restricted_product_ids'])->toContain($activeProduct->id)
        ->and($found['restricted_product_ids'])->toContain($inactiveProduct->id);
});
