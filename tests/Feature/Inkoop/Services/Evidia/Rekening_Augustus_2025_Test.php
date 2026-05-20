<?php

use App\Enums\Inkoop\InkoopInvoiceParser;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Inkoop\Concerns\ParsesInkoopPdf;

uses(ParsesInkoopPdf::class);

beforeEach(function () {
    Storage::fake('public');
});

it('evidia augustus 2025 parses 167 patients', function () {
    $filename = 'Rekening_Augustus_2025.pdf';
    $this->putPdfInStorage($filename);

    $invoice = $this->makeInkoopInvoice(InkoopInvoiceParser::EVIDIA_RADIOLOGIE, $filename);
    $result = $this->parseInkoop($invoice, $filename);

    expect($result['patients'])->toHaveCount(167)
        ->and($result['reference_date'])->not->toBeNull();

    $firstPatient = $result['patients'][0];
    expect($firstPatient)->toHaveKeys(['firstname', 'lastname', 'birthday', 'products'])
        ->and($firstPatient['products'])->toBeArray()
        ->and($firstPatient['products'][0])->toHaveKeys(['exam_date', 'product_name', 'price']);

    $kraftPatient = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Kraft' && $p['firstname'] === 'Ronald');
    expect($kraftPatient)->not->toBeNull()
        ->and($kraftPatient['birthday'])->toBe('1968-03-17')
        ->and($kraftPatient['products'][0]['exam_date'])->toBe('2025-08-01')
        ->and($kraftPatient['products'][0]['price'])->toBe('337.00')
        ->and($kraftPatient['products'][0]['product_name'])->toBe('MTB1 MTB 1');
});
