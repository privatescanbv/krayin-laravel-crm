<?php

namespace App\Console\Commands;

use App\Enums\EmailTemplateCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webkul\EmailTemplate\Models\EmailTemplate;

class VerifyEmailTemplateCodes extends Command
{
    protected $signature = 'email-templates:verify-codes';

    protected $description = 'Verify all required email template codes exist in the database.';

    public function handle(): int
    {
        $existing = EmailTemplate::whereNotNull('code')->pluck('code')->all();

        $missing = collect(EmailTemplateCode::cases())
            ->filter(fn ($case) => ! in_array($case->value, $existing))
            ->map(fn ($case) => $case->value)
            ->values()
            ->all();

        if (empty($missing)) {
            $this->info('All email template codes present.');

            return Command::SUCCESS;
        }

        Log::error('Email template codes missing from database', [
            'missing_codes' => $missing,
            'missing_count' => count($missing),
        ]);

        $this->error('Missing codes: '.implode(', ', $missing));

        return Command::FAILURE;
    }
}
