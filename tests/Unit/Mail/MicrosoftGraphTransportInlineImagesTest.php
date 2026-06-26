<?php

use App\Services\Mail\MicrosoftGraphMailTransport;
use App\Services\Mail\MicrosoftGraphTokenService;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function inlineImagesTransport(): MicrosoftGraphMailTransport
{
    config([
        'mail.mailboxes' => [
            'privatescan' => [
                'address' => 'crm@example.com',
                'graph'   => [
                    'tenant_id'     => 'test-tenant',
                    'client_id'     => 'test-client',
                    'client_secret' => 'test-secret',
                ],
            ],
        ],
    ]);

    return new MicrosoftGraphMailTransport(new MicrosoftGraphTokenService);
}

/**
 * Invoke the private processInlineImages method by reference,
 * matching the actual method signature (array &$payload).
 */
function invokeProcessInlineImages(MicrosoftGraphMailTransport $transport, array &$payload): void
{
    $method = (new ReflectionClass($transport))->getMethod('processInlineImages');
    $method->setAccessible(true);
    $method->invokeArgs($transport, [&$payload]);
}

function htmlPayload(string $html): array
{
    return [
        'message' => [
            'body' => [
                'contentType' => 'HTML',
                'content'     => $html,
            ],
        ],
    ];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test('no-op when body content is empty', function () {
    $payload = htmlPayload('');
    $original = $payload;

    invokeProcessInlineImages(inlineImagesTransport(), $payload);

    expect($payload)->toBe($original);
});

test('no-op when html has no img tags', function () {
    $payload = htmlPayload('<p>Hello world</p>');
    $original = $payload;

    invokeProcessInlineImages(inlineImagesTransport(), $payload);

    expect($payload)->toBe($original);
});

test('no-op when img src is an external url', function () {
    $payload = htmlPayload('<img src="https://example.com/logo.png">');
    $original = $payload;

    invokeProcessInlineImages(inlineImagesTransport(), $payload);

    expect($payload)->toBe($original);
});

test('no-op when img src is already a cid reference', function () {
    $payload = htmlPayload('<img src="cid:already-inline">');
    $original = $payload;

    invokeProcessInlineImages(inlineImagesTransport(), $payload);

    expect($payload)->toBe($original);
});

test('converts a data-uri image to a cid inline attachment', function () {
    $b64 = base64_encode('fake-png-bytes');
    $payload = htmlPayload('<p>Hello</p><img src="data:image/png;base64,'.$b64.'">');

    invokeProcessInlineImages(inlineImagesTransport(), $payload);

    // HTML body must reference cid, not the original data-uri
    expect($payload['message']['body']['content'])
        ->toContain('cid:img-1')
        ->not->toContain('data:image/png');

    // One inline attachment must be present
    expect($payload['message']['attachments'])->toHaveCount(1);

    expect($payload['message']['attachments'][0])->toMatchArray([
        '@odata.type'  => '#microsoft.graph.fileAttachment',
        'name'         => 'image1.png',
        'contentType'  => 'image/png',
        'contentBytes' => $b64,
        'isInline'     => true,
        'contentId'    => 'img-1',
    ]);
});

test('assigns sequential unique cids to multiple data-uri images', function () {
    $b64a = base64_encode('image-a');
    $b64b = base64_encode('image-b');
    $payload = htmlPayload(
        '<img src="data:image/jpeg;base64,'.$b64a.'">'.
        '<img src="data:image/gif;base64,'.$b64b.'">'
    );

    invokeProcessInlineImages(inlineImagesTransport(), $payload);

    $html = $payload['message']['body']['content'];
    expect($html)->toContain('cid:img-1')->toContain('cid:img-2');

    $attachments = $payload['message']['attachments'];
    expect($attachments)->toHaveCount(2)
        ->and($attachments[0])->toMatchArray([
            'contentId'    => 'img-1',
            'contentType'  => 'image/jpeg',
            'contentBytes' => $b64a,
            'isInline'     => true,
        ])
        ->and($attachments[1])->toMatchArray([
            'contentId'    => 'img-2',
            'contentType'  => 'image/gif',
            'contentBytes' => $b64b,
            'isInline'     => true,
        ]);

});

test('appends inline images after existing file attachments', function () {
    $b64 = base64_encode('fake-png');
    $payload = array_merge_recursive(
        htmlPayload('<img src="data:image/png;base64,'.$b64.'">'),
        [
            'message' => [
                'attachments' => [[
                    '@odata.type'  => '#microsoft.graph.fileAttachment',
                    'name'         => 'document.pdf',
                    'contentType'  => 'application/pdf',
                    'contentBytes' => base64_encode('pdf-bytes'),
                ]],
            ],
        ]
    );

    invokeProcessInlineImages(inlineImagesTransport(), $payload);

    expect($payload['message']['attachments'])->toHaveCount(2)
        ->and($payload['message']['attachments'][0]['name'])->toBe('document.pdf')
        ->and($payload['message']['attachments'][0])->not->toHaveKey('isInline')
        ->and($payload['message']['attachments'][1])->toMatchArray([
            'isInline'  => true,
            'contentId' => 'img-1',
            'name'      => 'image1.png',
        ]);

    // Original file attachment untouched at index 0

    // Inline image appended at index 1
});

test('mixed html: skips external urls and only converts data-uri images', function () {
    $b64 = base64_encode('logo');
    $payload = htmlPayload(
        '<img src="https://cdn.example.com/banner.jpg">'.
        '<img src="data:image/png;base64,'.$b64.'">'.
        '<img src="cid:signature-logo">'
    );

    invokeProcessInlineImages(inlineImagesTransport(), $payload);

    $html = $payload['message']['body']['content'];

    // External URL unchanged
    expect($html)->toContain('https://cdn.example.com/banner.jpg')
        ->and($html)->toContain('cid:img-1')->not->toContain('data:image/png')
        ->and($html)->toContain('cid:signature-logo')
        ->and($payload['message']['attachments'])->toHaveCount(1)
        ->and($payload['message']['attachments'][0]['contentId'])->toBe('img-1');

    // Data-URI replaced with CID

    // Existing CID unchanged

    // Only one inline attachment (the data-uri image)
});

test('preserves html body when content includes a full html document with doctype', function () {
    $b64 = base64_encode('inline-image');
    $payload = htmlPayload(
        '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'.
        '<body><p>Reply text</p><img src="data:image/png;base64,'.$b64.'"></body></html>'
    );

    invokeProcessInlineImages(inlineImagesTransport(), $payload);

    $html = $payload['message']['body']['content'];

    expect($html)->not->toBe('')
        ->toContain('Reply text')
        ->toContain('cid:img-1')
        ->not->toContain('data:image/png')
        ->and($payload['message']['attachments'])->toHaveCount(1);
});
