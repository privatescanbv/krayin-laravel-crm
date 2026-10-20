<?php

use App\Enums\Inkoop\InkoopInvoiceParser;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Inkoop\Concerns\ParsesInkoopPdf;

uses(ParsesInkoopPdf::class);

beforeEach(function () {
    Storage::fake('public');
});

it('mvz bochum factuur september 2026 parses all 36 patients', function () {
    $filename = 'factuur_september 2026.pdf';
    $this->putPdfInStorage($filename);

    $invoice = $this->makeInkoopInvoice(InkoopInvoiceParser::MVZ_BOCHUM, $filename);
    $result = $this->parseInkoop($invoice, $filename);

    $patients = collect($result['patients']);
    $find = fn (string $lastname, string $firstname) => $patients->first(fn ($p) => $p['lastname'] === $lastname && $p['firstname'] === $firstname);

    expect($result['reference_date'])->toBe('2026-09-30')
        ->and($result['warnings'])->toBeEmpty()
        ->and($patients)->toHaveCount(36)
        ->and(round($patients->sum(fn ($p) => (float) $p['products'][0]['price']), 2))->toBe(11950.11)
        ->and($find('Eckhardt', 'Stijn'))->not->toBeNull()
        ->and($find('Eckhardt', 'Stijn')['birthday'])->toBe('08.08.2005')
        ->and($find('Eckhardt', 'Stijn')['products'][0]['exam_date'])->toBe('2026-09-01')
        ->and($find('Eckhardt', 'Stijn')['products'][0]['price'])->toBe('45.48')
        ->and($find('Raso', 'Laura'))->not->toBeNull()
        ->and($find('Raso', 'Laura')['birthday'])->toBe('20.02.1996')
        ->and($find('Raso', 'Laura')['products'][0]['exam_date'])->toBe('2026-09-30')
        ->and($find('Zwijnenburg-Kuster', 'Ellen'))->not->toBeNull()
        ->and($find('Zwijnenburg-Kuster', 'Ellen')['birthday'])->toBe('16.06.1963')
        ->and($find('Zwijnenburg-Kuster', 'Ellen')['products'][0]['price'])->toBe('348.31')
        ->and($find('Strauß', 'Hetty'))->not->toBeNull()
        ->and($find('Strauß', 'Hetty')['birthday'])->toBe('15.05.1951')
        ->and($find('Van Steenes', 'Indra'))->not->toBeNull()
        ->and($find('Van Steenes', 'Indra')['products'][0]['price'])->toBe('402.23')
        ->and($find('Zwijnenburg', 'Henk')['products'][0]['price'] ?? null)->toBe('322.37');

    // first and last row

    // birthday wrapped to the next line

    // space between '*' and birthday

    // Zusatz with extra price on the price line

});
