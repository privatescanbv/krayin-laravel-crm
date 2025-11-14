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
                $this->info('No import runs found. Skipping report.');

                return 0;
            }
        }

        $this->info("Preparing import run report for run #{$importRun->id} ({$importRun->import_type})...");

        // Prepare email content
        $subject = "Import Run Report #{$importRun->id} - {$importRun->import_type}";
        $logs = $importRun->importLogs()->orderBy('created_at')->get();

        // Count logs by level
        $errorCount = $logs->where('level', 'error')->count();
        $warningCount = $logs->where('level', 'warning')->count();
        $infoCount = $logs->where('level', 'info')->count();

        // Format dates
        $startedAt = $importRun->started_at ? $importRun->started_at->format('d-m-Y H:i:s') : 'N/A';
        $completedAt = $importRun->completed_at ? $importRun->completed_at->format('d-m-Y H:i:s') : 'N/A';

        // Determine status color
        $statusColor = match ($importRun->status) {
            'completed' => '#10b981', // green
            'running'   => '#3b82f6',   // blue
            'failed'    => '#ef4444',     // red
            default     => '#6b7280',      // gray
        };

        // Build HTML email body
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px 8px 0 0; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .content { background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
                .info-item { padding: 15px; background: #f9fafb; border-radius: 6px; border-left: 3px solid #667eea; }
                .info-label { font-size: 12px; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px; }
                .info-value { font-size: 16px; color: #111827; font-weight: 500; }
                .status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; color: white; background: {$statusColor}; }
                .summary-section { background: #f9fafb; padding: 25px; border-radius: 8px; margin-top: 30px; }
                .summary-title { font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; }
                .summary-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
                .summary-card { background: white; padding: 20px; border-radius: 6px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .summary-number { font-size: 32px; font-weight: 700; margin-bottom: 5px; }
                .summary-label { font-size: 14px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
                .success { color: #10b981; }
                .error { color: #ef4444; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📊 Import Run Report</h1>
                </div>
                <div class='content'>
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-label'>Import Run ID</div>
                            <div class='info-value'>#{$importRun->id}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Import Type</div>
                            <div class='info-value'>{$importRun->import_type}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Status</div>
                            <div class='info-value'><span class='status-badge'>{$importRun->status}</span></div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Started At</div>
                            <div class='info-value'>{$startedAt}</div>
                        </div>
                        <div class='info-item' style='grid-column: 1 / -1;'>
                            <div class='info-label'>Completed At</div>
                            <div class='info-value'>{$completedAt}</div>
                        </div>
                    </div>

                    <div class='summary-section'>
                        <div class='summary-title'>📈 Import Overzicht</div>
                        <div class='summary-grid'>
                            <div class='summary-card'>
                                <div class='summary-number success'>{$importRun->records_imported}</div>
                                <div class='summary-label'>Succesvol Geïmporteerd</div>
                            </div>
                            <div class='summary-card'>
                                <div class='summary-number error'>{$importRun->records_errored}</div>
                                <div class='summary-label'>Fouten</div>
                            </div>
                            <div class='summary-card'>
                                <div class='summary-number' style='color: #6b7280;'>{$importRun->records_processed}</div>
                                <div class='summary-label'>Totaal Verwerkt</div>
                            </div>
                            <div class='summary-card'>
                                <div class='summary-number' style='color: #f59e0b;'>{$importRun->records_skipped}</div>
                                <div class='summary-label'>Overgeslagen</div>
                            </div>
                        </div>
                    </div>

                    <div class='footer'>
                        <p>Voor gedetailleerde logs, bekijk de import run in het CRM systeem.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        // Try to send email, but don't fail if mail is not configured
        try {
            $this->info("Sending report to {$email}...");
            Mail::html($htmlBody, function ($message) use ($email, $subject) {
                $message->to($email)
                    ->subject($subject);
            });

            $this->info('✓ Import run report sent successfully!');

            return 0;
        } catch (Exception $e) {
            $this->warn('⚠ Could not send email (mail not configured): '.$e->getMessage());
            $this->info('💡 Report is saved in database and can be viewed via the admin panel.');
            $this->newLine();
            $this->info('Summary:');
            $this->info("  Import Run: #{$importRun->id} ({$importRun->import_type})");
            $this->info("  Status: {$importRun->status}");
            $this->info("  Errors: {$errorCount}, Warnings: {$warningCount}, Info: {$infoCount}");

            // Log the report to Laravel log as fallback
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

            return 0; // Don't fail the script if email fails
        }
    }
}
