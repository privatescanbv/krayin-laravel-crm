<?php

use App\Enums\Inkoop\InkoopInvoiceParser;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Inkoop\Concerns\ParsesInkoopPdf;

uses(ParsesInkoopPdf::class);

beforeEach(function () {
    Storage::fake('public');
});

it('mvz bochum rekening april 2025 parses 69 patients', function () {
    $filename = 'Rekening_April_2025.pdf';
    $this->putPdfInStorage($filename);

    $invoice = $this->makeInkoopInvoice(InkoopInvoiceParser::MVZ_BOCHUM, $filename);
    $result = $this->parseInkoop($invoice, $filename);

    expect($result['patients'])->toHaveCount(69)
        ->and($result['reference_date'])->not->toBeNull();

    $firstPatient = $result['patients'][0];
    expect($firstPatient)->toHaveKeys(['firstname', 'lastname', 'birthday', 'products'])
        ->and($firstPatient['products'])->toBeArray()
        ->and($firstPatient['products'][0])->toHaveKeys(['exam_date', 'product_name', 'price']);

    $langenPatient = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Langen-van Lieshout' && $p['firstname'] === 'Miep');
    expect($langenPatient)->not->toBeNull()
        ->and($langenPatient['birthday'])->toBe('21.04.1951')
        ->and($langenPatient['products'][0]['exam_date'])->toBe('2025-04-30');

    $jonkhartPatient = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Jonkhart' && $p['firstname'] === 'Philip');
    expect($jonkhartPatient)->not->toBeNull()
        ->and($jonkhartPatient['birthday'])->toBe('16.07.1982')
        ->and($jonkhartPatient['products'][0]['exam_date'])->toBe('2025-04-29')
        ->and($jonkhartPatient['products'][0]['price'])->toBe('348.31')
        ->and($jonkhartPatient['products'][0]['product_name'])->toBe('Kardiologische Diagnostik');
});
