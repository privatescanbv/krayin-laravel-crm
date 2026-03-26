<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OrderPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rows'              => 'required|array|min:1',
            'rows.*.order_id'   => 'required|integer|exists:orders,id',
            'rows.*.payment_id' => 'nullable|integer|exists:order_payments,id',
            'rows.*.amount'     => 'required|numeric|min:0.01',
            'rows.*.type'       => 'required|in:advance,paid_at_clinic,refund',
            'rows.*.method'     => 'required|in:bank,pin,cash,creditcard',
            'rows.*.paid_at'    => 'nullable|date',
            'rows.*.currency'   => 'nullable|string|in:EUR',
        ];
    }
}
