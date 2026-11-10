<?php

namespace App\Services;

/**
 * Readable plain text from (rich) CRM HTML, keeping paragraphs and line breaks. Never returns markup.
 */
class PlainText
{
    public static function fromHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = preg_replace(['#<br\s*/?>#i', '#</(p|div|li|h[1-6])>#i'], ["\n", "\n\n"], $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)));

        return $text === '' ? null : $text;
    }

    /**
     * Plain user text as safe HTML for the CRM (which renders note comments as HTML): escaped, paragraphs kept.
     */
    public static function toHtml(string $text): string
    {
        $paragraphs = preg_split("/\n{2,}/", trim(str_replace("\r", '', $text)));

        return implode('', array_map(fn (string $p) => '<p>'.nl2br(e($p)).'</p>', $paragraphs));
    }
}
