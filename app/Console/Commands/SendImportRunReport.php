<?php

namespace App\Console\Commands;

use App\Models\ImportRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendImportRunReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:send-report 
                            {--import-run-id= : Specific import run ID to report (defaults to latest)}
                            {--email=mark.bulthuis@mbsoftware.nl : Email address to send report to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send import run report with all logs via email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $importRunId = $this->option('import-run-id');
        $email = $this->option('email');

        // Get the import run
        if ($importRunId) {
            $importRun = ImportRun::with('importLogs')->find($importRunId);
            if (! $importRun) {
                $this->error("Import run with ID {$importRunId} not found.");

                return 1;
            }
        } else {
            $importRun = ImportRun::with('importLogs')->latest('id')->first();
            if (! $importRun) {
                $this->error('No import runs found.');

                return 1;
            }
        }

        $this->info("Sending import run report for run #{$importRun->id} ({$importRun->import_type}) to {$email}...");

        // Prepare email content
        $subject = "Import Run Report #{$importRun->id} - {$importRun->import_type}";
        $logs = $importRun->importLogs()->orderBy('created_at')->get();

        // Count logs by level
        $errorCount = $logs->where('level', 'error')->count();
        $warningCount = $logs->where('level', 'warning')->count();
        $infoCount = $logs->where('level', 'info')->count();

        // Build email body
        $body = "Import Run Report\n";
        $body .= "=================\n\n";
        $body .= "Import Run ID: {$importRun->id}\n";
        $body .= "Import Type: {$importRun->import_type}\n";
        $body .= "Status: {$importRun->status}\n";
        $body .= "Started At: {$importRun->started_at}\n";
        $body .= "Completed At: {$importRun->completed_at}\n\n";
        $body .= "Statistics:\n";
        $body .= "-----------\n";
        $body .= "Records Processed: {$importRun->records_processed}\n";
        $body .= "Records Imported: {$importRun->records_imported}\n";
        $body .= "Records Skipped: {$importRun->records_skipped}\n";
        $body .= "Records Errored: {$importRun->records_errored}\n\n";
        $body .= "Logs Summary:\n";
        $body .= "-------------\n";
        $body .= "Errors: {$errorCount}\n";
        $body .= "Warnings: {$warningCount}\n";
        $body .= "Info: {$infoCount}\n\n";

        if ($logs->isEmpty()) {
            $body .= "No logs found for this import run.\n";
        } else {
            $body .= "Detailed Logs:\n";
            $body .= "==============\n\n";

            foreach ($logs as $log) {
                $body .= "[{$log->level}] {$log->created_at}\n";
                $body .= "Message: {$log->message}\n";
                if ($log->record_id) {
                    $body .= "Record ID: {$log->record_id}\n";
                }
                if ($log->context) {
                    $body .= "Context: ".json_encode($log->context, JSON_PRETTY_PRINT)."\n";
                }
                $body .= str_repeat('-', 80)."\n\n";
            }
        }

        // Send email
        try {
            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)
                    ->subject($subject);
            });

            $this->info('✓ Import run report sent successfully!');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to send email: '.$e->getMessage());

            return 1;
        }
    }
}