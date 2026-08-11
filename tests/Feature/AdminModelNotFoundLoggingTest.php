<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Webkul\Installer\Http\Middleware\CanInstall;

test('a deleted/missing record in the admin area 404s and logs a warning, not an error', function () {
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');

    Log::spy();

    $response = $this->getJson(route('admin.sales-leads.view', 999999));

    $response->assertStatus(404);

    Log::shouldNotHaveReceived('error');
    Log::shouldHaveReceived('warning')->once();
});
