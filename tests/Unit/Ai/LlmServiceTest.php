<?php

use App\Services\Ai\LlmJsonParseException;
use App\Services\Ai\LlmService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'services.llm.base_url'      => 'https://llm.test/v1',
        'services.llm.api_key'       => 'test-key',
        'services.llm.temperature'   => 0.7,
        'services.llm.timeout'       => 180,
        'services.llm.model'         => 'local-llama',
    ]);
});

test('chat sends request to completions endpoint with configured settings', function () {
    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Hello']],
            ],
        ], 200),
    ]);

    $service = new LlmService;
    $response = $service->chat('email_sender_extraction', '{"subject":"FW: test"}');

    expect($response)->toBe('Hello');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://llm.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $body['model'] === 'local-llama'
            && $body['temperature'] === 0.7
            && $body['messages'][0]['role'] === 'system'
            && $body['messages'][1]['role'] === 'user'
            && $body['messages'][1]['content'] === '{"subject":"FW: test"}';
    });
});

test('chat prefers the per use case base url, model and temperature overrides', function () {
    config([
        'ai_prompts.email_sender_extraction.base_url'    => 'https://other-llm.test/llm/v1/',
        'ai_prompts.email_sender_extraction.model'       => 'gpt-oss-120b',
        'ai_prompts.email_sender_extraction.temperature' => 0.3,
    ]);

    Http::fake([
        'https://other-llm.test/llm/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Hello']],
            ],
        ], 200),
    ]);

    (new LlmService)->chat('email_sender_extraction', '{}');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://other-llm.test/llm/v1/chat/completions'
            && $body['model'] === 'gpt-oss-120b'
            && $body['temperature'] === 0.3;
    });
});

test('chatJson strips markdown code fences', function () {
    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => "```json\n{\"senders\":[]}\n```"]],
            ],
        ], 200),
    ]);

    $service = new LlmService;
    $response = $service->chatJson('email_sender_extraction', '{}');

    expect($response)->toBe(['senders' => []]);
});

test('chat throws when use case prompt is missing', function () {
    $service = new LlmService;

    $service->chat('unknown_use_case', '{}');
})->throws(Exception::class, 'Unknown AI prompt use case: unknown_use_case');

test('chatJson parses json response', function () {
    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"senders":[{"email":"patient@example.com","name":"Jan","confidence":0.9,"role":"original_sender"}]}']],
            ],
        ], 200),
    ]);

    $service = new LlmService;
    $response = $service->chatJson('email_sender_extraction', '{}');

    expect($response)->toBe([
        'senders' => [
            [
                'email'      => 'patient@example.com',
                'name'       => 'Jan',
                'confidence' => 0.9,
                'role'       => 'original_sender',
            ],
        ],
    ]);
});

test('parseJsonResponse extracts json object embedded in text', function () {
    $service = new LlmService;

    $parsed = $service->parseJsonResponse(
        'Hier is het resultaat: {"senders":[{"email":"patient@example.com","name":"Jan","confidence":0.9,"role":"original_sender"}]} Bedankt.'
    );

    expect($parsed['senders'][0]['email'])->toBe('patient@example.com');
});

test('parseJsonResponse throws LlmJsonParseException for invalid content', function () {
    $service = new LlmService;

    $service->parseJsonResponse('Dit is geen JSON.');
})->throws(LlmJsonParseException::class);

test('extractJson handles balanced braces inside strings', function () {
    $service = new LlmService;

    $json = $service->extractJson('Prefix {"senders":[{"email":"a@b.com","name":"Test \"quoted\"","confidence":0.5,"role":"other"}]} suffix');

    expect(json_decode($json, true)['senders'][0]['email'])->toBe('a@b.com');
});

test('chat logs request and response with context', function () {
    Log::spy();

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"senders":[]}']],
            ],
            'usage' => ['total_tokens' => 42],
        ], 200),
    ]);

    $service = new LlmService;
    $service->chatJson('email_sender_extraction', '{"subject":"FW: test"}', ['email_id' => 123]);

    Log::shouldHaveReceived('info')
        ->with('LLM request', Mockery::on(function (array $context) {
            return $context['email_id'] === 123
                && $context['use_case'] === 'email_sender_extraction'
                && isset($context['request']['messages'][1]['content']);
        }))
        ->once();

    Log::shouldHaveReceived('info')
        ->with('LLM response', Mockery::on(function (array $context) {
            return $context['email_id'] === 123
                && $context['content'] === '{"senders":[]}'
                && $context['usage']['total_tokens'] === 42;
        }))
        ->once();

    Log::shouldHaveReceived('info')
        ->with('LLM response parsed', Mockery::on(function (array $context) {
            return $context['email_id'] === 123
                && $context['parsed'] === ['senders' => []];
        }))
        ->once();
});

test('chat can redact sensitive request and response content from logs', function () {
    Log::spy();

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"summary":"gevoelige modeluitvoer"}']],
            ],
        ], 200),
    ]);

    $service = new LlmService;
    $service->chatJson(
        'lead_summary',
        '{"note":"gevoelige broninhoud"}',
        ['lead_id' => 123],
        logContent: false,
    );

    Log::shouldHaveReceived('info')
        ->with('LLM request', Mockery::on(fn (array $context) => $context['request']['content_redacted'] === true
            && ! str_contains(json_encode($context), 'gevoelige broninhoud')))
        ->once();

    Log::shouldHaveReceived('info')
        ->with('LLM response', Mockery::on(fn (array $context) => $context['content'] === '[redacted]'))
        ->once();

    Log::shouldHaveReceived('info')
        ->with('LLM response parsed', Mockery::on(fn (array $context) => $context['parsed'] === '[redacted]'))
        ->once();
});
