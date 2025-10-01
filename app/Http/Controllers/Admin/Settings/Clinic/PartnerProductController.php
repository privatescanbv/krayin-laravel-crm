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
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        // Verify clinic exists
        $this->clinicRepository->findOrFail($id);

        // Return datagrid JSON response
        return datagrid(ClinicPartnerProductDataGrid::class)->process();
    }

    public function attach(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'partner_product_ids' => 'required|array|min:1',
            'partner_product_ids.*' => 'required|integer|exists:partner_products,id',
        ]);

        $clinic = $this->clinicRepository->findOrFail($id);

        // Attach partner products (without detaching existing ones)
        $clinic->partnerProducts()->syncWithoutDetaching($request->input('partner_product_ids'));

        return response()->json([
            'message' => trans('admin::app.settings.clinics.view.partner-products.attach-success'),
        ], 200);
    }

    public function detach(Request $request, int $id, int $partner_product_id): JsonResponse
    {
        $clinic = $this->clinicRepository->findOrFail($id);

        // Detach the partner product
        $clinic->partnerProducts()->detach($partner_product_id);

        return response()->json([
            'message' => trans('admin::app.settings.clinics.view.partner-products.detach-success'),
        ], 200);
    }
}
