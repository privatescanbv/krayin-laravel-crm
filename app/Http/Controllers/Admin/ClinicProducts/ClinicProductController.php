<?php

namespace App\Http\Controllers\Admin\ClinicProducts;

use App\DataGrids\ClinicProducts\ClinicProductDataGrid;
use App\Models\PartnerProduct;
use App\Repositories\ClinicRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;

class ClinicProductController extends Controller
{
    public function __construct(
        protected ClinicRepository $clinicRepository
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        // Verify clinic exists
        $this->clinicRepository->findOrFail($id);

        // Return datagrid JSON response
        return datagrid(ClinicProductDataGrid::class)->process();
    }

    public function destroy(Request $request, int $id, int $partner_product_id): JsonResponse
    {
        $clinic = $this->clinicRepository->findOrFail($id);
        $partnerProduct = PartnerProduct::whereNull('deleted_at')->findOrFail($partner_product_id);

        // Check how many clinics are linked to this partner product
        $clinicCount = $partnerProduct->clinics()->count();

        if ($clinicCount > 1) {
            // Multiple clinics: only detach from this clinic
            $clinic->partnerProducts()->detach($partner_product_id);
            $message = trans('admin::app.clinic-products.index.detach-success');
        } else {
            // Single clinic: soft delete the partner product
            $partnerProduct->delete();
            $message = trans('admin::app.clinic-products.index.delete-success');
        }

        return response()->json([
            'message' => $message,
        ], 200);
    }
}
