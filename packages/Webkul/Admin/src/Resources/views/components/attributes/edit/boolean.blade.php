<?php $selectedOption = old($attribute->code) ?: $value ?>

<x-adminc::components.field
    type="switch"
    name="{{ $attribute->code }}"
    id="{{ $attribute->code }}"
    value="1"
    :checked="(bool) $selectedOption"
/>
