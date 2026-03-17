<?php

namespace Webkul\Admin\Http\Controllers\Products;

use App\Enums\Currency;
use App\Helpers\ProductHelper;
use App\Http\Controllers\Admin\Settings\SimpleEntityController;
use App\Rules\PartnerProductsMatchResourceType;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Product\ProductDataGrid;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Resources\ProductResource;
use Webkul\Product\Repositories\ProductRepository;

class ProductController extends SimpleEntityController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected ProductRepository $productRepository)
    {
        parent::__construct($productRepository);

        $this->entityName = 'product';
        $this->datagridClass = ProductDataGrid::class;
        $this->indexView = 'admin::products.index';
        $this->createView = 'admin::products.create';
        $this->editView = 'admin::products.edit';
        $this->indexRoute = 'admin.products.index';
        $this->permissionPrefix = 'products';

        request()->request->add(['entity_type' => 'products']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            // Clear stale client-side sort (e.g., removed columns like total_in_stock)
            if (request()->query->has('sort')) {
                request()->query->remove('sort');
            }

            // Enforce a safe default sort
            request()->merge(['sort' => [
                ['field' => 'id', 'order' => 'desc']
            ]]);

            return datagrid($this->datagridClass)->process();
        }

        return view($this->indexView);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): View
    {
        return view($this->createView, $this->getCreateViewData($request));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->validateStore($request);

        Event::dispatch('product.create.before');

        $entity = $this->repository->create($this->transformPayload($request->all()));

        Event::dispatch('product.create.after', $entity);

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

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, int $id): View|JsonResponse
    {
        $product = $this->repository->findOrFail($id);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $product]);
        }

        return view($this->editView, $this->getEditViewData($request, $product));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->validateUpdate($request, $id);

        Event::dispatch('product.update.before', $id);

        $payload = $this->transformPayload($request->all(), $id);

        $entity = $this->repository->update($payload, $id);

        Event::dispatch('product.update.after', $entity);

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

    /**
     * Store a newly created resource in storage.
     */
    // Inventory APIs removed (not supported)

    /**
     * Search product results.
     *
     * Overrides parent search to return ProductResource collection instead of simple array.
     */
    public function search(Request $request): JsonResponse
    {
        // Use the search logic from HasEntitySearch trait
        $products = $this->performEntitySearch($request, $this->repository);

        // Eager load productGroup relation after search
        $products->load('productGroup');

        // Return resource collection - Laravel automatically wraps it in 'data' key
        return ProductResource::collection($products)->response();
    }

    /**
     * Get product name by ID.
     *
     * Returns product name with path for a given product ID.
     */
    public function getNameById(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (! $product) {
            return response()->json([
                'error' => 'Product not found',
            ], 404);
        }

        // Load productGroup with full parent chain for path calculation
        // Use the same pattern as getAllWithParents to ensure full hierarchy is loaded
        if (! $product->relationLoaded('productGroup')) {
            $product->load(['productGroup' => function ($query) {
                $query->with(['parent.parent.parent.parent.parent']);
            }]);
        } elseif ($product->productGroup && ! $product->productGroup->relationLoaded('parent')) {
            // If productGroup is loaded but parent chain is not, load it
            $product->productGroup->load(['parent.parent.parent.parent.parent']);
        }

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'name_with_path' => ProductHelper::formatNameWithPathLazy($product),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, ?int $id = null): RedirectResponse|JsonResponse
    {
        if (! $id) {
            $indices = $request['indices'];
            if (is_array($indices) && count($indices) > 0) {
                $id = (int) $indices[0];
            }
        }

        if (! $id) {
            return redirect()
                ->route($this->indexRoute)
                ->with('error', 'Geen geldig ID opgegeven.');
        }

        $entity = $this->repository->findOrFail($id);

        try {
            Event::dispatch('settings.products.delete.before', $id);

            $entity->delete();

            Event::dispatch('settings.products.delete.after', $id);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => $this->getDestroySuccessMessage(),
                ], 200);
            }

            return redirect()
                ->route($this->indexRoute)
                ->with('success', $this->getDestroySuccessMessage());
        } catch (Exception $exception) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => $this->getDeleteFailedMessage(),
                ], 400);
            }

            return redirect()
                ->route($this->indexRoute)
                ->with('error', $this->getDeleteFailedMessage());
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $validated = method_exists($massDestroyRequest, 'validated') ? $massDestroyRequest->validated() : [];
        $indices = $validated['indices'] ?? (request()->input('indices') ?? []);

        foreach ($indices as $index) {
            Event::dispatch('product.delete.before', $index);

            $this->repository->delete($index);

            Event::dispatch('product.delete.after', $index);
        }

        return new JsonResponse([
            'message' => trans('admin::app.products.index.delete-success'),
        ]);
    }

    /**
     * Validate store request.
     */
    protected function validateStore(Request $request): void
    {
        $this->normalizePriceFields($request);
        $request->merge([
            'active' => $request->boolean('active', true),
        ]);
        $request->validate($this->getValidationRules());
    }

    /**
     * Validate update request.
     */
    protected function validateUpdate(Request $request, int $id): void
    {
        $this->normalizePriceFields($request);
        $request->merge([
            'active' => $request->boolean('active', true),
        ]);
        $request->validate($this->getValidationRules($id));
    }

    /**
     * Get create view data.
     */
    protected function getCreateViewData(Request $request): array
    {
        return [
            'currencies'      => Currency::options(),
            'defaultCurrency' => Currency::default()->value,
        ];
    }

    /**
     * Get edit view data.
     */
    protected function getEditViewData(Request $request, Model $entity): array
    {
        // Get formatted partner products with clinic names
        $selectedPartnerProducts = $this->repository->getFormattedPartnerProducts($entity);

        return [
            $this->entityName => $entity,
            'currencies'      => Currency::options(),
            'defaultCurrency' => Currency::default()->value,
            'selectedPartnerProducts' => $selectedPartnerProducts,
        ];
    }

    /**
     * Transform payload before saving.
     */
    protected function transformPayload(array $payload, ?int $id = null): array
    {
        $payload['entity_type'] = 'products';
        return $this->cleanProductData($payload);
    }

    /**
     * Get create success message.
     */
    protected function getCreateSuccessMessage(): string
    {
        return trans('admin::app.products.index.create-success');
    }

    /**
     * Get update success message.
     */
    protected function getUpdateSuccessMessage(): string
    {
        return trans('admin::app.products.index.update-success');
    }

    /**
     * Get destroy success message.
     */
    protected function getDestroySuccessMessage(): string
    {
        return trans('admin::app.products.index.delete-success');
    }

    /**
     * Get delete failed message.
     */
    protected function getDeleteFailedMessage(): string
    {
        return trans('admin::app.products.index.delete-failed');
    }

    /**
     * Get validation rules for product.
     */
    protected function getValidationRules(?int $id = null): array
    {
        $resourceTypeId = request()->input('resource_type_id');

        // Debug logging removed - was too verbose
        return [
            'name'              => 'required|string|max:255',
            'active'            => 'required|boolean',
            'currency'          => 'required|in:'.implode(',', Currency::codes()),
            'description'       => 'nullable|string',
            'price'             => 'nullable|numeric|min:0',
            'product_group_id'  => 'required|integer|exists:product_groups,id',
            'product_type_id'   => 'nullable|integer|exists:product_types,id',
            'resource_type_id'  => 'nullable|integer|exists:resource_types,id',
            'partner_products'  => ['nullable', 'array', new PartnerProductsMatchResourceType($resourceTypeId)],
            'partner_products.*' => 'integer|exists:partner_products,id',
        ];
    }

    /**
     * Normalize price fields in request.
     */
    protected function normalizePriceFields(Request $request): void
    {
        $request->merge([
            'price' => Currency::normalizePrice($request->input('price')),
        ]);
    }

    /**
     * Normalize price strings like "1.234,56" or "45,00" to "1234.56".
     */
    // Price normalization centralized in App\Enums\Currency::normalizePrice

    /**
     * Clean product data before saving.
     * Converts empty strings to null for foreign key and numeric fields.
     */
    protected function cleanProductData(array $data): array
    {
        // Normalize active to boolean; default true
        if (! array_key_exists('active', $data)) {
            $data['active'] = true;
        } else {
            $data['active'] = (bool) $data['active'];
        }

        // Ensure partner_products is always present and normalized as array of integers
        if (! array_key_exists('partner_products', $data)) {
            $data['partner_products'] = [];
        } else {
            // Normalize partner_products to array of integers
            if (! is_array($data['partner_products'])) {
                $data['partner_products'] = [];
            } else {
                // Filter out empty values and convert to integers
                $data['partner_products'] = array_filter(
                    array_map('intval', $data['partner_products']),
                    fn($id) => $id > 0
                );
                // Re-index array to ensure sequential keys
                $data['partner_products'] = array_values($data['partner_products']);
            }
        }

        // Convert empty strings to null for foreign key fields
        $foreignKeyFields = ['product_group_id', 'product_type_id', 'resource_type_id'];

        foreach ($foreignKeyFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        // Convert empty strings to null for decimal fields
        $decimalFields = ['price'];

        foreach ($decimalFields as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
