<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\PartnerProductDataGrid;
use App\Enums\Currency;
use App\Models\Clinic;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Repositories\PartnerProductRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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

    public function view(int $id): View
    {
        $partnerProduct = $this->partnerProductRepository->findOrFail($id);

        return view('admin::settings.partner_products.view', [
            'partner_product' => $partnerProduct,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('query', '');

        $products = $this->partnerProductRepository
            ->scopeQuery(function ($q) use ($query) {
                return $q->where('active', true)
                    ->where('name', 'like', '%'.$query.'%')
                    ->orderBy('name')
                    ->limit(50);
            })
            ->all();

        $data = $products->map(function ($product) {
            return [
                'id'   => $product->id,
                'name' => $product->name,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->validateStore($request);

        Event::dispatch("settings.{$this->entityName}.create.before");

        $entity = $this->partnerProductRepository->create($this->transformPayload($request->all()));

        $entity->clinics()->sync($request->input('clinics', []));
        $entity->relatedProducts()->sync($request->input('related_products', []));
        $entity->resources()->sync($request->input('resources', []));

        Event::dispatch("settings.{$this->entityName}.create.after", $entity);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $entity,
                'message' => $this->getCreateSuccessMessage(),
            ], 200);
        }

        return redirect()
            ->route($this->indexRoute)
            ->with('success', $this->getCreateSuccessMessage());
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->validateUpdate($request, $id);

        Event::dispatch("settings.{$this->entityName}.update.before", $id);

        $entity = $this->partnerProductRepository->update($this->transformPayload($request->all(), $id), $id);

        $entity->clinics()->sync($request->input('clinics', []));
        $entity->relatedProducts()->sync($request->input('related_products', []));
        $entity->resources()->sync($request->input('resources', []));

        Event::dispatch("settings.{$this->entityName}.update.after", $entity);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $entity,
                'message' => $this->getUpdateSuccessMessage(),
            ]);
        }

        return redirect()
            ->route($this->indexRoute)
            ->with('success', $this->getUpdateSuccessMessage());
    }

    protected function getCreateViewData(Request $request): array
    {
        return [
            'resourceTypes'   => ResourceType::orderBy('name')->get(['id', 'name']),
            'currencies'      => Currency::options(),
            'defaultCurrency' => Currency::default()->value,
            'clinics'         => Clinic::orderBy('name')->get(['id', 'name']),
            'resources'       => Resource::orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        return [
            'partner_products' => $entity,
            'resourceTypes'    => ResourceType::orderBy('name')->get(['id', 'name']),
            'currencies'       => Currency::options(),
            'clinics'          => Clinic::orderBy('name')->get(['id', 'name']),
            'resources'        => Resource::orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function validateStore(Request $request): void
    {
        $request->merge([
            'sales_price' => $this->normalizePrice($request->input('sales_price')),
        ]);

        $request->validate($this->getValidationRules());
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->merge([
            'sales_price' => $this->normalizePrice($request->input('sales_price')),
        ]);

        $request->validate($this->getValidationRules($id));
    }

    protected function getValidationRules(?int $id = null): array
    {
        return [
            // base fields
            'currency'            => 'required|in:'.implode(',', Currency::codes()),
            'sales_price'         => 'required|numeric|min:0',
            'name'                => 'required|string|max:255',
            'active'              => 'required|boolean',
            'description'         => 'nullable|string',
            'discount_info'       => 'nullable|string',
            'resource_type_id'    => 'required|integer|exists:resource_types,id',

            // partner fields
            'clinic_description'  => 'nullable|string',
            'duration'            => 'nullable|integer|min:0',

            // relations
            'clinics'             => 'required|array|min:1',
            'clinics.*'           => 'integer|exists:clinics,id',
            'related_products'    => 'nullable|array',
            'related_products.*'  => 'integer|exists:partner_products,id',
            'resources'           => 'nullable|array',
            'resources.*'         => 'integer|exists:resources,id',
        ];
    }

    protected function transformPayload(array $payload, ?int $id = null): array
    {
        $payload['active'] = isset($payload['active']) ? (bool) $payload['active'] : true;

        if (array_key_exists('resource_type_id', $payload)) {
            $payload['resource_type_id'] = $payload['resource_type_id'] === '' ? null : $payload['resource_type_id'];
        }

        if (array_key_exists('sales_price', $payload)) {
            $payload['sales_price'] = $this->normalizePrice($payload['sales_price']);
        }

        return parent::transformPayload($payload, $id);
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

    /**
     * Normalize price strings like "1.234,56" or "45,00" to "1234.56".
     */
    private function normalizePrice($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        $value = preg_replace('/\s+/', '', $value ?? '');

        if ($value === '') {
            return $value;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            // Assume dot is thousands separator and comma is decimal separator
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($hasComma && ! $hasDot) {
            // Only comma present -> treat as decimal separator
            $value = str_replace(',', '.', $value);
        }

        return $value;
    }
}
