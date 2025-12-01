<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\View;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class EmailRenderingService
{
    /**
     * Render email HTML using the standard mail layout.
     * This ensures all emails use the same house style.
     *
     * The view should extend 'adminc.layouts.mail' and define @section('content').
     *
     * @param  string  $viewTemplate  The Blade view name (e.g., 'adminc.emails.portal-gvl-completed-patient')
     * @param  array  $data  Data to pass to the view
     * @param  string|null  $title  Optional title for the email (shown in blue bar)
     * @return string Rendered HTML
     */
    public function renderEmail(string $viewTemplate, array $data = [], ?string $title = null): string
    {
        // Merge title into data if provided
        if ($title !== null) {
            $data['title'] = $title;
        }
        // Render the view (which should extend adminc.layouts.mail)
        $html = View::make($viewTemplate, $data)->render();

        return $this->rendInlineCss($html, $data, $title);
    }

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
