<?php

namespace App\Console\Commands;

use App\Enums\PersonGender;
use App\Enums\PersonSalutation;
use App\Models\ImportLog;
use App\Models\ImportRun;
use DateTimeInterface;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Webkul\Core\Contracts\Validations\EmailValidator;
use Webkul\Core\Contracts\Validations\PhoneValidator;

abstract class AbstractSugarCRMImport extends Command
{
    protected ?ImportRun $currentImportRun = null;

    /**
     * Verbose info helper (-v)
     */
    public function infoV(string $message): void
    {
        if ($this->output->isVerbose()) {
            $this->info($message);
        }
    }

    /**
     * Very verbose info helper (-vv)
     */
    public function infoVV(string $message): void
    {
        if ($this->output->isVeryVerbose()) {
            $this->info($message);
        }
    }

    /**
     * @throws Exception if any of the specified tables do not exist
     */
    public function validateTableExists(string $connection, array $tables): void
    {
        foreach ($tables as $table) {
            if (! Schema::connection($connection)->hasTable($table)) {
                throw new Exception("Missing table: {$table}");
            }
        }
    }

    /**
     * Parse SugarCRM date format to our timezone
     */
    protected function parseSugarDate($value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            // Accept Carbon, DateTimeInterface, or string
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
            }

            // Parse SugarCRM date assuming it's already in the application timezone
            // SugarCRM dates appear to be stored in local time, not UTC
            return Carbon::parse((string) $value, config('app.timezone'))
                ->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Create an entity with proper timestamps from SugarCRM data
     *
     * @param  string  $modelClass  The model class to create
     * @param  array  $data  The entity data
     * @param  array  $timestamps  The timestamps to set (created_at, updated_at)
     * @return mixed The created entity
     */
    protected function createEntityWithTimestamps(string $modelClass, array $data, array $timestamps = [])
    {
        // Create entity without timestamps to avoid auto-override
        $entity = new $modelClass($data);
        $entity->timestamps = false;

        // Set custom timestamps if provided
        if (! empty($timestamps['created_at'])) {
            $entity->setAttribute('created_at', $timestamps['created_at']);
        }
        if (! empty($timestamps['updated_at'])) {
            $entity->setAttribute('updated_at', $timestamps['updated_at']);
        }

        // Save without triggering timestamps
        $entity->saveQuietly();

        // Re-enable timestamps for future operations
        $entity->timestamps = true;

        return $entity;
    }

    /**
     * Test database connection
     */
    protected function testConnection(string $connection): void
    {
        $this->info('Testing database connection...');
        DB::connection($connection)->getPdo();
        $this->info('✓ Database connection successful');
    }

    /**
     * Start a new import run
     */
    protected function startImportRun(string $importType): ImportRun
    {
        $this->currentImportRun = ImportRun::create([
            'started_at'  => now(),
            'status'      => 'running',
            'import_type' => $importType,
        ]);

        $this->info("📊 Import Run #{$this->currentImportRun->id} started");

        return $this->currentImportRun;
    }

    /**
     * Complete the current import run
     */
    protected function completeImportRun(array $stats = []): void
    {
        if ($this->currentImportRun) {
            $this->currentImportRun->update([
                'completed_at'       => now(),
                'status'             => 'completed',
                'records_processed'  => $stats['processed'] ?? 0,
                'records_imported'   => $stats['imported'] ?? 0,
                'records_skipped'    => $stats['skipped'] ?? 0,
                'records_errored'    => $stats['errored'] ?? 0,
            ]);
        }
    }

    /**
     * Fail the current import run
     */
    protected function failImportRun(string $reason = ''): void
    {
        if ($this->currentImportRun) {
            $this->currentImportRun->update([
                'completed_at' => now(),
                'status'       => 'failed',
            ]);

            if ($reason) {
                $this->logImportError($reason);
            }
        }
    }

    /**
     * Log warning with context to database and console
     */
    public function warn($string, $verbosity = null): void
    {
        parent::warn($string, $verbosity);
        $this->logImportWarning($string);
    }

