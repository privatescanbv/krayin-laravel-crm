<?php

use App\Enums\EmailTemplateCode;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Database\QueryException;
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

test('EmailTemplateSeeder bevat geen duplicate codes', function () {
    $reflection = new ReflectionClass(EmailTemplateSeeder::class);
    $method = $reflection->getMethod('templates');
    $method->setAccessible(true);
    $templates = $method->invoke(null);

    $codes = collect($templates)
        ->pluck('code')
        ->filter(fn ($code) => $code !== null)
        ->values();

    expect($codes->count())->toBe(
        $codes->unique()->count(),
        'Duplicate codes gevonden: '.implode(', ', $codes->duplicates()->all())
    );
});

test('email_templates tabel handhaaft unieke code constraint', function () {
    EmailTemplate::create([
        'name'    => 'Template A',
        'subject' => 'Subject A',
        'content' => 'Content A',
        'code'    => 'test-unique-code',
    ]);

    expect(fn () => EmailTemplate::create([
        'name'    => 'Template B',
        'subject' => 'Subject B',
        'content' => 'Content B',
        'code'    => 'test-unique-code',
    ]))->toThrow(QueryException::class);
});
