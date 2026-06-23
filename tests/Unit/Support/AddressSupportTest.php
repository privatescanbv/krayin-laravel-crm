<?php

use App\Models\Address;
use App\Support\AddressSupport;

test('strict required fields exclude postal code', function () {
    expect(AddressSupport::strictRequiredFields())->toBe([
        'house_number',
        'street',
        'city',
        'country',
    ]);
});

test('isMissingPostcode is true when address has data but no postal code', function () {
    $address = new Address([
        'street'       => 'Teststraat',
        'house_number' => '10',
        'city'         => 'Amsterdam',
        'country'      => 'Nederland',
        'postal_code'  => null,
    ]);

    expect(AddressSupport::isMissingPostcode($address))->toBeTrue()
        ->and(AddressSupport::warnings($address))->toBe([AddressSupport::WARNING_MISSING_POSTCODE]);
});

test('isMissingPostcode is false when postal code is present', function () {
    $address = new Address([
        'street'       => 'Teststraat',
        'house_number' => '10',
        'postal_code'  => '1234AB',
        'city'         => 'Amsterdam',
    ]);

    expect(AddressSupport::isMissingPostcode($address))->toBeFalse()
        ->and(AddressSupport::warnings($address))->toBe([]);
});

test('buildEmailVariables returns fallback for missing address', function () {
    $vars = AddressSupport::buildEmailVariables(null);

    expect($vars['address_line1'])->toBe('')
        ->and($vars['address_city'])->toBe('')
        ->and($vars['address_full'])->toBe('<span style="display:block;">Geen adres bekend</span>');
});

test('buildEmailVariables formats complete address', function () {
    $address = new Address([
        'street'              => 'Dorpsstraat',
        'house_number'        => '10',
        'house_number_suffix' => 'A',
        'postal_code'         => '1234AB',
        'city'                => 'Amsterdam',
        'country'             => 'Nederland',
    ]);

    $vars = AddressSupport::buildEmailVariables($address);

    expect($vars['address_line1'])->toBe('Dorpsstraat 10 A')
        ->and($vars['address_line2'])->toBe('1234 AB Amsterdam')
        ->and($vars['address_postal_code'])->toBe('1234 AB')
        ->and($vars['address_full'])->toContain('Dorpsstraat 10 A')
        ->and($vars['address_full'])->toContain('1234 AB Amsterdam');
});

test('buildEmailVariables formats address without postal code', function () {
    $address = new Address([
        'street'       => 'Buitenweg',
        'house_number' => '5',
        'city'         => 'Utrecht',
        'country'      => 'Nederland',
        'postal_code'  => null,
    ]);

    $vars = AddressSupport::buildEmailVariables($address);

    expect($vars['address_postal_code'])->toBe('')
        ->and($vars['address_line2'])->toBe('Utrecht')
        ->and($vars['address_full'])->toContain('Buitenweg 5')
        ->and($vars['address_full'])->toContain('Utrecht');
});

test('formatFull shows city without postal code', function () {
    $address = new Address([
        'street'       => 'Buitenweg',
        'house_number' => '5',
        'city'         => 'Utrecht',
    ]);

    expect(AddressSupport::formatFull($address))->toBe('Buitenweg 5, Utrecht');
});
