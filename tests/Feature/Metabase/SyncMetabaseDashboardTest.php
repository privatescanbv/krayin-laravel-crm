<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const MB_SOURCE = 'https://mb-dev.test';
const MB_TARGET = 'https://mb-prod.test';

beforeEach(function () {
    Storage::fake('local');

    config()->set('services.metabase.environments', [
        'dev'  => ['url' => MB_SOURCE, 'api_key' => 'src-key'],
        'prod' => ['url' => MB_TARGET, 'api_key' => 'dst-key'],
    ]);
});

/**
 * Route table for a minimal but realistic Metabase pair. Dashboard 12 has one
 * dashcard backed by card 34 (an MBQL question on table "orders", field "status").
 */
function fakeMetabase(array $overrides = []): void
{
    $routes = array_merge([
        'GET '.MB_SOURCE.'/api/session/properties' => ['version' => ['tag' => 'v0.49.6']],
        'GET '.MB_TARGET.'/api/session/properties' => ['version' => ['tag' => 'v0.49.6']],

        'GET '.MB_SOURCE.'/api/dashboard/12' => [
            'id'          => 12,
            'name'        => 'Omzetoverzicht',
            'description' => 'Maandelijkse omzet',
            'parameters'  => [['id' => 'abc123', 'name' => 'Status', 'slug' => 'status', 'type' => 'string/=']],
            'tabs'        => [],
            'dashcards'   => [[
                'id'                     => 1,
                'card_id'                => 34,
                'row'                    => 0,
                'col'                    => 0,
                'size_x'                 => 12,
                'size_y'                 => 8,
                'series'                 => [],
                'parameter_mappings'     => [[
                    'parameter_id' => 'abc123',
                    'card_id'      => 34,
                    'target'       => ['dimension', ['field', 100, null]],
                ]],
                'visualization_settings' => [],
            ]],
        ],

        'GET '.MB_SOURCE.'/api/card/34' => [
            'id'                     => 34,
            'name'                   => 'Omzet per status',
            'description'            => null,
            'display'                => 'bar',
            'visualization_settings' => [],
            'dataset_query'          => [
                'database' => 2,
                'type'     => 'query',
                'query'    => [
                    'source-table' => 10,
                    'aggregation'  => [['count']],
                    'breakout'     => [['field', 100, null]],
                ],
            ],
        ],

        'GET '.MB_SOURCE.'/api/database'   => [['id' => 2, 'name' => 'Analytics']],
        'GET '.MB_SOURCE.'/api/table/10'   => ['db_id' => 2, 'name' => 'orders', 'schema' => 'public'],
        'GET '.MB_SOURCE.'/api/field/100'  => ['table_id' => 10, 'name' => 'status'],

        'GET '.MB_TARGET.'/api/database'                  => [['id' => 5, 'name' => 'Analytics']],
        'GET '.MB_TARGET.'/api/database/5/metadata'       => ['tables' => [['id' => 77, 'name' => 'orders', 'schema' => 'public']]],
        'GET '.MB_TARGET.'/api/table/77/query_metadata'   => ['fields' => [['id' => 900, 'name' => 'status']]],

        'POST '.MB_TARGET.'/api/card'        => ['id' => 21],
        'PUT '.MB_TARGET.'/api/card/21'      => ['id' => 21],
        'POST '.MB_TARGET.'/api/dashboard'   => ['id' => 8],
        'GET '.MB_TARGET.'/api/dashboard/8'  => ['id' => 8, 'dashcards' => [], 'tabs' => [], 'parameters' => []],
        'PUT '.MB_TARGET.'/api/dashboard/8'  => [
            'id'        => 8,
            'tabs'      => [],
            'dashcards' => [['id' => 500, 'card_id' => 21, 'row' => 0, 'col' => 0]],
        ],
    ], $overrides);

    Http::fake(function ($request) use ($routes) {
        $key = $request->method().' '.strtok($request->url(), '?');

        if (! array_key_exists($key, $routes)) {
            return Http::response(['message' => "unmocked: {$key}"], 500);
        }

        $value = $routes[$key];

        return is_array($value) ? Http::response($value, 200) : $value;
    });
}

function seedMapping(): void
{
    Storage::disk('local')->put('metabase-sync/dev__prod.json', json_encode([
        'dashboard:12' => ['source' => 'dev', 'target' => 'prod', 'target_id' => 8],
        'card:34'      => ['source' => 'dev', 'target' => 'prod', 'target_id' => 21],
        'dashcard:1'   => ['source' => 'dev', 'target' => 'prod', 'target_id' => 500],
    ]));
}

it('requires --source, --target and --dashboard', function () {
    $this->artisan('metabase:sync-dashboard --source=dev --target=prod')
        ->assertFailed()
        ->expectsOutputToContain('are all required');
});

