<?php

namespace App\Services\Metabase;

use Closure;

/**
 * Rewrites a card's `dataset_query` so its database / table / field references
 * point at the target instance.
 *
 * - MBQL ("query"): database id, every `source-table`, every `["field", <int>, …]`
 *   reference (including nested `source-field` / `fk-field-id` in option maps and
 *   inside joins, breakouts, filters, expressions) and nested-card references
 *   (`"card__<id>"`) are translated.
 * - Native ("native"): only the database id is translated. Table and column names
 *   embedded in the SQL text are left untouched — the caller surfaces a warning.
 *   Structured field references inside `template-tags[*].dimension` are translated.
 *
 * `getCard()` returns Metabase's MBQL5 "lib" shape (`lib/type: mbql/query`,
 * everything nested under `stages[]`, field refs as `["field", <opts>, <id>]` —
 * options before id, the reverse of legacy MBQL). translate() normalizes a
 * single-stage MBQL5 query into the legacy `{type, native|query}` shape before
 * walking it, and always returns that legacy shape — which is what create/update
 * actually accept (never round-trip the "lib" shape back into the API).
 */
class QueryTranslator
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param  Closure(int): int  $cardIdResolver  maps a source saved-question id to its target id
     */
    public function __construct(
        private readonly DataSourceResolver $resolver,
        private readonly Closure $cardIdResolver,
    ) {}

    /** @return list<string> warnings collected during the last translate() call */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function translate(array $datasetQuery): array
    {
        $this->warnings = [];

        $database = isset($datasetQuery['database']) && is_int($datasetQuery['database'])
            ? $this->resolver->databaseId($datasetQuery['database'])
            : ($datasetQuery['database'] ?? null);

        if (isset($datasetQuery['stages'])) {
            $datasetQuery = $this->normalizeLibShape($datasetQuery);
        }

        $type = $datasetQuery['type'] ?? null;

        if ($type === 'query' && isset($datasetQuery['query'])) {
            $datasetQuery['query'] = $this->walk($datasetQuery['query']);
        }

        if ($type === 'native' && isset($datasetQuery['native'])) {
            $datasetQuery['native'] = $this->walkNative($datasetQuery['native']);
        }

        $datasetQuery['database'] = $database;

        return $datasetQuery;
    }

    /* --------------------------------------------------------------------- */

    /**
     * Reduce Metabase's MBQL5 "lib" shape (`stages: [...]`) to the legacy
     * `{type, native|query}` shape the rest of this class works with. Only a
     * single stage is supported — every card in this system is plain native SQL
     * (see class docblock); a multi-stage or structured (non-native) MBQL5 query
     * would need real support added here rather than being silently mistranslated.
     */
    private function normalizeLibShape(array $datasetQuery): array
    {
        $stages = $datasetQuery['stages'];

        if (count($stages) !== 1) {
            throw new MetabaseSyncException('Multi-stage MBQL5 queries are not supported by the sync.');
        }

        $stage = $stages[0];

        if (isset($stage['native'])) {
            return [
                'type'   => 'native',
                'native' => [
                    'query'         => $stage['native'],
                    'template-tags' => $stage['template-tags'] ?? [],
                ],
            ];
        }

        throw new MetabaseSyncException('Structured (non-native) MBQL5 queries are not supported by the sync.');
    }

    private function walkNative(array $native): array
    {
        if (isset($native['query']) && is_string($native['query']) && trim($native['query']) !== '') {
            $this->warnings[] = 'native SQL query text was copied verbatim; verify table/column names exist in the target';
        }

        foreach ($native['template-tags'] ?? [] as $name => $tag) {
            if (isset($tag['dimension'])) {
                $native['template-tags'][$name]['dimension'] = $this->walk($tag['dimension']);
            }
        }

        return $native;
    }

    private function walk(mixed $node): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        if ($this->isList($node)) {
            return $this->walkList($node);
        }

        return $this->walkMap($node);
    }

    /** @param list<mixed> $node */
    private function walkList(array $node): array
    {
        // a ["field", id|name, opts] reference (legacy MBQL) or
        // a ["field", opts, id|name] reference (MBQL5 "lib" shape - opts before id).
        // Normalize to the simple legacy ["field", id, null] shape either way: that's
        // what create/update actually accept, and carrying over the source instance's
        // opts (which can include its own internal lib/uuid) buys nothing.
        if (($node[0] ?? null) === 'field') {
            foreach ([1, 2] as $i) {
                if (isset($node[$i]) && is_int($node[$i])) {
                    return ['field', $this->resolver->fieldId($node[$i]), null];
                }
            }

            // By-name field ref (native query column result, not a real field id) -
            // nothing to translate on the ref itself, but its opts map can still
            // nest a `source-field` / `fk-field-id` id that needs translating.
            foreach ([1, 2] as $i) {
                if (isset($node[$i]) && is_array($node[$i])) {
                    $node[$i] = $this->walkMap($node[$i]);
                }
            }

            return $node;
        }

        return array_map(fn ($child) => $this->walk($child), $node);
    }

    /** @param array<string, mixed> $node */
    private function walkMap(array $node): array
    {
        foreach ($node as $key => $value) {
            $node[$key] = match ($key) {
                'source-table'                            => $this->translateSourceTable($value),
                'source-field', 'fk-field-id', 'field-id' => is_int($value) ? $this->resolver->fieldId($value) : $this->walk($value),
                default                                   => $this->walk($value),
            };
        }

        return $node;
    }

    private function translateSourceTable(mixed $value): mixed
    {
        if (is_int($value)) {
            return $this->resolver->tableId($value);
        }

        if (is_string($value) && str_starts_with($value, 'card__')) {
            $sourceCardId = (int) substr($value, 6);
            $targetCardId = ($this->cardIdResolver)($sourceCardId);

            return 'card__'.$targetCardId;
        }

        return $value;
    }

    /** @param array<mixed> $array */
    private function isList(array $array): bool
    {
        return array_is_list($array);
    }
}
