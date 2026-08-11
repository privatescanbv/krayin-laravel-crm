<?php

use App\Enums\Departments;

test('key returns the documented wire keys', function () {
    expect(Departments::PRIVATESCAN->key())->toBe('privatescan')
        ->and(Departments::HERNIA->key())->toBe('herniapoli');
});

test('fromKey is the exact inverse of key for every case', function () {
    foreach (Departments::cases() as $case) {
        expect(Departments::fromKey($case->key()))->toBe($case)
            ->and(Departments::tryFromKey($case->key()))->toBe($case);
    }
});

test('tryFromKey returns null for an unknown key', function () {
    // 'hernia' is de sleutelruimte van de clinic guide / order-editor, niet die van deze enum.
    expect(Departments::tryFromKey('hernia'))->toBeNull()
        ->and(Departments::tryFromKey('Privatescan'))->toBeNull()
        ->and(Departments::tryFromKey(''))->toBeNull();
});

test('fromKey throws on an unknown key', function () {
    Departments::fromKey('hernia');
})->throws(ValueError::class, 'Unknown department key: hernia');
