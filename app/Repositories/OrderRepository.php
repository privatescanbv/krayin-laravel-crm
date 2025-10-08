<?php

namespace App\Repositories;

use App\Models\Order;
use Webkul\Core\Eloquent\Repository;

class OrderRepository extends Repository
{
    public function model(): string
    {
        return Order::class;
    }
}

