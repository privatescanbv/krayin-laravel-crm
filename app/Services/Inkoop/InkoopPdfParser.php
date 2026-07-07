<?php

namespace App\Services\Inkoop;

use App\Enums\Inkoop\InkoopInvoiceParser;
use App\Models\Inkoop\InkoopInvoice;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser;

class InkoopPdfParser
{
    private const DEFAULT_PRODUCT_NAME = 'Kardiologische Diagnostik';

    private string $birthdayOutputFormat = 'Y-m-d'; // Default format

    /**
     * @throws Exception
     */
    public function parse(InkoopInvoice $invoice, string $fileName): array
    {
        try {
            // Parse PDF and extract order lines
            $pdfPath = Storage::disk('public')->path("inkoop_invoices/{$fileName}");

            // Check if file exists
            if (! file_exists($pdfPath)) {
                Log::error('PDF file not found', [
                    'file_path'  => $pdfPath,
                    'invoice_id' => $invoice->id,
                    'filename'   => $fileName,
                ]);
                throw new Exception('PDF file not found');
            }

            $parser = new Parser;
            $pdf = $parser->parseFile($pdfPath);
            if (empty($pdf)) {
                throw new Exception('Empty pdf file, nothing to handle');
            }

            // Extract text - try coordinate-based first, fallback to simple extraction
            $extractedText = $this->extractTextByCoordinates($pdf);

            // Fallback to simple text extraction if coordinate-based fails or returns empty
            if (empty(trim($extractedText))) {
                Log::info('Using simple text extraction', [
                    'invoice_id' => $invoice->id,
                    'filename'   => $fileName,
                ]);
                $extractedText = $pdf->getText();
            } else {
                Log::info('Using coordinate-based text extraction', [
                    'invoice_id'  => $invoice->id,
                    'filename'    => $fileName,
                    'text_length' => strlen($extractedText),
                ]);
            }

            // Log first 500 characters for debugging
            Log::debug('Extracted PDF text preview', [
                'invoice_id' => $invoice->id,
                'preview'    => substr($extractedText, 0, 500),
            ]);

            $normalizedText = $this->normalizePdfText($extractedText);

            // Process the invoice based on the parser
            $result = match ($invoice->parser) {
                InkoopInvoiceParser::EVIDIA_RADIOLOGIE => $this->extractTextFromPdfEvidia($invoice, $normalizedText),
                InkoopInvoiceParser::MVZ_BOCHUM        => $this->extractTextFromPdfMvzBochum($invoice, $normalizedText),
                InkoopInvoiceParser::PROCELSIOCLINIC   => $this->extractTextFromPdfClinic($invoice, $normalizedText),
            };

            return $result;
        } catch (Exception $e) {
            Log::error('Error parsing PDF', [
                'file_path'  => $pdfPath,
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function extractTextFromPdfEvidia(InkoopInvoice $invoice, $pdfText): array
    {
        $this->birthdayOutputFormat = 'Y-m-d'; // Evidia uses Y-m-d format
        $lines = explode("\n", $pdfText);
        $patients = [];
        $currentPersonData = null;
        $lastValidDate = null;
        $referenceDate = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Zoek referentiedatum "Rechnungsdatum: DD.MM.YYYY"
            if (! $referenceDate && preg_match('/Rechnungsdatum:\s*(\d{2}\.\d{2}\.\d{4})/', $line, $match)) {
                $referenceDate = $this->formatDate($match[1], 'Y-m-d');

                continue;
            }

            // skip header
            if (str_contains($line, ',')) {
                // Herken naam + geboortedatum
                if (preg_match('/^(.+?)\s+(\d{2}\.\d{2}\.\d{4})$/', $line, $matches)) {
                    // Als er al een patiënt is, voeg deze toe aan de lijst
                    if ($currentPersonData) {
                        $patients[] = $this->preparePatientData($currentPersonData);
                    }

                    if (! str_contains($line, ',')) {
                        Log::warning('Missing komma in name', ['line' => $line]);

                        continue;
                    }

                    $arrNames = preg_split('/,/', trim($matches[1]), 2);
                    if (count($arrNames) !== 2) {
                        Log::warning('Could not parse name', ['line' => $line]);

                        continue;
                    }

                    $currentPersonData = [
                        'firstname' => trim($arrNames[1]),
                        'lastname'  => trim($arrNames[0]),
                        'birthday'  => $this->formatDate($matches[2]),
                        'products'  => [],
                    ];

                    continue;
                }

                // Herken productregel
                if (preg_match('/(?:(\d{2}\.\d{2}\.\d{4})\s+)?(\d+)\s+(.*?)\s+(\d+)\s+([\d,]+)$/', $line, $matches)) {
                    if (! empty($matches[1])) {
                        $lastValidDate = $this->formatDate($matches[1], 'Y-m-d');
                        $datum = $lastValidDate;
                    } else {
                        $datum = $lastValidDate;
                    }
                    Log::debug('Found product line', [
                        'line'         => $line,
                        'exam_date'    => $datum,
                        'product_name' => trim($matches[3]),
                        'price'        => str_replace(',', '.', $matches[5]),
                    ]);

                    if ($currentPersonData) {
                        $currentPersonData['products'][] = [
                            'exam_date'    => $datum,
                            'product_name' => trim($matches[3]),
                            'price'        => str_replace(',', '.', $matches[5]),
                        ];
                    }
                }
            } else {
                Log::info('Skipping line: '.$line);
            }
        }

        // Voeg de laatste patiënt nog toe
        if ($currentPersonData) {
            $patients[] = $this->preparePatientData($currentPersonData);
        }

        Log::info('Extracted patients from evidia PDF', [
            'invoice_id'     => $invoice->id,
            'patient_count'  => count($patients),
            'reference_date' => $referenceDate,
        ]);

        return [
            'reference_date' => $referenceDate,
            'patients'       => $patients,
        ];
    }

    private function extractTextFromPdfMvzBochum(InkoopInvoice $invoice, string $pdfText): array
    {
        $this->birthdayOutputFormat = 'd.m.Y'; // MVZ Bochum uses d.m.Y format
        $lines = explode("\n", $pdfText);
        $patients = [];
        $referenceDate = null;
        $tableHeadersFound = false;
        $currentPersonData = null;
        $lastPrice = null;

        Log::info('Starting PDF parsing for MVZ Bochum', [
            'invoice_id'  => $invoice->id,
            'total_lines' => count($lines),
        ]);

        // Log first 20 lines for debugging
        Log::debug('First 20 lines of PDF', [
            'invoice_id' => $invoice->id,
            'lines'      => array_slice($lines, 0, 20),
        ]);

        $lastFoundedName = null;
        $lastBirthDate = null;
        $lastExamDate = null;
        $foundLastNameBySeparatorLineNumber = null;
        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if (! empty($foundLastNameBySeparatorLineNumber) && $lineNumber == $foundLastNameBySeparatorLineNumber + 2) {
                Log::debug('Resetting foundLastNameBySeparatorLineNumber', [
                    'line_number' => $lineNumber,
                    'content'     => $line,
                ]);
                $foundLastNameBySeparatorLineNumber = null; // reset after processing
            }

            // Check voor tabel headers
            if (str_contains($line, 'Patient:') ||
                str_contains($line, 'gstag:') ||
                str_contains($line, 'Betrag:') ||
                str_contains($line, 'Zusatz:')) {
                Log::info('Found table header', [
                    'line_number' => $lineNumber,
                    'header_line' => $line,
                ]);
                $tableHeadersFound = true;

                continue;
            }
            if ($tableHeadersFound) {
                Log::debug('Processing line', [
                    'line_number' => $lineNumber,
                    'content'     => $line,
                ]);
            }

            if (! $tableHeadersFound) {
                // Zoek referentiedatum "Per 30.03.2025"
                if (! $referenceDate && preg_match('/Per\s+(\d{2}\.\d{2}\.\d{4})/', $line, $match)) {
                    $referenceDate = $this->formatDate($match[1], 'Y-m-d');
                    Log::info('Found reference date', [
                        'line_number'    => $lineNumber,
                        'reference_date' => $referenceDate,
                    ]);
                }
            } else {
                // support all fields in one line, including price
                if ((str_contains($line, ',')) && str_contains($line, '*') && str_contains($line, '€') && preg_match('/^([\p{L}\s\'\-]+),\s+([\p{L}\s\'\-]+)\s+\*(\d{2}\.\d{2}\.\d{4})\s+(\d{2}\.\d{2}\.\d{4})\s+(\d{1,3},\d{2})\s+€/u', $line, $matches)) {

                    $lastName = $matches[1];
                    $firstName = $matches[2];
                    $birthday = $matches[3];
                    $examDate = $matches[4];
                    $lastPrice = $matches[5];
                    $personData = [
                        'firstname' => $firstName,
                        'lastname'  => $lastName,
                        'birthday'  => $this->formatDate($birthday),
                        'products'  => [
                            [
                                'exam_date'    => $this->formatDate($examDate, 'Y-m-d'),
                                'product_name' => self::DEFAULT_PRODUCT_NAME,
                                'price'        => str_replace(',', '.', $lastPrice),
                            ],
                        ],
                    ];
                    $patients[] = $this->preparePatientData($personData, $lastPrice);
                } elseif (str_contains($line, ',') &&
                    str_contains($line, '*') &&
                    ! str_contains($line, '€') &&
                    preg_match(
                        '/^([\p{L}\s\'\-]+),\s+([\p{L}\s\'\-]+)\s+\*\s*(\d{2}\.\d{2}\.\d{4})\s+(\d{2}\.\d{2}\.\d{4})$/u',
                        $line,
                        $matches
                    )
                ) {
                    // exceptional case in buchem invoice
                    // Van Drecheet, Marc * 29.10.1985 26.08.2025
                    // Van Drecheet, Marc * 29.10.1985 26.08.2025
                    $lastName = $matches[1];
                    $firstName = $matches[2];
                    $lastFoundedName = $lastName.', '.$firstName;
                    $lastBirthDate = $matches[3];
                    $lastExamDate = $matches[4];

                    $currentPersonData = [
                        'name'     => $lastFoundedName,
                        'birthday' => $this->formatDate($lastBirthDate),
                        'products' => [
                            [
                                'exam_date'    => $this->formatDate($lastExamDate, 'Y-m-d'),
                                'product_name' => self::DEFAULT_PRODUCT_NAME,
                            ],
                        ],
                    ];

                } elseif ((str_contains($line, ',') || str_contains($line, '-')) && ! str_contains($line, '*') && ! str_contains($line, '€') && empty($foundLastNameBySeparatorLineNumber)) {
                    if (! is_null($currentPersonData)) {
                        if (is_null($lastPrice)) {
                            throw new Exception('missing last price for patient: '.$currentPersonData['name']);
                        }
                        // store previous patient
                        $patients[] = $this->preparePatientData($currentPersonData, $lastPrice);

                        $currentPersonData = null;
                        $lastPrice = null;
                    }
                    if (str_contains($line, '-')) {
                        $foundLastNameBySeparatorLineNumber = $lineNumber;
                    }
                    $lastFoundedName = $line;
                    Log::debug('Found lastname line', [
                        'line_number' => $lineNumber,
                        'name'        => $lastFoundedName.' ('.$line.')',
                    ]);

                } elseif (str_contains($line, ',') && ! str_contains($line, '*') && ! str_contains($line, '€') && ! empty($foundLastNameBySeparatorLineNumber)) {
                    // second part last name on new line
                    $lastFoundedName .= $line;
                    Log::debug('Found second part last name', [
                        'line_number' => $lineNumber,
                        'name'        => $lastFoundedName.' ('.$line.')',
                    ]);
                    $foundLastNameBySeparatorLineNumber = null;
                } elseif (! str_contains($line, ',') && ! str_contains($line, '*') && ! str_contains($line, '€') && ! empty($lastFoundedName) && ! empty(trim($line)) && ! preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $line, $matches)) {
                    // first name on new line (skip empty lines)
                    $lastFoundedName .= $line;
                    Log::debug('Found firstname line', [
                        'line_number' => $lineNumber,
                        'name'        => $lastFoundedName.' ('.$line.')',
                    ]);
                } elseif (! empty($lastFoundedName) && preg_match('/\*(\d{2}\.\d{2}\.\d{4})\s+(\d{2}\.\d{2}\.\d{4})\s+(\d{1,3},\d{2})\s*€/', $line, $matches)) {
                    // *16.02.1969 22.05.2025 661,55 €
                    $lastPrice = $matches[3];
                    if ($currentPersonData && $lastPrice) {
                        $patients[] = $this->preparePatientData($currentPersonData, $lastPrice);
                        $lastPrice = null;
                    }
                    $lastExamDate = $matches[2];
                    $currentPersonData = [
                        'name'     => $lastFoundedName,
                        'birthday' => $this->formatDate($matches[1]),
                        'products' => [
                            [
                                'exam_date'    => $this->formatDate($matches[2], 'Y-m-d'),
                                'product_name' => self::DEFAULT_PRODUCT_NAME,
                                'price'        => $lastPrice,
                            ],
                        ],
                    ];

                    Log::debug('Found patient data (* datum price)', [
                        'line_number' => $lineNumber,
                        'name'        => $lastFoundedName,
                        'birthday'    => $matches[1],
                        'price'       => $lastPrice,
                        'exam_date'   => $lastExamDate,
                    ]);
                    $lastFoundedName = null;
                } elseif (! empty($lastFoundedName) && preg_match('/(\w+)\s+\*(\d{2}\.\d{2}\.\d{4})\s+(\d{2}\.\d{2}\.\d{4})/', $line, $matches)) {
                    // Desmond *03.08.1980 13.06.2025
                    $lastFoundedName .= $matches[1];
                    if ($currentPersonData && $lastPrice) {
                        $patients[] = $this->preparePatientData($currentPersonData, $lastPrice);
                        $lastPrice = null;
                    }
                    $lastExamDate = $matches[3];
                    $currentPersonData = [
                        'name'     => $lastFoundedName,
                        'birthday' => $this->formatDate($matches[2]),
                        'products' => [
                            [
                                'exam_date'    => $this->formatDate($lastExamDate, 'Y-m-d'),
                                'product_name' => self::DEFAULT_PRODUCT_NAME,
                            ],
                        ],
                    ];

                    Log::debug('Found patient data (voornaam * datum)', [
                        'line_number' => $lineNumber,
                        'name'        => $lastFoundedName,
                        'birthday'    => $matches[2],
                        'exam_date'   => $lastExamDate,
                    ]);
                    $lastFoundedName = null;
                } elseif (! empty($lastFoundedName) && preg_match('/(\w+)\s+\*(\d{2}\.\d{2}\.\d{4})/', $line, $matches)) {
                    // Huiberdine *13.11.1955
                    $lastFoundedName .= $matches[1];
                    // Store as raw date string, will be formatted when creating currentPersonData
                    $lastBirthDate = $matches[2];
                    Log::debug('Found patient data (voornaam * birthdate)', [
                        'line_number' => $lineNumber,
                        'name'        => $lastFoundedName,
                        'birthday'    => $lastBirthDate,
                    ]);
                } elseif (! empty($lastFoundedName) && preg_match('/^\*(\d{2}\.\d{2}\.\d{4})\s+(\d{2}\.\d{2}\.\d{4})/', $line, $matches)) {
                    // *03.08.1980 13.06.2025
                    if ($currentPersonData && $lastPrice) {
                        $patients[] = $this->preparePatientData($currentPersonData, $lastPrice);
                        $lastPrice = null;
                    }
                    $lastExamDate = $matches[2];
                    $currentPersonData = [
                        'name'     => $lastFoundedName,
                        'birthday' => $this->formatDate($matches[1]),
                        'products' => [
                            [
                                'exam_date'    => $this->formatDate($lastExamDate, 'Y-m-d'),
                                'product_name' => self::DEFAULT_PRODUCT_NAME,
                            ],
                        ],
                    ];

                    Log::debug('Found patient data (* datum)', [
                        'line_number' => $lineNumber,
                        'name'        => $lastFoundedName,
                        'birthday'    => $matches[1],
                        'exam_date'   => $lastExamDate,
                    ]);
                    $lastFoundedName = null;
                } elseif (! empty($lastFoundedName) && preg_match('/^\*(\d{2}\.\d{2}\.\d{4})$/', $line, $matches)) {
                    // *03.08.1980 (in losse delen van *03.08.1980 13.06.2025, dit is deel 1 -> birthday)
                    // Store as string, will be formatted when we create currentPersonData
                    $lastBirthDate = $matches[1];
                    Log::debug('Found patient birthdate date (* datum)', [
                        'line_number' => $lineNumber,
                        'name'        => $lastFoundedName,
                        'birthday'    => $lastBirthDate,
                        'exam_date'   => $lastExamDate,
                    ]);
                } elseif (! empty($lastFoundedName) && ! empty($lastBirthDate) && preg_match('/^(\d{2}\.\d{2}\.\d{4})\s+(\d{1,3},\d{2})\s*€/', $line, $matches)) {
                    // 01.10.2025 348,31 € (exam date + price on same line, after *birthday on previous line)
                    $lastExamDate = $this->formatDate($matches[1], 'Y-m-d');
                    $lastPrice = str_replace(',', '.', $matches[2]);

                    // Store previous patient if exists
                    if ($currentPersonData && $lastPrice) {
                        $patients[] = $this->preparePatientData($currentPersonData, $lastPrice);
                    }

                    // Create new patient data
                    // Keep birthday in d.m.Y format (as it appears in PDF), only format exam_date
                    $currentPersonData = [
                        'name'     => $lastFoundedName,
                        'birthday' => $this->formatDate($lastBirthDate),
                        'products' => [
                            [
                                'exam_date'    => $lastExamDate,
                                'product_name' => self::DEFAULT_PRODUCT_NAME,
                                'price'        => $lastPrice,
                            ],
                        ],
                    ];

                    Log::debug('Found patient data (exam date + price after birthday)', [
                        'line_number' => $lineNumber,
                        'name'        => $lastFoundedName,
                        'birthday'    => $lastBirthDate,
                        'exam_date'   => $lastExamDate,
                        'price'       => $lastPrice,
                    ]);

                    // Store this patient immediately since we have all data
                    $patients[] = $this->preparePatientData($currentPersonData, $lastPrice);

                    // Reset for next patient
                    $lastFoundedName = null;
                    $lastBirthDate = null;
                    $lastExamDate = null;
                    $lastPrice = null;
                    $currentPersonData = null;
                } elseif (! empty($lastFoundedName) && ! empty($lastBirthDate) && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $line, $matches)) {
                    // 03.08.1980 (in losse delen van *03.08.1980 13.06.2025, dit is deel 2 -> examDate)
                    $lastExamDate = $this->formatDate($matches[0], 'Y-m-d');
                    if ($currentPersonData && $lastPrice) {
                        $patients[] = $this->preparePatientData($currentPersonData, $lastPrice);
                        $lastPrice = null;
                    }
                    $currentPersonData = [
                        'name'     => $lastFoundedName,
                        'birthday' => $this->formatDate($lastBirthDate),
                        'products' => [
                            [
                                'exam_date'    => $lastExamDate,
                                'product_name' => self::DEFAULT_PRODUCT_NAME,
                            ],
                        ],
                    ];

                    Log::debug('Found patient data (datum)', [
                        'line_number' => $lineNumber,
                        'name'        => $lastFoundedName,
                        'birthday'    => $lastBirthDate,
                        'exam_date'   => $lastExamDate,
                    ]);
                    $lastFoundedName = null;
                    $lastBirthDate = null;
                    $lastExamDate = null;
                } elseif (preg_match('/(?<![\d.,+])\b(\d{1,3},\d{2})\s*€/', $line, $matches)) {
                    // '/(?<![+\d])([\d,.]+)\s*€/'
                    // Herken prijs regel, negeer het totaal op het einde...vandaar nu de strlen < 5
                    $price = str_replace(',', '.', $matches[1]);
                    if (strlen($price) < 9) {
                        // ignore total price by strlen
                        $lastPrice = $price;
                        if ($currentPersonData) {
                            // Update het laatste product met de prijs
                            $lastIndex = count($currentPersonData['products']) - 1;
                            $currentPersonData['products'][$lastIndex]['price'] = $lastPrice;
                            Log::debug('Update price', [
                                'line_number' => $lineNumber,
                                'price'       => $lastPrice,
                                'line'        => $line,
                            ]);
                            $patients[] = $this->preparePatientData($currentPersonData, $lastPrice);
                            $lastPrice = null;
                            $lastBirthDate = null;
                            $lastFoundedName = null;
                            $lastExamDate = null;
                            $currentPersonData = null;
                        }
                    }
                } else {
                    Log::debug('Skipping line', [
                        'line_number'       => $lineNumber,
                        'content'           => $line,
                        'last_founded_name' => $lastFoundedName,
                    ]);
                }

                //                if (!empty($line) && $currentPersonData) {
                //                    // Update het laatste product met de productnaam
                //                    $lastIndex = count($currentPersonData['products']) - 1;
                //                    $currentPersonData['products'][$lastIndex]['product_name'] = trim($line);
                //                    Log::debug('Update product name', [
                //                        'line_number' => $lineNumber,
                //                        'product_name' => trim($line),
                //                    ]);
                //                }
            }
        }

        // Verwerk laatste persoon als die nog niet verwerkt is
        if ($currentPersonData && $lastPrice) {
            $patients[] = $this->preparePatientData($currentPersonData, $lastPrice);
        }

        Log::info('Finished parsing PDF', [
            'invoice_id'          => $invoice->id,
            'patient_count'       => count($patients),
            'table_headers_found' => $tableHeadersFound,
            'reference_date'      => $referenceDate,
            'last_founded_name'   => $lastFoundedName,
            'current_person_data' => $currentPersonData,
            'last_price'          => $lastPrice,
        ]);

        // If no patients found, log more details
        if (empty($patients)) {
            Log::warning('No patients found in PDF', [
                'invoice_id'          => $invoice->id,
                'total_lines'         => count($lines),
                'table_headers_found' => $tableHeadersFound,
                'sample_lines'        => array_slice($lines, 0, 50),
            ]);
        }

        return [
            'reference_date' => $referenceDate,
            'patients'       => $patients,
        ];
    }

