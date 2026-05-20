<?php

use App\Http\Controllers\Admin\Inkoop\InkoopStep1Controller;

beforeEach(function () {
    $this->controller = new InkoopStep1Controller;
});

test('getSearchLastName extracts the last part of a hyphenated last name', function () {
    $lastname = 'Hoogendijk- van Aalen';
    $expected = 'Aalen';

    $result = $this->controller->getSearchLastName($lastname);

    expect($result)->toBe($expected);
});

test('getSearchLastName extracts the last part of a hyphenated last name with multiple prefix words', function () {
    $lastname = 'Hiltemann - van den Boogaard';
    $expected = 'Boogaard';

    $result = $this->controller->getSearchLastName($lastname);

    expect($result)->toBe($expected);
});
