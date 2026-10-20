<?php

use App\Services\Metabase\DashboardSyncService;
use App\Services\Metabase\MetabaseClient;
use App\Services\Metabase\SyncMapping;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function syncDashboardWithWidth(?string $width): ?array
{
    return syncDashboardFromSource($width ? ['width' => $width] : []);
}

function syncDashboardFromSource(array $extra): ?array
{
    Storage::fake('local');

    $source = ['id' => 9, 'name' => 'Hernia', 'description' => null, 'parameters' => [], 'dashcards' => [], 'tabs' => []]
        + $extra;

    Http::fake([
        'source.test/api/session/properties' => Http::response(['version' => ['tag' => 'v0.62.0']]),
        'target.test/api/session/properties' => Http::response(['version' => ['tag' => 'v0.62.0']]),
        'source.test/api/dashboard/9'        => Http::response($source),
        'target.test/api/dashboard'          => Http::response(['id' => 3]),
        'target.test/api/dashboard/3'        => Http::response(['id' => 3, 'dashcards' => [], 'tabs' => []]),
    ]);

    (new DashboardSyncService(
        new MetabaseClient('https://source.test', 'k', 'dev'),
        new MetabaseClient('https://target.test', 'k', 'prod'),
        new SyncMapping('dev', 'prod'),
        dryRun: false,
    ))->sync(9);

    $put = collect(Http::recorded())->map(fn ($pair) => $pair[0])
        ->first(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/api/dashboard/3'));

    return $put?->data();
}

it('always syncs dashboards as full width, whatever the source has', function (?string $sourceWidth) {
    expect(syncDashboardWithWidth($sourceWidth)['width'])->toBe('full');
})->with(['fixed', 'full', 'no width' => null]);

it('copies embedding settings so the CRM iframe can show the synced dashboard', function () {
    $payload = syncDashboardFromSource([
        'enable_embedding' => true,
        'embedding_params' => ['periode' => 'enabled', 'product' => 'enabled'],
    ]);

    expect($payload['enable_embedding'])->toBeTrue()
        ->and($payload['embedding_params'])->toBe(['periode' => 'enabled', 'product' => 'enabled']);
});

it('leaves target embedding untouched when the source is not embedded', function () {
    $payload = syncDashboardFromSource(['enable_embedding' => false, 'embedding_params' => null]);

    expect($payload)->not->toHaveKey('enable_embedding')->not->toHaveKey('embedding_params');
});