    public function info($string, $verbosity = null): void
    {
        parent::info($string, $verbosity);
        $this->logImportInfo($string);
    }

    public function error($string, $verbosity = null): void
    {
        parent::error($string, $verbosity);
        $this->logImportError($string);
    }

    /**
     * Log import error to database
     */
    protected function logImportError(string $message, array $context = []): void
    {
        $this->logImport($message, $context, 'error');
    }

    /**
     * Log import warning to database
     */
    protected function logImportWarning(string $message, array $context = []): void
    {
        $this->logImport($message, $context, 'warning');
    }

    /**
     * Log import info to database
     */
    protected function logImportInfo(string $message, array $context = []): void
    {
        $this->logImport($message, $context, 'info');
    }

    /**
     * Map Sugar gender string to PersonGender enum.
     */
    protected function mapGenderFromSugar(?string $sugarGender): ?PersonGender
    {
        if (! $sugarGender) {
            return null;
        }

        return match (strtolower(trim($sugarGender))) {
            'male', 'm' => PersonGender::Man,
            'female', 'f' => PersonGender::Female,
            default => null,
        };
    }

    /**
     * Derive salutation from PersonGender enum.
     */
    protected function mapSalutationFromGender(?PersonGender $gender): ?PersonSalutation
    {
        if (! $gender) {
            return null;
        }

        return match ($gender) {
            PersonGender::Man    => PersonSalutation::Dhr,
            PersonGender::Female => PersonSalutation::Mevr,
            default              => null,
        };
    }

    /**
     * Execute import with automatic webhook management
     *
     * @param  bool  $dryRun  Whether this is a dry run
     * @param  callable  $importLogic  The import logic to execute
     * @return mixed The result of the import logic
     */
    protected function executeImport(bool $dryRun, callable $importLogic)
    {
        // Disable webhooks during import to prevent external notifications
        if (! $dryRun) {
            $this->disableWebhooks();
        }

        try {
            return $importLogic();
        } finally {
            // Always re-enable webhooks after import, even if an error occurred
            if (! $dryRun) {
                $this->enableWebhooks();
            }
        }
    }

