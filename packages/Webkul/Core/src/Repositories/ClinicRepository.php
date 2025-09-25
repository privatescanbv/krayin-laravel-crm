<?php

namespace Webkul\Core\Repositories;

use Webkul\Core\Eloquent\Repository;
use App\Models\Clinic;

class ClinicRepository extends Repository
{
    public function model(): string
    {
        return Clinic::class;
    }
}

