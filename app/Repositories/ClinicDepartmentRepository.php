<?php

namespace App\Repositories;

use App\Models\ClinicDepartment;
use Webkul\Core\Eloquent\Repository;

class ClinicDepartmentRepository extends Repository
{
    protected $fieldSearchable = ['name', 'email'];

    public function model(): string
    {
        return ClinicDepartment::class;
    }
}
