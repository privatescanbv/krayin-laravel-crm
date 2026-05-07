<?php

use App\Enums\EmailTemplateCode;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\EmailTemplate\Models\EmailTemplate;

uses(RefreshDatabase::class);

test('all EmailTemplateCode cases are present in the database after seeding', function () {
    $this->seed(EmailTemplateSeeder::class);

    foreach (EmailTemplateCode::cases() as $case) {
        expect(EmailTemplate::where('code', $case->value)->exists())
            ->toBeTrue("Template met code '{$case->value}' niet gevonden na seeden.");
    }
});