    /**
     * Sanitize a raw phone input coming from SugarCRM by stripping any textual labels
     * appended to the number (e.g. "+31612345678 (prive)" or "prive +31612345678").
     *
     * Returns an array with [label, value]. The label is derived from any
     * detected keywords if present; otherwise the provided default label is used.
     *
     * Note: This method is intentionally conservative and does NOT normalize local
     * formats like "010-123" to keep backward compatibility with existing data and tests.
     */
    protected function sanitizePhoneAndInferLabel(?string $raw, string $defaultLabel): array
    {
        try {
            $value = trim($raw ?? '');
            if ($value === '') {
                return [$defaultLabel, $value];
            }

            // Known label keywords and their mapped labels used in our system
            $labelMap = [
                'prive'  => 'home',
                'privé'  => 'home',
                'werk'   => 'work',
                'work'   => 'work',
                'thuis'  => 'home',
                'home'   => 'home',
                'mobiel' => 'mobile',
                'mobile' => 'mobile',
                'gsm'    => 'mobile',
                'other'  => 'other',
                'overig' => 'other',
            ];

            $detectedLabel = $defaultLabel;

            // 1) Remove parenthesized label fragments like "(prive)" or "(werk)"
            $value = preg_replace_callback('/\(([^)]*)\)/u', function ($m) use (&$detectedLabel, $labelMap) {
                $inner = strtolower(trim($m[1] ?? ''));
                if ($inner !== '' && isset($labelMap[$inner])) {
                    $detectedLabel = $labelMap[$inner];
                }

                return '';
            }, $value);

            // 2) Check for label words at start or end and strip them
            foreach ($labelMap as $keyword => $mapped) {
                // Start: "prive +31..." or "mobiel 06-..."
                if (preg_match('/^\s*'.preg_quote($keyword, '/').'\s+/iu', $value)) {
                    $detectedLabel = $mapped;
                    $value = preg_replace('/^\s*'.preg_quote($keyword, '/').'\s+/iu', '', $value);
                }

                // End: "+31... prive" or "+31... mobiel"
                if (preg_match('/\s+'.preg_quote($keyword, '/').'\s*$/iu', $value)) {
                    $detectedLabel = $mapped;
                    $value = preg_replace('/\s+'.preg_quote($keyword, '/').'\s*$/iu', '', $value);
                }
            }

            // 3) Collapse extra whitespace and trim common trailing punctuation leftover
            $value = preg_replace('/\s{2,}/u', ' ', $value ?? '');
            $value = trim($value, " \t\n\r\0\x0B-;:,.");

            // 4) Normalize Dutch mobile 06 numbers to +316XXXXXXXX
            $digitsOnly = preg_replace('/\D+/', '', $value);
            if ($digitsOnly !== null && $digitsOnly !== '') {
                // If it looks like a Dutch mobile starting with 06 and followed by 8 digits
                if (preg_match('/^06(\d{8})$/', $digitsOnly, $m)) {
                    $value = '+316'.$m[1];
                    $detectedLabel = 'mobile';
                } elseif (preg_match('/^06/', $digitsOnly)) {
                    // 06 present but not in valid 06XXXXXXXX format -> invalid
                    throw new Exception('Ongeldig 06-nummer: verwacht exact 8 cijfers na 06');
                }
            }

            // 5) Normalize E.164 formatting: keep leading '+' and strip all non-digits afterwards
            //    This turns "+31 6 12 34 56 78" into "+31612345678" before validation.
            if (str_starts_with($value, '+')) {
                $value = '+'.preg_replace('/\D+/', '', substr($value, 1));
                $validator = new PhoneValidator;
                $failed = false;
                $failMessage = '';
                $validator->validate('phone', $value, function ($message) use (&$failed, &$failMessage) {
                    $failed = true;
                    $failMessage = (string) $message;
                });

                if ($failed) {
                    throw new Exception($failMessage ?: 'Ongeldig telefoonnummer');
                }
            }

            return [$detectedLabel, $value];
        } catch (Exception $e) {
            throw new Exception($e->getMessage().' With: '.$raw.' Label: '.$defaultLabel);
        }
    }

    /**
     * Validate email using EmailValidator and throw with raw context on failure
     */
    protected function validateEmailOrFail(?string $email, ?string $which = null): void
    {
        if ($email === null || $email === '') {
            return;
        }

        $validator = new EmailValidator;
        $failed = false;
        $failMessage = '';
        $validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = (string) $message;
        });

        if ($failed) {
            $prefix = 'Ongeldig e-mailadres';
            if ($which) {
                $prefix .= ' ('.$which.') tijdens import';
            }

            throw new Exception(($prefix ?: 'Ongeldig e-mailadres').' With: '.$email);
        }
    }

    private function logImport(string $message, array $context = [], string $logLevel = 'error'): void
    {
        if ($this->ensureLogRunHasStarted()) {
            ImportLog::create([
                'import_run_id' => $this->currentImportRun->id,
                'level' => $logLevel,
                'message' => $message,
                'context' => $context,
                'record_id' => $context['record_id'] ?? null,
            ]);
        }
    }

    /**
     * Disable webhooks during import operations
     */
    private function disableWebhooks(): void
    {
        $originalState = config('webhook.enabled', true);
        Config::set('webhook.enabled', false);

        $this->info('🔕 Webhooks disabled for import operation');
        $this->info('   Original state: '.($originalState ? 'enabled' : 'disabled'));
    }

    /**
     * Re-enable webhooks after import operations
     */
    private function enableWebhooks(): void
    {
        Config::set('webhook.enabled', true);
        $this->info('🔔 Webhooks re-enabled after import operation');
    }

    private function ensureLogRunHasStarted(): bool
    {
        if (! $this->currentImportRun) {
            parent::warn('Logging, will import operation has not been started yet');
        }
        return !is_null($this->currentImportRun);
    }
}
