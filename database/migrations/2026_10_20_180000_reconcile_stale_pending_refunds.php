<?php

use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\OrderRefundService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $orderIds = OrderPayment::where('type', PaymentType::REFUND->value)
            ->whereNull('paid_at')
            ->where('auto_created', true)
            ->distinct()
            ->pluck('order_id');

        $service = app(OrderRefundService::class);

        Order::whereIn('id', $orderIds)->each(fn (Order $order) => $service->reconcilePendingRefunds($order));
    }

    public function down(): void
    {
        //
    }
};
