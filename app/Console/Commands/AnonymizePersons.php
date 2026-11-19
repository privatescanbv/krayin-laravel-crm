<?php

namespace App\Console\Commands;

use App\Enums\PersonGender;
use Faker\Generator as FakerGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnonymizePersons extends Command
{
    /**
     * Tussenvoegsels used to build fake Dutch last names.
     *
     * @var list<string>
     */
    private const LASTNAME_PREFIXES = ['van', 'de', 'van der', 'van den', 'van de', 'ten', 'ter', "'t"];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'persons:anonymize {--force : Skip the confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Anonymize all personal data in the persons table with fake Dutch data. Local development only.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command may only be run in a local development environment.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'This will irreversibly replace all personal data in the persons table (and linked addresses) with fake data. Continue?'
        )) {
            return self::SUCCESS;
        }

        $faker = fake('nl_NL');
        $anonymized = 0;

        // Writes go through the query builder (not Eloquent) so this never fires person
        // observers: those create activity-log rows and push live updates to Keycloak,
        // which would both violate "no other tables changed" and leak fake data to an
        // external system.
        DB::table('persons')->orderBy('id')->chunkById(200, function ($persons) use ($faker, &$anonymized) {
            $addressIds = $persons->pluck('address_id')->filter()->unique()->values();

            $addresses = $addressIds->isEmpty()
                ? collect()
                : DB::table('addresses')->whereIn('id', $addressIds)->get()->keyBy('id');

            foreach ($persons as $person) {
                $this->anonymizePerson($person, $faker);

                if ($person->address_id && $addresses->has($person->address_id)) {
                    $this->anonymizeAddress($addresses->get($person->address_id), $faker);
                }

                $anonymized++;
            }
        });

        $this->info("Anonymized {$anonymized} person(s).");

        return self::SUCCESS;
    }

    /**
     * Replace the identifying fields of a single persons row with fake data.
     * Fields that are already empty are left untouched: anonymization should not invent
     * data that wasn't there.
     */
    private function anonymizePerson(object $person, FakerGenerator $faker): void
    {
        $update = [];

        $firstName = $person->first_name !== null ? $this->fakeFirstName($faker, $person->gender) : null;
        $lastNamePrefix = $person->lastname_prefix !== null
            ? $faker->randomElement(self::LASTNAME_PREFIXES)
            : null;
        $lastName = $person->last_name !== null ? $faker->lastName() : null;

        if ($person->first_name !== null) {
            $update['first_name'] = Str::ucfirst($firstName);
        }

        if ($person->last_name !== null) {
            $update['last_name'] = Str::ucfirst($lastName);
        }

        if ($person->lastname_prefix !== null) {
            $update['lastname_prefix'] = Str::lower($lastNamePrefix);
        }

        if ($person->married_name !== null) {
            $update['married_name'] = Str::ucfirst($faker->lastName());
        }

        if ($person->married_name_prefix !== null) {
            $update['married_name_prefix'] = Str::lower($faker->randomElement(self::LASTNAME_PREFIXES));
        }

        if ($person->initials !== null) {
            $update['initials'] = Str::upper(Str::substr($firstName ?? $faker->firstName(), 0, 1)).'.';
        }

        // The raw "name" column is a legacy, always-filled field predating the
        // first/last name split; it is never read directly by the app (Person::getNameAttribute
        // recomputes it) but may still hold a real name, so it is always refreshed.
        $update['name'] = trim(implode(' ', array_filter([
            $update['first_name'] ?? $person->first_name,
            $update['lastname_prefix'] ?? $person->lastname_prefix,
            $update['last_name'] ?? $person->last_name,
        ]))) ?: $faker->name();

        if ($person->date_of_birth !== null) {
            $update['date_of_birth'] = $faker->dateTimeBetween('-95 years', '-18 years')->format('Y-m-d');
        }

        if ($person->national_identification_number !== null) {
            // BSN-equivalent; a direct identifier, so it is cleared rather than faked.
            $update['national_identification_number'] = null;
        }

        if ($person->general_practitioner_name !== null) {
            $update['general_practitioner_name'] = $faker->name();
        }

        if ($person->general_practitioner_practice !== null) {
            $update['general_practitioner_practice'] = $faker->company();
        }

        if ($person->general_practitioner_phone !== null) {
            $update['general_practitioner_phone'] = $faker->phoneNumber();
        }

        if ($person->emergency_contact_name !== null) {
            $update['emergency_contact_name'] = $faker->name();
        }

        if ($person->emergency_contact_phone !== null) {
            $update['emergency_contact_phone'] = $faker->phoneNumber();
        }

        $emails = json_decode((string) $person->emails, true) ?: [];

        if (! empty($emails)) {
            $emailName = Str::slug(trim(($firstName ?? 'patient').' '.($lastName ?? '')), '.') ?: 'patient';

            foreach ($emails as $index => $email) {
                // Suffixed with the person id to guarantee table-wide uniqueness.
                $emails[$index]['value'] = "{$emailName}.{$person->id}@example.com";
            }

            $update['emails'] = json_encode($emails);
        }

        $phones = json_decode((string) $person->phones, true) ?: [];

        if (! empty($phones)) {
            foreach ($phones as $index => $phone) {
                $phones[$index]['value'] = $faker->phoneNumber();
            }

            $update['phones'] = json_encode($phones);
        }

        DB::table('persons')->where('id', $person->id)->update($update);
    }

    /**
     * Replace the identifying fields of a single addresses row with fake data, leaving
     * already-empty fields untouched.
     */
    private function anonymizeAddress(object $address, FakerGenerator $faker): void
    {
        $update = [];

        if ($address->street !== null) {
            $update['street'] = $faker->streetName();
        }

        if ($address->house_number !== null) {
            $update['house_number'] = (string) $faker->numberBetween(1, 250);
        }

        if ($address->house_number_suffix !== null) {
            $update['house_number_suffix'] = Str::upper($faker->randomLetter());
        }

        if ($address->postal_code !== null) {
            $update['postal_code'] = $faker->postcode();
        }

        if ($address->city !== null) {
            $update['city'] = $faker->city();
        }

        if (! empty($update)) {
            DB::table('addresses')->where('id', $address->id)->update($update);
        }
    }

    /**
     * Fake a first name matching the person's gender when known.
     */
    private function fakeFirstName(FakerGenerator $faker, ?string $gender): string
    {
        return match ($gender) {
            PersonGender::Man->value    => $faker->firstNameMale(),
            PersonGender::Female->value => $faker->firstNameFemale(),
            default                     => $faker->firstName(),
        };
    }
}
