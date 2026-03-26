<?php

namespace App\Http\Requests\Admin\Settings;

use App\Services\ContactValidationRules;
use App\Validators\ContactArrayValidator;
use Illuminate\Foundation\Http\FormRequest;
use Webkul\Core\Contracts\Validations\EmailValidator;
use Webkul\Core\Contracts\Validations\PhoneValidator;

class ClinicRequest extends FormRequest
{
    public static function rulesForCreate(bool $isPostalSameAsVisit = false): array
    {
        return (new self)->buildRules(create: true, isPostalSameAsVisit: $isPostalSameAsVisit);
    }

    public static function rulesForUpdate(int $id, bool $isPostalSameAsVisit = false): array
    {
        return (new self)->buildRules(create: false, id: (string) $id, isPostalSameAsVisit: $isPostalSameAsVisit);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Default Laravel validation rules (create context).
     *
     * Note: The admin controller uses the explicit static helpers above for
     * create/update, but keeping this method signature compatible with Laravel
     * avoids runtime errors when the framework calls rules() without arguments.
     */
    public function rules(): array
    {
        return $this->buildRules(create: true);
    }

    protected function buildRules(bool $create, ?string $id = null, bool $isPostalSameAsVisit = false): array
    {
        if ($create) {
            $nameRule = ['name' => 'required|unique:clinics,name|max:100'];
        } else {
            $nameRule = ['name' => 'required|max:100|unique:clinics,name,'.$id];
        }

        return array_merge($nameRule, [

            // Optional description
            'description' => 'nullable|string|max:2000',

            // Active status
            'is_active' => 'nullable|boolean',

            // Website and order confirmation
            'website_url'             => 'nullable|url|max:255',
            'order_confirmation_note' => 'nullable|string|max:1000',

            // Contact information
            'emails'         => ['nullable', new ContactArrayValidator('email')],
            'emails.*.value' => ['nullable', new EmailValidator],
            'emails.*.label' => 'nullable|string|max:50',

            'phones'         => ['nullable', new ContactArrayValidator('telefoon')],
            'phones.*.value' => ['nullable', new PhoneValidator],
            'phones.*.label' => 'nullable|string|max:50',

            // Addresses
            'is_postal_address_same_as_visit_address' => 'nullable|boolean',
        ] + ContactValidationRules::strictAddressRules('visit_address')
          + ($isPostalSameAsVisit ? [] : ContactValidationRules::strictAddressRules('postal_address')) + [

              // System fields
              'external_id' => 'nullable|string|max:255',
          ]);
    }
}
