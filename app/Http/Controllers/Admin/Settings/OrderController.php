<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\OrderDataGrid;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;

class OrderController extends SimpleEntityController
{
    public function __construct(protected OrderRepository $orderRepository)
    {
        parent::__construct($orderRepository);

        $this->entityName = 'orders';
        $this->datagridClass = OrderDataGrid::class;
        $this->indexView = 'admin::settings.orders.index';
        $this->createView = 'admin::settings.orders.create';
        $this->editView = 'admin::settings.orders.edit';
        $this->indexRoute = 'admin.settings.orders.index';
        $this->permissionPrefix = 'settings.orders';
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'sales_order_id' => ['nullable', 'string', 'max:255'],
            'total_price'    => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $this->validateStore($request);
    }

    protected function getCreateSuccessMessage(): string
    {
        return 'Order aangemaakt.';
    }

    protected function getUpdateSuccessMessage(): string
    {
        return 'Order bijgewerkt.';
    }

    protected function getDestroySuccessMessage(): string
    {
        return 'Order verwijderd.';
    }

    protected function getDeleteFailedMessage(): string
    {
        return 'Verwijderen mislukt.';
    }
}
