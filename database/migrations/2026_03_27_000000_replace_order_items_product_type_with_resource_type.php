<?php

use App\Enums\ProductType as ProductTypeEnum;
use App\Enums\ResourceType as ResourceTypeEnum;
use App\Models\ProductType;
use App\Models\ResourceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'resource_type_id')) {
                $table->unsignedBigInteger('resource_type_id')->nullable()->after('product_id');
                $table->foreign('resource_type_id')->references('id')->on('resource_types')->onDelete('set null');
            }
        });

        if (Schema::hasColumn('order_items', 'product_type_id')) {
            $this->migrateProductTypeOverridesToResourceType();

            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['product_type_id']);
                $table->dropColumn('product_type_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'product_type_id')) {
                $table->unsignedBigInteger('product_type_id')->nullable()->after('product_id');
                $table->foreign('product_type_id')->references('id')->on('product_types')->onDelete('set null');
            }
        });

        if (Schema::hasColumn('order_items', 'resource_type_id')) {
            $this->migrateResourceTypeOverridesBackToProductType();

            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['resource_type_id']);
                $table->dropColumn('resource_type_id');
            });
        }
    }

    private function migrateProductTypeOverridesToResourceType(): void
    {
        $rows = DB::table('order_items')
            ->whereNotNull('product_type_id')
            ->get(['id', 'product_type_id']);

        foreach ($rows as $row) {
            $productType = ProductType::query()->find($row->product_type_id);
            if (! $productType) {
                continue;
            }

            $productTypeEnum = null;
            foreach (ProductTypeEnum::cases() as $case) {
                if (strcasecmp($case->label(), $productType->name) === 0) {
                    $productTypeEnum = $case;
                    break;
                }
            }

            if (! $productTypeEnum) {
                continue;
            }

            $resourceTypeEnum = match ($productTypeEnum) {
                ProductTypeEnum::TOTAL_BODYSCAN => ResourceTypeEnum::MRI_SCANNER,
                ProductTypeEnum::MRI_SCAN       => ResourceTypeEnum::MRI_SCANNER,
                ProductTypeEnum::CT_SCAN        => ResourceTypeEnum::CT_SCANNER,
                ProductTypeEnum::PETSCAN        => ResourceTypeEnum::PET_CT_SCANNER,
                ProductTypeEnum::CARDIOLOGIE    => ResourceTypeEnum::CARDIOLOGIE,
                ProductTypeEnum::OPERATIONS     => ResourceTypeEnum::ARTSEN,
                ProductTypeEnum::ENDOSCOPIE,
                ProductTypeEnum::LABORATORIUM,
                ProductTypeEnum::VERTALING,
                ProductTypeEnum::DIENSTEN,
                ProductTypeEnum::OVERIG => ResourceTypeEnum::OTHER,
            };

            $resourceType = ResourceType::query()->where('name', $resourceTypeEnum->label())->first();
            if ($resourceType) {
                DB::table('order_items')->where('id', $row->id)->update([
                    'resource_type_id' => $resourceType->id,
                ]);
            }
        }
    }

    private function migrateResourceTypeOverridesBackToProductType(): void
    {
        $rows = DB::table('order_items')
            ->whereNotNull('resource_type_id')
            ->get(['id', 'resource_type_id']);

        $resourceEnumByLabel = [];
        foreach (ResourceTypeEnum::cases() as $case) {
            $resourceEnumByLabel[strtolower($case->label())] = $case;
        }

        foreach ($rows as $row) {
            $resourceType = ResourceType::query()->find($row->resource_type_id);
            if (! $resourceType) {
                continue;
            }

            $resourceEnum = $resourceEnumByLabel[strtolower($resourceType->name)] ?? null;
            if (! $resourceEnum) {
                continue;
            }

            $productTypeEnum = match ($resourceEnum) {
                ResourceTypeEnum::MRI_SCANNER     => ProductTypeEnum::MRI_SCAN,
                ResourceTypeEnum::CT_SCANNER      => ProductTypeEnum::CT_SCAN,
                ResourceTypeEnum::PET_CT_SCANNER  => ProductTypeEnum::PETSCAN,
                ResourceTypeEnum::CARDIOLOGIE     => ProductTypeEnum::CARDIOLOGIE,
                ResourceTypeEnum::ARTSEN          => ProductTypeEnum::OPERATIONS,
                ResourceTypeEnum::OTHER           => ProductTypeEnum::OVERIG,
                ResourceTypeEnum::RONTGEN         => null,
            };

            if (! $productTypeEnum) {
                continue;
            }

            $productType = ProductType::query()->where('name', $productTypeEnum->label())->first();
            if ($productType) {
                DB::table('order_items')->where('id', $row->id)->update([
                    'product_type_id' => $productType->id,
                ]);
            }
        }
    }
};
