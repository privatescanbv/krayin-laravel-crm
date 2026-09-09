<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PersonSuggestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('user');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name'                  => ['nullable', 'string', 'max:255'],
            'last_name'                   => ['nullable', 'string', 'max:255'],
            'lastname_prefix'             => ['nullable', 'string', 'max:255'],
            'married_name'                => ['nullable', 'string', 'max:255'],
            'married_name_prefix'         => ['nullable', 'string', 'max:255'],
            'initials'                    => ['nullable', 'string', 'max:50'],
            'date_of_birth'               => ['nullable', 'date'],
            'salutation'                  => ['nullable', 'string', 'max:50'],
            'gender'                      => ['nullable', 'string', 'max:50'],
            'emails'                      => ['nullable', 'array'],
            'emails.*.value'              => ['nullable', 'string', 'max:255'],
            'emails.*.label'              => ['nullable', 'string', 'max:50'],
            'phones'                      => ['nullable', 'array'],
            'phones.*.value'              => ['nullable', 'string', 'max:50'],
            'phones.*.label'              => ['nullable', 'string', 'max:50'],
            'address'                     => ['nullable', 'array'],
            'address.street'              => ['nullable', 'string', 'max:255'],
            'address.house_number'        => ['nullable', 'string', 'max:50'],
            'address.house_number_suffix' => ['nullable', 'string', 'max:10'],
            'address.postal_code'         => ['nullable', 'string', 'max:20'],
            'address.city'                => ['nullable', 'string', 'max:255'],
            'address.state'               => ['nullable', 'string', 'max:255'],
            'address.country'             => ['nullable', 'string', 'max:255'],
        ];
    }
}
