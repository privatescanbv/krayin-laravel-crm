<?php

use App\Enums\EmailTemplateCode;
use Database\Seeders\TestSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Webkul\EmailTemplate\Models\EmailTemplate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('it succeeds when all email template codes are present', function () {
    Log::spy();

    $this->artisan('email-templates:verify-codes')
        ->assertExitCode(Command::SUCCESS);

    Log::shouldNotHaveReceived('error');
});

test('it fails and logs when one email template code is missing', function () {
    Log::spy();

    EmailTemplate::where('code', EmailTemplateCode::PATIENT_PORTAL_NOTIFICATION->value)->delete();

    $this->artisan('email-templates:verify-codes')
        ->assertExitCode(Command::FAILURE);

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Email template codes missing from database', [
            'missing_codes' => [EmailTemplateCode::PATIENT_PORTAL_NOTIFICATION->value],
            'missing_count' => 1,
        ]);
});

test('it fails and logs when all email template codes are missing', function () {
    Log::spy();

    EmailTemplate::whereNotNull('code')->delete();

    $this->artisan('email-templates:verify-codes')
        ->assertExitCode(Command::FAILURE);

    $missingCodes = collect(EmailTemplateCode::cases())
        ->map(fn ($case) => $case->value)
        ->values()
        ->all();

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Email template codes missing from database', [
            'missing_codes' => $missingCodes,
            'missing_count' => count($missingCodes),
        ]);
});
