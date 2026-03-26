<?php

use App\Services\ContactValidationRules;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    // Force Dutch locale so lang/nl/validation.php messages are used.
    app()->setLocale('nl');
});

// ---------------------------------------------------------------------------
// Happy path: address is fully filled or fully empty
// ---------------------------------------------------------------------------

test('complete address with all required fields passes validation', function () {
    $data = [
        'address' => [
            'postal_code'  => '1234AB',
            'house_number' => '10',
            'street'       => 'Teststraat',
            'city'         => 'Amsterdam',
            'country'      => 'Nederland',
        ],
    ];

    $validator = Validator::make($data, ContactValidationRules::strictAddressRules());

    expect($validator->passes())->toBeTrue();
});

test('complete address with all optional fields also passes validation', function () {
    $data = [
        'address' => [
            'postal_code'         => '1234AB',
            'house_number'        => '10',
            'house_number_suffix' => 'A',
            'street'              => 'Teststraat',
            'city'                => 'Amsterdam',
            'state'               => 'Noord-Holland',
            'country'             => 'Nederland',
        ],
    ];

    $validator = Validator::make($data, ContactValidationRules::strictAddressRules());

    expect($validator->passes())->toBeTrue();
});

test('completely empty address passes validation (address is optional)', function () {
    $data = [
        'address' => [
            'postal_code'         => '',
            'house_number'        => '',
            'house_number_suffix' => '',
            'street'              => '',
            'city'                => '',
            'state'               => '',
            'country'             => '',
        ],
    ];

    $validator = Validator::make($data, ContactValidationRules::strictAddressRules());

    expect($validator->passes())->toBeTrue();
});

test('absent address block passes validation (address is optional)', function () {
    $validator = Validator::make([], ContactValidationRules::strictAddressRules());

    expect($validator->passes())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Failure path: partial address triggers required_with
// ---------------------------------------------------------------------------

test('only postal_code filled makes house_number, street, city, country required', function () {
    $data = [
        'address' => [
            'postal_code' => '1234AB',
        ],
    ];

    $validator = Validator::make($data, ContactValidationRules::strictAddressRules());

    expect($validator->fails())->toBeTrue();

    $errors = $validator->errors();
    expect($errors->has('address.house_number'))->toBeTrue();
    expect($errors->has('address.street'))->toBeTrue();
    expect($errors->has('address.city'))->toBeTrue();
    expect($errors->has('address.country'))->toBeTrue();
    // Optional fields must NOT appear in errors
    expect($errors->has('address.state'))->toBeFalse();
    expect($errors->has('address.house_number_suffix'))->toBeFalse();
});

test('missing city and country fails when other required fields are filled', function () {
    $data = [
        'address' => [
            'postal_code'  => '1234AB',
            'house_number' => '10',
            'street'       => 'Teststraat',
            // city and country missing
        ],
    ];

    $validator = Validator::make($data, ContactValidationRules::strictAddressRules());

    expect($validator->fails())->toBeTrue();

    $errors = $validator->errors();
    expect($errors->has('address.city'))->toBeTrue();
    expect($errors->has('address.country'))->toBeTrue();
    // Already-filled fields must NOT appear in errors
    expect($errors->has('address.postal_code'))->toBeFalse();
    expect($errors->has('address.house_number'))->toBeFalse();
    expect($errors->has('address.street'))->toBeFalse();
});

test('missing only country fails when other required fields are filled', function () {
    $data = [
        'address' => [
            'postal_code'  => '1234AB',
            'house_number' => '10',
            'street'       => 'Teststraat',
            'city'         => 'Amsterdam',
            // country missing
        ],
    ];

    $validator = Validator::make($data, ContactValidationRules::strictAddressRules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('address.country'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Dutch error messages
// ---------------------------------------------------------------------------

test('required_with error messages are in Dutch', function () {
    $data = [
        'address' => [
            'postal_code' => '1234AB',
            // all others missing → each should produce its custom Dutch message
        ],
    ];

    $validator = Validator::make($data, ContactValidationRules::strictAddressRules());

    $errors = $validator->errors();

    expect($errors->first('address.house_number'))->toBe('Huisnummer is verplicht als je een adres invult.');
    expect($errors->first('address.street'))->toBe('Straat is verplicht als je een adres invult.');
    expect($errors->first('address.city'))->toBe('Stad is verplicht als je een adres invult.');
    expect($errors->first('address.country'))->toBe('Land is verplicht als je een adres invult.');
});
