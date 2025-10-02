<?php

namespace Webkul\Admin\Http\Controllers\Products;

use App\Enums\Currency;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Product\ProductDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Resources\ProductResource;
use Webkul\Product\Repositories\ProductRepository;

class ProductController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected ProductRepository $productRepository)
    {
        request()->request->add(['entity_type' => 'products']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            // Clear stale client-side sort (e.g., removed columns like total_in_stock)
            if (request()->query->has('sort')) {
                request()->query->remove('sort');
            }

            // Enforce a safe default sort
            request()->merge(['sort' => [
                ['field' => 'id', 'order' => 'desc']
            ]]);

            return datagrid(ProductDataGrid::class)->process();
        }

        return view('admin::products.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin::products.create', [
            'currencies'      => Currency::options(),
            'defaultCurrency' => Currency::default()->value,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(AttributeForm $request)
    {
        $this->validateStore($request);

        Event::dispatch('product.create.before');

        $payload = request()->all();
        $payload['entity_type'] = 'products';
        $data = $this->cleanProductData($payload);
        $product = $this->productRepository->create($data);

        Event::dispatch('product.create.after', $product);

        session()->flash('success', trans('admin::app.products.index.create-success'));

        return redirect()->route('admin.products.index');
    }

    /**
     * Show the form for viewing the specified resource.
     */
    public function view(int $id): View
    {
        $product = $this->productRepository->findOrFail($id);

        return view('admin::products.view', compact('product'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View|JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);

        $currencies = Currency::options();
        $defaultCurrency = Currency::default()->value;

        // Inventory/warehouse logic removed for this deployment
        return view('admin::products.edit', compact('product', 'currencies', 'defaultCurrency'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeForm $request, int $id)
    {
        $this->validateUpdate($request, $id);

        Event::dispatch('product.update.before', $id);

        $payload = request()->all();
        $payload['entity_type'] = 'products';
        $data = $this->cleanProductData($payload);
        $product = $this->productRepository->update($data, $id);

        Event::dispatch('product.update.after', $product);

        if (request()->ajax()) {
            return response()->json([
                'message' => trans('admin::app.products.index.update-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.products.index.update-success'));

        return redirect()->route('admin.products.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    // Inventory APIs removed (not supported)

    /**
     * Search product results
     */
    public function search(): JsonResource
    {
        $products = $this->productRepository
            ->pushCriteria(app(RequestCriteria::class))
            ->all();

        return ProductResource::collection($products);
    }

    /**
     * Returns product inventories grouped by warehouse.
     */
    // Warehouses endpoint removed (not supported)

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);

        try {
            Event::dispatch('settings.products.delete.before', $id);

            $product->delete($id);

            Event::dispatch('settings.products.delete.after', $id);

            return new JsonResponse([
                'message' => trans('admin::app.products.index.delete-success'),
            ], 200);
        } catch (Exception $exception) {
            return new JsonResponse([
                'message' => trans('admin::app.products.index.delete-failed'),
            ], 400);
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

            $this->productRepository->delete($index);

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
            'active' => (bool) $request->boolean('active', true),
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
            'active' => (bool) $request->boolean('active', true),
        ]);
        $request->validate($this->getValidationRules($id));
    }

    /**
     * Get validation rules for product.
     */
    protected function getValidationRules(?int $id = null): array
    {
        return [
            'name'              => 'required|string|max:255',
            'active'            => 'required|boolean',
            'currency'          => 'required|in:'.implode(',', Currency::codes()),
            'description'       => 'nullable|string',
            'price'             => 'nullable|numeric|min:0',
            'costs'             => 'nullable|numeric|min:0',
            'product_group_id'  => 'required|integer|exists:product_groups,id',
            'product_type_id'   => 'nullable|integer|exists:product_types,id',
            'resource_type_id'  => 'nullable|integer|exists:resource_types,id',
            'partner_products'  => 'nullable|array',
            'partner_products.*' => 'integer|exists:partner_products,id',
        ];
    }

    /**
     * Normalize price fields in request.
     */
    protected function normalizePriceFields(Request $request): void
    {
        $request->merge([
            'price' => \App\Enums\Currency::normalizePrice($request->input('price')),
            'costs' => \App\Enums\Currency::normalizePrice($request->input('costs')),
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

        // Convert empty strings to null for foreign key fields
        $foreignKeyFields = ['product_group_id', 'product_type_id', 'resource_type_id'];

        foreach ($foreignKeyFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        // Convert empty strings to null for decimal fields
        $decimalFields = ['price', 'costs'];

        foreach ($decimalFields as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
