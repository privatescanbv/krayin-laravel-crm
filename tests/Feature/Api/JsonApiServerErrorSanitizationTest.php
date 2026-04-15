<?php

use Illuminate\Support\Facades\Route;

test('JSON API 500 responses omit exception details when debug is disabled', function () {
    config(['app.debug' => false]);

    Route::get('/__test_json_api_server_error', function () {
        throw new Exception('internal detail that must not leak');
    });

    $this->getJson('/__test_json_api_server_error')
        ->assertStatus(500)
        ->assertJsonMissing(['exception', 'trace', 'file', 'line'])
        ->assertJson(['message' => 'Internal Server Error']);
});

test('non-JSON requests under api/* get sanitized JSON 500 when debug is disabled', function () {
    config(['app.debug' => false]);

    Route::get('/__test_json_api_server_error_accept', function () {
        throw new Exception('internal detail');
    });

    $this->get('/__test_json_api_server_error_accept', [
        'Accept' => 'application/json',
    ])
        ->assertStatus(500)
        ->assertJsonMissing(['exception']);
});
