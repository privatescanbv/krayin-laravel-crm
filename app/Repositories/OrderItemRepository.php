<?php

namespace App\Repositories;

use App\Models\OrderItem;
use Webkul\Core\Eloquent\Repository;

class OrderItemRepository extends Repository
{
    public function model(): string
    {
        return OrderItem::class;
    }
}
