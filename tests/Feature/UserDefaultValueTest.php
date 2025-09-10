<?php

use Webkul\User\Models\User;
use Webkul\User\Models\UserDefaultValue;

it('can create default values for a user', function () {
    $user = User::factory()->create();

    test()->actingAs($user, 'user');

    $record = UserDefaultValue::create([
        'user_id' => $user->id,
        'key'     => 'lead.department_id',
        'value'   => '2',
    ]);

    expect($record->exists)->toBeTrue()
        ->and($record->user_id)->toBe($user->id)
        ->and($record->key)->toBe('lead.department_id')
        ->and($record->value)->toBe('2')
        ->and($record->created_by)->toBe($user->id)
        ->and($record->updated_by)->toBe($user->id);
});

it('updates existing default value for same key per user', function () {
    $user = User::factory()->create();

    test()->actingAs($user, 'user');

    $first = UserDefaultValue::create([
        'user_id' => $user->id,
        'key'     => 'lead.department_id',
        'value'   => '2',
    ]);

    $updated = UserDefaultValue::updateOrCreate([
        'user_id' => $user->id,
        'key'     => 'lead.department_id',
    ], [
        'value'   => '3',
    ]);

    expect($updated->id)->toBe($first->id)
        ->and($updated->value)->toBe('3');
});
