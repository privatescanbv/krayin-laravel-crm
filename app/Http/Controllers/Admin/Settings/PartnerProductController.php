<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\PartnerProductDataGrid;
use App\Enums\Currency;
use App\Repositories\PartnerProductRepository;
use App\Models\ResourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

    protected function getCreateViewData(Request $request): array
    {
        return [
            'resourceTypes' => ResourceType::orderBy('name')->get(['id', 'name']),
            'currencies'    => Currency::options(),
        ];
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        return [
            'partner_products' => $entity,
            'resourceTypes'    => ResourceType::orderBy('name')->get(['id', 'name']),
            'currencies'       => Currency::options(),
        ];
    }

    public function view(int $id): View
    {
        $partnerProduct = $this->partnerProductRepository->findOrFail($id);

        return view('admin::settings.partner_products.view', [
            'partner_product' => $partnerProduct,
        ]);
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            // base fields
            'currency'            => 'required|in:' . implode(',', Currency::codes()),
            'sales_price'         => 'required|numeric|min:0',
            'name'                => 'required|string|max:255',
            'active'              => 'required|boolean',
            'description'         => 'nullable|string',
            'discount_info'       => 'nullable|string',
            'resource_type_id'    => 'nullable|integer|exists:resource_types,id',

            // partner fields
            'partner_name'        => 'required|unique:partner_products,partner_name|max:100',
            'clinic_description'  => 'nullable|string',
            'duration'            => 'nullable|integer|min:0',
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate([
            // base fields
            'currency'            => 'required|in:' . implode(',', Currency::codes()),
            'sales_price'         => 'required|numeric|min:0',
            'name'                => 'required|string|max:255',
            'active'              => 'required|boolean',
            'description'         => 'nullable|string',
            'discount_info'       => 'nullable|string',
            'resource_type_id'    => 'nullable|integer|exists:resource_types,id',

            // partner fields
            'partner_name'        => 'required|max:100|unique:partner_products,partner_name,'.$id,
            'clinic_description'  => 'nullable|string',
            'duration'            => 'nullable|integer|min:0',
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
