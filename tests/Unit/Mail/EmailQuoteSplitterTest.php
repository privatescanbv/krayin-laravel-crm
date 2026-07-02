<?php

use App\Services\Mail\EmailQuoteSplitter;

function splitEmail(string $html): array
{
    return (new EmailQuoteSplitter)->split($html);
}

test('splits gmail-style blockquote reply', function () {
    $html = '<p>Thanks, sounds good.</p><blockquote class="gmail_quote">On Mon, Jan 1, someone wrote: original message</blockquote>';

    $result = splitEmail($html);

    expect($result['main'])->toContain('Thanks, sounds good.')
        ->and($result['main'])->not->toContain('original message')
        ->and($result['quoted'])->toContain('original message');
});

test('splits gmail_quote div', function () {
    $html = '<div>New reply text</div><div class="gmail_quote">Quoted history here</div>';

    $result = splitEmail($html);

    expect($result['main'])->toContain('New reply text')
        ->and($result['quoted'])->toContain('Quoted history here');
});

test('splits yahoo_quoted class', function () {
    $html = '<p>My reply</p><div class="yahoo_quoted">Yahoo quoted body</div>';

    $result = splitEmail($html);

    expect($result['main'])->toContain('My reply')
        ->and($result['quoted'])->toContain('Yahoo quoted body');
});

test('splits owa divRplyFwdMsg id', function () {
    $html = '<p>My reply</p><div id="divRplyFwdMsg">Van: iemand<br>Onderwerp: Re: Vraag</div>';

    $result = splitEmail($html);

    expect($result['main'])->toContain('My reply')
        ->and($result['quoted'])->toContain('divRplyFwdMsg');
});

test('splits outlook-desktop border-top divider with dutch headers', function () {
    $html = '<p>Bedankt voor uw bericht.</p>'
        .'<div style="border:none;border-top:solid #B5C4DF 1.0pt;padding:3.0pt 0cm 0cm 0cm">'
        .'<p><b>Van:</b> Jan Jansen<br><b>Verzonden:</b> dinsdag 1 juli 2026 10:00<br>'
        .'<b>Aan:</b> Piet Pietersen<br><b>Onderwerp:</b> Re: Vraag</p></div>';

    $result = splitEmail($html);

    expect($result['main'])->toContain('Bedankt voor uw bericht.')
        ->and($result['main'])->not->toContain('Jan Jansen')
        ->and($result['quoted'])->toContain('border-top')
        ->and($result['quoted'])->toContain('Jan Jansen');
});

test('splits outlook-desktop border-top divider with english headers', function () {
    $html = '<p>Thanks for reaching out.</p>'
        .'<div style="border:none;border-top:solid #B5C4DF 1.0pt;padding:3.0pt 0cm 0cm 0cm">'
        .'<p><b>From:</b> John Doe<br><b>Sent:</b> Tuesday, July 1, 2026 10:00 AM<br>'
        .'<b>To:</b> Jane Roe<br><b>Subject:</b> Re: Question</p></div>';

    $result = splitEmail($html);

    expect($result['main'])->toContain('Thanks for reaching out.')
        ->and($result['quoted'])->toContain('John Doe');
});

test('falls back to plain-text header block when no divider element exists (dutch)', function () {
    $html = '<p>Bedankt.</p>'
        .'<div>Van: Jan Jansen<br>Verzonden: dinsdag 1 juli 2026 10:00<br>Aan: Piet Pietersen<br>Onderwerp: Re: Vraag</div>'
        .'<p>Oorspronkelijke inhoud van het bericht.</p>';

    $result = splitEmail($html);

    expect($result['main'])->toContain('Bedankt.')
        ->and($result['main'])->not->toContain('Oorspronkelijke inhoud')
        ->and($result['quoted'])->toContain('Jan Jansen')
        ->and($result['quoted'])->toContain('Oorspronkelijke inhoud');
});

test('falls back to plain-text header block when no divider element exists (english, one field per paragraph)', function () {
    $html = '<p>Thanks.</p>'
        .'<p>From: John Doe</p><p>Sent: Tuesday, July 1, 2026 10:00 AM</p>'
        .'<p>To: Jane Roe</p><p>Subject: Re: Question</p>'
        .'<p>Original message body.</p>';

    $result = splitEmail($html);

    expect($result['main'])->toContain('Thanks.')
        ->and($result['main'])->not->toContain('John Doe')
        ->and($result['quoted'])->toContain('John Doe')
        ->and($result['quoted'])->toContain('Original message body.');
});

test('does not split a plain body with no markers at all', function () {
    $html = '<p>Just a normal reply with no quoted history.</p>';

    $result = splitEmail($html);

    expect($result['main'])->toBe($html)
        ->and($result['quoted'])->toBe('');
});

test('returns empty strings for empty input', function () {
    $result = splitEmail('');

    expect($result['main'])->toBe('')
        ->and($result['quoted'])->toBe('');
});

test('degrades gracefully on malformed unclosed html', function () {
    $html = '<p>Unclosed <b>bold <div class="gmail_quote">Quote</div>';

    $result = splitEmail($html);

    expect($result['quoted'])->toContain('Quote')
        ->and($result['main'])->toContain('Unclosed');
});

test('treats the whole body as unsplit when the quote marker is the very first node', function () {
    $html = '<blockquote>Nothing but quoted content, no new reply text.</blockquote>';

    $result = splitEmail($html);

    expect($result['main'])->toBe($html)
        ->and($result['quoted'])->toBe('');
});

test('never throws on plain text with no html tags', function () {
    $result = splitEmail('Just plain text, no markup whatsoever.');

    expect($result['main'])->toBe('Just plain text, no markup whatsoever.')
        ->and($result['quoted'])->toBe('');
});
