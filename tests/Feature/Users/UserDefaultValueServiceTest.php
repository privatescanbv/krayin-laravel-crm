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
