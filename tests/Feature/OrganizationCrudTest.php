<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Webkul\Contact\Models\Organization;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('organizations index returns datagrid json', function () {
    $user = auth()->guard('user')->user();
    $org1 = Organization::factory()->create(['user_id' => $user->id]);
    $org2 = Organization::factory()->create(['user_id' => $user->id]);

    $response = $this->getJson(route('admin.contacts.organizations.index'), [
        'X-Requested-With' => 'XMLHttpRequest'
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'records',
        'meta'
    ]);

    $ids = getDatagridIds($response);
    expect($ids)->toContain($org1->id, $org2->id);
});

test('can create organization with address', function () {
    $payload = [
        'name'    => 'Test Organization',
        'address' => [
            'postal_code'         => '1234 AB',
            'house_number'        => '123',
            'house_number_suffix' => 'A',
            'street'              => 'Teststraat',
            'city'                => 'Amsterdam',
            'state'               => 'Noord-Holland',
            'country'             => 'Nederland',
        ],
    ];

    $response = $this->postJson(route('admin.contacts.organizations.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('organizations', [
        'name' => 'Test Organization',
    ]);

    // Verify address is created
    $organization = Organization::where('name', 'Test Organization')->first();
    expect($organization->address)->not->toBeNull()
        ->and($organization->address->postal_code)->toBe('1234AB')
        ->and($organization->address->house_number)->toBe('123')
        ->and($organization->address->street)->toBe('Teststraat')
        ->and($organization->address->city)->toBe('Amsterdam')
        ->and($organization->address->state)->toBe('Noord-Holland')
        ->and($organization->address->country)->toBe('Nederland');
});

test('can create organization via ajax', function () {
    $payload = [
        'name'    => 'Test Organization AJAX',
        'address' => [
            'postal_code'  => '5678 CD',
            'house_number' => '456',
            'street'       => 'AJAX Straat',
            'city'         => 'Utrecht',
            'state'        => 'Utrecht',
            'country'      => 'Nederland',
        ],
    ];

    $response = $this->postJson(route('admin.contacts.organizations.store'), $payload, [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
            ],
            'message',
        ]);

    $this->assertDatabaseHas('organizations', [
        'name' => 'Test Organization AJAX',
    ]);
});

test('can update organization with address', function () {
    $user = auth()->guard('user')->user();
    $organization = Organization::factory()->create(['user_id' => $user->id]);
    $address = Address::factory()->create(['organization_id' => $organization->id]);

    $payload = [
        'name'    => 'Updated Organization',
        'address' => [
            'postal_code'         => '9999 ZZ',
            'house_number'        => '999',
            'house_number_suffix' => 'B',
            'street'              => 'Updated Straat',
            'city'                => 'Rotterdam',
            'state'               => 'Zuid-Holland',
            'country'             => 'Nederland',
        ],
        '_method' => 'put',
    ];

    $response = $this->postJson(route('admin.contacts.organizations.update', ['id' => $organization->id]), $payload, [
        'X-Requested-With' => 'XMLHttpRequest'
    ]);
    $response->assertOk();

    $this->assertDatabaseHas('organizations', [
        'id'   => $organization->id,
        'name' => 'Updated Organization',
    ]);

    // Verify address is updated
    $organization->refresh();
    expect($organization->address)->not->toBeNull()
        ->and($organization->address->postal_code)->toBe('9999ZZ')
        ->and($organization->address->house_number)->toBe('999')
        ->and($organization->address->street)->toBe('Updated Straat')
        ->and($organization->address->city)->toBe('Rotterdam');
});

test('can update organization and remove address', function () {
    $user = auth()->guard('user')->user();
    $organization = Organization::factory()->create(['user_id' => $user->id]);
    $address = Address::factory()->create(['organization_id' => $organization->id]);

    $payload = [
        'name'    => 'Organization Without Address',
        'address' => [
            'postal_code'  => '',
            'house_number' => '',
            'street'       => '',
            'city'         => '',
            'state'        => '',
            'country'      => '',
        ],
        '_method' => 'put',
    ];

    $response = $this->postJson(route('admin.contacts.organizations.update', ['id' => $organization->id]), $payload, [
        'X-Requested-With' => 'XMLHttpRequest'
    ]);
    $response->assertOk();

    $this->assertDatabaseHas('organizations', [
        'id'   => $organization->id,
        'name' => 'Organization Without Address',
    ]);

    // Verify address is removed
    $organization->refresh();
    expect($organization->address)->toBeNull();
});

test('can delete organization', function () {
    $user = auth()->guard('user')->user();
    $organization = Organization::factory()->create(['user_id' => $user->id]);

    $response = $this->deleteJson(route('admin.contacts.organizations.delete', ['id' => $organization->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('organizations', [
        'id' => $organization->id,
    ]);
});

test('can search organizations', function () {
    $user = auth()->guard('user')->user();
    $org1 = Organization::factory()->create(['name' => 'Test Company A', 'user_id' => $user->id]);
    $org2 = Organization::factory()->create(['name' => 'Test Company B', 'user_id' => $user->id]);
    $org3 = Organization::factory()->create(['name' => 'Different Company', 'user_id' => $user->id]);

    $response = $this->getJson(route('admin.contacts.organizations.search', ['search' => 'Test Company']));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'name',
                'address',
                'created_at',
                'updated_at'
            ]
        ]
    ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    $names = collect($data)->pluck('name')->toArray();
    expect($names)->toContain('Test Company A', 'Test Company B');
    expect($names)->not->toContain('Different Company');
});

test('validates required name field', function () {
    $payload = [
        'name' => '',
    ];

    $response = $this->postJson(route('admin.contacts.organizations.store'), $payload, [
        'X-Requested-With' => 'XMLHttpRequest'
    ]);
    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'Name is required'
    ]);
});

test('validates max length for name field', function () {
    $payload = [
        'name' => str_repeat('A', 101), // Exceeds max length of 100
    ];

    $response = $this->postJson(route('admin.contacts.organizations.store'), $payload, [
        'X-Requested-With' => 'XMLHttpRequest'
    ]);
    $response->assertStatus(200);
    $response->assertJson([
        'success' => true
    ]);
});

test('organization has audit trail fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $payload = [
        'name'    => 'Audit Trail Organization',
        'address' => [
            'postal_code'  => '1234 AB',
            'house_number' => '123',
            'street'       => 'Teststraat',
            'city'         => 'Amsterdam',
            'state'        => 'Noord-Holland',
            'country'      => 'Nederland',
        ],
    ];

    $response = $this->postJson(route('admin.contacts.organizations.store'), $payload);
    $response->assertOk();

    $organization = Organization::where('name', 'Audit Trail Organization')->first();
    expect($organization->created_by)->toBe($user->id);
    expect($organization->updated_by)->toBe($user->id);
});

test('can mass delete organizations', function () {
    $user = auth()->guard('user')->user();
    $org1 = Organization::factory()->create(['user_id' => $user->id]);
    $org2 = Organization::factory()->create(['user_id' => $user->id]);
    $org3 = Organization::factory()->create(['user_id' => $user->id]);

    $payload = [
        'indices' => [$org1->id, $org2->id],
    ];

    $response = $this->putJson(route('admin.contacts.organizations.mass_delete'), $payload);
    $response->assertOk();

    $this->assertDatabaseMissing('organizations', [
        'id' => $org1->id,
    ]);
    $this->assertDatabaseMissing('organizations', [
        'id' => $org2->id,
    ]);
    $this->assertDatabaseHas('organizations', [
        'id' => $org3->id,
    ]);
});
