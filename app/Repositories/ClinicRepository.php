<?php

namespace App\Repositories;

use App\Models\Clinic;
use Webkul\Core\Eloquent\Repository;

class ClinicRepository extends Repository
{
    public function model(): string
    {
        return Clinic::class;
    }
}
