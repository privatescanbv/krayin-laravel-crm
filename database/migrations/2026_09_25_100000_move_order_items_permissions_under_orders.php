<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The order-line permissions moved from settings.order_items.* to orders.order_items.* so the role
     * editor shows them under Orders. Rename them in existing roles, otherwise the grant is lost.
     */
    public function up(): void
    {
        $this->rename('settings.order_items', 'orders.order_items');
    }

    public function down(): void
    {
        $this->rename('orders.order_items', 'settings.order_items');
    }

    private function rename(string $from, string $to): void
    {
        foreach (DB::table('roles')->where('permission_type', 'custom')->get() as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true) ?: [];

            $renamed = array_map(
                fn (string $key) => $key === $from || str_starts_with($key, "$from.") ? $to.substr($key, strlen($from)) : $key,
                $permissions
            );

            if ($renamed !== $permissions) {
                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['permissions' => json_encode(array_values(array_unique($renamed)))]);
            }
        }
    }
};
