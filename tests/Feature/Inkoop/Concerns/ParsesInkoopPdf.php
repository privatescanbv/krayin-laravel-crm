<?php

namespace Tests\Feature\Inkoop\Concerns;

use App\Enums\Inkoop\InkoopInvoiceParser;
use App\Enums\Inkoop\InkoopInvoiceStatus;
use App\Models\Clinic;
use App\Models\Inkoop\InkoopInvoice;
use App\Services\Inkoop\InkoopPdfParser;
use Illuminate\Support\Facades\Storage;

trait ParsesInkoopPdf
{
    protected string $basePathPdf = 'tests/fixtures/inkoop/pdfs/';

    protected function putPdfInStorage(string $filename): void
    {
        $testPdfPath = base_path($this->basePathPdf.$filename);
        Storage::disk('public')->put(
            "inkoop_invoices/{$filename}",
            file_get_contents($testPdfPath)
        );
    }

    protected function makeInkoopInvoice(InkoopInvoiceParser $parser, string $filename): InkoopInvoice
    {
        $clinic = Clinic::factory()->create();

        return InkoopInvoice::create([
            'clinic_id' => $clinic->id,
            'parser'    => $parser,
            'filename'  => $filename,
            'pdf_path'  => 'inkoop_invoices/'.$filename,
            'status'    => InkoopInvoiceStatus::OPEN,
        ]);
    }

    protected function parseInkoop(InkoopInvoice $invoice, string $filename): array
    {
        $parser = new InkoopPdfParser;

        return $parser->parse($invoice, $filename);
    }
}
