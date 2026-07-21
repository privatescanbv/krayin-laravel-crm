<?php

use App\Services\Ai\AiPromptConfig;

beforeEach(function () {
    config([
        'services.llm.base_url'    => 'https://llm.test/v1',
        'services.llm.model'       => 'local-llama',
        'services.llm.temperature' => 0.7,
        'services.llm.timeout'     => 180,
    ]);
});

test('falls back to the global llm defaults when a use case has no overrides', function () {
    config(['ai_prompts.demo' => ['prompt' => 'Doe iets.']]);

    expect(AiPromptConfig::prompt('demo'))->toBe('Doe iets.')
        ->and(AiPromptConfig::baseUrl('demo'))->toBe('https://llm.test/v1')
        ->and(AiPromptConfig::model('demo'))->toBe('local-llama')
        ->and(AiPromptConfig::temperature('demo'))->toBe(0.7)
        ->and(AiPromptConfig::timeout('demo'))->toBe(180);
});

test('uses the per use case overrides when configured', function () {
    config(['ai_prompts.demo' => [
        'prompt'      => 'Doe iets.',
        'base_url'    => 'https://newcrm.dev.privatescan.nl/llm/v1',
        'model'       => 'gpt-oss-120b',
        'temperature' => 0.3,
        'timeout'     => 600,
    ]]);

    expect(AiPromptConfig::baseUrl('demo'))->toBe('https://newcrm.dev.privatescan.nl/llm/v1')
        ->and(AiPromptConfig::model('demo'))->toBe('gpt-oss-120b')
        ->and(AiPromptConfig::temperature('demo'))->toBe(0.3)
        ->and(AiPromptConfig::timeout('demo'))->toBe(600);
});

test('treats null and empty overrides as absent', function () {
    config(['ai_prompts.demo' => [
        'prompt'      => 'Doe iets.',
        'base_url'    => '',
        'model'       => null,
        'temperature' => '',
        'timeout'     => null,
    ]]);

    expect(AiPromptConfig::baseUrl('demo'))->toBe('https://llm.test/v1')
        ->and(AiPromptConfig::model('demo'))->toBe('local-llama')
        ->and(AiPromptConfig::temperature('demo'))->toBe(0.7)
        ->and(AiPromptConfig::timeout('demo'))->toBe(180);
});

test('keeps a zero temperature override instead of falling back', function () {
    config(['ai_prompts.demo' => ['prompt' => 'Doe iets.', 'temperature' => 0]]);

    expect(AiPromptConfig::temperature('demo'))->toBe(0.0);
});

test('casts string overrides coming from env', function () {
    config(['ai_prompts.demo' => [
        'prompt'      => 'Doe iets.',
        'temperature' => '0.4',
        'timeout'     => '90',
    ]]);

    expect(AiPromptConfig::temperature('demo'))->toBe(0.4)
        ->and(AiPromptConfig::timeout('demo'))->toBe(90);
});

test('accepts a plain string use case as prompt only shorthand', function () {
    config(['ai_prompts.demo' => 'Doe iets.']);

    expect(AiPromptConfig::prompt('demo'))->toBe('Doe iets.')
        ->and(AiPromptConfig::model('demo'))->toBe('local-llama');
});

test('returns null for an unknown or empty prompt', function () {
    config(['ai_prompts.demo' => ['prompt' => '   ']]);

    expect(AiPromptConfig::prompt('demo'))->toBeNull()
        ->and(AiPromptConfig::prompt('does_not_exist'))->toBeNull();
});

test('shipped use cases expose a prompt', function () {
    expect(AiPromptConfig::prompt('lead_summary'))->toContain('samenvatting')
        ->and(AiPromptConfig::prompt('email_sender_extraction'))->toContain('afzender');
});

test('every shipped use case exposes the override keys', function () {
    // TestCase clears the per use case base_url overrides, so read the file directly.
    $shipped = require config_path('ai_prompts.php');

    foreach ($shipped as $useCase => $settings) {
        expect($settings)->toHaveKeys(['prompt', 'base_url', 'model', 'temperature', 'timeout'], $useCase);
    }
});
