<?php

namespace App\Http\Resources;

class PatientDocumentsCollection extends PatientPaginatedCollection
{
    public $collects = PatientDocumentResource::class;
}
