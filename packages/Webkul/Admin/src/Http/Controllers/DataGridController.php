<?php

namespace Webkul\Admin\Http\Controllers;

use Illuminate\Support\Facades\Crypt;
use Webkul\User\Repositories\UserRepository;

class DataGridController extends Controller
{
    /**
     * Look up.
     */
    public function lookUp()
    {
        /**
         * Validation for parameters.
         */
        $params = $this->validate(request(), [
            'datagrid_id' => ['required'],
            'column'      => ['required'],
            'search'      => ['required', 'min:2'],
        ]);

        /**
         * Preparing the datagrid instance and only columns.
         */
        $datagrid = app(Crypt::decryptString($params['datagrid_id']));
        $datagrid->prepareColumns();

        /**
         * Finding the first column from the collection.
         */
        $column = collect($datagrid->getColumns())->map(fn ($column) => $column->toArray())->where('index', $params['column'])->firstOrFail();

        /**
         * Fetching on the basis of column options.
         */
        $repository = app($column['filterable_options']['repository']);

        $query = $repository
            ->select([$column['filterable_options']['column']['label'].' as label', $column['filterable_options']['column']['value'].' as value'])
            ->where($column['filterable_options']['column']['label'], 'LIKE', '%'.$params['search'].'%');

        // Doctors and clinic employees aren't valid "assigned to" users; exclude them everywhere this lookup is used for the user repository.
        if ($repository instanceof UserRepository) {
            $query = $repository->excludingClinicRoles($query);
        }

        return $query->get();
    }
}
