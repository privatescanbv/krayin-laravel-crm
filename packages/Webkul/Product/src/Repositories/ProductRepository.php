<?php

namespace Webkul\Product\Repositories;

use App\Models\PartnerProduct;
use App\Repositories\PartnerProductRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Product\Contracts\Product;

class ProductRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'sku',
        'name',
        'description',
    ];

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected PartnerProductRepository $partnerProductRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return Product::class;
    }

    /**
     * Create.
     *
     * @return \Webkul\Product\Contracts\Product
     */
    public function create(array $data)
    {
        $product = parent::create($data);

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $product->id,
        ]));

        $this->syncPartnerProducts($product, $data);

        return $product;
    }

    /**
     * Update.
     *
     * @param  int  $id
     * @param  array  $attribute
     * @return \Webkul\Product\Contracts\Product
     */
    public function update(array $data, $id, $attributes = [])
    {
        // Store partner_products before parent::update() might remove it
        $partnerProducts = $data['partner_products'] ?? null;
        
        $product = parent::update($data, $id);

        /**
         * If attributes are provided then only save the provided attributes and return.
         */
        if (! empty($attributes)) {
            $conditions = ['entity_type' => $data['entity_type']];

            if (isset($data['quick_add'])) {
                $conditions['quick_add'] = 1;
            }

            $attributes = $this->attributeRepository->where($conditions)
                ->whereIn('code', $attributes)
                ->get();

            $this->attributeValueRepository->save(array_merge($data, [
                'entity_id' => $product->id,
            ]), $attributes);

            // Sync partner products even in quick_add mode
            if ($partnerProducts !== null) {
                $this->syncPartnerProducts($product, ['partner_products' => $partnerProducts]);
            }

            return $product;
        }

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $product->id,
        ]));

        // Use stored partner_products or fallback to data array
        $syncData = ['partner_products' => $partnerProducts ?? ($data['partner_products'] ?? [])];
        $this->syncPartnerProducts($product, $syncData);

        return $product;
    }

    /**
     * Sync partner products relationship.
     *
     * @param  \Webkul\Product\Contracts\Product|\Webkul\Product\Models\Product  $product
     * @param  array  $data
     * @return void
     */
    protected function syncPartnerProducts($product, array $data): void
    {
        // Always reset product_id for all partner products that were previously linked to this product
        /** @var \Webkul\Product\Models\Product $product */
        $product->partnerProducts()->update(['product_id' => null]);

        // If partner_products key exists in data, sync the selected ones
        if (array_key_exists('partner_products', $data)) {
            $partnerProductIds = $data['partner_products'] ?? [];

            // Ensure it's an array and filter out invalid values
            if (! is_array($partnerProductIds)) {
                $partnerProductIds = [];
            }

            // Filter out empty values and convert to integers
            $partnerProductIds = array_filter(
                array_map('intval', $partnerProductIds),
                fn($id) => $id > 0
            );

            // Set product_id for the selected partner products
            if (! empty($partnerProductIds)) {
                PartnerProduct::whereIn('id', $partnerProductIds)
                    ->whereNull('deleted_at')
                    ->update(['product_id' => $product->id]);
            }
        }
    }

    /**
     * Retrieves customers count based on date.
     *
     * @return int
     */
    public function getProductCount($startDate, $endDate)
    {
        return $this
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->count();
    }

    /**
     * Get formatted partner products with clinic names for display.
     */
    public function getFormattedPartnerProducts(Product $product): array
    {
        // Explicitly query partner products that belong to this product and are not soft-deleted
        // Use explicit casting to ensure type matching (product_id is unsignedInteger, product->id might be unsignedBigInteger)
        $partnerProducts = PartnerProduct::where('product_id', (int) $product->id)
            ->whereNull('deleted_at')
            ->with('clinics:id,name')
            ->get();

        return $partnerProducts
            ->map(function ($partnerProduct) {
                try {
                    $displayName = $this->partnerProductRepository->formatDisplayName($partnerProduct);
                    return [
                        'id' => (int) $partnerProduct->id,
                        'name' => $displayName,
                    ];
                } catch (\Exception $e) {
                    Log::error('Error formatting partner product display name', [
                        'partner_product_id' => $partnerProduct->id,
                        'error' => $e->getMessage(),
                    ]);
                    return [
                        'id' => (int) $partnerProduct->id,
                        'name' => $partnerProduct->name ?? 'Unknown',
                    ];
                }
            })
            ->toArray();
    }
}
