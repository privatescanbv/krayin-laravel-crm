<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class AnalyticsSetupCommand extends Command
{
    /** Scripts die altijd opnieuw worden uitgevoerd (procedure + views zijn idempotent via DROP/CREATE OR REPLACE). */
    private const ALWAYS_RUN = [
        '003_sync_procedure.sql',
        '004_event.sql',
        '005_views.sql',
    ];

    /** Scripts die alleen bij eerste keer (of --force) worden uitgevoerd. */
    private const FIRST_RUN_ONLY = [
        '001_schema.sql',
        '002_dim_date.sql',
        '006_initieel.sql',
    ];

    protected $signature = 'analytics:setup {--force : Herlaad procedure en views ook als schema al bestaat}';

    protected $description = 'Initialiseert het analytics-schema (idempotent). Slaat initiële sync over als schema al bestaat, tenzij --force.';

    public function handle(): int
    {
        $basePath = database_path('analytics');
        $schemaExists = $this->analyticsSchemaExists();

        if ($schemaExists && ! $this->option('force')) {
            $this->info('Analytics-schema bestaat al. Herlaad procedure, event en views...');
            $scripts = self::ALWAYS_RUN;
        } else {
            $this->info($schemaExists ? 'Herinitialiseer analytics (--force)...' : 'Eerste keer inrichting analytics-schema...');
            $scripts = array_merge(self::FIRST_RUN_ONLY, self::ALWAYS_RUN);
            sort($scripts); // bestandsnamen bepalen de volgorde
        }

        foreach ($scripts as $filename) {
            $path = $basePath.DIRECTORY_SEPARATOR.$filename;

            if (! file_exists($path)) {
                $this->warn("Bestand niet gevonden, overgeslagen: {$filename}");

                continue;
            }

            $this->line("  → {$filename}");

            try {
                $this->runSqlFile($path);
            } catch (Throwable $e) {
                $this->error("Fout bij {$filename}: ".$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info('Analytics-setup voltooid.');

        return self::SUCCESS;
    }

    private function analyticsSchemaExists(): bool
    {
        try {
            DB::connection('analytics')->select('SELECT 1 FROM analytics.sync_watermark LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function runSqlFile(string $path): void
    {
        $sql = file_get_contents($path);

        // Vervang de hardcoded bronschema-naam door de geconfigureerde DB_DATABASE
        $crmDatabase = config('database.connections.mysql.database');
        $sql = str_replace('privatescan.', $crmDatabase.'.', $sql);

        // DELIMITER $$ blokken (stored procedures) worden als één geheel
        // doorgegeven via een multi-statement PDO-aanroep.
        $statements = $this->splitStatements($sql);

        $pdo = DB::connection('analytics')->getPdo();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') {
                continue;
            }

            $result = $pdo->query($stmt);
            // Consumeer alle result sets (nodig na CALL en UNION-queries)
            if ($result) {
                do {
                    $result->fetchAll();
                } while ($result->nextRowset());
            }
        }
    }

    /**
     * Splits SQL op ';' maar respecteert DELIMITER-blokken (stored procedures).
     *
     * @return string[]
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $delimiter = ';';
        $current = '';
        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            // Detecteer DELIMITER-omschakeling
            if (preg_match('/^DELIMITER\s+(\S+)/i', $trimmed, $m)) {
                $delimiter = $m[1];

                continue;
            }

            $current .= $line."\n";

            if ($delimiter !== ';' && str_contains($line, $delimiter)) {
                // Vervang het custom delimiter door een reguliere ; voor PDO
                $current = str_replace($delimiter, '', $current);
                $statements[] = $current;
                $current = '';
            } elseif ($delimiter === ';' && str_ends_with(rtrim($line), ';')) {
                $statements[] = $current;
                $current = '';
            }
        }

        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return $statements;
    }
}
