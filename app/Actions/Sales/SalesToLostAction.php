<?php

namespace App\Actions\Sales;

use App\Http\Controllers\Admin\AnamnesisController;
use App\Models\SalesLead;
use App\Repositories\OrderRepository;

/**
 * - remove uncompleted GVL forms
 * - remove uncompleted orders
 */
class SalesToLostAction
{
    public function __construct(
        private readonly AnamnesisController $anamnesisController,
        private readonly OrderRepository $orderRepository,
    ) {}

    public function execute(SalesLead $sales): void
    {
        logger()->info('Running clean up action for sales '.$sales->id);
        $sales->persons()->each(function ($person) use ($sales) {
            $this->anamnesisController->cleanUpForLead($person->id, $sales->lead->id);
        });

        $this->orderRepository->cleanUpFromLostSales($sales->id);
    }
}
