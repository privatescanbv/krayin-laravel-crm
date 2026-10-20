<?php

use App\Enums\ActivityType;
use App\Models\Order;
use App\Services\OrderRefundService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $orderIds = DB::table('activities')
            ->where('type', ActivityType::TASK->value)
            ->where('title', OrderRefundService::TASK_TITLE)
            ->where('is_done', false)
            ->whereNotNull('order_id')
            ->distinct()
            ->pluck('order_id');

        $service = app(OrderRefundService::class);

        Order::whereIn('id', $orderIds)->each(fn (Order $order) => $service->closeRefundTasksIfSettled($order));
    }

    public function down(): void
    {
        //
    }
};
