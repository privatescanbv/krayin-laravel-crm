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

        if (isset($datasetQuery['database']) && is_int($datasetQuery['database'])) {
            $datasetQuery['database'] = $this->resolver->databaseId($datasetQuery['database']);
        }

        $type = $datasetQuery['type'] ?? null;

        if ($type === 'query' && isset($datasetQuery['query'])) {
            $datasetQuery['query'] = $this->walk($datasetQuery['query']);
        }

        if ($type === 'native' && isset($datasetQuery['native'])) {
            $datasetQuery['native'] = $this->walkNative($datasetQuery['native']);
        }

        return $datasetQuery;
    }

    /* --------------------------------------------------------------------- */

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
        // a ["field", id|name, opts] reference
        if (($node[0] ?? null) === 'field') {
            if (isset($node[1]) && is_int($node[1])) {
                $node[1] = $this->resolver->fieldId($node[1]);
            }
            if (isset($node[2]) && is_array($node[2])) {
                $node[2] = $this->walkMap($node[2]);
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
