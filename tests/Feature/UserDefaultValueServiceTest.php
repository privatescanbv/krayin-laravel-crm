<?php

use App\Services\UserDefaultValueService;
use Webkul\User\Models\User;
use Webkul\User\Models\UserDefaultValue;

it('can get lead defaults for a user', function () {
    $user = User::factory()->create();
    $service = new UserDefaultValueService;

    // Create some default values
    UserDefaultValue::create([
        'user_id'    => $user->id,
        'key'        => 'lead.department_id',
        'value'      => '2',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    UserDefaultValue::create([
        'user_id'    => $user->id,
        'key'        => 'lead.lead_channel_id',
        'value'      => '3',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $defaults = $service->getLeadDefaults($user->id);

    expect($defaults)->toHaveKey('department_id')
        ->and($defaults)->toHaveKey('lead_channel_id')
        ->and($defaults['department_id'])->toBe('2')
        ->and($defaults['lead_channel_id'])->toBe('3');
});

it('can set and get a specific default value', function () {
    $user = User::factory()->create();
    $service = new UserDefaultValueService;

    // Set a default value
    $service->setDefault($user->id, 'lead.lead_source_id', '5');

    // Get the default value
    $value = $service->getDefault($user->id, 'lead.lead_source_id');

    expect($value)->toBe('5');
});

it('returns null for non-existent default values', function () {
    $user = User::factory()->create();
    $service = new UserDefaultValueService;

    $value = $service->getDefault($user->id, 'lead.non_existent');

    expect($value)->toBeNull();
});

it('can update existing default values', function () {
    $user = User::factory()->create();
    $service = new UserDefaultValueService;

    // Set initial value
    $service->setDefault($user->id, 'lead.department_id', '2');

    // Update the value
    $service->setDefault($user->id, 'lead.department_id', '3');

    $value = $service->getDefault($user->id, 'lead.department_id');

    expect($value)->toBe('3');

    // Should only have one record
    $count = UserDefaultValue::where('user_id', $user->id)
        ->where('key', 'lead.department_id')
        ->count();

    expect($count)->toBe(1);
});
