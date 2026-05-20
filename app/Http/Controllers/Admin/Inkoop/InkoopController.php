<?php

namespace App\Http\Controllers\Admin\Inkoop;

use App\Enums\ActivityType;
use App\Enums\Inkoop\InkoopInvoiceParser;
use App\Enums\Inkoop\InkoopInvoiceStatus;
use App\Models\Clinic;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopInvoiceItem;
use App\Models\Inkoop\InkoopPerson;
use App\Services\Inkoop\InkoopPdfParser;
use DateTimeImmutable;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Webkul\Activity\Repositories\ActivityRepository;

class InkoopController extends Controller
{
    public function __construct(
        private readonly InkoopPdfParser $pdfParser,
        private readonly ActivityRepository $activityRepository
    ) {}

    public function showUpload(Clinic $clinic)
    {
        return view('adminc::inkoop.upload', [
            'clinic'  => $clinic,
            'parsers' => InkoopInvoiceParser::cases(),
        ]);
    }

    public function store(Request $request, Clinic $clinic)
    {
        $request->validate([
            'file'   => 'required|mimes:pdf|max:10240',
            'parser' => 'required|string|in:'.implode(',', array_map(fn ($case) => $case->value, InkoopInvoiceParser::cases())),
        ]);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $fileName = time().'_'.$originalFilename;
        $filePath = $file->storeAs('inkoop_invoices', $fileName, 'public');
        $parser = InkoopInvoiceParser::from($request->string('parser')->toString());

        $invoice = InkoopInvoice::create([
            'clinic_id'      => $clinic->id,
            'invoice_number' => pathinfo($originalFilename, PATHINFO_FILENAME),
            'pdf_path'       => $filePath,
            'filename'       => $fileName,
            'name'           => $originalFilename,
            'status'         => InkoopInvoiceStatus::OPEN,
            'parser'         => $parser,
        ]);

        try {
            $this->storeParsedData($invoice, $clinic);
        } catch (Exception $e) {
            Log::error('Error parsing inkoop PDF after upload', [
                'invoice_id' => $invoice->id,
                'filename'   => $fileName,
                'file_path'  => $filePath,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }

        try {
            $this->activityRepository->create([
                'type'      => ActivityType::FILE,
                'title'     => 'Inkoop factuur: '.$originalFilename,
                'clinic_id' => $clinic->id,
                'user_id'   => auth()->guard('user')->id() ?: auth()->id(),
                'file'      => $file,
            ]);
        } catch (Exception $e) {
            Log::error('Error creating activity for inkoop PDF upload', [
                'invoice_id' => $invoice->id,
                'filename'   => $fileName,
                'error'      => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.inkoop.step0', ['invoice' => $invoice->id])
            ->with('success', 'Factuur is succesvol geupload.');
    }

    public function destroy(InkoopInvoice $invoice)
    {
        $clinicId = $invoice->clinic_id;

        try {
            $invoice->items()->delete();
            $invoice->persons()->delete();
            $invoice->delete();

            return redirect()->to(route('admin.clinics.view', ['id' => $clinicId]).'#inkoop-afletteren')
                ->with('success', 'Factuur succesvol verwijderd.');
        } catch (Exception $e) {
            Log::error('Error deleting inkoop invoice', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Er is een fout opgetreden bij het verwijderen van de factuur.');
        }
    }

    private function storeParsedData(InkoopInvoice $invoice, Clinic $clinic): void
    {
        try {
            $filePath = $invoice->pdf_path;
            $fullPath = Storage::disk('public')->path($filePath);

            Log::info('Attempting to parse inkoop PDF', [
                'invoice_id' => $invoice->id,
                'filename'   => $invoice->filename,
                'file_path'  => $filePath,
                'full_path'  => $fullPath,
            ]);

            $result = $this->pdfParser->parse($invoice, $invoice->filename);
            $patients = $result['patients'] ?? [];

            Log::info('Parsed inkoop PDF data', [
                'invoice_id'    => $invoice->id,
                'patient_count' => count($patients),
            ]);

            if (isset($result['reference_date'])) {
                $invoice->reference_date = $result['reference_date'];
                $invoice->invoice_date = $result['reference_date'];
                $invoice->save();
            }

            if (empty($patients)) {
                Log::warning('Parsed inkoop PDF contained no patients', [
                    'invoice_id' => $invoice->id,
                    'filename'   => $invoice->filename,
                ]);
            }

            foreach ($patients as $patientData) {
                $fullName = trim(($patientData['firstname'] ?? '').' '.($patientData['lastname'] ?? ''));

                $patient = InkoopPerson::create([
                    'clinic_id'  => $clinic->id,
                    'invoice_id' => $invoice->id,
                    'name'       => $fullName,
                    'firstname'  => $patientData['firstname'] ?? null,
                    'lastname'   => $patientData['lastname'] ?? null,
                    'birthday'   => $this->formatDateForStorage($patientData['birthday'] ?? null),
                ]);

                try {
                    foreach ($patientData['products'] as $product) {
                        $price = $this->formatDecimalForStorage($product['price'] ?? null);

                        $invoiceItem = new InkoopInvoiceItem([
                            'clinic_id'   => $clinic->id,
                            'date'        => $this->formatDateForStorage($product['exam_date'] ?? null),
                            'quantity'    => 1,
                            'description' => $product['product_name'] ?? 'Onbekend product',
                            'name'        => $product['product_name'] ?? 'Onbekend product',
                            'unit_price'  => $price,
                            'price'       => $price,
                            'total_price' => $price,
                        ]);

                        $invoiceItem->invoice()->associate($invoice);
                        $invoiceItem->person()->associate($patient);
                        $invoiceItem->save();
                    }
                } catch (QueryException $e) {
                    Log::error('Error saving inkoop invoice items', [
                        'invoice_id' => $invoice->id,
                        'patient_id' => $patient->id,
                        'products'   => $patientData['products'],
                        'error'      => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }

            $invoice->total_amount = $invoice->items()->sum('total_price');
            $invoice->save();
        } catch (Exception $e) {
            Log::error('Error storing parsed inkoop data', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function formatDateForStorage(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        foreach (['Y-m-d', 'd.m.Y'] as $format) {
            $dateTime = DateTimeImmutable::createFromFormat('!'.$format, $date);

            if ($dateTime !== false) {
                return $dateTime->format('Y-m-d');
            }
        }

        return $date;
    }

    private function formatDecimalForStorage(null|string|int|float $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_replace(',', '.', (string) $value);
    }
}
