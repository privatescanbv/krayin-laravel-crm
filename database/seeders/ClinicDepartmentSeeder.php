<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\ClinicDepartment;

/**
 * Afdelingen per kliniek voor initiële seed.
 *
 * Standaard krijgt elke actieve kliniek de rijen uit {@see defaultDepartments()} (meestal één "Standaard").
 * Klinieken die meerdere of andere afdelingen nodig hebben, zet je onder {@see departmentsByClinicName()}:
 * sleutel = exacte klinieknaam (zoals in de database), waarde = lijst met afdelingen.
 * Voor die kliniek worden dan alleen die rijen aangemaakt; wil je ook "Standaard" plus extra's, zet "Standaard"
 * expliciet als eerste element in die lijst.
 *
 * Per afdeling: `name` is verplicht; `description` en `email` zijn optioneel. Is `email` null, dan wordt
 * {@see resolveEmail()} gebruikt (zelfde logica als voorheen bij één standaardafdeling).
 */
class ClinicDepartmentSeeder extends BaseSeeder
{
    public function run(): void
    {
        // Skip if departments already exist
        if (ClinicDepartment::count() > 0) {
            return;
        }

        $clinics = Clinic::where('is_active', true)->get();
        $activeNames = $clinics->pluck('name')->all();
        $byClinicName = $this->departmentsByClinicName();

        foreach (array_keys($byClinicName) as $configuredName) {
            if (! in_array($configuredName, $activeNames, true)) {
                $this->command?->warn("ClinicDepartmentSeeder: geen actieve kliniek gevonden voor naam \"{$configuredName}\" (controleer spelling).");
            }
        }

        foreach ($clinics as $clinic) {
            $rows = $byClinicName[$clinic->name] ?? $this->defaultDepartments();

            foreach ($rows as $row) {
                ClinicDepartment::create([
                    'clinic_id'   => $clinic->id,
                    'name'        => $row['name'],
                    'description' => $row['description'] ?? null,
                    'email'       => $row['email'] ?? $this->resolveEmail($clinic),
                ]);
            }
        }
    }

    /**
     * Afdelingen voor klinieken zonder eigen invoer in {@see departmentsByClinicName()}.
     *
     * @return list<array{name: string, description: ?string, email: ?string}>
     */
    protected function defaultDepartments(): array
    {
        return [
            [
                'name'        => 'Standaard',
                'description' => null,
                'email'       => null,
            ],
        ];
    }

    /**
     * Optioneel: per klinieknaam (exact) een volledige lijst afdelingen. Overschrijft het default-pakket voor die kliniek.
     *
     * @return array<string, list<array{name: string, description: ?string, email: ?string}>>
     */
    protected function departmentsByClinicName(): array
    {
        return [
            // Voorbeeld:
            // 'Clinic Bel Etage' => [
            //     ['name' => 'Standaard', 'description' => null, 'email' => null],
            //     ['name' => 'Cardiologie', 'description' => null, 'email' => 'cardiologie@voorbeeld.nl'],
            // ],
        ];
    }

    /**
     * Resolve an email address for the department.
     * Uses the clinic's default/first email if available,
     * otherwise derives one from the clinic name.
     */
    private function resolveEmail(Clinic $clinic): string
    {
        $emails = $clinic->emails ?? [];

        // Find the default email, or fall back to the first one
        foreach ($emails as $entry) {
            if (! empty($entry['is_default']) && ! empty($entry['value'])) {
                return $entry['value'];
            }
        }

        foreach ($emails as $entry) {
            if (! empty($entry['value'])) {
                return $entry['value'];
            }
        }

        // Derive a placeholder from the clinic name
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $clinic->name));
        $slug = trim($slug, '-');

        return "info@{$slug}.nl";
    }
}
