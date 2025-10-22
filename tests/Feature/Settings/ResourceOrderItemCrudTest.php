<?php

namespace Tests\Feature\Settings;

use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use Tests\TestCase;

class ResourceOrderItemCrudTest extends TestCase
{
    public function test_can_create_resource_order_item(): void
    {
        $resource = Resource::factory()->create();
        $orderItem = OrderItem::factory()->create();

        $roi = ResourceOrderItem::create([
            'resource_id'  => $resource->id,
            'orderitem_id' => $orderItem->id,
            'from'         => now()->addDay(),
            'to'           => now()->addDay()->addHour(),
        ]);

        $this->assertDatabaseHas('resource_orderitem', [
            'id'           => $roi->id,
            'resource_id'  => $resource->id,
            'orderitem_id' => $orderItem->id,
        ]);
    }
}
