<?php

namespace App\Services\Ai;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmService
{
    /**
     * Send a chat completion request and return the assistant message content.
     *
     * @param  array<string, mixed>  $context  Extra fields included in log output (e.g. email_id).
     * @param  array{prompt_tokens?: int, completion_tokens?: int}|null  $usage  Set to the API's token usage, by reference.
     *
     * @throws Exception
     */
    public function chat(
        string $useCase,
        string $userContent,
        array $context = [],
        ?string $systemPromptOverride = null,
        bool $logContent = true,
        ?array &$usage = null,
    ): string {
        $systemPrompt = $systemPromptOverride ?: AiPromptConfig::prompt($useCase);

        if (empty($systemPrompt)) {
            throw new Exception("Unknown AI prompt use case: {$useCase}");
        }

        $baseUrl = rtrim(AiPromptConfig::baseUrl($useCase), '/');
        $url = "{$baseUrl}/chat/completions";
        $model = AiPromptConfig::model($useCase);
        $temperature = AiPromptConfig::temperature($useCase);
        $timeout = AiPromptConfig::timeout($useCase);

        $requestBody = [
            'model'       => $model,
            'temperature' => $temperature,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role'    => 'user',
                    'content' => $userContent,
                ],
            ],
        ];

        if (config('services.llm.response_format_json', true)) {
            $requestBody['response_format'] = ['type' => 'json_object'];
        }

        Log::info('LLM request', array_merge($context, [
            'use_case'            => $useCase,
            'url'                 => $url,
            'model'               => $model,
            'temperature'         => $temperature,
            'timeout'             => $timeout,
            'system_prompt_bytes' => $context['system_prompt_bytes'] ?? strlen($systemPrompt),
            'user_payload_bytes'  => $context['user_payload_bytes'] ?? strlen($userContent),
            'request'             => $logContent ? $requestBody : [
                'model'            => $model,
                'temperature'      => $temperature,
                'response_format'  => $requestBody['response_format'] ?? null,
                'content_redacted' => true,
            ],
        ]));

        $startedAt = microtime(true);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer '.config('services.llm.api_key'),
                'Content-Type'  => 'application/json',
            ])
            ->post($url, $requestBody);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::error('LLM request failed', array_merge($context, [
                'use_case'     => $useCase,
                'url'          => $url,
                'status'       => $response->status(),
                'duration_ms'  => $durationMs,
                'response_raw' => $logContent ? $response->body() : '[redacted]',
            ]));

            throw new Exception('LLM request failed with status '.$response->status());
        }

        $data = $response->json();

        if (isset($data['error'])) {
            $message = is_array($data['error'])
                ? ($data['error']['message'] ?? json_encode($data['error']))
                : (string) $data['error'];

            Log::error('LLM returned error payload', array_merge($context, [
                'use_case'    => $useCase,
                'duration_ms' => $durationMs,
                'error'       => $logContent ? $data['error'] : '[redacted]',
            ]));

            throw new Exception($logContent ? 'LLM error: '.$message : 'LLM returned an error payload');
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            Log::error('LLM response missing message content', array_merge($context, [
                'use_case'      => $useCase,
                'duration_ms'   => $durationMs,
                'response_data' => $logContent ? $data : '[redacted]',
            ]));

            throw new Exception('LLM response did not contain message content');
        }

        $content = trim($content);
        $usage = $data['usage'] ?? null;

        Log::info('LLM response', array_merge($context, [
            'use_case'    => $useCase,
            'duration_ms' => $durationMs,
            'status'      => $response->status(),
            'usage'       => $usage,
            'content'     => $logContent ? $content : '[redacted]',
        ]));

        return $content;
    }

    /**
     * Send a chat completion request and parse the response as JSON.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function chatJson(
        string $useCase,
        string $userContent,
        array $context = [],
        ?string $systemPromptOverride = null,
        bool $logContent = true,
    ): array {
        $content = $this->chat($useCase, $userContent, $context, $systemPromptOverride, $logContent);

        return $this->parseJsonResponse($content, $context, $useCase, $logContent);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     *
     * @throws LlmJsonParseException
     */
    public function parseJsonResponse(
        string $content,
        array $context = [],
        ?string $useCase = null,
        bool $logContent = true,
    ): array {
        $json = $this->extractJson($content);
        $decoded = json_decode($json, true);
        $jsonError = json_last_error();
        $jsonErrorMessage = json_last_error_msg();

        if ($jsonError === JSON_ERROR_NONE && is_array($decoded)) {
            Log::info('LLM response parsed', array_merge($context, array_filter([
                'use_case' => $useCase,
                'parsed'   => $logContent ? $decoded : '[redacted]',
            ])));

            return $decoded;
        }

        Log::error('LLM response is not valid JSON', array_merge($context, array_filter([
            'use_case'          => $useCase,
            'content'           => $logContent ? $content : '[redacted]',
            'extracted_json'    => $logContent ? $json : '[redacted]',
            'json_error'        => $jsonErrorMessage,
            'json_error_code'   => $jsonError,
        ])));

        $reason = $jsonError !== JSON_ERROR_NONE
            ? $jsonErrorMessage
            : 'response is not a JSON object';

        throw new LlmJsonParseException(
            'LLM response is not valid JSON: '.$reason,
            $content,
            $json,
        );
    }

    /**
     * Extract a JSON object from LLM output (markdown fences, preamble text, etc.).
     */
    public function extractJson(string $content): string
    {
        $trimmed = trim($content);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $trimmed, $matches)) {
            $candidate = trim($matches[1]);
            if ($this->looksLikeJsonObject($candidate)) {
                return $candidate;
            }
        }

        if ($this->looksLikeJsonObject($trimmed)) {
            return $trimmed;
        }

        $start = strpos($trimmed, '{');
        if ($start !== false) {
            $candidate = $this->extractBalancedJsonObject($trimmed, $start);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return $trimmed;
    }

    private function looksLikeJsonObject(string $value): bool
    {
        if ($value === '' || ! str_starts_with($value, '{')) {
            return false;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded);
    }

    private function extractBalancedJsonObject(string $content, int $start): ?string
    {
        $length = strlen($content);
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $content[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($content, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
