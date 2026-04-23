<?php

namespace App\Services\Api;

use App\Contracts\Api\ApiHttpTrafficLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DefaultApiHttpTrafficLogger implements ApiHttpTrafficLogger
{
    public function log(Request $request, Response $response, float $durationMs): void
    {
        if (! config('api-http-log.enabled', true)) {
            return;
        }

        try {
            $channel = config('api-http-log.channel', 'api_http');

            $context = [
                'request_id'       => $request->attributes->get('api_http_request_id', Str::uuid()->toString()),
                'method'           => $request->getMethod(),
                'path'             => '/'.$request->path(),
                'route'            => $request->route()?->getName(),
                'action'           => $request->route()?->getActionName(),
                'status'           => $response->getStatusCode(),
                'duration_ms'      => round($durationMs, 3),
                'ip'               => $request->ip(),
                'query'            => $this->limitArrayDepth($request->query->all(), 5),
                'request_headers'  => $this->redactHeaders($request->headers->all()),
                'request_body'     => $this->snippetRequestBody($request),
                'response_body'    => $this->snippetResponseBody($response),
            ];

            if (config('api-http-log.log_response_headers', true)) {
                $context['response_headers'] = $this->redactHeaders($response->headers->all());
            }

            Log::channel($channel)->info('api_http', $context);
        } catch (Throwable $exception) {
            Log::error('Could not log request and response', ['exception' => $exception]);
        }
    }

    /**
     * @param  array<string, list<string|null>>  $headers
     * @return array<string, list<string|null|string>>
     */
    private function redactHeaders(array $headers): array
    {
        $redact = array_map('strtolower', config('api-http-log.redact_headers', []));

        $out = [];
        foreach ($headers as $name => $values) {
            $lower = strtolower($name);
            if (in_array($lower, $redact, true)) {
                $out[$name] = ['[REDACTED]'];
            } else {
                $out[$name] = $values;
            }
        }

        return $out;
    }

    private function snippetRequestBody(Request $request): string|array
    {
        $max = (int) config('api-http-log.max_request_body_bytes', 8192);

        if ($request->isJson()) {
            $decoded = json_decode($request->getContent(), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->truncateString(
                    json_encode($this->redactArray($decoded), JSON_UNESCAPED_UNICODE),
                    $max
                );
            }
        }

        $raw = $request->getContent();
        if ($raw === '' || $raw === '0') {
            $merged = array_merge(
                $request->request->all(),
                $request->query->all()
            );
            if ($merged !== []) {
                return $this->truncateString(
                    json_encode($this->redactArray($merged), JSON_UNESCAPED_UNICODE),
                    $max
                );
            }

            return '';
        }

        if (! is_string($raw)) {
            return '[non-text]';
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            return '[binary]';
        }

        return $this->truncateString($raw, $max);
    }

    private function snippetResponseBody(Response $response): string
    {
        $max = (int) config('api-http-log.max_response_body_bytes', 8192);

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return '';
        }

        if (! is_string($content)) {
            return '[non-text]';
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            return '[binary]';
        }

        $ct = $response->headers->get('Content-Type', '');
        if (str_contains($ct, 'application/json')) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->truncateString(
                    json_encode($this->redactArray($decoded), JSON_UNESCAPED_UNICODE),
                    $max
                );
            }
        }

        return $this->truncateString($content, $max);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redactArray(array $data): array
    {
        $keys = array_map('strtolower', config('api-http-log.redact_input_keys', []));

        $flat = Arr::dot($data);
        foreach ($flat as $key => $_value) {
            $segments = explode('.', $key);
            foreach ($segments as $segment) {
                if (in_array(strtolower((string) $segment), $keys, true)) {
                    $flat[$key] = '[REDACTED]';
                    break;
                }
            }
        }

        return Arr::undot($flat);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function limitArrayDepth(array $data, int $maxKeys): array
    {
        if (count($data) <= $maxKeys) {
            return $data;
        }

        return array_slice($data, 0, $maxKeys, true) + ['__truncated' => true];
    }

    private function truncateString(string $value, int $maxBytes): string
    {
        if (strlen($value) <= $maxBytes) {
            return $value;
        }

        return substr($value, 0, $maxBytes).'…[truncated]';
    }
}
