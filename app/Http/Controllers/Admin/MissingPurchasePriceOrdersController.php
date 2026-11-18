<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderItemStatus;
use App\Enums\PurchasePriceType;
use App\Models\OrderItem;
use App\Models\PurchasePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * Tijdelijk overzicht: orders met gewonnen, planbare orderregels zonder inkoopprijs.
 */
class MissingPurchasePriceOrdersController extends Controller
{
    public function index(Request $request): View
    {
        $filter = in_array($request->query('planbaar'), ['ja', 'nee', 'alles'], true) ? $request->query('planbaar') : 'ja';
        $plannableTypes = ['mri scanner', 'ct scanner', 'pet ct scanner', 'artsen', 'cardiologie', 'rontgen'];

        $resourceTypeId = <<<'SQL'
            COALESCE(
                order_items.resource_type_id,
                products.resource_type_id,
                (SELECT pp.resource_type_id FROM partner_products pp
                  WHERE pp.product_id = products.id AND pp.deleted_at IS NULL
                  ORDER BY pp.id LIMIT 1)
            )
        SQL;

        $orders = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('resource_types', 'resource_types.id', '=', DB::raw($resourceTypeId))
            ->leftJoin('purchase_prices', function ($join) {
                $join->on('purchase_prices.priceable_id', '=', 'order_items.id')
                    ->where('purchase_prices.priceable_type', (new OrderItem)->getMorphClass())
                    ->where('purchase_prices.type', PurchasePriceType::MAIN->value);
            })
            ->leftJoin('lead_pipeline_stages', 'lead_pipeline_stages.id', '=', 'orders.pipeline_stage_id')
            ->where('order_items.status', OrderItemStatus::WON->value)
            // product zonder (actief) partnerproduct = geen inkoop verwacht
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('partner_products')
                ->whereColumn('partner_products.product_id', 'order_items.product_id')
                ->where('partner_products.active', true)
                ->whereNull('partner_products.deleted_at'))
            // planbaar = alle resource types behalve 'Other' (onbekend/leeg telt als niet planbaar)
            ->when($filter === 'ja', fn ($q) => $q->whereIn(DB::raw('LOWER(resource_types.name)'), $plannableTypes))
            ->when($filter === 'nee', fn ($q) => $q->where(fn ($q) => $q
                ->whereNull('resource_types.name')
                ->orWhereNotIn(DB::raw('LOWER(resource_types.name)'), $plannableTypes)))
            ->where(function ($query) {
                foreach (['purchase_price', ...PurchasePrice::priceableFieldNames()] as $column) {
                    $query->whereRaw("COALESCE(purchase_prices.{$column}, 0) = 0");
                }
            })
            ->groupBy('orders.id', 'orders.order_number', 'orders.title', 'orders.created_at', 'lead_pipeline_stages.name')
            ->orderBy('orders.created_at')
            ->get([
                'orders.id',
                'orders.order_number',
                'orders.title',
                'orders.created_at',
                'lead_pipeline_stages.name as status',
                DB::raw('COUNT(order_items.id) as items_count'),
            ]);

        return view('admin::reports.missing-purchase-prices.index', ['orders' => $orders, 'filter' => $filter]);
    }
}
