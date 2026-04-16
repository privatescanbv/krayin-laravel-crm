<?php

use App\Support\GvlFormLink;

test('buildImpersonationWrapperUrl contains person id and redirect query', function () {
    $destination = 'https://patient.dev.privatescan.nl/patient/forms/85/step/1';
    $url = GvlFormLink::buildImpersonationWrapperUrl(621, $destination);

    expect($url)->toContain('/admin/contacts/persons/621/impersonate-and-open-form')
        ->and($url)->toContain('redirect=');

    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $q);
    expect($q['redirect'] ?? null)->toBe($destination);
});

test('adminOpenUrl returns null for empty gvl link', function () {
    expect(GvlFormLink::adminOpenUrl(null, 1, true))->toBeNull();
    expect(GvlFormLink::adminOpenUrl('', 1, true))->toBeNull();
});

test('adminOpenUrl returns raw link without portal account', function () {
    $raw = 'https://patient.example/patient/forms/1/step/1';
    expect(GvlFormLink::adminOpenUrl($raw, 5, false))->toBe($raw);
});

test('adminOpenUrlForPerson returns raw link when person is null', function () {
    $raw = 'https://patient.example/patient/forms/1/step/1';
    expect(GvlFormLink::adminOpenUrlForPerson($raw, null))->toBe($raw);
});
