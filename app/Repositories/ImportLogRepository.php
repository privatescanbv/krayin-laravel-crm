<?php

namespace App\Repositories;

use App\Models\ImportLog;
use Webkul\Core\Eloquent\Repository;

class ImportLogRepository extends Repository
{
    public function model()
    {
        return ImportLog::class;
    }
}