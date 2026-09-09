<?php

use Webklex\PHPIMAP\Attachment as ImapAttachment;
use Webkul\Email\Repositories\AttachmentRepository;

/**
 * Build a Webklex attachment instance without invoking its real constructor
 * (which needs a live Message/Part), directly seeding the protected
 * `attributes` array it reads `id`/`hash` from via magic getters.
 */
function makeImapAttachment(?string $id, string $hash = 'fallback-hash'): ImapAttachment
{
    $attachment = (new ReflectionClass(ImapAttachment::class))->newInstanceWithoutConstructor();

    $attributes = (new ReflectionClass($attachment))->getProperty('attributes');
    $attributes->setAccessible(true);
    $attributes->setValue($attachment, ['id' => $id, 'hash' => $hash]);

    return $attachment;
}

function resolveContentId($attachment, string $source): ?string
{
    $method = (new ReflectionClass(AttachmentRepository::class))->getMethod('resolveContentId');
    $method->setAccessible(true);

    return $method->invoke(app(AttachmentRepository::class), $attachment, $source);
}

test('resolves the real Content-ID header from a Webklex IMAP attachment', function () {
    $attachment = makeImapAttachment(id: 'image001@01D12345', hash: 'unrelated-hash');

    expect(resolveContentId($attachment, 'email'))->toBe('image001@01D12345');
});

test('ignores Webklex hash fallback when no Content-ID header exists', function () {
    // Webklex sets `id` to the content hash itself when no Content-ID header is present.
    $attachment = makeImapAttachment(id: 'same-hash', hash: 'same-hash');

    expect(resolveContentId($attachment, 'email'))->toBeNull();
});

test('ignores Content-ID when source is not email', function () {
    $attachment = makeImapAttachment(id: 'image001@01D12345', hash: 'unrelated-hash');

    expect(resolveContentId($attachment, 'web-form'))->toBeNull();
});

test('reads contentId property for non-IMAP attachment objects', function () {
    $attachment = (object) ['contentId' => 'sendgrid-cid-1'];

    expect(resolveContentId($attachment, 'email'))->toBe('sendgrid-cid-1');
});

test('returns null when non-IMAP attachment has no contentId', function () {
    $attachment = (object) [];

    expect(resolveContentId($attachment, 'email'))->toBeNull();
});
