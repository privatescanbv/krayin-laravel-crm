<?php

use App\Support\NameSimilarity;

test('first names match exactly ignoring case and accents', function () {
    expect(NameSimilarity::firstNamesAreSimilar('Désirée', 'Desiree'))->toBeTrue()
        ->and(NameSimilarity::firstNamesAreSimilar('ANNA', 'anna'))->toBeTrue();
});

test('first names match when one is a token of the other', function () {
    expect(NameSimilarity::firstNamesAreSimilar('Anna Maria', 'Anna'))->toBeTrue()
        ->and(NameSimilarity::firstNamesAreSimilar('Anna', 'Anna Maria'))->toBeTrue();
});

test('first names match with a small typo on the first token', function () {
    expect(NameSimilarity::firstNamesAreSimilar('Marie', 'Maria'))->toBeTrue();
});

test('first names do not match nicknames or unrelated names', function () {
    expect(NameSimilarity::firstNamesAreSimilar('Jan', 'Johannes'))->toBeFalse()
        ->and(NameSimilarity::firstNamesAreSimilar('Anna', 'Annabelle'))->toBeFalse()
        ->and(NameSimilarity::firstNamesAreSimilar('Linda', 'Piet'))->toBeFalse();
});

test('blank first names are not similar', function () {
    expect(NameSimilarity::firstNamesAreSimilar(null, 'Anna'))->toBeFalse()
        ->and(NameSimilarity::firstNamesAreSimilar('Anna', ''))->toBeFalse()
        ->and(NameSimilarity::isBlank('  '))->toBeTrue();
});
