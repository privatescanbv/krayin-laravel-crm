<?php

namespace App\Console\Commands;

use App\Models\ImportRun;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendImportRunReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:send-report
                            {--import-run-id= : Specific import run ID to report (defaults to all runs)}
                            {--limit= : Limit number of runs to include (defaults to all)}
                            {--email=mark.bulthuis@mbsoftware.nl,mark.klaucke@digi4you.nl : Email address(es) to send report to (comma-separated)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send import run report with summary of all import runs via email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $importRunId = $this->option('import-run-id');
        $limit = $this->option('limit');
        $emailOption = $this->option('email');

        // Parse email addresses (support comma-separated list)
        $emails = array_map('trim', explode(',', $emailOption));

        // Get import runs
        if ($importRunId) {
            $importRuns = ImportRun::with('importLogs')->where('id', $importRunId)->get();
            if ($importRuns->isEmpty()) {
                $this->error("Import run with ID {$importRunId} not found.");

                return 1;
            }
        } else {
            $query = ImportRun::with('importLogs')->latest('id');
            if ($limit) {
                $query->limit((int) $limit);
            }
            $importRuns = $query->get();

            if ($importRuns->isEmpty()) {
                $this->info('No import runs found. Skipping report.');

                return 0;
            }
        }

        $this->info("Preparing import run report for {$importRuns->count()} run(s)...");

        // Prepare email content
        $subject = $importRuns->count() === 1
            ? "Import Run Report #{$importRuns->first()->id} - {$importRuns->first()->import_type}"
            : "Import Run Report - {$importRuns->count()} runs";

        // Build HTML email body with compact overview
        $runsHtml = '';
        foreach ($importRuns as $importRun) {
            $startedAt = $importRun->started_at ? $importRun->started_at->format('d-m-Y H:i') : 'N/A';
            $completedAt = $importRun->completed_at ? $importRun->completed_at->format('d-m-Y H:i') : 'N/A';

            // Determine status color
            $statusColor = match ($importRun->status) {
                'completed' => '#10b981', // green
                'running'   => '#3b82f6',   // blue
                'failed'    => '#ef4444',     // red
                default     => '#6b7280',      // gray
            };

            $runsHtml .= "
                <div class='run-item'>
                    <div class='run-header'>
                        <div class='run-id'>#{$importRun->id}</div>
                        <div class='run-type'>{$importRun->import_type}</div>
                        <div class='run-status' style='background: {$statusColor};'>{$importRun->status}</div>
                    </div>
                    <div class='run-stats'>
                        <span class='stat success'>{$importRun->records_imported} ✓</span>
                        <span class='stat error'>{$importRun->records_errored} ✗</span>
                        <span class='stat'>{$importRun->records_processed} verwerkt</span>
                        <span class='stat'>{$importRun->records_skipped} overgeslagen</span>
                    </div>
                    <div class='run-dates'>
                        <span>{$startedAt}</span>
                        <span>→</span>
                        <span>{$completedAt}</span>
                    </div>
                </div>
            ";
        }

        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px 8px 0 0; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .content { background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; }
                .runs-container { display: flex; flex-direction: column; gap: 12px; }
                .run-item { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; }
                .run-header { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; }
                .run-id { font-weight: 700; color: #111827; font-size: 16px; }
                .run-type { color: #6b7280; font-size: 14px; flex: 1; }
                .run-status { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; color: white; text-transform: uppercase; }
                .run-stats { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 8px; }
                .stat { font-size: 13px; color: #6b7280; }
                .stat.success { color: #10b981; font-weight: 600; }
                .stat.error { color: #ef4444; font-weight: 600; }
                .run-dates { display: flex; gap: 8px; font-size: 12px; color: #9ca3af; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📊 Import Run Overzicht</h1>
                </div>
                <div class='content'>
                    <div class='runs-container'>
                        {$runsHtml}
                    </div>

                    <div class='footer'>
                        <p>Voor gedetailleerde logs, bekijk de import runs in het CRM systeem.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        // Try to send email, but don't fail if mail is not configured
        try {
            $emailList = implode(', ', $emails);
            $this->info("Sending report to {$emailList}...");
            Mail::html($htmlBody, function ($message) use ($emails, $subject) {
                $message->to($emails)
                    ->subject($subject);
            });

            $this->info('✓ Import run report sent successfully!');

            return 0;
        } catch (Exception $e) {
            $this->warn('⚠ Could not send email (mail not configured): '.$e->getMessage());
            $this->info('💡 Report is saved in database and can be viewed via the admin panel.');
            $this->newLine();
            $this->info('Summary:');
            foreach ($importRuns as $importRun) {
                $logs = $importRun->importLogs;
                $errorCount = $logs->where('level', 'error')->count();
                $warningCount = $logs->where('level', 'warning')->count();
                $infoCount = $logs->where('level', 'info')->count();

                $this->info("  Run #{$importRun->id} ({$importRun->import_type}): {$importRun->status}");
                $this->info("    Imported: {$importRun->records_imported}, Errors: {$importRun->records_errored}, Processed: {$importRun->records_processed}");
            }

            // Log the report to Laravel log as fallback
            foreach ($importRuns as $importRun) {
                $logs = $importRun->importLogs;
                $errorCount = $logs->where('level', 'error')->count();
                $warningCount = $logs->where('level', 'warning')->count();
                $infoCount = $logs->where('level', 'info')->count();

                Log::info("Import Run Report #{$importRun->id}", [
                    'import_type'        => $importRun->import_type,
                    'status'             => $importRun->status,
                    'records_processed'  => $importRun->records_processed,
                    'records_imported'   => $importRun->records_imported,
                    'records_skipped'    => $importRun->records_skipped,
                    'records_errored'    => $importRun->records_errored,
                    'error_count'        => $errorCount,
                    'warning_count'      => $warningCount,
                    'info_count'         => $infoCount,
                ]);
            }

            return 0; // Don't fail the script if email fails
        }
    }
}
