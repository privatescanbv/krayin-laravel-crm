<?php

namespace App\Repositories;

use App\Models\PartnerProduct;
use Webkul\Core\Eloquent\Repository;

class PartnerProductRepository extends Repository
{
    public function model(): string
    {
        return PartnerProduct::class;
    }
}

