<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

test('an admin exception is not logged again under a fixed catch-all message', function () {
    config(['app.debug' => false]);
    Route::get('/_test/admin-boom', fn () => throw new \RuntimeException('boom'));

    Log::spy();

    $this->get('/_test/admin-boom')->assertStatus(500);

    // render() must not add fixed-message errors: "Admin exception occurred" / "General error in admin"
    // lumped every admin error into one Bugsink issue. report() (Sentry + bootstrap/app.php) covers it.
    Log::shouldNotHaveReceived('error');
});
