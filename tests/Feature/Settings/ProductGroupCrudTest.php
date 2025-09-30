<?php

namespace Tests\Feature\Settings;

use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\ProductGroup;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('product groups index returns datagrid json', function () {
    $g1 = ProductGroup::factory()->create();
    $g2 = ProductGroup::factory()->create();

    $response = $this->getJson(route('admin.productgroups.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($g1->id, $g2->id);
});

test('can create product group', function () {
    $payload = [
        'name'        => 'Main Product Group',
        'description' => 'Test group',
    ];

    $response = $this->post(route('admin.productgroups.store'), $payload);
    $response->assertRedirect(route('admin.productgroups.index'));

    $this->assertDatabaseHas('product_groups', [
        'name' => 'Main Product Group',
    ]);
});

test('can create product group with parent', function () {
    $parent = ProductGroup::factory()->create(['name' => 'Parent Group']);

    $payload = [
        'name'        => 'Child Group',
        'description' => 'Test child',
        'parent_id'   => $parent->id,
    ];

    $response = $this->post(route('admin.productgroups.store'), $payload);
    $response->assertRedirect(route('admin.productgroups.index'));

    $this->assertDatabaseHas('product_groups', [
        'name'      => 'Child Group',
        'parent_id' => $parent->id,
    ]);
});

test('cannot create product group with invalid parent', function () {
    $payload = [
        'name'      => 'Invalid Child',
        'parent_id' => 99999,
    ];

    $response = $this->post(route('admin.productgroups.store'), $payload);
    $response->assertSessionHasErrors('parent_id');
});

test('can update product group', function () {
    $group = ProductGroup::factory()->create();

    $payload = [
        'name'        => 'Updated Group',
        'description' => 'Updated',
    ];

    $response = $this->put(route('admin.productgroups.update', ['id' => $group->id]), $payload);
    $response->assertRedirect(route('admin.productgroups.index'));

    $this->assertDatabaseHas('product_groups', [
        'id'   => $group->id,
        'name' => 'Updated Group',
    ]);
});

test('can delete product group', function () {
    $group = ProductGroup::factory()->create();

    $response = $this->deleteJson(route('admin.productgroups.destroy', ['id' => $group->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('product_groups', [
        'id' => $group->id,
    ]);
});

test('deleting parent sets children parent_id to null', function () {
    $parent = ProductGroup::factory()->create();
    $child = ProductGroup::factory()->withParent($parent)->create();

    $this->deleteJson(route('admin.productgroups.destroy', ['id' => $parent->id]));

    $child->refresh();
    expect($child->parent_id)->toBeNull();
});