<?php

namespace App\Http\Controllers\Admin\Contacts;

use App\DataGrids\Contact\PersonLeadDataGrid;
use Illuminate\Http\Request;

class PersonLeadsController
{
    public function index(Request $request, int $id)
    {
        // Make route param accessible for the datagrid (it reads request('person_id')).
        $request->merge(['person_id' => $id]);

        return datagrid(PersonLeadDataGrid::class)->process();
    }
}
