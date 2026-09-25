<?php

use App\Models\OrderItem;
use Database\Seeders\TestSeeder;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);
});

test('order line permissions sit under orders in the role editor', function () {
    $orders = acl()->getItems()->firstWhere('key', 'orders');

    expect($orders->children->pluck('key'))->toContain('orders.order_items');
});

test('order line edit follows the orders.order_items.edit permission', function (array $permissions, int $status) {
    $role = Role::factory()->create(['permission_type' => 'custom', 'permissions' => $permissions]);
    $user = User::factory()->create(['role_id' => $role->id, 'view_permission' => 'global', 'status' => 1]);

    $this->actingAs($user, 'user')
        ->get(route('admin.order_items.edit', OrderItem::factory()->create()->id))
        ->assertStatus($status);
})->with([
    'with permission'    => [['orders', 'orders.order_items', 'orders.order_items.edit'], 200],
    'without permission' => [['orders', 'orders.edit'], 401],
]);

test('migration renames granted settings.order_items permissions', function () {
    $role = Role::factory()->create([
        'permission_type' => 'custom',
        'permissions'     => ['settings', 'settings.order_items', 'settings.order_items.edit', 'settings.folders'],
    ]);

    (require database_path('migrations/2026_09_25_100000_move_order_items_permissions_under_orders.php'))->up();

    expect($role->fresh()->permissions)
        ->toBe(['settings', 'orders.order_items', 'orders.order_items.edit', 'settings.folders']);
});

test('order page only offers the order line edit link with the permission', function (array $permissions, bool $visible) {
    $orderItem = OrderItem::factory()->create();
    $role = Role::factory()->create(['permission_type' => 'custom', 'permissions' => $permissions]);
    $user = User::factory()->create(['role_id' => $role->id, 'view_permission' => 'global', 'status' => 1]);

    $html = $this->actingAs($user, 'user')
        ->get(route('admin.orders.edit', $orderItem->order_id))
        ->assertOk()
        ->getContent();

    $editBaseUrl = rtrim(route('admin.order_items.edit', ['id' => 0]), '0');

    expect(str_contains($html, 'edit-base-url="'.$editBaseUrl.'"'))->toBe($visible);
})->with([
    'with permission'    => [['orders', 'orders.edit', 'orders.order_items', 'orders.order_items.edit'], true],
    'without permission' => [['orders', 'orders.edit'], false],
]);
