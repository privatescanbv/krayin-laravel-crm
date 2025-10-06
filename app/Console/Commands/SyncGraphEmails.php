<?php

namespace App\Console\Commands;

use App\Services\Mail\GraphMailService;
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
    protected $signature = 'emails:sync-graph';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync emails from Microsoft Graph API';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting Microsoft Graph email sync...');

        try {
            $graphService = new GraphMailService($this->emailRepository, $this->attachmentRepository);
            $graphService->processMessagesFromAllFolders();

            $this->info('Microsoft Graph email sync completed successfully.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Microsoft Graph email sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
