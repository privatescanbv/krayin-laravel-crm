<?php

namespace App\Helpers;

use App\Enums\PathDivider;
use Illuminate\Support\Collection;
use Webkul\Product\Models\Product;

class ProductHelper
{
    /**
     * Format product name with product group path for display.
     */
    public static function formatNameWithPath(Product $product): string
    {
        $name = $product->name;

        if ($product->productGroup) {
            $path = $product->productGroup->path;

            return "{$path}".PathDivider::value()."{$name}";
        }

        return $name;
    }

    /**
     * Format product name with product group path for display.
     * This method loads the product group relationship if not already loaded.
     */
    public static function formatNameWithPathLazy(Product $product): string
    {
        $name = $product->name;

        // Load product group if not already loaded
        if (! $product->relationLoaded('productGroup')) {
            $product->load('productGroup');
        }

        if ($product->productGroup) {
            $path = $product->productGroup->path;

            return "{$path}".PathDivider::value()."{$name}";
        }

        return $name;
    }

    /**
     * Format multiple products with their paths.
     */
    public static function formatCollectionWithPaths(Collection $products): Collection
    {
        logger()->info('Formatting products with paths', ['count' => $products->count(), 'class' => get_class($products[0])]);

        return $products->map(function ($product) {
            return [
                'id'               => $product->id,
                'name'             => $product->name,
                'name_with_path'   => self::formatNameWithPathLazy($product),
                'description'      => $product->description,
                'currency'         => $product->currency,
                'price'            => $product->price,
                'resource_type_id' => $product->resource_type_id,
            ];
        });
    }
}
