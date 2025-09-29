<?php

namespace App\Repositories;

use App\Models\Clinic;
use App\Models\Resource;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Eloquent\Repository;

class ClinicRepository extends Repository
{
    public function model(): string
    {
        return Clinic::class;
    }

    public function deleteWithResourceDetach(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $clinic = $this->findOrFail($id);

            // Detach resources by nullifying clinic_id
            Resource::where('clinic_id', $clinic->id)->update(['clinic_id' => null]);

            return (bool) parent::delete($id);
        });
    }
}
