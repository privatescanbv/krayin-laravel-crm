<?php

use App\Services\Mail\CrmReplyQuoteWrapper;
use App\Services\Mail\EmailQuoteSplitter;
use Carbon\Carbon;
use Webkul\Email\Models\Email;

function crmReplyQuoteWrapper(): CrmReplyQuoteWrapper
{
    return new CrmReplyQuoteWrapper(new EmailQuoteSplitter);
}

test('wrapQuotedHtml produces gmail quote markers', function () {
    $wrapped = crmReplyQuoteWrapper()->wrapQuotedHtml(
        '<div dir="ltr">meer info graag</div>',
        'Van: Mark &lt;customer@example.com&gt;:'
    );

    expect($wrapped)
        ->toContain('gmail_quote')
        ->toContain('<blockquote class="gmail_quote"')
        ->toContain('meer info graag')
        ->toContain('gmail_attr');
});

test('ensureReplyBodyWrapped wraps unmarked parent content in submitted reply', function () {
    $parent = new Email([
        'name'       => 'Mark Bulthuis',
        'from'       => ['email' => 'customer@example.com', 'name' => 'Mark Bulthuis'],
        'reply'      => '<html><body><div dir="ltr">meer info graag</div></body></html>',
        'created_at' => Carbon::parse('2026-07-02 10:38:08'),
    ]);

    $reply = '<p>Wat zijn uw klachten?</p><p>&nbsp;</p><div dir="ltr">meer info graag</div>';

    $normalized = crmReplyQuoteWrapper()->ensureReplyBodyWrapped($reply, $parent);
    $split = (new EmailQuoteSplitter)->split($normalized);

    expect($normalized)
        ->toContain('gmail_quote')
        ->and($split['main'])->toContain('Wat zijn uw klachten?')
        ->and($split['main'])->not->toContain('meer info graag')
        ->and($split['quoted'])->toContain('meer info graag');
});

test('ensureReplyBodyWrapped leaves already wrapped replies unchanged', function () {
    $parent = new Email([
        'reply' => '<div>Parent body</div>',
    ]);

    $reply = '<p>Antwoord</p><blockquote class="gmail_quote"><div>Parent body</div></blockquote>';

    expect(crmReplyQuoteWrapper()->ensureReplyBodyWrapped($reply, $parent))->toBe($reply);
});

test('quotedContentFromEmail strips outer html wrappers', function () {
    $email = new Email([
        'reply' => '<html><body><div dir="ltr">meer info graag</div></body></html>',
    ]);

    expect(crmReplyQuoteWrapper()->quotedContentFromEmail($email))
        ->toBe('<div dir="ltr">meer info graag</div>');
});

test('quotedMainFromEmail uses splitter main content only', function () {
    $email = new Email([
        'reply' => '<p>Nieuwe vraag</p><blockquote class="gmail_quote"><p>Oud</p></blockquote>',
    ]);

    expect(crmReplyQuoteWrapper()->quotedMainFromEmail($email))->toBe('<p>Nieuwe vraag</p>');
});
