<?php

namespace App\Repositories;

use App\Models\ImportRun;
use Webkul\Core\Eloquent\Repository;

class ImportRunRepository extends Repository
{
    public function model()
    {
        return ImportRun::class;
    }
}