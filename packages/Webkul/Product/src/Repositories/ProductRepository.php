<?php

namespace Webkul\Product\Repositories;

use App\Models\PartnerProduct;
use App\Repositories\PartnerProductRepository;
use Illuminate\Container\Container;
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

            return $product;
        }

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $product->id,
        ]));

        $this->syncPartnerProducts($product, $data);

        return $product;
    }

    /**
     * Sync partner products relationship.
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @param  array  $data
     * @return void
     */
    protected function syncPartnerProducts($product, array $data): void
    {
        // Always reset product_id for all partner products that were previously linked to this product
        $product->partnerProducts()->update(['product_id' => null]);

        // If partner_products key exists in data, sync the selected ones
        if (array_key_exists('partner_products', $data)) {
            $partnerProductIds = $data['partner_products'] ?? [];

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
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @return array
     */
    public function getFormattedPartnerProducts($product): array
    {

        return $product->partnerProducts()
            ->with('clinics:id,name')
            ->get()
            ->map(function ($partnerProduct) {
                return [
                    'id' => $partnerProduct->id,
                    'name' => $this->partnerProductRepository->formatDisplayName($partnerProduct),
                ];
            })
            ->toArray();
    }
}
