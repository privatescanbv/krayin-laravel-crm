<?php

use App\Enums\Inkoop\InkoopInvoiceParser;
use App\Models\Clinic;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopPerson;
use App\Models\User;
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
