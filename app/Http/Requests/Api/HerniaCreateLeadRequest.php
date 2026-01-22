<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class HerniaCreateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaign_id'      => ['required', 'string'],
            'lead_source'      => ['required', 'string'],
            'kanaal_c'         => ['required', 'string'],
            'soort_aanvraag_c' => ['required', 'string'],

            'salutation'   => ['nullable', 'string'],
            'first_name'   => ['required', 'string'],
            'last_name'    => ['required', 'string'],
            'birthdate'    => ['nullable', 'date_format:Y-m-d'],
            'email1'       => ['required', 'email'],
            'phone_mobile' => ['nullable', 'string'],

            'primary_huisnr_c'            => ['nullable', 'string'],
            'primary_huisnr_toevoeging_c' => ['nullable', 'string'],
            'primary_address_postalcode'  => ['nullable', 'string'],

            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * Extra metadata for Scribe docs (options + defaults).
     */
    public function bodyParameters(): array
    {
        return [
            'lead_source' => [
                'description' => 'Broncode (string) die gemapt wordt naar lead_source_id. Zelfde mapping als in `InboundLeadPayloadMapper::mapLeadSourceId()`. Bij geen match: default naar "Anders" (lead_source_id=32).',
                'example' => 'Herniapoli.nl',
            ],
            'kanaal_c' => [
                'description' => 'Kanaal (string) die gemapt wordt naar lead_channel_id. Ondersteunde waarden: telefoon, website, email (of e-mail), tel-en-tel, agenten, partners, social media (of socialmedia), webshop, campagne. Bij geen match: default naar Website (lead_channel_id=2).',
                'example' => 'website',
            ],
            'soort_aanvraag_c' => [
                'description' => 'Type aanvraag (string) die gemapt wordt naar lead_type_id. Ondersteunde waarden: preventie (1), gericht (2), operatie (3), overig (4). Bij geen match: default naar Overig (lead_type_id=4).',
                'example' => 'operatie',
            ],
            'salutation' => [
                'description' => 'Enum-achtige waarde. Toegestane waarden: "Dhr.", "Mevr." (ook "Mr."/"Mrs." wordt geaccepteerd en omgezet). Bij geen match: validatie faalt (422) omdat de lead-validatie alleen "Dhr."/"Mevr." accepteert.',
                'example' => 'Dhr.',
            ],
            'phone_mobile' => [
                'description' => 'Telefoonnummer. Wordt genormaliseerd naar E.164 (bv 0612345678 → +31612345678) vóór validatie.',
                'example' => '0612345678',
            ],
            'primary_huisnr_c' => [
                'description' => 'Huisnummer (optioneel).',
                'example' => '12',
            ],
            'primary_address_postalcode' => [
                'description' => 'Postcode (optioneel).',
                'example' => '1234AB',
            ],
        ];
    }

    protected function passedValidation(): void
    {
        // Enforce JSON schema's additionalProperties=false by rejecting unknown keys.
        $allowedKeys = array_keys($this->rules());
        // Added globally by some controllers/middleware in this codebase.
        $allowedKeys[] = 'entity_type';
        $incomingKeys = array_keys($this->all());
        $unknown = array_values(array_diff($incomingKeys, $allowedKeys));

        if (! empty($unknown)) {
            throw ValidationException::withMessages([
                'payload' => ['Unknown properties: '.implode(', ', $unknown)],
            ]);
        }
    }
}
