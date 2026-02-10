<?php

namespace Database\Seeders;

use App\Enums\ContactLabel;
use App\Models\Address;
use App\Models\Clinic;

class ClinicSeeder extends BaseSeeder
{
    public function run(): void
    {
        // If clinics already exist (seeded by installer or previous runs), skip to avoid duplicates
        if (Clinic::count() > 0) {
            return;
        }

        /**
         * Contact + address data sourced from:
         * /Users/mark/Downloads/Klinieken stamgegevens Sept 30 2025.csv
         *
         * IMPORTANT: Do not add new clinics from this CSV; only enrich existing seeded clinics.
         */
        $csvByExternalId = [
            'db0c47c3-aaf8-ce22-a112-4d9abef87a8f' => [
                'emails'                                  => [],
                'phones'                                  => ['+49 5241 210 14 0'],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => null,
                'postal'                                  => [
                    'street_and_number' => 'Neuenkirchener Straße 97',
                    'house_number'      => null,
                    'postal_code'       => '33332',
                    'city'              => 'Gütersloh',
                    'country'           => 'Germany',
                ],
            ],
            'ae8ea8a1-95c4-d665-4373-5ecfc67d6096' => [
                'emails'                                  => ['bielas@suedring.eu'],
                'phones'                                  => [],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Bergstrasse 26',
                    'house_number'      => '26',
                    'postal_code'       => '44791',
                    'city'              => 'Bochum',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            'cd901e3d-8015-d910-5f64-5f36336bcfc8' => [
                'emails'                                  => [],
                'phones'                                  => ['+492117817950'],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Reichsstraße 59',
                    'house_number'      => '59',
                    'postal_code'       => '40217',
                    'city'              => 'Düsseldorf',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            '6e7519bf-aa5f-90e1-e18e-4d9da38d7e96' => [
                'emails'                                  => [],
                'phones'                                  => ['+49 (0)5921 84 0'],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Albert-Schweitzer-Str. 10',
                    'house_number'      => null,
                    'postal_code'       => '48527',
                    'city'              => 'Nordhorn',
                    'country'           => 'Germany',
                ],
                'postal' => null,
            ],
            'da548322-a3a3-672a-2dd0-4d9adaf29b78' => [
                'emails'                                  => [],
                'phones'                                  => ['+49 281 106 1'],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => null,
                'postal'                                  => [
                    'street_and_number' => 'Schermbecker Landstraße 88',
                    'house_number'      => null,
                    'postal_code'       => '46485',
                    'city'              => 'Wesel',
                    'country'           => 'Germany',
                ],
            ],
            '3e31d364-93f7-837f-6e7c-5e3027d93466' => [
                'emails'                                  => [],
                'phones'                                  => [],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Bergstrasse 25',
                    'house_number'      => null,
                    'postal_code'       => '44791',
                    'city'              => 'Bochum',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            'f90e0ef6-e600-b56c-b9af-679b4021bc10' => [
                'emails'                                  => ['termine@praxis-gradus.de'],
                'phones'                                  => ['+49 211 96839654'],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Luise-Rainer-Straße 6-10',
                    'house_number'      => '6-10',
                    'postal_code'       => '40235',
                    'city'              => 'Dusseldorf',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            '847b31c2-60fd-12f8-3302-4e6a176653bd' => [
                'emails'                                  => [],
                'phones'                                  => ['0546-473030'],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'De Wulp 53',
                    'house_number'      => null,
                    'postal_code'       => '7609 LN',
                    'city'              => 'Almelo',
                    'country'           => 'Nederland',
                ],
                'postal' => null,
            ],
            '65a7b039-8fd3-c49e-1029-567271e9f437' => [
                'emails'                                  => ['info@grandarc.de'],
                'phones'                                  => ['+49 211 4477 1000'],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Luise-Rainer-Straße 6-10',
                    'house_number'      => '6-10',
                    'postal_code'       => '40235',
                    'city'              => 'Düsseldorf',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            '839fbf0b-d86b-2aa8-b9e2-5eb01973e3b3' => [
                'emails'                                  => [],
                'phones'                                  => [],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Heibeckstr. 30',
                    'house_number'      => null,
                    'postal_code'       => '45711',
                    'city'              => 'Datteln',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            '8ec053e9-9815-903a-f50b-5eb015d338a6' => [
                'emails'                                  => [],
                'phones'                                  => [],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Röntgenstr. 10',
                    'house_number'      => null,
                    'postal_code'       => '45661',
                    'city'              => 'Recklinghausen',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            'ccdb4356-923a-8408-1e4a-5cf91286a7c9' => [
                'emails'                                  => ['info@pradus.de'],
                'phones'                                  => ['+49 211 8308 7644'],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Reichsstraße 59',
                    'house_number'      => '59',
                    'postal_code'       => '40217',
                    'city'              => 'Düsseldorf',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            '6fd1b389-dcb8-2718-c908-4e6a19367911' => [
                'emails'                                  => [],
                'phones'                                  => [],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Theodor-Althoff-Straße 47',
                    'house_number'      => null,
                    'postal_code'       => '45133',
                    'city'              => 'Essen',
                    'country'           => 'Germany',
                ],
                'postal' => null,
            ],
            '619c39c5-c437-6e2c-17c2-5eabedb46fe4' => [
                'emails'                                  => [],
                'phones'                                  => ['+49201877880'],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Girardetstrasse 8',
                    'house_number'      => null,
                    'postal_code'       => '45131',
                    'city'              => 'Essen',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            '39bd339d-0b40-4a80-1012-5cf902f1960d' => [
                'emails'                                  => ['info@radiologie-duesseldorf-mitte.de'],
                'phones'                                  => ['+49 211 5422 8000'],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Marienstraße 10',
                    'house_number'      => '10',
                    'postal_code'       => '40212',
                    'city'              => 'Düsseldorf',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            '4b7dd6e9-2824-b1fe-d515-5e9839813d42' => [
                'emails'                                  => [],
                'phones'                                  => [],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Hordeler strasse 7-9',
                    'house_number'      => null,
                    'postal_code'       => '44651',
                    'city'              => 'Herne',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            'c4de98b0-4d3e-02c4-4b80-4ea2a2ad376a' => [
                'emails'                                  => [],
                'phones'                                  => [],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Henricistrasse 40',
                    'house_number'      => null,
                    'postal_code'       => '45136',
                    'city'              => 'Essen',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            '7a30fda1-5764-fb0a-12c7-63286b29f7f7' => [
                'emails'                                  => [],
                'phones'                                  => ['+492115670'],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Am Heerdter Krankenhaus 2',
                    'house_number'      => null,
                    'postal_code'       => '40549',
                    'city'              => 'Düsseldorf',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            'd0257a7c-7b68-b517-25c6-5f71a142b666' => [
                'emails'                                  => [],
                'phones'                                  => [],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Möllenweg 22',
                    'house_number'      => null,
                    'postal_code'       => '48599',
                    'city'              => 'Gronau',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
            '768753bc-2f1c-9651-99e7-50867822c36d' => [
                'emails'                                  => [],
                'phones'                                  => [],
                'is_postal_address_same_as_visit_address' => true,
                'visit'                                   => [
                    'street_and_number' => 'Vredener Strasse 58',
                    'house_number'      => null,
                    'postal_code'       => '48703',
                    'city'              => 'Stadtlohn',
                    'country'           => 'Duitsland',
                ],
                'postal' => null,
            ],
        ];

        $clinics = [
            [
                'external_id'                   => 'db0c47c3-aaf8-ce22-a112-4d9abef87a8f',
                'is_active'                     => false,
                'name'                          => 'ALTA Klinik GmbH',
                'registration_form_clinic_name' => 'Alta',
            ],
            [
                'external_id'                   => 'ae8ea8a1-95c4-d665-4373-5ecfc67d6096',
                'is_active'                     => true,
                'name'                          => 'Ambulante Kardiologie Augusta',
                'registration_form_clinic_name' => 'Amb. Kardio Augusta',
            ],
            [
                'external_id'                   => '85e31add-d6bd-092c-f009-4e71fad20c0f',
                'is_active'                     => false,
                'name'                          => 'Apollo Arthotel Brugge',
                'registration_form_clinic_name' => 'Apollo Hotel',
            ],
            [
                'external_id'                   => '4006e894-d718-f009-4b65-4e71f864a02d',
                'is_active'                     => false,
                'name'                          => 'AZ St. Jan Brugge',
                'registration_form_clinic_name' => 'AZ St. Jan',
            ],
            [
                'external_id'                   => 'cd901e3d-8015-d910-5f64-5f36336bcfc8',
                'is_active'                     => true,
                'name'                          => 'Clinic Bel Etage',
                'registration_form_clinic_name' => 'Bel Etage',
            ],
            [
                'external_id'                   => '6e7519bf-aa5f-90e1-e18e-4d9da38d7e96',
                'is_active'                     => false,
                'name'                          => 'Euregio-Klinik GmbH',
                'registration_form_clinic_name' => 'Euregio',
            ],
            [
                'external_id'                   => 'da548322-a3a3-672a-2dd0-4d9adaf29b78',
                'is_active'                     => false,
                'name'                          => 'Evangelisches Krankenhaus Wesel GmbH',
                'registration_form_clinic_name' => 'Wesel',
            ],
            [
                'external_id'                   => '3e31d364-93f7-837f-6e7c-5e3027d93466',
                'is_active'                     => true,
                'name'                          => 'Evidia - Augusta Klinik',
                'registration_form_clinic_name' => 'Evidia - Augusta Klinik',
            ],
            [
                'external_id'                   => 'f90e0ef6-e600-b56c-b9af-679b4021bc10',
                'is_active'                     => true,
                'name'                          => 'GRADUS - Orthopadie & Unfallchirurgie Dusseldorf',
                'registration_form_clinic_name' => 'Gradus Dusseldorf',
            ],
            [
                'external_id'                   => '847b31c2-60fd-12f8-3302-4e6a176653bd',
                'is_active'                     => true,
                'name'                          => 'Intertran',
                'registration_form_clinic_name' => 'Intertran',
            ],
            [
                'external_id'                   => '65a7b039-8fd3-c49e-1029-567271e9f437',
                'is_active'                     => false,
                'name'                          => 'Medical Center Düsseldorf',
                'registration_form_clinic_name' => 'Luisenkrankenhaus Dusseldorf',
            ],
            [
                'external_id'                   => '839fbf0b-d86b-2aa8-b9e2-5eb01973e3b3',
                'is_active'                     => false,
                'name'                          => 'ONZ Datteln',
                'registration_form_clinic_name' => 'ONZ Datteln',
            ],
            [
                'external_id'                   => '8ec053e9-9815-903a-f50b-5eb015d338a6',
                'is_active'                     => false,
                'name'                          => 'ONZ Recklinghausen',
                'registration_form_clinic_name' => 'ONZ Recklinghausen',
            ],
            [
                'external_id'                   => 'ccdb4356-923a-8408-1e4a-5cf91286a7c9',
                'is_active'                     => true,
                'name'                          => 'Pradus Medical Center Düsseldorf',
                'registration_form_clinic_name' => 'Pradus Dusseldorf',
            ],
            [
                'external_id'                   => '6fd1b389-dcb8-2718-c908-4e6a19367911',
                'is_active'                     => false,
                'name'                          => 'Preventicum',
                'registration_form_clinic_name' => 'Preventicum',
            ],
            [
                'external_id'                   => '619c39c5-c437-6e2c-17c2-5eabedb46fe4',
                'is_active'                     => true,
                'name'                          => 'Procelsio Clinic GMBH',
                'registration_form_clinic_name' => 'Procelsio',
            ],
            [
                'external_id'                   => '39bd339d-0b40-4a80-1012-5cf902f1960d',
                'is_active'                     => false,
                'name'                          => 'Radiologie Düsseldorf Mitte',
                'registration_form_clinic_name' => 'Dusseldorf Mitte',
            ],
            [
                'external_id'                   => '4b7dd6e9-2824-b1fe-d515-5e9839813d42',
                'is_active'                     => true,
                'name'                          => 'Radiologie Herne - EvK Eickel',
                'registration_form_clinic_name' => 'Rad. Herne - EvK Eickel',
            ],
            [
                'external_id'                   => 'c4de98b0-4d3e-02c4-4b80-4ea2a2ad376a',
                'is_active'                     => false,
                'name'                          => 'Radionuk Essen',
                'registration_form_clinic_name' => 'Radionuk Essen',
            ],
            [
                'external_id'                   => '7a30fda1-5764-fb0a-12c7-63286b29f7f7',
                'is_active'                     => true,
                'name'                          => 'Schön Klinik Düsseldorf',
                'registration_form_clinic_name' => 'Schön Kliniek',
            ],
            [
                'external_id'                   => 'd0257a7c-7b68-b517-25c6-5f71a142b666',
                'is_active'                     => false,
                'name'                          => 'St. Antonius-Hospital Gronau GmbH',
                'registration_form_clinic_name' => 'St. Antonius-Hospital',
            ],
            [
                'external_id'                   => '768753bc-2f1c-9651-99e7-50867822c36d',
                'is_active'                     => false,
                'name'                          => 'Maria-Hilf Krankenhaus',
                'registration_form_clinic_name' => 'Maria-Hilf Krankenhaus',
            ],
        ];

        foreach ($clinics as $clinicData) {
            $csv = $csvByExternalId[$clinicData['external_id']] ?? null;

            if ($csv) {
                $phones = $this->sanitizePhones($csv['phones'] ?? []);
                $emails = $this->sanitizeList($csv['emails'] ?? []);

                if (! empty($phones)) {
                    $clinicData['phones'] = $this->toContactItems($phones);
                }

                if (! empty($emails)) {
                    $clinicData['emails'] = $this->toContactItems($emails);
                }

                $visitPayload = $csv['visit'] ?? null;
                $postalPayload = $csv['postal'] ?? null;
                $sameAsVisit = (bool) ($csv['is_postal_address_same_as_visit_address'] ?? false);

                $visitAddress = null;
                $postalAddress = null;

                if ($sameAsVisit) {
                    // If only postal is provided, use it as visit.
                    $visitAddress = $this->createAddressFromCsv($visitPayload ?: $postalPayload);
                    $clinicData['is_postal_address_same_as_visit_address'] = true;
                } else {
                    $visitAddress = $this->createAddressFromCsv($visitPayload);
                    $postalAddress = $this->createAddressFromCsv($postalPayload);

                    // If only postal exists, use it as visit (per requirement).
                    if (! $visitAddress && $postalAddress) {
                        $visitAddress = $postalAddress;
                        $postalAddress = null;
                        $clinicData['is_postal_address_same_as_visit_address'] = true;
                    }
                }

                if ($visitAddress) {
                    $clinicData['visit_address_id'] = $visitAddress->id;
                }

                if ($postalAddress) {
                    $clinicData['postal_address_id'] = $postalAddress->id;
                }

                if ($visitAddress && $postalAddress) {
                    $clinicData['is_postal_address_same_as_visit_address'] = false;
                }
            }

            Clinic::create($clinicData);
        }
    }

    /**
     * @param  array<int, string>|null  $values
     * @return array<int, string>
     */
    private function sanitizeList(?array $values): array
    {
        $values = $values ?? [];

        $out = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Sanitize phone numbers:
     * - trim
     * - remove all whitespace (spaces, tabs, etc.)
     * - unique
     *
     * @param  array<int, string>|null  $values
     * @return array<int, string>
     */
    private function sanitizePhones(?array $values): array
    {
        $values = $values ?? [];

        $out = [];
        foreach ($values as $value) {
            $value = preg_replace('/\s+/u', '', (string) $value);
            $value = trim((string) $value);
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Convert a list of scalar contact values into the standard contact array shape:
     * [['value' => string, 'label' => string, 'is_default' => bool], ...]
     *
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, label: string, is_default: bool}>
     */
    private function toContactItems(array $values): array
    {
        $values = array_values(array_filter($values, fn ($v) => trim((string) $v) !== ''));
        $values = array_values(array_unique($values));

        $out = [];
        foreach ($values as $i => $value) {
            $out[] = [
                'value'      => (string) $value,
                'label'      => ContactLabel::default()->value,
                'is_default' => $i === 0,
            ];
        }

        return $out;
    }

    /**
     * CSV address payload shape:
     * - street_and_number: string|null
     * - house_number: string|null (optional override)
     * - postal_code: string|null
     * - city: string|null
     * - country: string|null
     */
    private function createAddressFromCsv(?array $csvAddress): ?Address
    {
        if (! $csvAddress) {
            return null;
        }

        $postalCode = isset($csvAddress['postal_code']) ? trim((string) $csvAddress['postal_code']) : '';
        if ($postalCode === '') {
            return null;
        }

        $parsed = $this->splitStreetAndNumber(
            $csvAddress['street_and_number'] ?? null,
            $csvAddress['house_number'] ?? null
        );

        if (! $parsed) {
            return null;
        }

        $addressData = [
            'street'              => $parsed['street'] ?? null,
            'house_number'        => $parsed['house_number'],
            'house_number_suffix' => $parsed['house_number_suffix'] ?? null,
            'postal_code'         => $postalCode,
            'city'                => isset($csvAddress['city']) ? trim((string) $csvAddress['city']) : null,
            'country'             => isset($csvAddress['country']) ? trim((string) $csvAddress['country']) : null,
        ];

        // Address columns are nullable except house_number + postal_code.
        // Keep empty strings out of the DB.
        foreach ($addressData as $k => $v) {
            if (is_string($v)) {
                $v = trim($v);
                $addressData[$k] = $v === '' ? null : $v;
            }
        }

        if (! $addressData['house_number']) {
            return null;
        }

        return Address::create($addressData);
    }

    /**
     * Split a combined "Street + House number" string into structured components.
     *
     * Examples:
     * - "Marienstraße 10" -> street="Marienstraße", house_number="10"
     * - "Luise-Rainer-Straße 6-10" -> street="Luise-Rainer-Straße", house_number="6-10"
     * - "Hordeler strasse 7-9" -> street="Hordeler strasse", house_number="7-9"
     */
    private function splitStreetAndNumber(?string $streetAndNumber, ?string $houseNumberOverride): ?array
    {
        $streetAndNumber = $streetAndNumber !== null ? trim($streetAndNumber) : '';
        $houseNumberOverride = $houseNumberOverride !== null ? trim($houseNumberOverride) : '';

        if ($houseNumberOverride !== '') {
            // Best-effort: derive street by stripping trailing house number occurrence.
            if ($streetAndNumber !== '' && str_ends_with($streetAndNumber, ' '.$houseNumberOverride)) {
                $street = trim(substr($streetAndNumber, 0, -1 * (strlen($houseNumberOverride) + 1)));
            } else {
                // Fallback to parsing the full string; override house number if parse succeeds.
                $parsed = $this->splitStreetAndNumber($streetAndNumber, null);
                $street = $parsed['street'] ?? null;
            }

            return [
                'street'              => $street !== '' ? $street : null,
                'house_number'        => $houseNumberOverride,
                'house_number_suffix' => null,
            ];
        }

        if ($streetAndNumber === '') {
            return null;
        }

        // Capture last token that starts with a digit: "Some Street 12A", "Street 6-10", etc.
        if (preg_match('/^(.*\S)\s+(\d[\dA-Za-z\-\/]*)(?:\s+([A-Za-z]{1,10}))?$/u', $streetAndNumber, $m)) {
            $street = trim((string) $m[1]);
            $houseNumber = trim((string) $m[2]);
            $suffix = isset($m[3]) ? trim((string) $m[3]) : null;

            return [
                'street'              => $street !== '' ? $street : null,
                'house_number'        => $houseNumber,
                'house_number_suffix' => $suffix !== '' ? $suffix : null,
            ];
        }

        return null;
    }

    // Intentionally no address equality/normalization logic here.
    // If visit and postal are the same, we represent that explicitly in $csvByExternalId.
}
