<?php

use App\Enums\Inkoop\InkoopInvoiceParser;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Inkoop\Concerns\ParsesInkoopPdf;

uses(ParsesInkoopPdf::class);

beforeEach(function () {
    Storage::fake('public');
});

it('mvz bochum rekening juni 2025 parses 63 patients', function () {
    $filename = 'Rekening_mvz_bockum_2025_juni.pdf';
    $this->putPdfInStorage($filename);

    $invoice = $this->makeInkoopInvoice(InkoopInvoiceParser::MVZ_BOCHUM, $filename);
    $result = $this->parseInkoop($invoice, $filename);

    expect($result['patients'])->toHaveCount(63)
        ->and($result['reference_date'])->not()->toBeNull();

    $firstPatient = $result['patients'][0];
    expect($firstPatient)->toHaveKeys(['firstname', 'lastname', 'birthday', 'products'])
        ->and($firstPatient['products'])->toBeArray()
        ->and($firstPatient['products'][0])->toHaveKeys(['exam_date', 'product_name', 'price']);

    $desmondPatient = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Van der Winden' && $p['firstname'] === 'Desmond');
    expect($desmondPatient)->not->toBeNull()
        ->and($desmondPatient['birthday'])->toBe('03.08.1980')
        ->and($desmondPatient['products'][0]['exam_date'])->toBe('2025-06-13')
        ->and($desmondPatient['products'][0]['price'])->toBe('348.31');

    $isabellaPatient = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Buitendijk' && $p['firstname'] === 'Isabella');
    expect($isabellaPatient)->not->toBeNull()
        ->and($isabellaPatient['birthday'])->toBe('16.06.1975')
        ->and($isabellaPatient['products'][0]['exam_date'])->toBe('2025-06-27');

    collect($result['patients'])->each(function ($patient) {
        foreach ($patient['products'] as $product) {
            expect(in_array($product['price'], ['348.31', '386.20', '62.38', '121.26', '48.39', '377.49', '45.48', '397.85', '302.83', '687.61']))
                ->toBeTrue("Unexpected price found: {$product['price']}");
        }
    });
});
