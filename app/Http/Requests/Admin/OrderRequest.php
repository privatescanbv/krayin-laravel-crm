<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderItemStatus;
use App\Models\SalesLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * Validates the order store/update payload (admin order form).
 */
class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                           => ['required', 'string', 'max:255'],
            'total_price'                     => ['nullable', 'numeric', 'min:0'],
            'sales_lead_id'                   => ['required', 'integer', 'exists:salesleads,id'],
            'user_id'                         => ['nullable', 'integer', 'exists:users,id'],
            'clinic_coordinator_user_id'      => ['nullable', 'integer', 'exists:users,id'],
            'combine_order'                   => ['nullable', 'boolean'],
            'invoice_number'                  => ['nullable', 'string', 'max:255'],
            'is_business'                     => ['nullable', 'boolean'],
            'organization_id'                 => [
                Rule::requiredIf(fn () => $this->boolean('is_business') && ! $this->filled('new_org.name')),
                'nullable', 'integer', 'exists:organizations,id',
            ],
            'new_org'                         => ['nullable', 'array'],
            'new_org.name'                    => ['nullable', 'string', 'max:255'],
            'new_org.postal_code'             => ['nullable', 'string', 'max:20'],
            'new_org.house_number'            => ['nullable', 'string', 'max:20'],
            'new_org.house_number_suffix'     => ['nullable', 'string', 'max:20'],
            'new_org.street'                  => ['nullable', 'string', 'max:255'],
            'new_org.city'                    => ['nullable', 'string', 'max:255'],
            'new_org.country'                 => ['nullable', 'string', 'max:100'],
            'first_examination_date'          => ['nullable', 'date'],
            'first_examination_time'          => ['nullable', 'string', 'max:5'],
            'first_examination_date_override' => ['nullable', 'boolean'],
            'first_examination_time_override' => ['nullable', 'boolean'],
            'items'                           => ['nullable', 'array'],
            'items.*.product_id'              => ['nullable', 'integer', 'exists:products,id'],
            'items.*.person_id'               => [
                'required_with:items.*.product_id',
                'nullable',
                'integer',
                'exists:persons,id',
            ],
            'items.*.quantity'              => ['nullable', 'integer', 'min:1'],
            'items.*.total_price'           => ['nullable', 'numeric', 'min:0'],
            'items.*.status'                => ['nullable', new Enum(OrderItemStatus::class)],
            'removed_order_item_ids'        => ['nullable', 'array'],
            'removed_order_item_ids.*'      => ['integer', 'exists:order_items,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.person_id.required_with' => 'Elk orderitem met een product moet een persoon hebben.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $salesLeadId = $this->input('sales_lead_id');

            if ($salesLeadId && ! SalesLead::where('id', $salesLeadId)->whereNotNull('lead_id')->exists()) {
                $validator->errors()->add('sales_lead_id', 'De geselecteerde saleslead heeft geen gekoppeld lead.');
            }
        });
    }

    /**
     * Normalize items array keys and values before validation.
     *
     * The frontend sends items with keys like "1" (for existing) or "item_1" (for new),
     * while Laravel validation requires numeric keys for the items.* pattern. The original
     * keys are preserved in `_items_original_keys` so the controller can distinguish
     * existing items from new ones.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);

        if (! is_array($items) || empty($items)) {
            return;
        }

        $normalizedItems = [];
        $originalKeys = [];
        $nextNewKey = 1000000; // Start high to avoid conflicts with existing IDs

        foreach ($items as $key => $item) {
            // Skip rows without product (e.g. after removing last item; avoids validation on empty rows)
            $hasProduct = isset($item['product_id']) && $item['product_id'] !== null && $item['product_id'] !== '';
            if (! $hasProduct) {
                continue;
            }

            // Skip rows with product but no person (stale data when removing last item; avoids validation error)
            $hasPerson = isset($item['person_id']) && $item['person_id'] !== null && $item['person_id'] !== '';
            if (! $hasPerson) {
                continue;
            }

            $item['product_id'] = (int) $item['product_id'];
            $item['person_id'] = (int) $item['person_id'];
            if (isset($item['quantity']) && $item['quantity'] !== null && $item['quantity'] !== '') {
                $item['quantity'] = (int) $item['quantity'];
            }

            $normalizedKey = is_numeric($key) ? (int) $key : $nextNewKey++;
            $normalizedItems[$normalizedKey] = $item;
            $originalKeys[$normalizedKey] = $key;
        }

        $this->replace(array_merge($this->except('items'), [
            'items'                => $normalizedItems,
            '_items_original_keys' => $originalKeys,
        ]));
    }
}
