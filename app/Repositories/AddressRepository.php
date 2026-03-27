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
        // Explicit delete request from the UI ("Wis adres" button sets address[_clear]=1).
        if (! empty($addressData['_clear'])) {
            if ($entity->address_id) {
                $this->deleteForEntity($entity);
            }

            return null;
        }

        // Strip UI-only meta keys (e.g. _clear) before processing address fields
        $addressFields = array_diff_key($addressData, array_flip(['_clear']));

        // Normalize empty strings to null so cleared fields are stored as null
        $normalized = array_map(function ($value) {
            if (is_string($value) && trim($value) === '') {
                return null;
            }

            return $value;
        }, $addressFields);

        // Filter to filled fields only for update behavior decision
        $filled = array_filter($normalized, function ($value) {
            return ! is_null($value);
        });

        // If all fields are empty and entity already has an address, delete it
        if (empty($filled)) {
            if ($entity->address_id) {
                $this->deleteForEntity($entity);
            }

            return null;
        }

        // Check if entity already has an address — update all provided fields (including nulls)
        if ($entity->address_id && $existing = $this->find($entity->address_id)) {
            $this->update($normalized, $existing->id);

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
