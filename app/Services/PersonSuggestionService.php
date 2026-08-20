<?php

namespace App\Services;

use App\Support\EmailNormalizer;
use App\Support\NameSimilarity;
use App\Support\PhoneNormalizer;
use App\Support\PostcodeNormalizer;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

class PersonSuggestionService
{
    public const REASON_EMAIL = 'email';

    public const REASON_PHONE = 'phone';

    public const REASON_LAST_NAME = 'last_name';

    public const REASON_FIRST_NAME_SIMILAR = 'first_name_similar';

    public const REASON_FIRST_NAME_DIFFERS = 'first_name_differs';

    public const REASON_DOB = 'dob';

    public const REASON_POSTAL_CODE = 'postal_code';

    private const BRANCH_LIMIT = 50;

    private const NAME_SOFT_LIMIT = 30;

    /**
     * @param  array<int>|null  $authorizedUserIds  null = no permission restriction
     * @return Collection<int, Person>
     */
    public function findCandidates(Lead $lead, ?array $authorizedUserIds = null): Collection
    {
        $lead->loadMissing('address');

        $candidates = collect()
            ->concat($this->findByEmail($lead, $authorizedUserIds))
            ->concat($this->findByPhone($lead, $authorizedUserIds))
            ->concat($this->findByLastName($lead, $authorizedUserIds))
            ->unique('id')
            ->values();

        return $candidates
            ->map(function (Person $person) use ($lead) {
                $person->loadMissing('address');
                $reasons = $this->computeReasons($lead, $person);

                if (! $this->isEligible($reasons)) {
                    return null;
                }

                $person->match_reasons = $reasons;

                return $person;
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<string>
     */
    public function computeReasons(Lead $lead, Person $person): array
    {
        $reasons = [];

        if ($this->emailsMatch($lead, $person)) {
            $reasons[] = self::REASON_EMAIL;
        }

        if ($this->phonesMatch($lead, $person)) {
            $reasons[] = self::REASON_PHONE;
        }

        if ($this->lastNamesMatch($lead, $person)) {
            $reasons[] = self::REASON_LAST_NAME;
        }

        $leadFirstBlank = NameSimilarity::isBlank($lead->first_name);
        $personFirstBlank = NameSimilarity::isBlank($person->first_name);
        $firstSimilar = NameSimilarity::firstNamesAreSimilar($lead->first_name, $person->first_name);

        if ($firstSimilar) {
            $reasons[] = self::REASON_FIRST_NAME_SIMILAR;
        } elseif (! $leadFirstBlank && ! $personFirstBlank) {
            $reasons[] = self::REASON_FIRST_NAME_DIFFERS;
        }

        if ($this->datesMatch($lead->date_of_birth, $person->date_of_birth)) {
            $reasons[] = self::REASON_DOB;
        }

        if ($this->postalCodesMatch($lead, $person)) {
            $reasons[] = self::REASON_POSTAL_CODE;
        }

        return $reasons;
    }

    /**
     * @param  list<string>  $reasons
     */
    public function isEligible(array $reasons): bool
    {
        if (in_array(self::REASON_EMAIL, $reasons, true) || in_array(self::REASON_PHONE, $reasons, true)) {
            return true;
        }

        if (! in_array(self::REASON_LAST_NAME, $reasons, true)) {
            return false;
        }

        return in_array(self::REASON_FIRST_NAME_SIMILAR, $reasons, true)
            || in_array(self::REASON_DOB, $reasons, true)
            || in_array(self::REASON_POSTAL_CODE, $reasons, true)
            || (! in_array(self::REASON_FIRST_NAME_DIFFERS, $reasons, true));
    }

    /**
     * @param  array<int>|null  $authorizedUserIds
     * @return Collection<int, Person>
     */
    private function findByEmail(Lead $lead, ?array $authorizedUserIds): Collection
    {
        $variants = $this->emailSearchVariants($lead);
        if ($variants === []) {
            return collect();
        }

        return $this->baseQuery($authorizedUserIds)
            ->where(function (Builder $query) use ($variants) {
                foreach ($variants as $value) {
                    $this->orJsonValueMatch($query, 'emails', $value);
                }
            })
            ->limit(self::BRANCH_LIMIT)
            ->get();
    }

    /**
     * @param  array<int>|null  $authorizedUserIds
     * @return Collection<int, Person>
     */
    private function findByPhone(Lead $lead, ?array $authorizedUserIds): Collection
    {
        $variants = $this->phoneSearchVariants($lead);
        if ($variants === []) {
            return collect();
        }

        return $this->baseQuery($authorizedUserIds)
            ->where(function (Builder $query) use ($variants) {
                foreach ($variants as $value) {
                    $this->orJsonValueMatch($query, 'phones', $value);
                }
            })
            ->limit(self::BRANCH_LIMIT)
            ->get();
    }

    /**
     * @param  array<int>|null  $authorizedUserIds
     * @return Collection<int, Person>
     */
    private function findByLastName(Lead $lead, ?array $authorizedUserIds): Collection
    {
        $lastNames = $this->leadLastNameNeedles($lead);
        if ($lastNames === []) {
            return collect();
        }

        $query = $this->baseQuery($authorizedUserIds);
        $this->applyExactLastName($query, $lastNames);

        $count = (clone $query)->count();

        if ($count > self::NAME_SOFT_LIMIT) {
            $query->where(function (Builder $extra) use ($lead) {
                $this->applyNameExtraSignal($extra, $lead);
            });
        }

        return $query->limit(self::BRANCH_LIMIT)->get();
    }

    /**
     * @param  array<int>|null  $authorizedUserIds
     */
    private function baseQuery(?array $authorizedUserIds): Builder
    {
        $query = Person::query()->with(['address']);

        if ($authorizedUserIds !== null) {
            $query->whereIn('user_id', $authorizedUserIds);
        }

        return $query;
    }

    /**
     * @param  list<string>  $lastNames
     */
    private function applyExactLastName(Builder $query, array $lastNames): void
    {
        $query->where(function (Builder $q) use ($lastNames) {
            foreach ($lastNames as $name) {
                $q->orWhereRaw('LOWER(last_name) = ?', [$name])
                    ->orWhereRaw('LOWER(married_name) = ?', [$name]);
            }
        });
    }

    private function applyNameExtraSignal(Builder $query, Lead $lead): void
    {
        $first = NameSimilarity::normalize($lead->first_name);

        if ($first === '') {
            $query->orWhereNull('first_name')->orWhere('first_name', '');
        } else {
            $token = explode(' ', $first)[0];
            $escapedFirst = $this->escapeLike($first);
            $escapedToken = $this->escapeLike($token);

            $query->orWhereNull('first_name')
                ->orWhere('first_name', '')
                ->orWhereRaw('LOWER(TRIM(first_name)) = ?', [$first])
                ->orWhereRaw('LOWER(first_name) LIKE ?', [$escapedToken.' %']);

            if (DB::getDriverName() === 'sqlite') {
                $query->orWhereRaw(
                    "(first_name IS NOT NULL AND TRIM(first_name) != '' AND ? LIKE '%' || LOWER(TRIM(first_name)) || '%')",
                    [$first]
                );
            } else {
                $query->orWhereRaw(
                    "(first_name IS NOT NULL AND TRIM(first_name) != '' AND ? LIKE CONCAT('%', LOWER(TRIM(first_name)), '%'))",
                    [$first]
                );
            }

            if ($escapedFirst !== $escapedToken) {
                $query->orWhereRaw('LOWER(first_name) LIKE ?', [$escapedFirst.' %']);
            }
        }

        $date = $this->dateString($lead->date_of_birth);
        if ($date !== null) {
            $query->orWhereDate('date_of_birth', $date);
        }

        $postal = $this->leadPostalCode($lead);
        if ($postal !== null) {
            $query->orWhereHas('address', function (Builder $addressQuery) use ($postal) {
                $addressQuery->where('postal_code', $postal);
            });
        }
    }

    private function orJsonValueMatch(Builder $query, string $field, string $value): void
    {
        $escaped = $this->escapeLike($value);

        if (DB::getDriverName() === 'sqlite') {
            $query->orWhere($field, 'LIKE', '%"'.$escaped.'"%');

            return;
        }

        $query->orWhere(function (Builder $q) use ($field, $value, $escaped) {
            $q->whereJsonContains($field, [['value' => $value]])
                ->orWhere($field, 'LIKE', '%"'.$escaped.'"%');
        });
    }

    private function emailsMatch(Lead $lead, Person $person): bool
    {
        $leadEmails = array_filter(array_map(
            [EmailNormalizer::class, 'normalize'],
            $this->extractContactValues($lead->emails)
        ));
        $personEmails = array_filter(array_map(
            [EmailNormalizer::class, 'normalize'],
            $this->extractContactValues($person->emails)
        ));

        return array_intersect($leadEmails, $personEmails) !== [];
    }

    private function phonesMatch(Lead $lead, Person $person): bool
    {
        $leadPhones = array_filter(array_map(
            [PhoneNormalizer::class, 'toDutchLocal'],
            $this->extractContactValues($lead->phones)
        ));
        $personPhones = array_filter(array_map(
            [PhoneNormalizer::class, 'toDutchLocal'],
            $this->extractContactValues($person->phones)
        ));

        return array_intersect($leadPhones, $personPhones) !== [];
    }

    private function lastNamesMatch(Lead $lead, Person $person): bool
    {
        $leadNames = $this->leadLastNameNeedles($lead);
        $personNames = array_values(array_filter([
            NameSimilarity::normalize($person->last_name),
            NameSimilarity::normalize($person->married_name),
        ]));

        return array_intersect($leadNames, $personNames) !== [];
    }

    private function datesMatch(mixed $left, mixed $right): bool
    {
        $a = $this->dateString($left);
        $b = $this->dateString($right);

        return $a !== null && $a === $b;
    }

    private function postalCodesMatch(Lead $lead, Person $person): bool
    {
        $leadPostal = $this->leadPostalCode($lead);
        $personPostal = PostcodeNormalizer::normalize($person->address?->postal_code);

        return $leadPostal !== null && $personPostal !== null && $personPostal !== '' && $leadPostal === $personPostal;
    }

    private function dateString(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        return $value !== '' ? substr($value, 0, 10) : null;
    }

    private function leadPostalCode(Lead $lead): ?string
    {
        $normalized = PostcodeNormalizer::normalize($lead->address?->postal_code);

        return ($normalized === null || $normalized === '') ? null : $normalized;
    }

    /**
     * @return list<string>
     */
    private function leadLastNameNeedles(Lead $lead): array
    {
        return array_values(array_unique(array_filter([
            NameSimilarity::normalize($lead->last_name),
            NameSimilarity::normalize($lead->married_name),
        ])));
    }

    /**
     * @return list<string>
     */
    private function emailSearchVariants(Lead $lead): array
    {
        $variants = [];
        foreach ($this->extractContactValues($lead->emails) as $email) {
            $variants[] = $email;
            $normalized = EmailNormalizer::normalize($email);
            if ($normalized !== null) {
                $variants[] = $normalized;
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * @return list<string>
     */
    private function phoneSearchVariants(Lead $lead): array
    {
        $variants = [];
        foreach ($this->extractContactValues($lead->phones) as $phone) {
            $variants[] = $phone;
            $e164 = PhoneNormalizer::toE164($phone);
            if ($e164 !== null) {
                $variants[] = $e164;
                $variants[] = ltrim($e164, '+');
            }
            $local = PhoneNormalizer::toDutchLocal($phone);
            if ($local !== '') {
                $variants[] = $local;
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * @return list<string>
     */
    private function extractContactValues(mixed $field): array
    {
        if (is_string($field)) {
            $decoded = json_decode($field, true);
            $field = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($field)) {
            return [];
        }

        $values = [];
        foreach ($field as $item) {
            if (is_array($item) && ! empty($item['value'])) {
                $values[] = trim((string) $item['value']);
            } elseif (is_string($item) && trim($item) !== '') {
                $values[] = trim($item);
            }
        }

        return array_values(array_unique($values));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
