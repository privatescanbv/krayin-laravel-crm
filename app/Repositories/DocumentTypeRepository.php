<?php

namespace App\Repositories;

use App\Models\DocumentType;
use Webkul\Core\Eloquent\Repository;

class DocumentTypeRepository extends Repository
{
    protected $fieldSearchable = ['name'];

    public function model(): string
    {
        return DocumentType::class;
    }
}
