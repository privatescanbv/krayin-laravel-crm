<?php

namespace App\Http\Controllers\Admin\Settings\Clinic;

use App\DataGrids\Settings\ClinicPartnerProductDataGrid;
use App\Repositories\ClinicRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;

class PartnerProductController extends Controller
{
    public function __construct(
        protected ClinicRepository $clinicRepository
    ) {
    }

    public function index(Request $request, int $id): JsonResponse
    {
        // Verify clinic exists
        $this->clinicRepository->findOrFail($id);

        // Return datagrid JSON response
        return datagrid(ClinicPartnerProductDataGrid::class)->process();
    }
}
