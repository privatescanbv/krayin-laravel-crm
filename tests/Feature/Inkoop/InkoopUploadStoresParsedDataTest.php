<?php

use App\Enums\Inkoop\InkoopInvoiceParser;
use App\Models\Address;
use App\Models\Clinic;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopPerson;
use App\Models\User;
use App\Services\Inkoop\InkoopPdfParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create(), 'user');
});

it('stores parsed patients after uploading an inkoop pdf', function () {
    $clinic = Clinic::factory()->create();
    $filename = 'Rekening_April_2025.pdf';
    $path = base_path('tests/fixtures/inkoop/pdfs/'.$filename);
    $file = new UploadedFile($path, $filename, 'application/pdf', null, true);

    $response = $this->post(route('admin.inkoop.clinics.upload.store', ['clinic' => $clinic->id]), [
        'parser' => InkoopInvoiceParser::MVZ_BOCHUM->value,
        'file'   => $file,
    ]);

    $invoice = InkoopInvoice::query()->first();

    $response->assertRedirect(route('admin.inkoop.step0', ['invoice' => $invoice->id]));

    expect($invoice)->not->toBeNull()
        ->and($invoice->activity?->title)->toBe('Inkoop factuur: '.$filename)
        ->and(InkoopPerson::where('invoice_id', $invoice->id)->count())->toBe(69);

    $storedPatient = InkoopPerson::where('invoice_id', $invoice->id)
        ->where('lastname', 'Langen-van Lieshout')
        ->where('firstname', 'Miep')
        ->first();

    expect($storedPatient?->birthday?->format('Y-m-d'))->toBe('1951-04-21');

    $stepResponse = $this->get(route('admin.inkoop.step1', ['invoice' => $invoice->id]));
    $stepResponse->assertOk();
    $stepResponse->assertViewHas('patients', fn ($patients) => $patients->count() === 69);
});

it('shows a parse warning on step0 when the parsed total differs from the invoice total', function () {
    $warning = 'Totaal van ingelezen regels (€ 22.566,04, 68 patiënten) wijkt af van het factuurtotaal (€ 22.782,86).';
    $this->mock(InkoopPdfParser::class, fn ($mock) => $mock->shouldReceive('parse')->andReturn([
        'reference_date' => null,
        'patients'       => [],
        'warnings'       => [$warning],
    ]));

    $clinic = Clinic::factory()->create();
    $filename = 'Rekening_mvz_bochum_2025_mei.pdf';
    $file = new UploadedFile(base_path('tests/fixtures/inkoop/pdfs/'.$filename), $filename, 'application/pdf', null, true);

    $this->post(route('admin.inkoop.clinics.upload.store', ['clinic' => $clinic->id]), [
        'parser' => InkoopInvoiceParser::MVZ_BOCHUM->value,
        'file'   => $file,
    ]);

    $invoice = InkoopInvoice::query()->first();

    expect($invoice->parse_warnings)->toBe([$warning]);

    $this->get(route('admin.inkoop.step0', ['invoice' => $invoice->id]))
        ->assertOk()
        ->assertSee('De factuur is mogelijk niet volledig ingelezen')
        ->assertSee('wijkt af van het factuurtotaal')
        ->assertSee('Meld dit bij de technische dienst');
});

it('shows no parse warning on step0 when the parsed total matches the invoice total', function () {
    $clinic = Clinic::factory()->create();
    $filename = 'factuur_september 2026.pdf';
    $file = new UploadedFile(base_path('tests/fixtures/inkoop/pdfs/'.$filename), $filename, 'application/pdf', null, true);

    $this->post(route('admin.inkoop.clinics.upload.store', ['clinic' => $clinic->id]), [
        'parser' => InkoopInvoiceParser::MVZ_BOCHUM->value,
        'file'   => $file,
    ]);

    $invoice = InkoopInvoice::query()->first();

    expect($invoice->parse_warnings)->toBeNull()
        ->and(InkoopPerson::where('invoice_id', $invoice->id)->count())->toBe(36);

    $this->get(route('admin.inkoop.step0', ['invoice' => $invoice->id]))
        ->assertOk()
        ->assertSee('Parser:')
        ->assertSee('MVZ Bochum')
        ->assertDontSee('De factuur is mogelijk niet volledig ingelezen');
});

it('parses the Augusta Bochum II Kostenaufstelling Oktober 2026 where price and euro sign are on separate lines', function () {
    $address = Address::factory()->create([
        'street'       => 'Farnstraße',
        'house_number' => '9',
        'postal_code'  => '44789',
        'city'         => 'Bochum',
    ]);
    $clinic = Clinic::factory()->create([
        'name'             => 'MVZ Augusta Bochum II GmbH',
        'visit_address_id' => $address->id,
    ]);
    $filename = 'Kostenaufstelling-Oktober-2026.pdf';
    $file = new UploadedFile(base_path('tests/fixtures/inkoop/pdfs/'.$filename), $filename, 'application/pdf', null, true);

    $this->post(route('admin.inkoop.clinics.upload.store', ['clinic' => $clinic->id]), [
        'parser' => InkoopInvoiceParser::MVZ_BOCHUM->value,
        'file'   => $file,
    ]);

    $invoice = InkoopInvoice::query()->first();

    expect($invoice->clinic_id)->toBe($clinic->id)
        ->and($invoice->parse_warnings)->toBeNull()
        ->and(InkoopPerson::where('invoice_id', $invoice->id)->count())->toBe(60);

    // Standaard patiënt
    $standard = InkoopPerson::where('invoice_id', $invoice->id)->where('lastname', 'Hoff')->where('firstname', 'Matthie')->first();
    expect($standard?->birthday?->format('Y-m-d'))->toBe('1954-08-12');

    // Dubbele achternaam over meerdere regels
    $wrapped = InkoopPerson::where('invoice_id', $invoice->id)->where('lastname', 'Jansen-van de Grift')->where('firstname', 'Hans')->first();
    expect($wrapped?->birthday?->format('Y-m-d'))->toBe('1938-08-02')
        ->and(InkoopPerson::where('invoice_id', $invoice->id)->where('lastname', 'Van Raaltem Liesbeth')->exists())->toBeTrue();

    // Naam zonder komma blijft behouden als achternaam
});
