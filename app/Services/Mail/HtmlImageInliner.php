<?php

namespace App\Services\Mail;

use DOMDocument;
use Illuminate\Support\Facades\Log;

/**
 * Replaces relative and CRM-hosted <img src="..."> URLs in HTML with
 * base64 data URIs so images are embedded in outgoing emails and do not
 * require an externally reachable server.
 */
class HtmlImageInliner
{
    public function inline(string $html): string
    {
        if (empty(trim($html))) {
            return $html;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');

        libxml_use_internal_errors(true);
        $dom->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $changed = false;

        foreach ($dom->getElementsByTagName('img') as $img) {
            $src = $img->getAttribute('src');

            if (empty($src)) {
                continue;
            }

            $path = $this->resolveToLocalPath($src);

            if (! $path || ! file_exists($path)) {
                continue;
            }

            try {
                $mime = mime_content_type($path);
                $data = base64_encode(file_get_contents($path));
                $img->setAttribute('src', "data:{$mime};base64,{$data}");
                $changed = true;
            } catch (\Throwable $e) {
                Log::warning('HtmlImageInliner: could not embed image', [
                    'src'   => $src,
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $changed) {
            return $html;
        }

        return $dom->saveHTML();
    }

    private function resolveToLocalPath(string $src): ?string
    {
        // Already a data URI — skip
        if (str_starts_with($src, 'data:')) {
            return null;
        }

        // Absolute URL pointing to this CRM — strip origin and map to public_path
        $appUrl = rtrim(config('app.url'), '/');

        if (str_starts_with($src, $appUrl)) {
            $relative = substr($src, strlen($appUrl));

            return public_path(ltrim($relative, '/'));
        }

        // Relative path (e.g. /images/email-signature/fb.gif)
        if (! str_starts_with($src, 'http')) {
            return public_path(ltrim($src, '/'));
        }

        // External URL — skip
        return null;
    }
}
