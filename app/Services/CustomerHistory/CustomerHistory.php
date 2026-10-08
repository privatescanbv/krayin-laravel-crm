<?php

namespace App\Services\CustomerHistory;

use App\Enums\CustomerType;
use Carbon\CarbonImmutable;

final readonly class CustomerHistory
{
    public function __construct(
        public CustomerType $customerType,
        public int $priorLeadCount,
        public int $priorLostLeadCount,
        public int $purchaseCount,
        public ?CarbonImmutable $firstPurchaseAt,
        public ?CarbonImmutable $lastPurchaseAt,
        public float $revenueTotal,
        public int $salesActivityCount,
    ) {}
}
