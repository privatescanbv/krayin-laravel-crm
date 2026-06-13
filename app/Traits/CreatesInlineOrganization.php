<?php

namespace App\Traits;

use App\Repositories\AddressRepository;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Repositories\OrganizationRepository;

trait CreatesInlineOrganization
{
    /**
     * Create an organization inline (without a separate lookup step) and
     * optionally persist an address for it.
     *
     * @param  string  $name  Organization name.
     * @param  array  $addressData  Optional address fields (postal_code, house_number, …).
     */
    protected function createInlineOrganization(string $name, array $addressData = []): Organization
    {
        Event::dispatch('contacts.organization.create.before');

        /** @var Organization $organization */
        $organization = app(OrganizationRepository::class)->create([
            'name'        => trim($name),
            'entity_type' => 'organizations',
        ]);

        $filteredAddress = array_filter([
            'postal_code'         => $addressData['postal_code'] ?? null,
            'house_number'        => $addressData['house_number'] ?? null,
            'house_number_suffix' => $addressData['house_number_suffix'] ?? null,
            'street'              => $addressData['street'] ?? null,
            'city'                => $addressData['city'] ?? null,
            'country'             => $addressData['country'] ?? 'Nederland',
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($filteredAddress)) {
            try {
                app(AddressRepository::class)->upsertForEntity($organization, $filteredAddress);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException($this->inlineOrganizationAddressErrorMessage($e));
            }
        }

        Event::dispatch('contacts.organization.create.after', $organization);

        return $organization;
    }

    private function inlineOrganizationAddressErrorMessage(InvalidArgumentException $exception): string
    {
        $message = $exception->getMessage();
        $prefix = 'Address validation failed: ';

        if (str_starts_with($message, $prefix)) {
            return substr($message, strlen($prefix));
        }

        return $message;
    }
}