    private function preparePatientData(array $personData, ?string $price = null): array
    {
        // Als we firstname en lastname direct hebben
        if (isset($personData['firstname']) && isset($personData['lastname'])) {
            $baseData = [
                'firstname' => $personData['firstname'],
                'lastname'  => $personData['lastname'],
                'birthday'  => $personData['birthday'],
                'products'  => $personData['products'] ?? [],
            ];

            // Als er een enkel product is en geen products array
            if (empty($baseData['products']) && (isset($personData['exam_date']) || isset($personData['exam_date']))) {
                $baseData['products'][] = [
                    'exam_date'    => $personData['exam_date'] ?? $personData['exam_date'] ?? null,
                    'product_name' => $personData['product_name'] ?: self::DEFAULT_PRODUCT_NAME,
                    'price'        => $price ?? $personData['price'],
                ];
            }

            return $baseData;
        }

        // Als we een naam string hebben die we moeten splitsen
        $nameParts = explode(',', $personData['name']);
        if (count($nameParts) === 2) {
            $lastname = trim($nameParts[0]);
            $firstname = trim($nameParts[1]);
        } else {
            $firstname = '';
            $lastname = trim($personData['name']);
        }

        $baseData = [
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'birthday'  => $personData['birthday'],
            'products'  => $personData['products'] ?? [],
        ];

        // Als er een enkel product is en geen products array
        if (empty($baseData['products']) && isset($personData['exam_date'])) {
            $baseData['products'][] = [
                'exam_date'    => $personData['exam_date'],
                'product_name' => $personData['product_name'] ?: self::DEFAULT_PRODUCT_NAME,
                'price'        => $price,
            ];
        }
        Log::debug('ADD person: '.$baseData['firstname'].' '.$baseData['lastname'].' - '.$baseData['birthday']);

        return $baseData;
    }

