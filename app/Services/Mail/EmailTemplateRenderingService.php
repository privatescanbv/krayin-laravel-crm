<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\View;
use Webkul\EmailTemplate\Models\EmailTemplate;

class EmailTemplateRenderingService
{
    public function __construct(
        private readonly EmailRenderingService $emailRenderingService
    ) {}

    /**
     * Render an EmailTemplate from DB into final HTML (with standard layout + inline CSS).
     *
     * @return array{subject: string, html: string}
     */
    public function render(EmailTemplate $template, array $variables = []): array
    {
        $subject = $this->interpolateTemplate($template->subject, $variables);
        $interpolatedContent = $this->interpolateTemplate($template->content, $variables);

        // Re-use the standard mail wrapper used by admin mail templates.
        $templateWithInterpolatedContent = clone $template;
        $templateWithInterpolatedContent->content = $interpolatedContent;

        $htmlContent = View::make('adminc.emails.mail-template', [
            'template' => $templateWithInterpolatedContent,
        ])->render();

        return [
            'subject' => $subject,
            'html'    => $this->emailRenderingService->rendInlineCss($htmlContent),
        ];
    }

    /**
     * Very small template interpolation compatible with existing email template syntax.
     *
     * Supports:
     * - {{ variable }} and {{ $variable }}
     * - {% variable %} and {% object.property %}
     *
     * Unknown variables are replaced with an empty string.
     */
    private function interpolateTemplate(string $template, array $variables): string
    {
        $resolve = function (string $key) use ($variables): string {
            $key = trim($key);

            // Allow {{ $lastname }} style.
            if (str_starts_with($key, '$')) {
                $key = ltrim($key, '$');
            }

            // Nested access (e.g. lead.name) – best-effort.
            if (str_contains($key, '.')) {
                $parts = explode('.', $key);
                $root = array_shift($parts);

                $value = $variables[$root] ?? null;
                foreach ($parts as $part) {
                    if (is_array($value)) {
                        $value = $value[$part] ?? null;
                    } elseif (is_object($value)) {
                        // Eloquent models support getAttribute()
                        if (method_exists($value, 'getAttribute')) {
                            $value = $value->getAttribute($part);
                        } elseif (isset($value->{$part})) {
                            $value = $value->{$part};
                        } else {
                            $value = null;
                        }
                    } else {
                        $value = null;
                    }
                }

                return $value === null ? '' : (string) $value;
            }

            if (! array_key_exists($key, $variables)) {
                return '';
            }

            $value = $variables[$key];

            return $value === null ? '' : (string) $value;
        };

        $template = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/', function ($matches) use ($resolve) {
            return $resolve($matches[1] ?? '');
        }, $template) ?? $template;

        $template = preg_replace_callback('/\{\%\s*(.*?)\s*\%\}/', function ($matches) use ($resolve) {
            return $resolve($matches[1] ?? '');
        }, $template) ?? $template;

        return $template;
    }
}
