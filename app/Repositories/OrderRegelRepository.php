<?php

namespace App\Repositories;

use App\Models\OrderRegel;
use Webkul\Core\Eloquent\Repository;

class OrderRegelRepository extends Repository
{
    public function model(): string
    {
        return OrderRegel::class;
    }
}

