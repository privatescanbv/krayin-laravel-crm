<?php

namespace App\Repositories;

use App\Models\Clinic;
use App\Models\Resource;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Eloquent\Repository;

class ClinicRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'name',
    ];

    public function model(): string
    {
        return Clinic::class;
    }

    /**
     * Return all active clinics.
     */
    public function allActive(array $columns = ['*'])
    {
        return Clinic::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get($columns);
    }

    public function deleteWithResourceDetach(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $clinic = $this->findOrFail($id);

            // Detach resources by nullifying clinic_id and clinic_department_id
            Resource::where('clinic_id', $clinic->id)->update(['clinic_id' => null, 'clinic_department_id' => null]);

            return (bool) parent::delete($id);
        });
    }
}
