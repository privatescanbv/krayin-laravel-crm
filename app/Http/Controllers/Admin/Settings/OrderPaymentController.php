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
use Webkul\Activity\Repositories\ActivityRepository;

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

    public function destroy(int $orderId, int $paymentId, ActivityRepository $activityRepository): JsonResponse
    {
        $payment = OrderPayment::where('order_id', $orderId)->findOrFail($paymentId);
        $payment->delete();

        $amount = '€ '.number_format((float) $payment->amount, 2, ',', '.');

        $activityRepository->createSystem([
            'title'      => sprintf('%s van %s verwijderd', $payment->type->label(), $amount),
            'comment'    => sprintf(
                'Methode: %s, datum: %s',
                $payment->method->label(),
                $payment->paid_at?->format('d-m-Y') ?? 'nog niet betaald',
            ),
            'user_id'    => auth()->guard('user')->id(),
            'order_id'   => $orderId,
            'additional' => [
                'old'        => ['label' => $amount],
                'new'        => ['label' => 'Verwijderd'],
                'payment_id' => $payment->id,
            ],
        ]);

        return response()->json(['success' => true]);
    }

    private function getValidationRules(): array
    {
        return [
            'amount'   => 'required|numeric|min:0',
            'type'     => 'required|in:'.implode(',', array_column(PaymentType::cases(), 'value')),
            'method'   => 'required|in:'.implode(',', array_column(PaymentMethod::cases(), 'value')),
            'paid_at'  => 'nullable|date',
            'currency' => 'required|string|in:'.implode(',', array_column(Currency::cases(), 'value')),
        ];
    }
}
