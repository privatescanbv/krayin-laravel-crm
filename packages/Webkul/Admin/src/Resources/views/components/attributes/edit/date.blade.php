@php
    use Carbon\Carbon;if (! empty($value)) {
        if ($value instanceof Carbon) {
            $value = $value->format('d-m-Y');
        } elseif (is_string($value)) {
            $value = Carbon::parse($value)->format('d-m-Y');
        }
    }
@endphp

<x-admin::form.control-group.control
    type="date"
    :id="$attribute->code"
    :name="$attribute->code"
    :value="$value"
    :rules="$validations.'|regex:^\d{2}-\d{2}-\d{4}$'"
    :label="$attribute->name"
/>
