<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrderPaymentController extends Controller
{
    public function store(int $orderId, Request $request): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        $data = $request->validate($this->getValidationRules());

        $payment = $order->payments()->create($data);

        return response()->json($payment->fresh());
    }

    public function update(int $orderId, int $paymentId, Request $request): JsonResponse
    {
        $payment = OrderPayment::where('order_id', $orderId)->findOrFail($paymentId);

        $data = $request->validate($this->getValidationRules());

        $payment->update($data);

        return response()->json($payment->fresh());
    }

    public function destroy(int $orderId, int $paymentId): JsonResponse
    {
        $payment = OrderPayment::where('order_id', $orderId)->findOrFail($paymentId);
        $payment->delete();

        return response()->json(['success' => true]);
    }

    private function getValidationRules(): array
    {
        return [
            'amount'   => 'required|numeric|min:0',
            'type'     => 'required|in:'.implode(',', array_column(PaymentType::cases(), 'value')),
            'method'   => 'required|in:'.implode(',', array_column(PaymentMethod::cases(), 'value')),
            'paid_at'  => 'nullable|date',
            'currency' => 'nullable|string|in:'.implode(',', array_column(Currency::cases(), 'value')),
        ];
    }
}
