<?php

namespace App\Console\Commands;

use App\Enums\Departments;
use App\Mail\RevOpsNoLeadAlert;
use App\Models\Department;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webkul\Lead\Models\Lead;

class RevOpsCheckLeadActivity extends Command
{
    protected $signature = 'revops:check-lead-activity';

    protected $description = 'Alert configured recipients when no lead has been created for a department in 24 hours.';

    public function handle(): int
    {
        $recipientConfig = (string) config('revops.no_lead_alert_recipients', '');

        if (empty($recipientConfig)) {
            $this->info('RevOps lead alert disabled (REVOPS_NO_LEAD_ALERT_RECIPIENTS not set).');

            return Command::SUCCESS;
        }

        $recipients = array_values(array_filter(array_map('trim', explode(',', $recipientConfig))));

        foreach (Departments::cases() as $department) {
            $this->checkDepartment($department, $recipients);
        }

        return Command::SUCCESS;
    }

    private function checkDepartment(Departments $department, array $recipients): void
    {
        $departmentId = Department::query()
            ->where('name', $department->value)
            ->value('id');

        if (! $departmentId) {
            Log::warning('RevOps: department not found in database, skipping.', ['department' => $department->value]);

            return;
        }

        $hasRecentLead = Lead::query()
            ->where('department_id', $departmentId)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        $cacheKey = "revops.no_lead_alert.{$department->key()}.sent";

        if ($hasRecentLead) {
            Cache::forget($cacheKey);

            return;
        }

        if (Cache::has($cacheKey)) {
            $this->info("RevOps: alert already sent for {$department->value}, skipping.");

            return;
        }

        $lastLead = Lead::query()
            ->where('department_id', $departmentId)
            ->latest()
            ->first();

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new RevOpsNoLeadAlert($department, $lastLead));
        }

        // Suppress repeat alerts for 23 h so the next 24-h gap triggers again
        Cache::put($cacheKey, true, now()->addHours(23));

        Log::info("RevOps: no-lead alert sent for {$department->value}.", [
            'recipients'   => $recipients,
            'last_lead_at' => $lastLead?->created_at,
        ]);

        $this->info("RevOps: alert sent for {$department->value} → ".implode(', ', $recipients));
    }
}
