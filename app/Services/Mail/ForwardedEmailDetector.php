<?php

namespace App\Services\Mail;

class ForwardedEmailDetector
{
    /**
     * @var list<string>
     */
    private const BODY_MARKERS = [
        '-----Original Message-----',
        'Begin forwarded message',
        'Doorgestuurd bericht',
        'Oorspronkelijk bericht',
        'Van:',
        'From:',
    ];

    public static function looksLikeForward(string $subject, string $body): bool
    {
        if (preg_match('/^(fw|fwd|doorgestuurd|forward)\s*:/i', trim($subject))) {
            return true;
        }

        $normalizedBody = html_entity_decode(strip_tags($body));

        return array_any(self::BODY_MARKERS, fn ($marker) => str_contains($normalizedBody, $marker));

    }
}
