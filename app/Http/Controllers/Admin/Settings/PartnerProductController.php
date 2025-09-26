<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\PartnerProductDataGrid;
use App\Repositories\PartnerProductRepository;
use Illuminate\Http\Request;

class PartnerProductController extends SimpleEntityController
{
    public function __construct(protected PartnerProductRepository $partnerProductRepository)
    {
        parent::__construct($partnerProductRepository);

        $this->entityName = 'partner_products';
        $this->datagridClass = PartnerProductDataGrid::class;
        $this->indexView = 'admin::settings.partner_products.index';
        $this->createView = 'admin::settings.partner_products.create';
        $this->editView = 'admin::settings.partner_products.edit';
        $this->indexRoute = 'admin.settings.partner_products.index';
        $this->permissionPrefix = 'settings.partner_products';
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'partner_name' => 'required|unique:partner_products,partner_name|max:100',
            'description'  => 'nullable|string',
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate([
            'partner_name' => 'required|max:100|unique:partner_products,partner_name,'.$id,
            'description'  => 'nullable|string',
        ]);
    }

    protected function getCreateSuccessMessage(): string
    {
        return trans('admin::app.settings.partner_products.index.create-success');
    }

    protected function getUpdateSuccessMessage(): string
    {
        return trans('admin::app.settings.partner_products.index.update-success');
    }

    protected function getDestroySuccessMessage(): string
    {
        return trans('admin::app.settings.partner_products.index.destroy-success');
    }

    protected function getDeleteFailedMessage(): string
    {
        return trans('admin::app.settings.partner_products.index.delete-failed');
    }
}

