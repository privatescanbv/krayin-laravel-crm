<?php

namespace App\Services\Mail;

use Webkul\Email\Models\Email;

/**
 * Wrap quoted history in CRM-composed replies so EmailQuoteSplitter can
 * consistently collapse prior messages behind the toggle.
 */
class CrmReplyQuoteWrapper
{
    public function __construct(
        private readonly EmailQuoteSplitter $emailQuoteSplitter,
    ) {}

    public function hasQuoteMarker(string $html): bool
    {
        return (bool) preg_match(
            '/\b(gmail_quote|yahoo_quoted)\b|<blockquote\b|divRplyFwdMsg/i',
            $html
        );
    }

    public function wrapQuotedHtml(string $quotedHtml, string $attribution = ''): string
    {
        $quotedHtml = trim($quotedHtml);

        if ($quotedHtml === '') {
            return '';
        }

        $attrBlock = $attribution !== ''
            ? '<div class="gmail_attr">'.$attribution.'</div>'
            : '';

        return '<div class="gmail_quote gmail_quote_container">'.$attrBlock
            .'<blockquote class="gmail_quote" type="cite">'.$quotedHtml.'</blockquote></div>';
    }

    public function buildAttribution(Email $email): string
    {
        $name = trim((string) $email->name) ?: 'afzender';
        $address = $this->extractSenderEmail($email);
        $name = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $address = htmlspecialchars($address, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($email->created_at) {
            $formatted = $email->created_at
                ->timezone(config('app.timezone', 'UTC'))
                ->format('d-m-Y H:i');

            return sprintf('Op %s schreef %s &lt;%s&gt;:', $formatted, $name, $address);
        }

        return sprintf('Van: %s &lt;%s&gt;:', $name, $address);
    }

    public function quotedMainFromEmail(Email $email): string
    {
        return trim($this->emailQuoteSplitter->split((string) $email->reply)['main']);
    }

    /**
     * Content that should be quoted when replying to an email.
     * Strips outer html/head/body wrappers when present.
     */
    public function quotedContentFromEmail(Email $email): string
    {
        $main = $this->quotedMainFromEmail($email);

        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $main, $matches)) {
            return trim($matches[1]);
        }

        return $main;
    }

    /**
     * @return list<string>
     */
    public function quoteNeedlesForEmail(Email $email): array
    {
        $needles = array_values(array_filter(array_unique([
            $this->quotedContentFromEmail($email),
            $this->quotedMainFromEmail($email),
            trim(strip_tags((string) $email->reply)),
        ])));

        usort($needles, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return $needles;
    }

    /**
     * Ensure a submitted CRM reply body has explicit quote markers around
     * the parent message when the client did not already wrap it.
     */
    public function ensureReplyBodyWrapped(string $replyHtml, Email $parentEmail): string
    {
        if ($this->hasQuoteMarker($replyHtml)) {
            return $replyHtml;
        }

        $position = false;

        foreach ($this->quoteNeedlesForEmail($parentEmail) as $needle) {
            if ($needle === '') {
                continue;
            }

            $foundAt = stripos($replyHtml, $needle);

            if ($foundAt !== false) {
                $position = $foundAt;

                break;
            }
        }

        if ($position === false) {
            return $replyHtml;
        }

        $before = substr($replyHtml, 0, $position);
        $quotedPart = substr($replyHtml, $position);

        return rtrim($before).$this->wrapQuotedHtml(
            $quotedPart,
            $this->buildAttribution($parentEmail),
        );
    }

    public function extractSenderEmail(Email $email): string
    {
        $from = $email->from;

        if (is_array($from)) {
            if (! empty($from['email']) && is_string($from['email'])) {
                return $from['email'];
            }

            $key = array_key_first($from);

            if (is_string($key) && str_contains($key, '@')) {
                return $key;
            }
        }

        if (is_string($from) && str_contains($from, '@')) {
            return $from;
        }

        return (string) ($email->sender_email ?? '');
    }
}
