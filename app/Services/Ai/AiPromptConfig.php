<?php

namespace App\Services\Ai;

/**
 * Resolves the settings for one AI use case.
 *
 * Each use case in config/ai_prompts.php may override the global LLM defaults
 * from config/services.php (base_url, model, temperature, timeout). A null or
 * empty override falls back to the global default.
 */
class AiPromptConfig
{
    public static function prompt(string $useCase): ?string
    {
        $prompt = self::entry($useCase)['prompt'] ?? null;

        return is_string($prompt) && trim($prompt) !== '' ? $prompt : null;
    }

    public static function baseUrl(string $useCase): string
    {
        return (string) (self::override($useCase, 'base_url') ?? config('services.llm.base_url'));
    }

    public static function model(string $useCase): string
    {
        return (string) (self::override($useCase, 'model') ?? config('services.llm.model'));
    }

    public static function temperature(string $useCase): float
    {
        return (float) (self::override($useCase, 'temperature') ?? config('services.llm.temperature', 0.7));
    }

    public static function timeout(string $useCase): int
    {
        return (int) (self::override($useCase, 'timeout') ?? config('services.llm.timeout', 180));
    }

    /**
     * @return array<string, mixed>
     */
    private static function entry(string $useCase): array
    {
        $entry = config("ai_prompts.{$useCase}");

        // A plain string is shorthand for a use case without overrides.
        if (is_string($entry)) {
            return ['prompt' => $entry];
        }

        return is_array($entry) ? $entry : [];
    }

    private static function override(string $useCase, string $key): mixed
    {
        $value = self::entry($useCase)[$key] ?? null;

        return $value === null || $value === '' ? null : $value;
    }
}
