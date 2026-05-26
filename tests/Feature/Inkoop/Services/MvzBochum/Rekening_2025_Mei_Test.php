<?php

use App\Enums\Inkoop\InkoopInvoiceParser;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Inkoop\Concerns\ParsesInkoopPdf;

uses(ParsesInkoopPdf::class);

beforeEach(function () {
    Storage::fake('public');
});

it('mvz_bochum_rekening_mei_2025_parses_68_patients', function () {
    $filename = 'Rekening_mvz_bochum_2025_mei.pdf';
    $this->putPdfInStorage($filename);

    $invoice = $this->makeInkoopInvoice(InkoopInvoiceParser::MVZ_BOCHUM, $filename);
    $result = $this->parseInkoop($invoice, $filename);

    expect($result['patients'])->toHaveCount(68)
        ->and($result['reference_date'])->toBeNull();

    $firstPatient = $result['patients'][0];
    expect($firstPatient)->toHaveKeys(['firstname', 'lastname', 'birthday', 'products'])
        ->and($firstPatient['products'])->toBeArray()
        ->and($firstPatient['products'][0])->toHaveKeys(['exam_date', 'product_name', 'price']);

    $muellerPatient = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Mueller-Pfeiffer' && $p['firstname'] === 'Daniela');
    expect($muellerPatient)->not->toBeNull()
        ->and($muellerPatient['birthday'])->toBe('07.06.1964')
        ->and($muellerPatient['products'][0]['exam_date'])->toBe('2025-05-09')
        ->and($muellerPatient['products'][0]['price'])->toBe('348.31');

    $snijdersPatient = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Snijders' && $p['firstname'] === 'Ferdy');
    expect($snijdersPatient)->not->toBeNull()
        ->and($snijdersPatient['birthday'])->toBe('23.11.1981')
        ->and($snijdersPatient['products'][0]['exam_date'])->toBe('2025-05-02');

    $leeuwPatient = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'De Leeuw' && $p['firstname'] === 'Debora');
    expect($leeuwPatient)->not->toBeNull()
        ->and($leeuwPatient['birthday'])->toBe('14.08.1983')
        ->and($leeuwPatient['products'][0]['exam_date'])->toBe('2025-05-30')
        ->and($leeuwPatient['products'][0]['price'])->toBe('348.31');
});
