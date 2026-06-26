<?php

namespace App\Console\Commands;

use App\Services\Mail\GraphMailService;
use App\Services\Mail\MicrosoftGraphTokenService;
use Exception;
use Illuminate\Console\Command;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;

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
    public function __construct(
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository,
        protected MicrosoftGraphTokenService $tokenService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(GraphMailService $graphService)
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
            $folderName = $mailboxConfig['folder_name'] ?? 'inbox';

            if (empty($address)) {
                $this->warn("Mailbox '{$key}' has no address configured, skipping.");
                continue;
            }

            $this->info("Syncing mailbox [{$key}] {$address} ...");

            try {
                $graphService->configureMailbox($address, $key, $folderName);
                $graphService->processMessagesFromAllFolders();

                $this->info("  -> Done.");
            } catch (Exception $e) {
                $this->error("  -> Failed: {$e->getMessage()}");
                $errors++;
            }
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
