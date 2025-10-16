<?php

namespace App\Helpers;

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

            return "{$path} > {$name}";
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

            return "{$path} > {$name}";
        }

        return $name;
    }

    /**
     * Format multiple products with their paths.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $products
     * @return \Illuminate\Support\Collection
     */
    public static function formatCollectionWithPaths($products)
    {
        return $products->map(function ($product) {
            return [
                'id'               => $product->id,
                'name'             => $product->name,
                'name_with_path'   => self::formatNameWithPathLazy($product),
                'description'      => $product->description,
                'currency'         => $product->currency,
                'price'            => $product->price,
                'costs'            => $product->costs,
                'resource_type_id' => $product->resource_type_id,
            ];
        });
    }
}
