<?php

use App\Enums\Inkoop\InkoopInvoiceParser;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Inkoop\Concerns\ParsesInkoopPdf;

uses(ParsesInkoopPdf::class);

beforeEach(function () {
    Storage::fake('public');
});

it('evidia radiologie april 2025 parses 179 patients and products', function () {
    $filename = 'Monatsabrechnung_April_2025_evidia_radiologie.pdf';
    $this->putPdfInStorage($filename);

    $invoice = $this->makeInkoopInvoice(InkoopInvoiceParser::EVIDIA_RADIOLOGIE, $filename);
    $result = $this->parseInkoop($invoice, $filename);

    expect($result['patients'])->toHaveCount(179)
        ->and($result['reference_date'])->not->toBeNull();

    $firstPatient = $result['patients'][0];
    expect($firstPatient)->toHaveKeys(['firstname', 'lastname', 'birthday', 'products'])
        ->and($firstPatient['products'])->toBeArray()
        ->and($firstPatient['products'])->toHaveCount(3)
        ->and($firstPatient['products'][0])->toHaveKeys(['exam_date', 'product_name', 'price'])
        ->and($firstPatient['products'])->toMatchArray([
            ['exam_date' => '2025-04-01', 'product_name' => 'MTB1 MTB 1', 'price' => '337.00'],
            ['exam_date' => '2025-04-01', 'product_name' => 'MR LWS MRT LWS nach Vereinbarung', 'price' => '56.00'],
            ['exam_date' => '2025-04-01', 'product_name' => 'CT Her CT Herz', 'price' => '112.00'],
        ]);

    $vanMeursPatient = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Van Meurs' && $p['firstname'] === 'Reggy');
    expect($vanMeursPatient)->not->toBeNull()
        ->and($vanMeursPatient['birthday'])->toBe('1970-05-16')
        ->and($vanMeursPatient['products'][0]['exam_date'])->toBe('2025-04-01');
});
