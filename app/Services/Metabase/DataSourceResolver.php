<?php

namespace App\Services\Metabase;

/**
 * Translates source database / table / field ids to the equivalent ids in the
 * target instance, matched by name (schema + table name, table + field name,
 * database name). Every lookup is cached. A missing match throws
 * {@see MetabaseSyncException} so the caller can abort before writing anything.
 */
class DataSourceResolver
{
    /** @var array<int, int> source db id => target db id */
    private array $databases = [];

    /** @var array<int, int> source table id => target table id */
    private array $tables = [];

    /** @var array<int, int> source field id => target field id */
    private array $fields = [];

    /** @var array<int, array{db_id: int, name: string, schema: string|null}> */
    private array $sourceTableCache = [];

    /** @var array<int, array{table_id: int, name: string}> */
    private array $sourceFieldCache = [];

    /** @var array<int, list<array{name: string, schema: string|null, id: int}>> target db id => tables */
    private array $targetTableCache = [];

    /** @var array<int, array<string, int>> target table id => [field name => id] */
    private array $targetFieldCache = [];

    /** @var list<array{name: string, id: int}>|null */
    private ?array $targetDatabaseCache = null;

    public function __construct(
        private readonly MetabaseClient $source,
        private readonly MetabaseClient $target,
    ) {}

    public function databaseId(int $sourceDatabaseId): int
    {
        return $this->databases[$sourceDatabaseId] ??= $this->resolveDatabase($sourceDatabaseId);
    }

    public function tableId(int $sourceTableId): int
    {
        return $this->tables[$sourceTableId] ??= $this->resolveTable($sourceTableId);
    }

    public function fieldId(int $sourceFieldId): int
    {
        return $this->fields[$sourceFieldId] ??= $this->resolveField($sourceFieldId);
    }

    /* --------------------------------------------------------------------- */

    private function resolveDatabase(int $sourceDatabaseId): int
    {
        $name = $this->sourceDatabaseName($sourceDatabaseId);

        foreach ($this->targetDatabases() as $db) {
            if ($this->equalsCi($db['name'], $name)) {
                return $db['id'];
            }
        }

        throw new MetabaseSyncException(
            "Database '{$name}' (source id {$sourceDatabaseId}) does not exist in target '{$this->target->label}'."
        );
    }

    private function resolveTable(int $sourceTableId): int
    {
        $table = $this->sourceTable($sourceTableId);
        $targetDbId = $this->databaseId($table['db_id']);

        foreach ($this->targetTables($targetDbId) as $candidate) {
            if ($this->equalsCi($candidate['name'], $table['name'])
                && $this->equalsCi((string) $candidate['schema'], (string) $table['schema'])) {
                return $candidate['id'];
            }
        }

        $schema = $table['schema'] ? $table['schema'].'.' : '';

        throw new MetabaseSyncException(
            "Table '{$schema}{$table['name']}' (source id {$sourceTableId}) does not exist in target '{$this->target->label}'."
        );
    }

    private function resolveField(int $sourceFieldId): int
    {
        $field = $this->sourceField($sourceFieldId);
        $targetTableId = $this->tableId($field['table_id']);

        $byName = $this->targetFieldCache[$targetTableId] ??= $this->loadTargetFields($targetTableId);

        $key = mb_strtolower($field['name']);

        if (! isset($byName[$key])) {
            throw new MetabaseSyncException(
                "Field '{$field['name']}' (source id {$sourceFieldId}) does not exist in the matching target table."
            );
        }

        return $byName[$key];
    }

    /* ---- source lookups ------------------------------------------------- */

    private function sourceDatabaseName(int $id): string
    {
        foreach ($this->source->listDatabases() as $db) {
            if ((int) ($db['id'] ?? 0) === $id) {
                return (string) ($db['name'] ?? '');
            }
        }

        throw new MetabaseSyncException("Database id {$id} not found in source '{$this->source->label}'.");
    }

    /** @return array{db_id: int, name: string, schema: string|null} */
    private function sourceTable(int $id): array
    {
        return $this->sourceTableCache[$id] ??= (function () use ($id): array {
            $table = $this->source->getTable($id);

            return [
                'db_id'  => (int) ($table['db_id'] ?? 0),
                'name'   => (string) ($table['name'] ?? ''),
                'schema' => $table['schema'] ?? null,
            ];
        })();
    }

    /** @return array{table_id: int, name: string} */
    private function sourceField(int $id): array
    {
        return $this->sourceFieldCache[$id] ??= (function () use ($id): array {
            $field = $this->source->getField($id);

            return [
                'table_id' => (int) ($field['table_id'] ?? 0),
                'name'     => (string) ($field['name'] ?? ''),
            ];
        })();
    }

    /* ---- target caches ------------------------------------------------- */

    /** @return list<array{name: string, id: int}> */
    private function targetDatabases(): array
    {
        return $this->targetDatabaseCache ??= array_map(
            fn (array $db): array => ['name' => (string) ($db['name'] ?? ''), 'id' => (int) ($db['id'] ?? 0)],
            $this->target->listDatabases(),
        );
    }

    /** @return list<array{name: string, schema: string|null, id: int}> */
    private function targetTables(int $targetDbId): array
    {
        return $this->targetTableCache[$targetDbId] ??= array_map(
            fn (array $t): array => [
                'name'   => (string) ($t['name'] ?? ''),
                'schema' => $t['schema'] ?? null,
                'id'     => (int) ($t['id'] ?? 0),
            ],
            $this->target->getDatabaseMetadata($targetDbId)['tables'] ?? [],
        );
    }

    /** @return array<string, int> lowercased field name => id */
    private function loadTargetFields(int $targetTableId): array
    {
        $map = [];

        foreach ($this->target->getTableQueryMetadata($targetTableId)['fields'] ?? [] as $field) {
            $map[mb_strtolower((string) ($field['name'] ?? ''))] = (int) ($field['id'] ?? 0);
        }

        return $map;
    }

    private function equalsCi(string $a, string $b): bool
    {
        return mb_strtolower($a) === mb_strtolower($b);
    }
}
