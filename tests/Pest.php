<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
 */

use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Webkul\User\Models\User;

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
 */

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
 */

/**
 * Get default admin which is created on fresh instance.
 *
 * @return User
 */
function getDefaultAdmin()
{
    $admin = User::orderBy('id', 'asc')->first();

    return $admin;
}

/**
 * Sanctum authenticated admin.
 *
 * @return Authenticatable|HasApiTokens
 */
function actingAsSanctumAuthenticatedAdmin(): HasApiTokens|Authenticatable
{
    return Sanctum::actingAs(
        getDefaultAdmin(),
        ['*']
    );
}

/**
 * Create a test user with default attributes.
 *
 * @param array $attrs
 * @return User
 */
function makeUser(array $attrs = []): User
{
    return User::factory()->create(array_merge(['status' => 1], $attrs));
}

/**
 * Extract IDs from datagrid response.
 *
 * @param mixed $response
 * @return array
 */
function getDatagridIds($response): array
{
    $payload = $response->json();
    $records = $payload['records'] ?? [];

    return collect($records)->pluck('id')->all();
}

/**
 * Link an active partner product for the order item's product to the resource's clinic.
 */
function attachPartnerProductForOrderItemAndResource(OrderItem $orderItem, Resource $resource): PartnerProduct
{
    $resource->loadMissing('clinicDepartment');
    $clinicId = $resource->clinicDepartment->clinic_id;

    $partnerProduct = PartnerProduct::factory()->create([
        'product_id' => $orderItem->product_id,
        'active'     => true,
    ]);
    $partnerProduct->clinics()->sync([$clinicId]);

    return $partnerProduct;
}

/**
 * Create a resource with a shift covering the given date (weekdays 09:00–17:00).
 */
function resourceWithShiftCovering(Carbon $date, bool $allowOutside = false): Resource
{
    $resource = Resource::factory()->create([
        'allow_outside_availability' => $allowOutside,
    ]);

    Shift::factory()->create([
        'resource_id'         => $resource->id,
        'period_start'        => $date->copy()->subDay()->toDateString(),
        'period_end'          => null,
        'available'           => true,
        'weekday_time_blocks' => [
            1 => [['from' => '09:00', 'to' => '17:00']],
            2 => [['from' => '09:00', 'to' => '17:00']],
            3 => [['from' => '09:00', 'to' => '17:00']],
            4 => [['from' => '09:00', 'to' => '17:00']],
            5 => [['from' => '09:00', 'to' => '17:00']],
            6 => [['from' => '09:00', 'to' => '17:00']],
            7 => [['from' => '09:00', 'to' => '17:00']],
        ],
    ]);

    return $resource->fresh('shifts');
}
