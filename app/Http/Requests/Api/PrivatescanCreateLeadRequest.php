<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PrivatescanCreateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_source'      => ['required', 'string'],
            'kanaal_c'         => ['required', 'string'],
            'soort_aanvraag_c' => ['required', 'string', 'in:preventie'],

            'salutation' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value === false) {
                        return;
                    }

                    if ($value === null) {
                        return;
                    }

                    if (! is_string($value)) {
                        $fail('The salutation must be a string or false.');

                        return;
                    }

                    if (! in_array($value, ['Mr.', 'Mrs.'], true)) {
                        $fail('The salutation must be Mr., Mrs., or false.');
                    }
                },
            ],

            'first_name' => ['nullable', 'string'],
            'last_name'  => ['nullable', 'string'],
            'email'      => ['nullable', 'email'],
            'phone'      => ['nullable', 'string'],

            'assigned_user_id' => ['nullable'], // always false in current implementation; ignored
            'description'      => ['nullable', 'string'],
            'url'              => ['nullable', 'url'],
            'section'          => ['nullable', 'string'],
            'select_verzoek'   => ['nullable', 'string'],
            'select_interesse' => ['nullable', 'string'],
            'personen'         => ['nullable'],
            'campaign_id'      => ['nullable', 'string'],
            'source'           => ['nullable', 'string'],
            'medium'           => ['nullable', 'string'],
            'campaign'         => ['nullable', 'string'],
            'adgroup'          => ['nullable', 'string'],
            'utm_term'         => ['nullable', 'string'],
            'utm_content'      => ['nullable', 'string'],
            'utm_id'           => ['nullable', 'string'],
            'gclid'            => ['nullable', 'string'],
            'gbraid'           => ['nullable', 'string'],
            'wbraid'           => ['nullable', 'string'],
            'gad_source'       => ['nullable', 'string'],
            'gad_campaignid'   => ['nullable', 'string'],
            'landing_page'     => ['nullable', 'string'],
            'referrer'         => ['nullable', 'string'],
            'first_visit_at'   => ['nullable', 'string'],
            'last_visit_at'    => ['nullable', 'string'],
            'attribution_url'  => ['nullable', 'string'],
        ];
    }

    /**
     * Extra metadata for Scribe docs (options + defaults).
     */
    public function bodyParameters(): array
    {
        return [
            'lead_source' => [
                'description' => 'Broncode (string) die gemapt wordt naar lead_source_id. Ondersteunde waarden o.a.: bodyscannl, privatescannl, mriscannl, ccsvionlinenl, ccsvionlinecom, bodyscan.nl, privatescan.nl, mri-scan.nl, ccsvi-online.nl, ccsvi-online.com, google zoeken, adwords, krant telegraaf, krant spits, krant regionaal, krant overige dagbladen, krant redactioneel, magazine dito, magazine humo belgie, dokterdokter.nl, vrouw.nl, dito-magazine.nl, groupdeal.nl, marktplaats, zorgplanet.nl, linkpartner, youtube, linkedin, twitter, facebook, rtl business class, nieuwsbrief, bestaande klant, zakenrelatie, vrienden, familie, kennissen, collega, anders, wegener webshop, herniapoli.nl. Bij geen match: default naar "Anders" (lead_source_id=32).',
                'example'     => 'privatescannl',
            ],
            'kanaal_c' => [
                'description' => 'Kanaal (string) die gemapt wordt naar lead_channel_id. Ondersteunde waarden: telefoon, website, email (of e-mail), tel-en-tel, agenten, partners, social media (of socialmedia), webshop, campagne. Bij geen match: default naar Website (lead_channel_id=2).',
                'example'     => 'website',
            ],
            'soort_aanvraag_c' => [
                'description' => 'Type aanvraag. Voor deze endpoint alleen "preventie" (wordt lead_type_id=1). Bij geen match: default naar Overig (lead_type_id=4).',
                'example'     => 'preventie',
            ],
            'salutation' => [
                'description' => 'Enum-achtige waarde. Toegestane waarden: "Mr.", "Mrs." of false. Mapping: "Mr." → "Dhr.", "Mrs." → "Mevr.". Bij false/null: geen aanspreekvorm (salutation wordt leeg).',
                'example'     => 'Mr.',
            ],
            'phone' => [
                'description' => 'Telefoonnummer. Wordt genormaliseerd naar E.164 (bv 0612345678 → +31612345678) vóór validatie.',
                'example'     => '0611111111',
            ],
            'campaign_id' => [
                'description' => 'Optionele campaign waarde vanuit PrivateScan. Wordt niet gevalideerd tegen CRM marketing campaigns; Google campaign info staat in `gad_campaignid`.',
                'example'     => 'private-campaign',
            ],
        ];
    }
}
