<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\PartnerProductDataGrid;
use App\Enums\Currency;
use App\Enums\ProductReports;
use App\Helpers\ProductHelper;
use App\Helpers\RequestHelper;
use App\Models\PartnerProduct;
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
        $this->indexView = 'adminc.partner-products.index';
        $this->createView = 'adminc.partner-products.create';
        $this->editView = 'adminc.partner-products.edit';
        $this->indexRoute = 'admin.partner_products.index';
        $this->permissionPrefix = 'partner_products';
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
            ->get(['id', 'name', 'description', 'currency', 'price', 'resource_type_id', 'product_group_id']);

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
            'resource_type_id' => $product->resource_type_id,
        ];

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->validateStore($request);

        Event::dispatch("settings.{$this->entityName}.create.before");

        $entity = $this->partnerProductRepository->create($this->transformPayload($request->all()));

        $this->syncRelationships($entity, $request);

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
                ->route('admin.clinics.view', $firstClinicId)
                ->with('success', $this->getCreateSuccessMessage());
        }

        return redirect()
            ->route($this->indexRoute)
            ->with('success', $this->getCreateSuccessMessage());
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $request->merge([
            'active' => $request->boolean('active', false),
        ]);
        $this->validateUpdate($request, $id);

        Event::dispatch("settings.{$this->entityName}.update.before", $id);

        $entity = $this->partnerProductRepository->update($this->transformPayload($request->all(), $id), $id);

        $this->syncRelationships($entity, $request);

        Event::dispatch("settings.{$this->entityName}.update.after", $entity);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $entity,
                'message' => $this->getUpdateSuccessMessage(),
            ]);
        }

        // Check if we should return to clinic view
        if ($request->input('return_to') === 'clinic_view') {
            $clinicId = $request->input('clinic_id');
            if ($clinicId) {
                return redirect()
                    ->route('admin.clinics.view', $clinicId)
                    ->with('success', $this->getUpdateSuccessMessage());
            }

            // Fallback to first clinic from the clinics array
            $clinicIds = $request->input('clinics');
            if ($clinicIds) {
                $firstClinicId = is_array($clinicIds) ? reset($clinicIds) : $clinicIds;

                return redirect()
                    ->route('admin.clinics.view', $firstClinicId)
                    ->with('success', $this->getUpdateSuccessMessage());
            }
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
            'preSelectedProductId' => $request->query('product_id'),
            'returnTo'             => $request->query('return_to'),
        ];
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        // Load product relationship for name_with_path
        $entity->load('product.productGroup', 'purchasePrice', 'relatedPurchasePrice');

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

        // Normalize related_products to always be an array using RequestHelper
        $relatedProducts = RequestHelper::filterIntegerArray($request, 'related_products', []);
        $request->merge(['related_products' => $relatedProducts]);

        // Normalize purchase price fields before validation
        $this->normalizePurchasePriceFields($request, [
            'purchase_price_misc',
            'purchase_price_doctor',
            'purchase_price_cardiology',
            'purchase_price_clinic',
            'purchase_price_radiology',
        ]);

        $this->normalizePurchasePriceFields($request, [
            'rel_purchase_price_misc',
            'rel_purchase_price_doctor',
            'rel_purchase_price_cardiology',
            'rel_purchase_price_clinic',
            'rel_purchase_price_radiology',
        ]);

        $request->validate($this->getValidationRules());

        // Additional validation: resources must belong to selected clinics
        $this->validateResourcesMatchClinics($request);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        // removed from maintaining, but we want to keep the imported data here. so, don't touch it.
        unset($request['sales_price']);
        unset($request['related_sales_price']);

        // Normalize related_products to always be an array using RequestHelper
        $relatedProducts = RequestHelper::filterIntegerArray($request, 'related_products', []);
        $request->merge(['related_products' => $relatedProducts]);

        // Normalize purchase price fields before validation
        $this->normalizePurchasePriceFields($request, [
            'purchase_price_misc',
            'purchase_price_doctor',
            'purchase_price_cardiology',
            'purchase_price_clinic',
            'purchase_price_radiology',
        ]);

        $this->normalizePurchasePriceFields($request, [
            'rel_purchase_price_misc',
            'rel_purchase_price_doctor',
            'rel_purchase_price_cardiology',
            'rel_purchase_price_clinic',
            'rel_purchase_price_radiology',
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
            'reporting.*'         => 'string|in:'.implode(',', array_column(ProductReports::cases(), 'value')),

            // purchase price fields (all optional, default to 0)
            'purchase_price_misc'           => 'nullable|numeric|min:0',
            'purchase_price_doctor'         => 'nullable|numeric|min:0',
            'purchase_price_cardiology'     => 'nullable|numeric|min:0',
            'purchase_price_clinic'         => 'nullable|numeric|min:0',
            'purchase_price_radiology'      => 'nullable|numeric|min:0',

            // related purchase price fields (all optional, default to 0)
            'rel_purchase_price_misc'       => 'nullable|numeric|min:0',
            'rel_purchase_price_doctor'     => 'nullable|numeric|min:0',
            'rel_purchase_price_cardiology' => 'nullable|numeric|min:0',
            'rel_purchase_price_clinic'     => 'nullable|numeric|min:0',
            'rel_purchase_price_radiology'  => 'nullable|numeric|min:0',

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
     * Normalize purchase price fields before validation.
     */
    protected function normalizePurchasePriceFields(Request $request, array $fields, $defaultValue = 0): void
    {
        foreach ($fields as $field) {
            $value = $request->input($field);
            $normalized = Currency::normalizePrice($value);
            $request->merge([
                $field => ($normalized === '' || $normalized === null) ? $defaultValue : $normalized,
            ]);
        }
    }

    /**
     * Normalize purchase price fields and calculate total.
     */
    protected function normalizeAndCalculatePurchasePrices(array $payload, array $fields, string $totalField): array
    {
        $total = 0;

        foreach ($fields as $field) {
            if (array_key_exists($field, $payload)) {
                $normalized = Currency::normalizePrice($payload[$field]);
                $payload[$field] = ($normalized === '' || $normalized === null) ? 0 : $normalized;
            } else {
                $payload[$field] = 0;
            }
            $total += floatval($payload[$field]);
        }

        $payload[$totalField] = $total;

        return $payload;
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

        // Strip purchase price fields — saved separately via purchasePrice relations
        $purchasePriceKeys = [
            'purchase_price_misc', 'purchase_price_doctor', 'purchase_price_cardiology',
            'purchase_price_clinic', 'purchase_price_radiology',
            'rel_purchase_price_misc', 'rel_purchase_price_doctor', 'rel_purchase_price_cardiology',
            'rel_purchase_price_clinic', 'rel_purchase_price_radiology',
        ];
        foreach ($purchasePriceKeys as $key) {
            unset($payload[$key]);
        }

        // Normalize reporting field - ensure it's always an array or null
        if (array_key_exists('reporting', $payload)) {
            // Normalize using the model's static method
            $normalized = PartnerProduct::normalizeReporting($payload['reporting']);
            // Set to null if empty, otherwise keep as array (Laravel will auto-convert to JSON via cast)
            $payload['reporting'] = ! empty($normalized) ? $normalized : null;
        } else {
            // If reporting is not in payload (no checkboxes checked), set to null
            $payload['reporting'] = null;
        }

        return parent::transformPayload($payload, $id);
    }

    protected function getCreateSuccessMessage(): string
    {
        return trans('admin::app.partner_products.index.create-success');
    }

    protected function getUpdateSuccessMessage(): string
    {
        return trans('admin::app.partner_products.index.update-success');
    }

    protected function getDestroySuccessMessage(): string
    {
        return trans('admin::app.partner_products.index.destroy-success');
    }

    protected function getDeleteFailedMessage(): string
    {
        return trans('admin::app.partner_products.index.delete-failed');
    }

    /**
     * Sync all relationships for a partner product.
     */
    protected function syncRelationships(Model $entity, Request $request): void
    {
        RequestHelper::syncRelationFromRequest($entity, 'clinics', $request, 'clinics');
        RequestHelper::syncRelationFromRequest($entity, 'relatedProducts', $request, 'related_products');
        RequestHelper::syncRelationFromRequest($entity, 'resources', $request, 'resources');
        $this->savePurchasePrices($entity, $request);
    }

    protected function savePurchasePrices(Model $entity, Request $request): void
    {
        $mainFields = [
            'purchase_price_misc', 'purchase_price_doctor', 'purchase_price_cardiology',
            'purchase_price_clinic', 'purchase_price_radiology',
        ];
        $mainData = [];
        $mainTotal = 0;
        foreach ($mainFields as $field) {
            $value = floatval($request->input($field, 0));
            $mainData[$field] = $value;
            $mainTotal += $value;
        }
        $mainData['purchase_price'] = $mainTotal;
        $entity->purchasePrice()->updateOrCreate([], $mainData);

        $relMapping = [
            'rel_purchase_price_misc'       => 'purchase_price_misc',
            'rel_purchase_price_doctor'     => 'purchase_price_doctor',
            'rel_purchase_price_cardiology' => 'purchase_price_cardiology',
            'rel_purchase_price_clinic'     => 'purchase_price_clinic',
            'rel_purchase_price_radiology'  => 'purchase_price_radiology',
        ];
        $relData = [];
        $relTotal = 0;
        foreach ($relMapping as $requestField => $dbField) {
            $value = floatval($request->input($requestField, 0));
            $relData[$dbField] = $value;
            $relTotal += $value;
        }
        $relData['purchase_price'] = $relTotal;
        $entity->relatedPurchasePrice()->updateOrCreate([], $relData);
    }

    // Price normalization centralized in App\Enums\Currency::normalizePrice
}
