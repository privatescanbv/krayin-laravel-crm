<?php

use App\Http\Controllers\Concerns\ReturnUrl;
use Illuminate\Http\Request;

test('currentWithHash appends tab fragment to request uri', function () {
    $request = Request::create('/admin/leads/view/1525', 'GET');

    expect(ReturnUrl::currentWithHash('activiteiten', $request))
        ->toBe('/admin/leads/view/1525#activiteiten');
});

test('appendQuery adds return_url parameter', function () {
    $url = ReturnUrl::appendQuery('/admin/activities/edit/1', '/admin/leads/view/2#activiteiten');

    expect($url)->toBe('/admin/activities/edit/1?return_url=%2Fadmin%2Fleads%2Fview%2F2%23activiteiten');
});

test('resolveFromRequest allows relative urls with hash', function () {
    $request = Request::create('/foo', 'GET', [
        'return_url' => '/admin/leads/view/2#activiteiten',
    ]);

    expect(ReturnUrl::resolveFromRequest($request))
        ->toBe('/admin/leads/view/2#activiteiten');
});

test('resolveFromRequest rejects external urls', function () {
    $request = Request::create('/foo', 'GET', [
        'return_url' => 'https://evil.example/phish',
    ]);

    expect(ReturnUrl::resolveFromRequest($request))->toBeNull();
});

test('appendResolvedQuery preserves return_url from request', function () {
    $request = Request::create('/foo', 'GET', [
        'return_url' => '/admin/leads/view/2#activiteiten',
    ]);

    expect(ReturnUrl::appendResolvedQuery('/admin/activities/edit/1', $request))
        ->toBe('/admin/activities/edit/1?return_url=%2Fadmin%2Fleads%2Fview%2F2%23activiteiten');
});
