<?php

namespace Webkul\Contact\Repositories;

use App\Models\Address;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Validator;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Contracts\Person;
use Webkul\Core\Eloquent\Repository;

class PersonRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'emails',
        'contact_numbers',
        'organization_id',
        'job_title',
        'organization.name',
        'user_id',
        'user.name',
    ];

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected OrganizationRepository $organizationRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return Person::class;
    }

    /**
     * Create.
     *
     * @return \Webkul\Contact\Contracts\Person
     */
    public function create(array $data)
    {
        $data = $this->sanitizeRequestedPersonData($data);

        if (! empty($data['organization_name'])) {
            $organization = $this->fetchOrCreateOrganizationByName($data['organization_name']);

            $data['organization_id'] = $organization->id;
        }

        if (isset($data['user_id'])) {
            $data['user_id'] = $data['user_id'] ?: null;
        }

        $person = parent::create($data);

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $person->id,
        ]));

                // Handle address data for new persons
        if (isset($data['address']) && !empty($data['address'])) {
            $addressData = $data['address'];
            // Check if there's any meaningful address data (not just empty strings)
            $hasAddressData = !empty(array_filter($addressData, function($value) {
                return !empty(trim($value));
            }));

            if ($hasAddressData) {
                // Add person_id to address data for validation
                $addressDataWithPersonId = array_merge($addressData, [
                    'person_id' => $person->id,
                ]);
                
                // Validate address data
                $validator = Validator::make($addressDataWithPersonId, Address::$rules);
                if ($validator->fails()) {
                    throw new InvalidArgumentException('Address validation failed: ' . $validator->errors()->first());
                }
                
                Address::create($addressDataWithPersonId);
            }
        }

        return $person;
    }

    /**
     * Update.
     *
     * @return \Webkul\Contact\Contracts\Person
     */
    public function update(array $data, $id, $attributes = [])
    {
        $data = $this->sanitizeRequestedPersonData($data);

        $data['user_id'] = empty($data['user_id']) ? null : $data['user_id'];

        if (! empty($data['organization_name'])) {
            $organization = $this->fetchOrCreateOrganizationByName($data['organization_name']);

            $data['organization_id'] = $organization->id;

            unset($data['organization_name']);
        }

        $person = parent::update($data, $id);

        /**
         * If attributes are provided then only save the provided attributes and return.
         */
        if (! empty($attributes)) {
            $conditions = ['entity_type' => $data['entity_type']];

            if (isset($data['quick_add'])) {
                $conditions['quick_add'] = 1;
            }

            $attributes = $this->attributeRepository->where($conditions)
                ->whereIn('code', $attributes)
                ->get();

            $this->attributeValueRepository->save(array_merge($data, [
                'entity_id' => $person->id,
            ]), $attributes);

            return $person;
        }

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $person->id,
        ]));

        // Handle address data
        if (isset($data['address']) && !empty($data['address'])) {
            $addressData = $data['address'];
            // Check if there's any meaningful address data (not just empty strings)
            $filledAddressData = array_filter($addressData, function($value) {
                return !empty(trim($value));
            });
            $hasAddressData = !empty($filledAddressData);
            
            Log::info('Address filtering debug:', [
                'original_data' => $addressData,
                'filled_data' => $filledAddressData,
                'has_address_data' => $hasAddressData,
            ]);
            Log::info('Updating address ', [
                'address' => $addressData,
                'city_value' => $addressData['city'] ?? 'NOT_SET',
                'city_isset' => isset($addressData['city']),
                'city_empty' => empty($addressData['city'] ?? ''),
                'city_trimmed' => trim($addressData['city'] ?? ''),
            ]);
            if ($hasAddressData) {
                // Add person_id to address data for validation
                $addressDataWithPersonId = array_merge($addressData, [
                    'person_id' => $id,
                ]);
                
                // Validate address data
                $validator = Validator::make($addressDataWithPersonId, Address::$rules);
                if ($validator->fails()) {
                    throw new InvalidArgumentException('Address validation failed: ' . $validator->errors()->first());
                }
                
                // Check if person already has an address
                $existingAddress = Address::where('person_id', $id)->first();

                if ($existingAddress) {
                    // Update existing address - only update filled fields
                    $existingAddress->update($filledAddressData);
                } else {
                    // Create new address - use all data (including empty fields)
                    Address::create($addressDataWithPersonId);
                }
            }
        }

        return $person;
    }

    /**
     * Retrieves customers count based on date.
     *
     * @return int
     */
    public function getCustomerCount($startDate, $endDate)
    {
        return $this
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->count();
    }

    /**
     * Fetch or create an organization.
     */
    public function fetchOrCreateOrganizationByName(string $organizationName)
    {
        $organization = $this->organizationRepository->findOneWhere([
            'name' => $organizationName,
        ]);

        return $organization ?: $this->organizationRepository->create([
            'entity_type' => 'organizations',
            'name'        => $organizationName,
        ]);
    }

    /**
     * Sanitize requested person data and return the clean array.
     */
    private function sanitizeRequestedPersonData(array $data): array
    {
        if (
            array_key_exists('organization_id', $data)
            && empty($data['organization_id'])
        ) {
            $data['organization_id'] = null;
        }

        $uniqueIdParts = array_filter([
            $data['user_id'] ?? null,
            $data['organization_id'] ?? null,
            $data['emails'][0]['value'] ?? null,
        ]);

        $data['unique_id'] = implode('|', $uniqueIdParts);

        if (isset($data['contact_numbers'])) {
            $data['contact_numbers'] = collect($data['contact_numbers'])->filter(fn ($number) => ! is_null($number['value']))->toArray();

            if (!empty($data['contact_numbers'])) {
                $data['unique_id'] .= '|'.$data['contact_numbers'][0]['value'];
            }
        }

        // If unique_id is empty after generation, set it to null to avoid duplicate key errors
        if (empty($data['unique_id'])) {
            $data['unique_id'] = null;
        }

        return $data;
    }
}
