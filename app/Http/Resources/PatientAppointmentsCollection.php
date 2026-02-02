<?php

namespace App\Http\Resources;

class PatientAppointmentsCollection extends PatientPaginatedCollection
{
    public $collects = AppointmentResource::class;
}
