<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ValueNormalizer;
use Carbon\Carbon;

test('formatValueForDisplay formats emails array', function () {
    $normalizer = new ValueNormalizer;

    $result = $normalizer->formatValueForDisplay([
        ['value' => 'a@example.com', 'label' => 'werk'],
        ['value' => 'b@example.com', 'label' => 'prive'],
    ], 'emails');

    expect($result)->toBe('a@example.com, b@example.com');
});

test('formatValueForDisplay formats phones array with plain strings', function () {
    $normalizer = new ValueNormalizer;

    $result = $normalizer->formatValueForDisplay(['+31612345678', '+31687654321'], 'phones');

    expect($result)->toBe('+31612345678, +31687654321');
});

test('formatValueForDisplay returns placeholder for empty emails array', function () {
    $normalizer = new ValueNormalizer;

    expect($normalizer->formatValueForDisplay([], 'emails'))->toBe('Geen waarde');
});

test('formatValueForDisplay formats date_of_birth strings', function () {
    $normalizer = new ValueNormalizer;

    expect($normalizer->formatValueForDisplay('1980-08-03 00:00:00', 'date_of_birth'))->toBe('1980-08-03');
});

test('formatValueForDisplay formats date_of_birth Carbon instances', function () {
    $normalizer = new ValueNormalizer;

    expect($normalizer->formatValueForDisplay(Carbon::create(1980, 8, 3), 'date_of_birth'))->toBe('1980-08-03');
});

test('formatValueForDisplay returns placeholder for invalid date_of_birth', function () {
    $normalizer = new ValueNormalizer;

    expect($normalizer->formatValueForDisplay('0000-00-00', 'date_of_birth'))->toBe('Geen waarde');
    expect($normalizer->formatValueForDisplay('not-a-date', 'date_of_birth'))->toBe('Geen waarde');
});

test('formatValueForDisplay returns placeholder for null', function () {
    $normalizer = new ValueNormalizer;

    expect($normalizer->formatValueForDisplay(null, 'name'))->toBe('Geen waarde');
});
