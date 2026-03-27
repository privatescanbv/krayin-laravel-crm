<?php

use App\Models\Address;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->user = User::factory()->active()->create();
});

test('submitting empty address fields via HTTP clears the address', function () {
    $lead = Lead::factory()->withAddress()->create([
        'user_id' => $this->user->id,
    ]);

    $originalAddressId = $lead->address_id;
    $this->assertNotNull($originalAddressId);

    $response = $this->actingAs($this->user, 'user')
        ->put(route('admin.leads.update', $lead->id), [
            'first_name' => $lead->first_name ?? 'Test',
            'last_name'  => $lead->last_name ?? 'Lead',
            'address'    => [
                '_clear'              => '0',
                'street'              => '',
                'house_number'        => '',
                'house_number_suffix' => '',
                'postal_code'         => '',
                'city'                => '',
                'state'               => '',
                'country'             => '',
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $lead->refresh();
    $this->assertNull($lead->address_id);
    $this->assertDatabaseMissing('addresses', ['id' => $originalAddressId]);
});

test('submitting _clear=1 via HTTP deletes the address', function () {
    $lead = Lead::factory()->withAddress()->create([
        'user_id' => $this->user->id,
    ]);

    $originalAddressId = $lead->address_id;
    $this->assertNotNull($originalAddressId);

    $response = $this->actingAs($this->user, 'user')
        ->put(route('admin.leads.update', $lead->id), [
            'first_name' => $lead->first_name ?? 'Test',
            'last_name'  => $lead->last_name ?? 'Lead',
            'address'    => [
                '_clear' => '1',
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $lead->refresh();
    $this->assertNull($lead->address_id);
    $this->assertDatabaseMissing('addresses', ['id' => $originalAddressId]);
});
