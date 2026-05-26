<?php

use App\Enums\Inkoop\InkoopInvoiceParser;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Inkoop\Concerns\ParsesInkoopPdf;

uses(ParsesInkoopPdf::class);

beforeEach(function () {
    Storage::fake('public');
});

it('mvz bochum rekening augustus 2025 parses 74 patients', function () {
    $filename = 'Rekening_bochem_augustus_2025.pdf';
    $this->putPdfInStorage($filename);

    $invoice = $this->makeInkoopInvoice(InkoopInvoiceParser::MVZ_BOCHUM, $filename);
    $result = $this->parseInkoop($invoice, $filename);

    $allnames = collect($result['patients'])->map(fn ($p) => $p['lastname'].', '.$p['firstname'])->all();

    //    expect($result['patients'])->toHaveCount(74)
    //        ->and($result['reference_date'])->not->toBeNull();

    $firstPatient = $result['patients'][0];
    expect($firstPatient)->toHaveKeys(['firstname', 'lastname', 'birthday', 'products'])
        ->and($firstPatient['products'])->toBeArray()
        ->and($firstPatient['products'][0])->toHaveKeys(['exam_date', 'product_name', 'price']);

    $patient1 = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Van den Heuvel' && $p['firstname'] === 'Myrthe');
    expect($patient1)->not->toBeNull()
        ->and($patient1['birthday'])->toBe('24.04.1986')
        ->and($patient1['products'][0]['exam_date'])->toBe('2025-08-01');

    $patient2 = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Verbeek' && $p['firstname'] === 'Arend');
    expect($patient2)->not->toBeNull()
        ->and($patient2['birthday'])->toBe('24.05.1983')
        ->and($patient2['products'][0]['exam_date'])->toBe('2025-08-01')
        ->and($patient2['products'][0]['price'])->toBe('348.31')
        ->and($patient2['products'][0]['product_name'])->toBe('Kardiologische Diagnostik');

    $patient3 = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Knippers- van der Heijde' && $p['firstname'] === 'Huiberdine');
    expect($patient3)->not->toBeNull()
        ->and($patient3['birthday'])->toBe('13.11.1955')
        ->and($patient3['products'][0]['exam_date'])->toBe('2025-08-05')
        ->and($patient3['products'][0]['price'])->toBe('348.31')
        ->and($patient3['products'][0]['product_name'])->toBe('Kardiologische Diagnostik');

    $patient4 = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'Vervaeke' && $p['firstname'] === 'Nathalie');
    expect($patient4)->not->toBeNull()
        ->and($patient4['birthday'])->toBe('14.02.1970')
        ->and($patient4['products'][0]['exam_date'])->toBe('2025-08-05')
        ->and($patient4['products'][0]['price'])->toBe('348.31')
        ->and($patient4['products'][0]['product_name'])->toBe('Kardiologische Diagnostik');

    $patient5 = collect($result['patients'])->first(fn ($p) => $p['lastname'] === 'De Roo' && $p['firstname'] === 'Erwin');
    expect($patient5)->not->toBeNull()
        ->and($patient5['birthday'])->toBe('28.11.1962')
        ->and($patient5['products'][0]['exam_date'])->toBe('2025-08-22')
        ->and($patient5['products'][0]['price'])->toBe('17.49')
        ->and($patient5['products'][0]['product_name'])->toBe('Kardiologische Diagnostik');
});
