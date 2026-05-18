<?php

namespace Tests\Feature\Admin;

use App\Services\PostcodeApiService;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('address lookup response includes netherlands as country', function () {
    $this->mock(PostcodeApiService::class, function ($mock) {
        $mock->shouldReceive('lookup')
            ->once()
            ->with('1234AB', 10)
            ->andReturn([
                'street'   => 'Damrak',
                'city'     => 'Amsterdam',
                'province' => 'Noord-Holland',
            ]);
    });

    $response = $this->getJson('/admin/address/lookup?postcode=1234AB&huisnummer=10');

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'street'  => 'Damrak',
        'city'    => 'Amsterdam',
        'state'   => 'Noord-Holland',
        'country' => 'Nederland',
    ]);
});