it('rejects a non-numeric dashboard id', function () {
    $this->artisan('metabase:sync-dashboard --source=dev --target=prod --dashboard=abc')
        ->assertFailed()
        ->expectsOutputToContain('must be a numeric');
});

it('rejects an unknown environment', function () {
    fakeMetabase();

    $this->artisan('metabase:sync-dashboard --source=nope --target=prod --dashboard=12')
        ->assertFailed()
        ->expectsOutputToContain("Unknown Metabase environment 'nope'");
});

it('rejects syncing an environment onto itself', function () {
    fakeMetabase();

    $this->artisan('metabase:sync-dashboard --source=dev --target=dev --dashboard=12')
        ->assertFailed()
        ->expectsOutputToContain('same Metabase instance');
});

it('creates the card and dashboard on a first run and records the mapping', function () {
    fakeMetabase();

    $this->artisan('metabase:sync-dashboard --source=dev --target=prod --dashboard=12 --force')
        ->assertSuccessful();

    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with(strtok($r->url(), '?'), '/api/card'));
    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with(strtok($r->url(), '?'), '/api/dashboard'));

    // MBQL references were retargeted to the target instance ids.
    Http::assertSent(function ($r) {
        if ($r->method() !== 'POST' || ! str_ends_with(strtok($r->url(), '?'), '/api/card')) {
            return false;
        }
        $q = $r->data()['dataset_query'];

        return $q['database'] === 5
            && $q['query']['source-table'] === 77
            && $q['query']['breakout'][0] === ['field', 900, null];
    });

    $mapping = json_decode(Storage::disk('local')->get('metabase-sync/dev__prod.json'), true);
    expect($mapping['dashboard:12']['target_id'])->toBe(8)
        ->and($mapping['card:34']['target_id'])->toBe(21)
        ->and($mapping['dashcard:1']['target_id'])->toBe(500);
});

it('sends empty visualization_settings as a JSON object, not a list', function () {
    fakeMetabase();

    $this->artisan('metabase:sync-dashboard --source=dev --target=prod --dashboard=12 --force')
        ->assertSuccessful();

    // Metabase rejects `[]` here ("Value must be a map").
    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && str_ends_with(strtok($r->url(), '?'), '/api/card')
        && str_contains($r->body(), '"visualization_settings":{}'));
});

it('updates existing objects on a second run without creating duplicates', function () {
    fakeMetabase();
    seedMapping();

    $this->artisan('metabase:sync-dashboard --source=dev --target=prod --dashboard=12 --force')
        ->assertSuccessful();

    Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_ends_with(strtok($r->url(), '?'), '/api/card'));
    Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_ends_with(strtok($r->url(), '?'), '/api/dashboard'));
    Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with(strtok($r->url(), '?'), '/api/card/21'));
    Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with(strtok($r->url(), '?'), '/api/dashboard/8'));
});

it('makes no writes and no mapping file in dry-run', function () {
    fakeMetabase();

    $this->artisan('metabase:sync-dashboard --source=dev --target=prod --dashboard=12 --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('Dry run');

    Http::assertNotSent(fn ($r) => in_array($r->method(), ['POST', 'PUT'], true));
    expect(Storage::disk('local')->exists('metabase-sync/dev__prod.json'))->toBeFalse();
});

it('aborts with a concrete error and no writes when a target database is missing', function () {
    fakeMetabase(['GET '.MB_TARGET.'/api/database' => [['id' => 5, 'name' => 'Some Other DB']]]);

    $this->artisan('metabase:sync-dashboard --source=dev --target=prod --dashboard=12 --force')
        ->assertFailed()
        ->expectsOutputToContain("Database 'Analytics'");

    Http::assertNotSent(fn ($r) => in_array($r->method(), ['POST', 'PUT'], true));
});

it('aborts on a major version mismatch unless --force is given', function () {
    fakeMetabase(['GET '.MB_TARGET.'/api/session/properties' => ['version' => ['tag' => 'v0.52.1']]]);

    $this->artisan('metabase:sync-dashboard --source=dev --target=prod --dashboard=12')
        ->expectsConfirmation('Proceed with the sync?', 'yes')
        ->assertFailed()
        ->expectsOutputToContain('version mismatch');

    Http::assertNotSent(fn ($r) => in_array($r->method(), ['POST', 'PUT'], true));
});

it('does not leak the api key in output', function () {
    fakeMetabase(['GET '.MB_SOURCE.'/api/dashboard/12' => Http::response(['message' => 'boom'], 500)]);

    $this->artisan('metabase:sync-dashboard --source=dev --target=prod --dashboard=12')
        ->assertFailed()
        ->doesntExpectOutputToContain('src-key');
});
