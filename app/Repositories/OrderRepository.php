<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Webkul\Core\Eloquent\Repository;

class OrderRepository extends Repository
{
    public function model(): string
    {
        return Order::class;
    }

    public function createFromSalesLead(int $salesLeadId, string $salesLeadName): Order
    {
        return $this->create(
            ['title'            => 'Order voor '.$salesLeadName,
                'total_price'   => 0.00,
                'status'        => OrderStatus::NEW,
                'sales_lead_id' => $salesLeadId]
        );
    }
}
