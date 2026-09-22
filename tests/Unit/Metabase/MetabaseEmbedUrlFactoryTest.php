<?php

use App\Services\Metabase\MetabaseEmbedException;
use App\Services\Metabase\MetabaseEmbedUrlFactory;
use Illuminate\Support\Facades\Http;

function decodeMetabaseJwt(string $token): array
{
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

it('signs a guest-embed token matching Metabase embed.js (empty params, embedding_params)', function () {
    $token = (new MetabaseEmbedUrlFactory)->token(3, [], [
        'periode'  => 'enabled',
        'afdeling' => 'enabled',
        'campagne' => 'enabled',
        'leadbron' => 'enabled',
        'maand'    => 'enabled',
    ], 1_700_000_000);

    $payload = decodeMetabaseJwt($token);
    $json = metabaseJwtBase64UrlDecode(explode('.', $token)[1]);

    expect($payload['resource'])->toBe(['dashboard' => 3])
        ->and($json)->toContain('"params":{}')
        ->and($payload['exp'])->toBe(1_700_000_000)
        ->and($payload['iat'])->toBe(1_700_000_000 - 600)
        ->and($payload['_embedding_params'])->toBe([
            'periode'  => 'enabled',
            'afdeling' => 'enabled',
            'campagne' => 'enabled',
            'leadbron' => 'enabled',
            'maand'    => 'enabled',
        ]);
});

it('puts locked filter values in the jwt params object', function () {
    $token = (new MetabaseEmbedUrlFactory)->token(3, ['periode' => 'past6months'], [
        'periode' => 'locked',
    ], 1_700_000_000);

    expect(decodeMetabaseJwt($token)['params'])->toBe(['periode' => 'past6months']);
});

it('encodes empty params as a json object not an array', function () {
    $token = (new MetabaseEmbedUrlFactory)->token(3, [], [], 1_700_000_000);
    $json = metabaseJwtBase64UrlDecode(explode('.', $token)[1]);

    expect($json)->toContain('"params":{}')
        ->not->toContain('"params":[]')
        ->not->toContain('_embedding_params');
});

it('omits empty filter values from the jwt', function () {
    $token = (new MetabaseEmbedUrlFactory)->token(3, [
        'periode'  => 'past6months',
        'afdeling' => '',
        'campagne' => null,
    ], [], 1_700_000_000);

    expect(decodeMetabaseJwt($token)['params'])->toBe(['periode' => 'past6months']);
});

it('fails when the embedding secret is missing and Metabase is not configured', function () {
    config([
        'services.metabase.embed.secret'      => '',
        'services.metabase.environments'      => [],
        'services.metabase.embed.environment' => 'dev',
    ]);

    (new MetabaseEmbedUrlFactory)->token(3);
})->throws(MetabaseEmbedException::class);

it('ignores mb_ API keys and signs with the secret from the Metabase API', function () {
    config([
        'services.metabase.embed.secret'      => 'mb_not-an-embed-secret',
        'services.metabase.embed.environment' => 'dev',
        'services.metabase.environments'      => [
            'dev' => ['url' => 'https://mb-dev.test', 'api_key' => 'src-key'],
        ],
    ]);

    Http::fake([
        'https://mb-dev.test/api/session/properties' => Http::response([
            'embedding-secret-key' => 'real-embed-secret',
            'version'              => ['tag' => 'v0.62.0'],
        ], 200),
    ]);

    $token = (new MetabaseEmbedUrlFactory)->token(3, ['periode' => 'past6months'], [], 1_700_000_000);
    $parts = explode('.', $token);
    $signature = hash_hmac('sha256', $parts[0].'.'.$parts[1], 'real-embed-secret', true);
    $expected = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    expect($parts[2])->toBe($expected)
        ->and(json_decode(metabaseJwtBase64UrlDecode($parts[1]), true)['resource'])->toBe(['dashboard' => 3]);
});
