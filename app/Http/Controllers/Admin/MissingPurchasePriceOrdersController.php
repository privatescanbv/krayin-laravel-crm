<?php

namespace App\Http\Controllers\Admin;

use App\DataGrids\Reports\MissingPurchasePriceOrderDataGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * Tijdelijk overzicht: orders met gewonnen, planbare orderregels zonder inkoopprijs.
 */
class MissingPurchasePriceOrdersController extends Controller
{
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(MissingPurchasePriceOrderDataGrid::class)->process();
        }

        return view('admin::reports.missing-purchase-prices.index');
    }
}
