<?php

namespace App\Services\Mail;

use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class EmailRenderingService
{
    /**
     * Render html with the given data and convert CSS to inline styles.
     *
     * @param  string  $html  body
     * @return string The rendered HTML with inline styles
     */
    public function rendInlineCss(string $html): string
    {
        // Read the CSS file
        $cssPath = resource_path('css/email-templates.css');
        $css = file_exists($cssPath) ? file_get_contents($cssPath) : '';

        // Convert CSS to inline styles for email compatibility
        // This is necessary because many email clients strip <style> tags
        if (! empty($css)) {
            $cssToInlineStyles = new CssToInlineStyles;
            $html = $cssToInlineStyles->convert($html, $css);
        }

        return $html;
    }
}
