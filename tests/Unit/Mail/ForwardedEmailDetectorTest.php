<?php

use App\Services\Mail\ForwardedEmailDetector;

test('detects forward from subject prefix', function () {
    expect(ForwardedEmailDetector::looksLikeForward('FW: Patient vraag', 'Body'))->toBeTrue()
        ->and(ForwardedEmailDetector::looksLikeForward('Fwd: Patient vraag', 'Body'))->toBeTrue()
        ->and(ForwardedEmailDetector::looksLikeForward('Doorgestuurd: Patient vraag', 'Body'))->toBeTrue()
        ->and(ForwardedEmailDetector::looksLikeForward('Forward: Patient vraag', 'Body'))->toBeTrue();
});

test('detects forward from body markers', function () {
    expect(ForwardedEmailDetector::looksLikeForward('Subject', "Line\n-----Original Message-----\nFrom: a@b.com"))->toBeTrue()
        ->and(ForwardedEmailDetector::looksLikeForward('Subject', 'Begin forwarded message'))->toBeTrue()
        ->and(ForwardedEmailDetector::looksLikeForward('Subject', 'Doorgestuurd bericht'))->toBeTrue()
        ->and(ForwardedEmailDetector::looksLikeForward('Subject', 'Oorspronkelijk bericht'))->toBeTrue()
        ->and(ForwardedEmailDetector::looksLikeForward('Subject', 'Van: Jan <jan@example.com>'))->toBeTrue()
        ->and(ForwardedEmailDetector::looksLikeForward('Subject', 'From: Jan <jan@example.com>'))->toBeTrue();
});

test('does not detect normal email as forward', function () {
    expect(ForwardedEmailDetector::looksLikeForward('Patient vraag', 'Hallo, dit is mijn vraag.'))->toBeFalse();
});

test('detects forward markers in html body', function () {
    $body = '<p>Doorgestuurd bericht</p><p>Van: patient@example.com</p>';

    expect(ForwardedEmailDetector::looksLikeForward('Re: vraag', $body))->toBeTrue();
});
