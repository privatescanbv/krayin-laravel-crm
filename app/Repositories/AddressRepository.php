<?php

namespace App\Repositories;

use App\Models\Address;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
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
     * Validate and upsert an address for a given entity (Lead, Person, Organization).
     * Creates or updates address and sets the address_id on the entity.
     *
     * @param  Model  $entity  The entity (Lead, Person, or Organization)
     * @param  array  $addressData  The address data
     * @return Address|null Returns the address if created/updated, null if skipped
     */
    public function upsertForEntity(Model $entity, array $addressData): ?Address
    {
        // Filter to filled fields only for update behavior decision
        $filled = array_filter($addressData, function ($value) {
            return ! (is_null($value) || trim((string) $value) === '');
        });

        if (empty($filled)) {
            return null;
        }

        // Check if entity already has an address — partial updates are allowed
        if ($entity->address_id && $existing = $this->find($entity->address_id)) {
            $this->update($filled, $existing->id);

            return $existing->fresh();
        }

        // For new addresses, require minimum fields for a valid payload
        $houseNumber = isset($addressData['house_number']) ? trim((string) $addressData['house_number']) : '';
        $postalCode = isset($addressData['postal_code']) ? trim((string) $addressData['postal_code']) : '';
        if ($houseNumber === '' || $postalCode === '') {
            return null;
        }

        $validator = Validator::make($addressData, Address::$rules);
        if ($validator->fails()) {
            throw new InvalidArgumentException('Address validation failed: '.$validator->errors()->first());
        }

        // Create new address and link to entity
        $address = $this->create($addressData);
        $entity->address_id = $address->id;
        $entity->save();

        return $address;
    }

    /**
     * Delete the address for an entity and clear the address_id.
     *
     * @param  Model  $entity  The entity (Lead, Person, or Organization)
     */
    public function deleteForEntity(Model $entity): void
    {
        if ($entity->address_id) {
            $this->delete($entity->address_id);
            $entity->address_id = null;
            $entity->save();
        }
    }
}
