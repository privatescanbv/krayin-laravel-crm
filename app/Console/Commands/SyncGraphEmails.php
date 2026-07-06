<?php

namespace App\Console\Commands;

use App\Services\Mail\GraphMailService;
use App\Services\Mail\MicrosoftGraphTokenService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Sentry\State\Scope;
use Webkul\Email\Enums\EmailFolderEnum;

use function Sentry\captureException;
use function Sentry\withScope;

class SyncGraphEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:sync-graph {--mailbox= : Sync only the mailbox with this key (e.g. privatescan or herniapoli)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync emails from Microsoft Graph API (all configured mailboxes)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(GraphMailService $graphService, MicrosoftGraphTokenService $tokenService)
    {
        $mailboxes = config('mail.mailboxes', []);

        if (empty($mailboxes)) {
            $this->error('No mailboxes configured in config/mail.php under mail.mailboxes');

            return Command::FAILURE;
        }

        $filterKey = $this->option('mailbox');

        $errors = 0;

        foreach ($mailboxes as $key => $mailboxConfig) {
            if ($filterKey && $filterKey !== $key) {
                continue;
            }

            $address = $mailboxConfig['address'] ?? null;
            $folderName = $mailboxConfig['folder_name'] ?? EmailFolderEnum::INBOX->value;

            if (empty($address)) {
                $this->warn("Mailbox '{$key}' has no address configured, skipping.");

                continue;
            }

            $this->info("Syncing mailbox [{$key}] {$address} ...");

            try {
                $tokenService->clearToken($key);
                $graphService->configureMailbox($address, $key, $folderName);
                $graphService->processMessagesFromAllFolders();

                $this->info('  -> Done.');
            } catch (Exception $e) {
                $this->error("  -> Failed: {$e->getMessage()}");

                Log::error('Graph mailbox sync failed', [
                    'mailbox'   => $key,
                    'address'   => $address,
                    'exception' => $e,
                ]);

                // Report the underlying exception to Sentry with mailbox context so the
                // actual cause is visible, instead of the generic scheduler
                // "Scheduled command [...] failed with exit code [1]" wrapper.
                withScope(function (Scope $scope) use ($e, $key, $address): void {
                    $scope->setTag('mailbox', $key);
                    $scope->setContext('mailbox', ['key' => $key, 'address' => $address]);
                    captureException($e);
                });

                $errors++;
            }
        }

        // A transient failure of a single mailbox runs every minute; the real
        // exceptions are already reported to Sentry above with full context, so
        // return SUCCESS to avoid the noisy scheduler-level "exit code 1" wrapper.
        if ($errors > 0) {
            $this->warn("{$errors} mailbox(es) failed to sync; see Sentry for details.");
        }

        return Command::SUCCESS;
    }
}
