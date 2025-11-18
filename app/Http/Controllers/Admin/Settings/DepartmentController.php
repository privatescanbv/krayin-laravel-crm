<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;

class DepartmentController extends Controller
{
    /**
     * Search departments.
     */
    public function search(Request $request): JsonResponse
    {
        $query = Department::query();

        if ($searchTerm = $request->get('search')) {
            $query->where('name', 'like', "%{$searchTerm}%");
        }

        $departments = $query->get()->map(fn ($dept) => [
            'id'   => $dept->id,
            'name' => $dept->name,
        ]);

        return response()->json(['data' => $departments]);
    }
}
