<?php

namespace App\Services;

use App\Enums\LostReason;
use App\Enums\MRIStatus;
use App\Enums\PersonGender;
use App\Enums\PersonSalutation;
use App\Validators\ContactArrayValidator;
use App\Validators\DateValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Webkul\Core\Contracts\Validations\EmailValidator;
use Webkul\Core\Contracts\Validations\PhoneValidator;
use Webkul\Lead\Models\Stage;

class LeadValidationService
{
    /**
     * Get the validation rules for Lead creation and updates
     */
    public static function getValidationRules($request = null, bool $create = true): array
    {
        $rules = [
            'first_name'                     => 'required|string|max:255',
            'last_name'                      => 'required|string|max:255',
            'description'                    => 'nullable|string',
            'salutation'                     => ['nullable', new Enum(PersonSalutation::class)],
            'mri_status'                     => ['nullable', new Enum(MRIStatus::class)],
            'initials'                       => 'nullable|string|max:50',
            'lastname_prefix'                => 'nullable|string|max:50',
            'married_name'                   => 'nullable|string|max:255',
            'married_name_prefix'            => 'nullable|string|max:50',
            'gender'                         => ['nullable', new Enum(PersonGender::class)],
            'date_of_birth'                  => ['nullable', new DateValidator],
            'national_identification_number' => 'nullable|string|max:255',

            // Contact information
            'emails'         => ['nullable', new ContactArrayValidator('email')],
            'emails.*.value' => ['nullable', new EmailValidator],
            'emails.*.label' => 'nullable|string|max:50',
            'phones'         => ['nullable', new ContactArrayValidator('telefoon')],
            'phones.*.value' => ['nullable', new PhoneValidator],
            'phones.*.label' => 'nullable|string|max:50',

            // Lead specific fields
            'lost_reason'            => ['nullable', new Enum(LostReason::class)],
            'lead_value'             => 'nullable|numeric|min:0',
            'lead_source_id'         => 'nullable|numeric|exists:lead_sources,id',
            'lead_channel_id'        => 'nullable|numeric|exists:lead_channels,id',
            'lead_type_id'           => 'nullable|numeric|exists:lead_types,id',
            'department_id'          => 'required|numeric|exists:departments,id',
            'user_id'                => 'nullable|numeric|exists:users,id',
            'lead_pipeline_id'       => 'nullable|numeric|exists:lead_pipelines,id',
            'lead_pipeline_stage_id' => [
                'nullable',
                'numeric',
                'exists:lead_pipeline_stages,id',
                function ($attribute, $value, $fail) {
                    if ($value && request('lead_pipeline_id')) {
                        $stage = Stage::find($value);
                        if ($stage && $stage->lead_pipeline_id != request('lead_pipeline_id')) {
                            $fail('The selected stage does not belong to the specified pipeline.');
                        }
                    }
                },
            ],

            // Person relationships (multiple persons supported)
            'person_ids'                => 'nullable|array',
            'person_ids.*'              => 'numeric|exists:persons,id',
            'persons'                   => 'nullable|array',
            'persons.*.id'              => 'nullable|numeric|exists:persons,id',
            'persons.*.name'            => 'nullable|string|max:255',
            'persons.*.emails'          => 'nullable|array',
            'persons.*.contact_numbers' => 'nullable|array',

            // Lead organization (standalone for billing)
            'organization_id' => 'nullable|numeric|exists:organizations,id',

            // Address fields
            'address.postal_code'         => 'nullable|string|max:20',
            'address.house_number'        => 'nullable|string|max:20',
            'address.house_number_suffix' => 'nullable|string|max:20',
            'address.street'              => 'nullable|string|max:255',
            'address.city'                => 'nullable|string|max:255',
            'address.state'               => 'nullable|string|max:255',
            'address.country'             => 'nullable|string|max:255',
        ];
        if ($create) {
            // Anamnesis quick questions on lead form
            $rules['metals'] = 'nullable|boolean';
            $rules['metals_notes'] = 'required_if:metals,1|nullable|string';
            $rules['claustrophobia'] = 'nullable|boolean';
            $rules['allergies'] = 'nullable|boolean';
            $rules['allergies_notes'] = 'required_if:allergies,1|nullable|string';
            // Height and weight fields (optional)
            $rules['height'] = 'nullable|numeric|min:100|max:250';
            $rules['weight'] = 'nullable|integer|min:20|max:300';
        }

        // Enforce: at least one contact (email or phone) must be provided
        // Attach as a closure on a guaranteed field so it always runs
        $baseFirstNameRules = is_string($rules['first_name'])
            ? array_filter(explode('|', $rules['first_name']))
            : (array) $rules['first_name'];

        $rules['first_name'] = [
            ...$baseFirstNameRules,
            function ($attribute, $value, $fail) use ($request) {
                $data = $request?->all() ?? request()->all();

                $hasEmail = false;
                if (! empty($data['emails']) && is_array($data['emails'])) {
                    foreach ($data['emails'] as $email) {
                        if (is_array($email) && isset($email['value']) && trim((string) $email['value']) !== '') {
                            $hasEmail = true;
                            break;
                        }
                    }
                }

                $hasPhone = false;
                if (! empty($data['phones']) && is_array($data['phones'])) {
                    foreach ($data['phones'] as $phone) {
                        if (is_array($phone) && isset($phone['value']) && trim((string) $phone['value']) !== '') {
                            $hasPhone = true;
                            break;
                        }
                    }
                }

                if (! ($hasEmail || $hasPhone)) {
                    $fail('Vul ten minste één e-mail of telefoonnummer in.');
                }
            },
        ];

        return $rules;
    }

    /**
     * Get validation rules specifically for API endpoints
     */
    public static function getApiValidationRules($request = null): array
    {
        $rules = self::getValidationRules($request, true); // Pass true for create mode to include anamnesis fields

        // For API, make some fields required that are optional in web
        $rules['lead_source_id'] = 'required|numeric|exists:lead_sources,id';
        $rules['lead_channel_id'] = 'required|numeric|exists:lead_channels,id';
        $rules['lead_type_id'] = 'required|numeric|exists:lead_types,id';

        return $rules;
    }

    /**
     * Get validation rules for web forms
     */
    public static function getWebValidationRules($request = null, bool $create = true): array
    {
        return self::getValidationRules($request, $create);
    }

    /**
     * Validate that at least one email or phone is provided; throws ValidationException on failure.
     */
    public static function validateAtLeastOneContactOrFail(array $data): void
    {
        $hasEmail = false;
        if (! empty($data['emails']) && is_array($data['emails'])) {
            foreach ($data['emails'] as $email) {
                if (is_array($email) && isset($email['value']) && trim((string) $email['value']) !== '') {
                    $hasEmail = true;
                    break;
                }
            }
        }

        $hasPhone = false;
        if (! empty($data['phones']) && is_array($data['phones'])) {
            foreach ($data['phones'] as $phone) {
                if (is_array($phone) && isset($phone['value']) && trim((string) $phone['value']) !== '') {
                    $hasPhone = true;
                    break;
                }
            }
        }

        if (! ($hasEmail || $hasPhone)) {
            throw new ValidationException(
                Validator::make([], []),
                response()->json([
                    'message' => 'Vul ten minste één e-mail of telefoonnummer in.',
                    'errors'  => ['emails' => ['Vul ten minste één e-mail of telefoonnummer in.']],
                ], 422)
            );
        }
    }
}
