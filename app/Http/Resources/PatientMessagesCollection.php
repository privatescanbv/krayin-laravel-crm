<?php

namespace App\Http\Resources;

class PatientMessagesCollection extends PatientPaginatedCollection
{
    public $collects = PatientMessageResource::class;
}
