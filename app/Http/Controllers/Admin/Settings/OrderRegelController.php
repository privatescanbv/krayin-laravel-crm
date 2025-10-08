<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\OrderRegelDataGrid;
use App\Repositories\OrderRegelRepository;
use Illuminate\Http\Request;

class OrderRegelController extends SimpleEntityController
{
    public function __construct(protected OrderRegelRepository $orderRegelRepository)
    {
        parent::__construct($orderRegelRepository);

        $this->entityName = 'order_regels';
        $this->datagridClass = OrderRegelDataGrid::class;
        $this->indexView = 'admin::settings.order_regels.index';
        $this->createView = 'admin::settings.order_regels.create';
        $this->editView = 'admin::settings.order_regels.edit';
        $this->indexRoute = 'admin.settings.order_regels.index';
        $this->permissionPrefix = 'settings.order_regels';
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
        return 'Orderregel aangemaakt.';
    }

    protected function getUpdateSuccessMessage(): string
    {
        return 'Orderregel bijgewerkt.';
    }

    protected function getDestroySuccessMessage(): string
    {
        return 'Orderregel verwijderd.';
    }

    protected function getDeleteFailedMessage(): string
    {
        return 'Verwijderen mislukt.';
    }
}

