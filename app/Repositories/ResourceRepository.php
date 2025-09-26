<?php

namespace App\Repositories;

use App\Models\Resource;
use Webkul\Core\Eloquent\Repository;

class ResourceRepository extends Repository
{
    public function model(): string
    {
        return Resource::class;
    }
}
