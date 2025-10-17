<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\PartnerProductDataGrid;
use App\Enums\Currency;
use App\Helpers\ProductHelper;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Repositories\ClinicRepository;
use App\Repositories\PartnerProductRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Product\Models\Product;

class PartnerProductController extends SimpleEntityController
{
    public function __construct(
        protected PartnerProductRepository $partnerProductRepository,
        private readonly ClinicRepository $clinicRepository,
    ) {
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
        $data = $this->partnerProductRepository->searchFormatted($query, 50);

        return response()->json(['data' => $data]);
    }

    public function getTemplateProducts(Request $request): JsonResponse
    {
        $query = $request->input('query', '');

        $products = Product::with('productGroup')
            ->where('active', true)
            ->where('name', 'like', '%'.$query.'%')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'description', 'currency', 'price', 'costs', 'resource_type_id', 'product_group_id']);

        $data = ProductHelper::formatCollectionWithPaths($products);

        return response()->json(['data' => $data]);
    }

    public function getTemplateProduct(int $id): JsonResponse
    {
        $product = Product::with('productGroup')->findOrFail($id);

        $data = [
            'id'               => $product->id,
            'name'             => $product->name,
            'name_with_path'   => ProductHelper::formatNameWithPath($product),
            'description'      => $product->description,
            'currency'         => $product->currency,
            'price'            => $product->price,
            'costs'            => $product->costs,
            'resource_type_id' => $product->resource_type_id,
        ];

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

        // Check if we should return to clinic view
        if ($request->input('return_to') === 'clinic_view' && $request->input('clinics')) {
            $clinicIds = $request->input('clinics');
            $firstClinicId = is_array($clinicIds) ? reset($clinicIds) : $clinicIds;

            return redirect()
                ->route('admin.settings.clinics.view', $firstClinicId)
                ->with('success', $this->getCreateSuccessMessage());
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
            'resourceTypes'        => ResourceType::orderBy('name')->get(['id', 'name']),
            'currencies'           => Currency::options(),
            'defaultCurrency'      => Currency::default()->value,
            'clinics'              => $this->clinicRepository->allActive(['id', 'name']),
            'resources'            => Resource::orderBy('name')->get(['id', 'name']),
            'preSelectedClinicId'  => $request->query('clinic_id'),
            'returnTo'             => $request->query('return_to'),
        ];
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        return [
            'partner_products' => $entity,
            'resourceTypes'    => ResourceType::orderBy('name')->get(['id', 'name']),
            'currencies'       => Currency::options(),
            'clinics'          => $this->clinicRepository->allActive(['id', 'name']),
            'resources'        => Resource::orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function validateStore(Request $request): void
    {
        $request->merge([
            'sales_price' => Currency::normalizePrice($request->input('sales_price')),
        ]);

        $request->validate($this->getValidationRules());

        // Additional validation: resources must belong to selected clinics
        $this->validateResourcesMatchClinics($request);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->merge([
            'sales_price' => Currency::normalizePrice($request->input('sales_price')),
        ]);

        $request->validate($this->getValidationRules($id));

        // Additional validation: resources must belong to selected clinics
        $this->validateResourcesMatchClinics($request);
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
            'product_id'          => 'nullable|integer|exists:products,id',

            // partner fields
            'clinic_description'  => 'nullable|string',
            'duration'            => 'nullable|integer|min:0',
            'reporting'           => 'nullable|array',
            'reporting.*'         => 'string|in:'.implode(',', array_column(\App\Enums\ProductReports::cases(), 'value')),

            // relations
            'clinics'             => 'required|array|min:1',
            'clinics.*'           => 'integer|exists:clinics,id',
            'related_products'    => 'nullable|array',
            'related_products.*'  => 'integer|exists:partner_products,id',
            'resources'           => 'nullable|array',
            'resources.*'         => 'integer|exists:resources,id',
        ];
    }

    /**
     * Validate that resources belong to selected clinics.
     */
    protected function validateResourcesMatchClinics(Request $request): void
    {
        $resourceIds = $request->input('resources', []);
        $clinicIds = $request->input('clinics', []);

        if (empty($resourceIds) || empty($clinicIds)) {
            return;
        }

        $validResources = Resource::whereIn('id', $resourceIds)
            ->whereIn('clinic_id', $clinicIds)
            ->pluck('id')
            ->toArray();

        $invalidResources = array_diff($resourceIds, $validResources);

        if (! empty($invalidResources)) {
            throw ValidationException::withMessages([
                'resources' => ['Gekozen resource(s) horen niet bij de geselecteerde kliniek(en).'],
            ]);
        }
    }

    protected function transformPayload(array $payload, ?int $id = null): array
    {
        $payload['active'] = isset($payload['active']) ? (bool) $payload['active'] : true;

        if (array_key_exists('resource_type_id', $payload)) {
            $payload['resource_type_id'] = $payload['resource_type_id'] === '' ? null : $payload['resource_type_id'];
        }

        if (array_key_exists('product_id', $payload)) {
            $payload['product_id'] = $payload['product_id'] === '' ? null : $payload['product_id'];
        }

        if (array_key_exists('sales_price', $payload)) {
            $payload['sales_price'] = Currency::normalizePrice($payload['sales_price']);
        }

        // Normalize purchase price fields
        $purchasePriceFields = [
            'purchase_price_misc',
            'purchase_price_doctor',
            'purchase_price_cardiology',
            'purchase_price_clinic',
            'purchase_price_royal_doctors',
            'purchase_price_radiology',
        ];

        foreach ($purchasePriceFields as $field) {
            if (array_key_exists($field, $payload)) {
                $normalized = Currency::normalizePrice($payload[$field]);
                $payload[$field] = ($normalized === '' || $normalized === null) ? 0 : $normalized;
            } else {
                $payload[$field] = 0;
            }
        }

        // Calculate total purchase price
        $payload['purchase_price'] =
            floatval($payload['purchase_price_misc'] ?? 0) +
            floatval($payload['purchase_price_doctor'] ?? 0) +
            floatval($payload['purchase_price_cardiology'] ?? 0) +
            floatval($payload['purchase_price_clinic'] ?? 0) +
            floatval($payload['purchase_price_royal_doctors'] ?? 0) +
            floatval($payload['purchase_price_radiology'] ?? 0);

        // Normalize reporting field - convert to JSON array
        if (array_key_exists('reporting', $payload)) {
            $payload['reporting'] = $payload['reporting'] ? json_encode($payload['reporting']) : null;
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

    // Price normalization centralized in App\Enums\Currency::normalizePrice
}