    private function extractTextFromPdfClinic(InkoopInvoice $invoice, $pdfText): array
    {
        $this->birthdayOutputFormat = 'Y-m-d'; // Clinic uses Y-m-d format
        Log::info('Starting PDF parsing for clinic', [
            'invoice_id' => $invoice->id,
        ]);

        $lines = explode("\n", $pdfText);
        $patients = [];
        $referenceDate = null;
        $birthday = null;
        $patientName = null;
        $foundOperationskosten = false;
        $price = null;
        $firstname = null;
        $lastname = null;

        // Debug: Log alle regels
        foreach ($lines as $i => $line) {
            Log::info('Line '.$i, ['content' => $line]);
        }

        // Zoek de naam (staat meestal op regel 11)
        if (isset($lines[11])) {
            $patientName = trim($lines[11]);

            // Zoek naar een hoofdletter in het midden van de naam
            if (preg_match('/^([A-Z][a-z]+)([A-Z][a-z]+)$/', $patientName, $matches)) {
                $firstname = $matches[1];
                $lastname = $matches[2];
            } else {
                // Fallback: split op spaties als er geen hoofdletter patroon is
                $nameParts = explode(' ', $patientName);
                if (count($nameParts) >= 2) {
                    $firstname = array_shift($nameParts);
                    $lastname = implode(' ', $nameParts);
                } else {
                    $firstname = $patientName;
                    $lastname = '';
                }
            }

            Log::info('Found patient name', [
                'name'      => $patientName,
                'firstname' => $firstname,
                'lastname'  => $lastname,
            ]);
        }

        // Zoek de geboortedatum (staat meestal op regel 12)
        if (isset($lines[12]) && preg_match('/(\d{2}\.\d{2}\.\d{4})/', $lines[12], $match)) {
            $birthday = $this->formatDate($match[1]);
            Log::info('Found birthday', ['date' => $birthday]);
        }

        // Zoek de referentiedatum (staat meestal op regel 18)
        if (isset($lines[18]) && preg_match('/(\d{2}\.\d{2}\.\d{4})/', $lines[18], $match)) {
            $referenceDate = $this->formatDate($match[1], 'Y-m-d');
            Log::info('Found reference date', ['date' => $referenceDate]);
        }

        // Zoek de prijs
        foreach ($lines as $line) {
            if (preg_match('/(\d+)\.(\d{3},\d{2})\s*€/', $line, $match)) {
                // Combineer de duizendtallen en decimalen
                $fullPrice = $match[1].$match[2];
                // Vervang komma door punt voor decimale notatie
                $price = str_replace(',', '.', $fullPrice);
                $foundOperationskosten = true;
                Log::info('Found price', [
                    'original'  => $line,
                    'thousands' => $match[1],
                    'decimals'  => $match[2],
                    'price'     => $price,
                ]);
                break;
            }
        }

        // Validatie
        if (! $referenceDate) {
            throw new RuntimeException('Referentiedatum (Rechnungsdatum) niet gevonden.');
        }
        if (! $patientName || ! $birthday) {
            throw new RuntimeException('Patiëntgegevens (naam of geboortedatum) niet gevonden.');
        }
        if (! $foundOperationskosten) {
            throw new RuntimeException('Factuurregel "Operationskosten" niet gevonden.');
        }

        // Voeg patiënt toe aan de lijst
        $patients[] = [
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'birthday'  => $birthday,
            'products'  => [
                [
                    'exam_date'    => $referenceDate,
                    'product_name' => 'Operationskosten',
                    'price'        => $price,
                ],
            ],
        ];

        return [
            'reference_date' => $referenceDate,
            'patients'       => $patients,
        ];
    }

