<?php

namespace App\Http\Controllers\Admin\Settings\Clinic;

use App\DataGrids\Settings\ClinicPartnerProductDataGrid;
use App\Models\PartnerProduct;
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

    public function destroy(Request $request, int $id, int $partner_product_id): JsonResponse
    {
        $clinic = $this->clinicRepository->findOrFail($id);
        $partnerProduct = PartnerProduct::findOrFail($partner_product_id);

        // Check how many clinics are linked to this partner product
        $clinicCount = $partnerProduct->clinics()->count();

        if ($clinicCount > 1) {
            // Multiple clinics: only detach from this clinic
            $clinic->partnerProducts()->detach($partner_product_id);
            $message = trans('admin::app.settings.clinics.view.partner-products.detach-success');
        } else {
            // Single clinic: hard delete the partner product
            $partnerProduct->delete();
            $message = trans('admin::app.settings.clinics.view.partner-products.delete-success');
        }

        return response()->json([
            'message' => $message,
        ], 200);
    }
}
