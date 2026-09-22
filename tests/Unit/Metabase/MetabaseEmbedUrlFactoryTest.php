<?php

use App\Services\Metabase\MetabaseEmbedException;
use App\Services\Metabase\MetabaseEmbedUrlFactory;

function decodeMetabaseJwt(string $url): array
{
    expect($url)->toContain('/embed/dashboard/');

    $path = parse_url($url, PHP_URL_PATH);
    $token = basename((string) $path);
    $parts = explode('.', $token);

    expect($parts)->toHaveCount(3);

    $payload = json_decode(metabaseJwtBase64UrlDecode($parts[1]), true, 512, JSON_THROW_ON_ERROR);
    $header = json_decode(metabaseJwtBase64UrlDecode($parts[0]), true, 512, JSON_THROW_ON_ERROR);

    expect($header['alg'])->toBe('HS256');

    $signature = hash_hmac('sha256', $parts[0].'.'.$parts[1], (string) config('services.metabase.embed.secret'), true);
    $expected = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    expect($parts[2])->toBe($expected);

    return $payload;
}

function metabaseJwtBase64UrlDecode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'), true);
}

it('signs an embed url for dashboard 3 with the default periode', function () {
    $url = (new MetabaseEmbedUrlFactory)->forDashboard(3, ['periode' => 'past6months'], 1_700_000_000);

    expect($url)
        ->toStartWith('https://reports.example.test/embed/dashboard/')
        ->toEndWith('#bordered=false&titled=true&background=false');

    $payload = decodeMetabaseJwt($url);

    expect($payload['resource'])->toBe(['dashboard' => 3])
        ->and($payload['params'])->toBe(['periode' => 'past6months'])
        ->and($payload['exp'])->toBe(1_700_000_000);
});

it('encodes empty params as a json object not an array', function () {
    $url = (new MetabaseEmbedUrlFactory)->forDashboard(3, [], 1_700_000_000);
    $path = parse_url($url, PHP_URL_PATH);
    $token = basename((string) $path);
    $json = metabaseJwtBase64UrlDecode(explode('.', $token)[1]);

    expect($json)->toContain('"params":{}')
        ->not->toContain('"params":[]');
});

it('omits empty filter values from the jwt', function () {
    $url = (new MetabaseEmbedUrlFactory)->forDashboard(3, [
        'periode'  => 'past6months',
        'afdeling' => '',
        'campagne' => null,
    ], 1_700_000_000);

    expect(decodeMetabaseJwt($url)['params'])->toBe(['periode' => 'past6months']);
});

it('fails when the embedding secret is missing', function () {
    config(['services.metabase.embed.secret' => '']);

    (new MetabaseEmbedUrlFactory)->forDashboard(3);
})->throws(MetabaseEmbedException::class, 'METABASE_EMBEDDING_SECRET_KEY');
