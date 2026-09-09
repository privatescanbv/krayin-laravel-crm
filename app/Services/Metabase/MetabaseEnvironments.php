<?php

namespace App\Services\Metabase;

/**
 * Resolves a --source / --target reference (an environment name, or a full URL
 * that matches a configured environment) into a {@see MetabaseClient}.
 *
 * Source and target are never implicit: the caller must always pass a reference.
 */
class MetabaseEnvironments
{
    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->configured());
    }

    public function resolve(string $reference): MetabaseClient
    {
        $reference = trim($reference);

        if ($reference === '') {
            throw new MetabaseSyncException('Empty Metabase environment reference.');
        }

        [$name, $config] = $this->lookup($reference);

        if ($config === null) {
            throw new MetabaseSyncException($this->unknownReferenceMessage($reference));
        }

        if (empty($config['api_key'])) {
            throw new MetabaseSyncException("Metabase environment '{$name}' has no API key configured (METABASE_".strtoupper($name).'_API_KEY).');
        }

        return new MetabaseClient(
            baseUrl: $config['url'],
            apiKey: $config['api_key'],
            label: $name,
            timeout: (int) config('services.metabase.timeout', 30),
        );
    }

    /** @return array<string, array{url: string, api_key: string|null}> */
    private function configured(): array
    {
        return config('services.metabase.environments', []);
    }

    /**
     * @return array{0: string, 1: array{url: string, api_key: string|null}|null}
     */
    private function lookup(string $reference): array
    {
        $environments = $this->configured();

        if (isset($environments[$reference])) {
            return [$reference, $environments[$reference]];
        }

        // Allow a full URL as long as it matches a configured environment.
        if (str_starts_with($reference, 'http://') || str_starts_with($reference, 'https://')) {
            $needle = rtrim($reference, '/');

            foreach ($environments as $name => $config) {
                if (rtrim((string) $config['url'], '/') === $needle) {
                    return [$name, $config];
                }
            }

            return [$reference, null];
        }

        return [$reference, null];
    }

    private function unknownReferenceMessage(string $reference): string
    {
        $known = $this->names();
        $list = $known === [] ? '(none configured)' : implode(', ', $known);

        if (str_starts_with($reference, 'http')) {
            return "Metabase URL '{$reference}' does not match any configured environment. Add it to config/services.php (services.metabase.environments) so its API key can be resolved. Known: {$list}.";
        }

        return "Unknown Metabase environment '{$reference}'. Known: {$list}.";
    }
}
