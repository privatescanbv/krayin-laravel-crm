<?php

use Illuminate\Support\Facades\Http;

const MB_DEV = 'https://mb-dev.test';

beforeEach(function () {
    config()->set('services.metabase.environments', [
        'dev' => ['url' => MB_DEV, 'api_key' => 'src-key'],
    ]);
});

it('publishes each configured dashboard as a guest embed without rewriting dashcards', function () {
    Http::fake([
        MB_DEV.'/api/setting/enable-embedding-simple' => Http::response('true', 200),
        MB_DEV.'/api/setting/enable-embedding-static' => Http::response('true', 200),
        MB_DEV.'/api/setting/enable-embedding'        => Http::response('true', 200),
        MB_DEV.'/api/dashboard/3'                     => Http::response([
            'id'               => 3,
            'name'             => 'Leads per maand',
            'enable_embedding' => false,
            'parameters'       => [
                ['slug' => 'afdeling'],
                ['slug' => 'campagne'],
                ['slug' => 'leadbron'],
                ['slug' => 'maand'],
                ['slug' => 'periode'],
            ],
        ], 200),
        MB_DEV.'/api/dashboard'                       => Http::response([
            ['id' => 3, 'name' => 'Leads per maand'],
            ['id' => 5, 'name' => 'Verloren leads'],
        ], 200),
        MB_DEV.'/api/session/properties'              => Http::response([
            'embedding-secret-key' => 'real-embed-secret',
            'version'              => ['tag' => 'v0.62.0'],
        ], 200),
    ]);

    $this->artisan('metabase:enable-embeds --environment=dev')
        ->assertSuccessful()
        ->expectsOutputToContain('enable-embedding-simple')
        ->expectsOutputToContain('Published guest embed for dashboard 3')
        ->expectsOutputToContain('dashboard_id => 5')
        ->expectsOutputToContain('embedding secret is set');

    Http::assertSent(function ($request) {
        if ($request->method() !== 'PUT' || ! str_ends_with($request->url(), '/api/dashboard/3')) {
            return false;
        }

        $payload = $request->data();

        return ($payload['enable_embedding'] ?? false) === true
            && ! array_key_exists('dashcards', $payload)
            && ($payload['embedding_params']['periode'] ?? null) === 'enabled'
            && ($payload['embedding_params']['afdeling'] ?? null) === 'enabled';
    });
});

it('falls back to enable-embedding when the static setting is unknown', function () {
    Http::fake([
        MB_DEV.'/api/setting/enable-embedding-simple'  => Http::response(['message' => 'not found'], 404),
        MB_DEV.'/api/setting/enable-embedding-static'  => Http::response(['message' => 'not found'], 404),
        MB_DEV.'/api/setting/enable-embedding'         => Http::response('true', 200),
        MB_DEV.'/api/dashboard/3'                      => Http::response([
            'id'         => 3,
            'name'       => 'Leads per maand',
            'parameters' => [],
        ], 200),
        MB_DEV.'/api/dashboard'                       => Http::response([], 200),
        MB_DEV.'/api/session/properties'              => Http::response([
            'embedding-secret-key' => 'real-embed-secret',
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

it('skips republishing a dashboard that already has matching guest embed settings', function () {
    Http::fake([
        MB_DEV.'/api/setting/enable-embedding-simple' => Http::response('true', 200),
        MB_DEV.'/api/setting/enable-embedding-static' => Http::response('true', 200),
        MB_DEV.'/api/setting/enable-embedding'        => Http::response('true', 200),
        MB_DEV.'/api/dashboard/3'                     => Http::response([
            'id'               => 3,
            'name'             => 'Leads per maand',
            'enable_embedding' => true,
            'embedding_params' => [
                'periode'  => 'enabled',
                'afdeling' => 'enabled',
                'campagne' => 'enabled',
                'leadbron' => 'enabled',
                'maand'    => 'enabled',
            ],
            'parameters'       => [
                ['slug' => 'afdeling'],
                ['slug' => 'campagne'],
                ['slug' => 'leadbron'],
                ['slug' => 'maand'],
                ['slug' => 'periode'],
            ],
        ], 200),
        MB_DEV.'/api/dashboard'                       => Http::response([
            ['id' => 3, 'name' => 'Leads per maand'],
        ], 200),
        MB_DEV.'/api/session/properties'              => Http::response([
            'embedding-secret-key' => 'real-embed-secret',
        ], 200),
    ]);

    $this->artisan('metabase:enable-embeds --environment=dev')
        ->assertSuccessful()
        ->expectsOutputToContain('already published as a guest embed');

    Http::assertNotSent(fn ($request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/api/dashboard/3'));
});

it('publishes configured locked filters instead of enabling every slug', function () {
    config(['metabase_dashboards' => [[
        'key'              => 'dashboard',
        'name'             => 'Dashboard',
        'path'             => 'dashboard',
        'dashboard_id'     => 3,
        'params'           => ['periode' => 'past6months'],
        'embedding_params' => [
            'periode'  => 'enabled',
            'afdeling' => 'locked',
        ],
    ]]]);

    Http::fake([
        MB_DEV.'/api/setting/enable-embedding-simple' => Http::response('true', 200),
        MB_DEV.'/api/setting/enable-embedding-static' => Http::response('true', 200),
        MB_DEV.'/api/setting/enable-embedding'        => Http::response('true', 200),
        MB_DEV.'/api/dashboard/3'                     => Http::response([
            'id'         => 3,
            'parameters' => [
                ['slug' => 'periode'],
                ['slug' => 'afdeling'],
            ],
        ], 200),
        MB_DEV.'/api/dashboard'                       => Http::response([], 200),
        MB_DEV.'/api/session/properties'              => Http::response([
            'embedding-secret-key' => 'real-embed-secret',
        ], 200),
    ]);

    $this->artisan('metabase:enable-embeds --environment=dev')
        ->assertSuccessful()
        ->expectsOutputToContain('Published guest embed for dashboard 3');

    Http::assertSent(function ($request) {
        if ($request->method() !== 'PUT' || ! str_ends_with($request->url(), '/api/dashboard/3')) {
            return false;
        }

        $payload = $request->data();

        return ($payload['embedding_params']['periode'] ?? null) === 'enabled'
            && ($payload['embedding_params']['afdeling'] ?? null) === 'locked';
    });
});