    private function formatDate(string $date, ?string $outputFormat = null): string
    {
        // Use birthday format for birthdays, or provided format, or default Y-m-d
        $format = $outputFormat ?? $this->birthdayOutputFormat;

        // Check if date is already in the desired output format
        if ($format === 'Y-m-d' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        if ($format === 'd.m.Y' && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            return $date;
        }

        // Try to parse as d.m.Y format
        $dateTime = DateTime::createFromFormat('d.m.Y', $date);
        if (! $dateTime) {
            // Try to parse as Y-m-d format
            $dateTime = DateTime::createFromFormat('Y-m-d', $date);
            if (! $dateTime) {
                throw new RuntimeException("Ongeldige datumformaat: {$date}");
            }
        }

        return $dateTime->format($format);
    }

    /**
     * Extract text from PDF by grouping text objects on the same Y-coordinate
     * This ensures columns on the same visual line are read as one line
     */
    private function extractTextByCoordinates($pdf): string
    {
        try {
            $pages = $pdf->getPages();
            $allLines = [];

            foreach ($pages as $page) {
                // Try to get text with position information
                $text = $page->getText();

                // Try to get text array if available (some PDF parsers support this)
                $textArray = null;
                if (method_exists($page, 'getTextArray')) {
                    $textArray = $page->getTextArray();
                } elseif (method_exists($page, 'get')) {
                    $details = $page->getDetails();
                    // Check if we can access text objects with coordinates
                    if (isset($details['text'])) {
                        $textArray = $details['text'];
                    }
                }

                // If we can't get detailed text objects, use simple text extraction
                if (empty($textArray) || ! is_array($textArray)) {
                    // For now, use the simple text but try to improve line detection
                    $lines = explode("\n", $text);
                    $allLines = array_merge($allLines, array_filter($lines, fn ($l) => ! empty(trim($l))));

                    continue;
                }

                // Group text by Y-coordinate (rounded to handle slight variations)
                $yGroups = [];
                foreach ($textArray as $textObj) {
                    if (is_array($textObj) && isset($textObj['y'])) {
                        $y = round((float) $textObj['y'], 1); // Round to 1 decimal to group nearby lines
                        if (! isset($yGroups[$y])) {
                            $yGroups[$y] = [];
                        }
                        $yGroups[$y][] = [
                            'x'    => (float) ($textObj['x'] ?? 0),
                            'text' => $textObj['text'] ?? '',
                        ];
                    }
                }

                // Sort each Y-group by X-coordinate (left to right) and combine
                foreach ($yGroups as $y => $textItems) {
                    usort($textItems, fn ($a, $b) => $a['x'] <=> $b['x']);
                    $lineText = implode(' ', array_column($textItems, 'text'));
                    if (! empty(trim($lineText))) {
                        $allLines[] = trim($lineText);
                    }
                }
            }

            $result = implode("\n", $allLines);

            // If result is empty, try to get simple text as fallback
            if (empty(trim($result))) {
                try {
                    $pages = $pdf->getPages();
                    $simpleText = [];
                    foreach ($pages as $page) {
                        $simpleText[] = $page->getText();
                    }
                    $result = implode("\n", $simpleText);
                } catch (Exception $e2) {
                    Log::warning('Error in fallback text extraction', [
                        'error' => $e2->getMessage(),
                    ]);
                }
            }

            return $result;
        } catch (Exception $e) {
            Log::warning('Error in coordinate-based extraction', [
                'error' => $e->getMessage(),
            ]);
            // Fallback to simple text extraction
            try {
                $pages = $pdf->getPages();
                $simpleText = [];
                foreach ($pages as $page) {
                    $simpleText[] = $page->getText();
                }

                return implode("\n", $simpleText);
            } catch (Exception $e2) {
                return '';
            }
        }
    }

    /**
     * Normalize PDF text to handle inconsistent line breaks and spacing
     * This helps ensure columns are read consistently
     */
    private function normalizePdfText(string $text): string
    {
        // Replace tabs with spaces
        $text = str_replace("\t", ' ', $text);

        // Replace multiple spaces with single space (but preserve intentional spacing)
        $text = preg_replace('/[ \t]+/', ' ', $text);

        // Normalize line breaks - ensure consistent \n
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove trailing spaces from lines
        $lines = explode("\n", $text);
        $lines = array_map('rtrim', $lines);

        // Rejoin lines
        return implode("\n", $lines);
    }
}
