<?php

use Illuminate\Support\Facades\Http;

const MB_DEV = 'https://mb-dev.test';

beforeEach(function () {
    config()->set('services.metabase.environments', [
        'dev' => ['url' => MB_DEV, 'api_key' => 'src-key'],
    ]);
});

it('enables static embedding globally and per configured dashboard', function () {
    Http::fake([
        MB_DEV.'/api/setting/enable-embedding-static' => Http::response('true', 200),
        MB_DEV.'/api/setting/enable-embedding'        => Http::response('true', 200),
        MB_DEV.'/api/dashboard/3'                     => Http::response([
            'id'         => 3,
            'name'       => 'Leads per maand',
            'parameters' => [
                ['slug' => 'afdeling'],
                ['slug' => 'campagne'],
                ['slug' => 'leadbron'],
                ['slug' => 'maand'],
                ['slug' => 'periode'],
            ],
        ], 200),
    ]);

    $this->artisan('metabase:enable-embeds --environment=dev')
        ->assertSuccessful()
        ->expectsOutputToContain('enable-embedding-static')
        ->expectsOutputToContain('dashboard 3');

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && str_ends_with($request->url(), '/api/setting/enable-embedding-static')
            && $request->body() === 'true';
    });

    Http::assertSent(function ($request) {
        if ($request->method() !== 'PUT' || ! str_ends_with($request->url(), '/api/dashboard/3')) {
            return false;
        }

        $payload = $request->data();

        return ($payload['enable_embedding'] ?? false) === true
            && ($payload['embedding_params']['periode'] ?? null) === 'enabled'
            && ($payload['embedding_params']['afdeling'] ?? null) === 'enabled';
    });
});

it('falls back to enable-embedding when the static setting is unknown', function () {
    Http::fake([
        MB_DEV.'/api/setting/enable-embedding-static' => Http::response(['message' => 'not found'], 404),
        MB_DEV.'/api/setting/enable-embedding'        => Http::response('true', 200),
        MB_DEV.'/api/dashboard/3'                     => Http::response([
            'id'         => 3,
            'name'       => 'Leads per maand',
            'parameters' => [],
        ], 200),
    ]);

    $this->artisan('metabase:enable-embeds --environment=dev')
        ->assertSuccessful()
        ->expectsOutputToContain('enable-embedding');
});

it('fails when the environment is not configured', function () {
    $this->artisan('metabase:enable-embeds --environment=prod')
        ->assertFailed()
        ->expectsOutputToContain("Unknown Metabase environment 'prod'");
});
