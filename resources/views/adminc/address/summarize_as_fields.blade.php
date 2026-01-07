@props(['address'])
<!-- Straat en huisnummer -->
<x-adminc::components.field
    label="Straat en huisnummer"
    value="{{ $address ? ($address->street . ' ' . $address->house_number . ($address->house_number_suffix ? '-' . $address->house_number_suffix : '')) : '' }}"
    readonly />

<!-- Postcode -->
<x-adminc::components.field
    label="Postcode"
    value="{{ $address->postal_code ?? '' }}"
    readonly />

<!-- Woonplaats -->
<x-adminc::components.field
    label="Woonplaats"
    value="{{ $address->city ?? '' }}"
    readonly />

<!-- Land -->
<x-adminc::components.field
    label="Land"
    value="{{ $address->country ?? '' }}"
    readonly />
