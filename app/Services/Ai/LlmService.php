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
     *
     * @throws Exception
     */
    public function chat(string $useCase, string $userContent, array $context = [], ?string $systemPromptOverride = null): string
    {
        $systemPrompt = $systemPromptOverride ?: config("ai_prompts.{$useCase}");

        if (empty($systemPrompt)) {
            throw new Exception("Unknown AI prompt use case: {$useCase}");
        }

        $baseUrl = rtrim((string) config('services.llm.base_url'), '/');
        $url = "{$baseUrl}/chat/completions";
        $model = config('services.llm.model');
        $temperature = (float) config('services.llm.temperature', 0.7);

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
            'use_case'    => $useCase,
            'url'         => $url,
            'model'       => $model,
            'temperature' => $temperature,
            'request'     => $requestBody,
        ]));

        $startedAt = microtime(true);

        $response = Http::timeout((int) config('services.llm.timeout', 180))
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
                'response_raw' => $response->body(),
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
                'error'       => $data['error'],
            ]));

            throw new Exception('LLM error: '.$message);
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            Log::error('LLM response missing message content', array_merge($context, [
                'use_case'      => $useCase,
                'duration_ms'   => $durationMs,
                'response_data' => $data,
            ]));

            throw new Exception('LLM response did not contain message content');
        }

        $content = trim($content);

        Log::info('LLM response', array_merge($context, [
            'use_case'    => $useCase,
            'duration_ms' => $durationMs,
            'status'      => $response->status(),
            'usage'       => $data['usage'] ?? null,
            'content'     => $content,
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
    public function chatJson(string $useCase, string $userContent, array $context = [], ?string $systemPromptOverride = null): array
    {
        $content = $this->chat($useCase, $userContent, $context, $systemPromptOverride);

        return $this->parseJsonResponse($content, $context, $useCase);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     *
     * @throws LlmJsonParseException
     */
    public function parseJsonResponse(string $content, array $context = [], ?string $useCase = null): array
    {
        $json = $this->extractJson($content);
        $decoded = json_decode($json, true);
        $jsonError = json_last_error();
        $jsonErrorMessage = json_last_error_msg();

        if ($jsonError === JSON_ERROR_NONE && is_array($decoded)) {
            Log::info('LLM response parsed', array_merge($context, array_filter([
                'use_case' => $useCase,
                'parsed'   => $decoded,
            ])));

            return $decoded;
        }

        Log::error('LLM response is not valid JSON', array_merge($context, array_filter([
            'use_case'          => $useCase,
            'content'           => $content,
            'extracted_json'    => $json,
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
