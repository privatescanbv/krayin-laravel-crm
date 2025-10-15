<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\OrderItemDataGrid;
use App\Repositories\OrderItemRepository;
use Illuminate\Http\Request;

class OrderItemController extends SimpleEntityController
{
    public function __construct(protected OrderItemRepository $orderItemRepository)
    {
        parent::__construct($orderItemRepository);

        $this->entityName = 'order_items';
        $this->datagridClass = OrderItemDataGrid::class;
        $this->indexView = 'admin::order_items.index';
        $this->createView = 'admin::order_items.create';
        $this->editView = 'admin::order_items.edit';
        $this->indexRoute = 'admin.order_items.index';
        $this->permissionPrefix = 'settings.order_items';
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'order_id'    => ['required', 'integer', 'exists:orders,id'],
            'product_id'  => ['required', 'integer', 'exists:products,id'],
            'quantity'    => ['required', 'integer', 'min:1'],
            'total_price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $this->validateStore($request);
    }

    protected function getCreateSuccessMessage(): string
    {
        return 'Orderitem aangemaakt.';
    }

    protected function getUpdateSuccessMessage(): string
    {
        return 'Orderitem bijgewerkt.';
    }

    protected function getDestroySuccessMessage(): string
    {
        return 'Orderitem verwijderd.';
    }

    protected function getDeleteFailedMessage(): string
    {
        return 'Verwijderen mislukt.';
    }
}
