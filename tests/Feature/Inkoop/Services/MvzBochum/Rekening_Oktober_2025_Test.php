<?php

use App\Enums\Inkoop\InkoopInvoiceParser;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Inkoop\Concerns\ParsesInkoopPdf;

uses(ParsesInkoopPdf::class);

beforeEach(function () {
    Storage::fake('public');
});

it('mvz bochum rekening augustus 2025 parses 74 patients', function () {
    $filename = 'Rechnung_Oktober_2025.pdf';
    $this->putPdfInStorage($filename);

    $invoice = $this->makeInkoopInvoice(InkoopInvoiceParser::MVZ_BOCHUM, $filename);
    $result = $this->parseInkoop($invoice, $filename);

    $allnames = collect($result['patients'])->map(fn ($p) => $p['lastname'].', '.$p['firstname'])->all();

    $this->assertNotEmpty($allnames);
    expect($result['patients'])->toHaveCount(74)
        ->and($result['reference_date'])->not->toBeNull();

    $firstPatient = $result['patients'][0];
    expect($firstPatient)->toHaveKeys(['firstname', 'lastname', 'birthday', 'products'])
        ->and($firstPatient['products'])->toBeArray()
        ->and($firstPatient['products'][0])->toHaveKeys(['exam_date', 'product_name', 'price']);
    $patient1 = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Zeru' && $p['firstname'] === 'Christina');
    expect($patient1)->not->toBeNull()
        ->and($patient1['birthday'])->toBe('11.04.1984')
        ->and($patient1['products'][0]['exam_date'])->toBe('2025-10-01');

    $patient2 = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Boumans' && $p['firstname'] === 'Paul');
    expect($patient2)->not->toBeNull()
        ->and($patient2['birthday'])->toBe('22.11.1972')
        ->and($patient2['products'][0]['exam_date'])->toBe('2025-10-01')
        ->and($patient2['products'][0]['price'])->toBe('348.31');
});
