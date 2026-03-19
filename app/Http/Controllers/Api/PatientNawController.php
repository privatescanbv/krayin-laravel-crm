<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\ContactValidationRules;
use App\Services\Keycloak\KeycloakService;
use App\Support\EmailNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Contact\Models\Person;

class PatientNawController extends Controller
{
    public function __construct(private readonly KeycloakService $keycloakService) {}

    /**
     * Get NAW data for a patient.
     *
     * @group Patient NAW
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @response 200 scenario="Success" {"salutation":"Dhr.","first_name":"Jan","lastname_prefix":"van","last_name":"Berg","married_name_prefix":null,"married_name":null,"initials":"J.","date_of_birth":"1985-03-15","gender":"Man","phones":[{"value":"+31612345678","label":"eigen","is_default":true}],"emails":[{"value":"jan@example.com","label":"eigen","is_default":true}],"address":{"street":"Hoofdstraat","house_number":"1","house_number_suffix":null,"postal_code":"1234AB","city":"Amsterdam","state":null,"country":"Nederland"}}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     */
    public function show(string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            abort(404);
        }

        return response()->json($this->formatResponse($person));
    }

    /**
     * Update NAW data for a patient.
     *
     * @group Patient NAW
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @bodyParam salutation string Salutation. Allowed values: Dhr., Mevr. Example: Dhr.
     * @bodyParam first_name string First name. Example: Jan
     * @bodyParam lastname_prefix string Lastname prefix (tussenvoegsel). Example: van
     * @bodyParam last_name string Last name. Example: Berg
     * @bodyParam married_name_prefix string Married name prefix. Example: de
     * @bodyParam married_name string Married name. Example: Vries
     * @bodyParam initials string Initials. Example: J.
     * @bodyParam date_of_birth string Date of birth (Y-m-d). Example: 1985-03-15
     * @bodyParam gender string Gender. Allowed values: Man, Vrouw, Anders. Example: Man
     * @bodyParam phones array List of phone numbers. Exactly one entry must have is_default set to true.
     * @bodyParam phones[].value string required Phone number (E.164). Example: +31612345678
     * @bodyParam phones[].label string required Label. Allowed values: eigen, relatie, anders. Example: eigen
     * @bodyParam phones[].is_default boolean Whether this is the default number. Example: true
     * @bodyParam emails array List of email addresses. Exactly one entry must have is_default set to true.
     * @bodyParam emails[].value string required Email address. Example: jan@example.com
     * @bodyParam emails[].label string required Label. Allowed values: eigen, relatie, anders. Example: eigen
     * @bodyParam emails[].is_default boolean Whether this is the default email. Example: true
     * @bodyParam address object Address data.
     * @bodyParam address.street string Street name. Example: Hoofdstraat
     * @bodyParam address.house_number string House number. Example: 1
     * @bodyParam address.house_number_suffix string House number suffix. Example: A
     * @bodyParam address.postal_code string Postal code. Example: 1234AB
     * @bodyParam address.city string City. Example: Amsterdam
     * @bodyParam address.state string State / province. Example: Noord-Holland
     * @bodyParam address.country string Country. Example: Nederland
     *
     * @response 200 scenario="Success" {"salutation":"Dhr.","first_name":"Jan","lastname_prefix":"van","last_name":"Berg","married_name_prefix":null,"married_name":null,"initials":"J.","date_of_birth":"1985-03-15","gender":"Man","phones":[{"value":"+31612345678","label":"eigen","is_default":true}],"emails":[{"value":"jan@example.com","label":"eigen","is_default":true}],"address":{"street":"Hoofdstraat","house_number":"1","house_number_suffix":null,"postal_code":"1234AB","city":"Amsterdam","state":null,"country":"Nederland"}}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     * @response 422 scenario="Validation error" {"message":"The given data was invalid."}
     */
    public function update(Request $request, string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            abort(404);
        }

        $isNewAddress = is_null($person->address);

        $validated = $request->validate(array_merge(
            ['first_name' => 'nullable|string|max:255', 'last_name' => 'nullable|string|max:255'],
            ContactValidationRules::personalNameRules(),
            ContactValidationRules::strictPhoneRules(),
            ContactValidationRules::strictEmailRules(),
            ContactValidationRules::addressRulesForPerson($isNewAddress),
        ));

        if (isset($validated['emails'])) {
            $validated['emails'] = array_map(function ($email) {
                if (isset($email['value'])) {
                    $email['value'] = EmailNormalizer::normalize($email['value']) ?? $email['value'];
                }

                return $email;
            }, $validated['emails']);
        }

        $personFields = ['salutation', 'first_name', 'lastname_prefix', 'last_name', 'married_name_prefix', 'married_name', 'initials', 'date_of_birth', 'gender', 'phones', 'emails'];
        $personData = array_intersect_key($validated, array_flip($personFields));

        if (! empty($personData)) {
            $person->fill($personData);
            $person->save();
        }

        if (isset($validated['address'])) {
            if ($person->address) {
                $person->address->update($validated['address']);
            } else {
                $address = Address::create($validated['address']);
                $person->address_id = $address->id;
                $person->save();
            }
            $person->refresh();
        }

        return response()->json($this->formatResponse($person));
    }

    private function formatResponse(Person $person): array
    {
        $address = $person->address;

        return [
            'salutation'          => $person->salutation?->value,
            'first_name'          => $person->first_name,
            'lastname_prefix'     => $person->lastname_prefix,
            'last_name'           => $person->last_name,
            'married_name_prefix' => $person->married_name_prefix,
            'married_name'        => $person->married_name,
            'initials'            => $person->initials,
            'date_of_birth'       => $person->date_of_birth?->format('Y-m-d'),
            'gender'              => $person->gender?->value,
            'phones'              => $person->phones ?? [],
            'emails'              => $person->emails ?? [],
            'address'             => $address ? [
                'street'              => $address->street,
                'house_number'        => $address->house_number,
                'house_number_suffix' => $address->house_number_suffix,
                'postal_code'         => $address->postal_code,
                'city'                => $address->city,
                'state'               => $address->state,
                'country'             => $address->country,
            ] : null,
        ];
    }
}
