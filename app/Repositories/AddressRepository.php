<?php

namespace App\Repositories;

use App\Models\Address;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Webkul\Core\Eloquent\Repository;

class AddressRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return Address::class;
    }

    /**
     * Validate and upsert an address for a given lead.
     * - Validates against Address::$rules with lead_id
     * - Updates existing address with provided filled fields, otherwise creates
     * - Silently returns if no meaningful address fields are provided
     */
    public function upsertForLead(int $leadId, array $addressData): void
    {
        $this->upsert('lead_id', $leadId, $addressData);
    }

    /**
     * Validate and upsert an address for a given person.
     * - Validates against Address::$rules with person_id
     * - Updates existing address with provided filled fields, otherwise creates
     * - Silently returns if no meaningful address fields are provided
     */
    public function upsertForPerson(int $personId, array $addressData): void
    {
        $this->upsert('person_id', $personId, $addressData);
    }

    /**
     * Shared upsert implementation for lead/person owners.
     */
    private function upsert(string $ownerKey, int $ownerId, array $addressData): void
    {
        // Filter to filled fields only for update behavior decision
        $filled = array_filter($addressData, function ($value) {
            return ! (is_null($value) || trim((string) $value) === '');
        });

        if (empty($filled)) {
            return;
        }

        $payload = array_merge($addressData, [
            $ownerKey => $ownerId,
        ]);

        $validator = Validator::make($payload, Address::$rules);
        if ($validator->fails()) {
            throw new InvalidArgumentException('Address validation failed: '.$validator->errors()->first());
        }

        $existing = $this->findOneWhere([$ownerKey => $ownerId]);

        if ($existing) {
            $this->update($filled, $existing->id);

            return;
        }

        $this->create($payload);
    }
}
