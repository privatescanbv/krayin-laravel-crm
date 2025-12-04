@php
    use Carbon\Carbon;
@endphp

@foreach ($customAttributes as $attribute)
    @php
        $validations = [];

        if ($attribute->is_required) {
            $validations[] = 'required';
        }

        if ($attribute->type == 'price') {
            $validations[] = 'decimal';
        }

        $validations[] = $attribute->validation;

        $validations = implode('|', array_filter($validations));
        $value = isset($entity) ? $entity[$attribute->code] : null;
        $simpleTypes = ['text', 'textarea', 'select', 'date', 'datetime'];
    @endphp

    @if (in_array($attribute->type, $simpleTypes))
        @php
            $label = $attribute->name;
            if ($attribute->type == 'price') {
                $label .= ' (' . core()->currencySymbol(config('app.currency')) . ')';
            }
        @endphp

        @if ($attribute->type == 'select')
            @php
                $options = $attribute->lookup_type
                    ? app('Webkul\Attribute\Repositories\AttributeRepository')->getLookUpOptions($attribute->lookup_type)
                    : $attribute->options()->orderBy('sort_order')->get();
            @endphp
            <x-adminc::components.field
                type="select"
                id="{{ $attribute->code }}"
                name="{{ $attribute->code }}"
                label="{{ $label }}"
                value="{{ old($attribute->code) ?? $value }}"
                rules="{{ $validations }}"
                class="mb-2.5 w-full"
            >
                @foreach ($options as $option)
                    <option value="{{ $option->id }}" {{ (old($attribute->code) ?? $value) == $option->id ? 'selected' : '' }}>
                        {{ $option->name }}
                    </option>
                @endforeach
            </x-adminc::components.field>
        @elseif ($attribute->type == 'date')
            @php
                $dateValue = $value;
                if (! empty($dateValue)) {
                    if ($dateValue instanceof Carbon) {
                        $dateValue = $dateValue->format('d-m-Y');
                    } elseif (is_string($dateValue)) {
                        $dateValue = Carbon::parse($dateValue)->format('d-m-Y');
                    }
                }
            @endphp
            <x-adminc::components.field
                type="date"
                id="{{ $attribute->code }}"
                name="{{ $attribute->code }}"
                label="{{ $label }}"
                value="{{ $dateValue }}"
                rules="{{ $validations }}|regex:^\d{2}-\d{2}-\d{4}$"
                class="mb-2.5 w-full"
            />
        @elseif ($attribute->type == 'datetime')
            <x-adminc::components.field
                type="datetime"
                id="{{ $attribute->code }}"
                name="{{ $attribute->code }}"
                label="{{ $label }}"
                value="{{ old($attribute->code) ?? $value }}"
                rules="{{ $validations }}|regex:^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$"
                class="mb-2.5 w-full"
            />
        @else
            <x-adminc::components.field
                type="{{ $attribute->type }}"
                id="{{ $attribute->code }}"
                name="{{ $attribute->code }}"
                label="{{ $label }}"
                value="{{ old($attribute->code) ?? $value }}"
                rules="{{ $validations }}"
                class="mb-2.5 w-full"
            />
        @endif
    @else
        <x-admin::form.control-group class="mb-2.5 w-full">
            @if (isset($attribute))
                <x-admin::attributes.edit.index
                    :attribute="$attribute"
                    :validations="$validations"
                    :value="$value"
                />
            @endif
            <x-admin::form.control-group.label
                for="{{ $attribute->code }}"
                :class="$attribute->is_required ? 'required' : ''"
            >
                {{ $attribute->name }}

                @if ($attribute->type == 'price')
                    <span class="currency-code">({{ core()->currencySymbol(config('app.currency')) }})</span>
                @endif
            </x-admin::form.control-group.label>
            <x-admin::form.control-group.error :control-name="$attribute->code" />
        </x-admin::form.control-group>
    @endif
@endforeach
