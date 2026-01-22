<?php

namespace Webkul\Admin\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Core\Contracts\Validations\Decimal;

class LeadForm extends FormRequest
{
    /**
     * @var array
     */
    protected $rules = [];

    /**
     * Create a new form request instance.
     *
     * @return void
     */
    public function __construct(
        protected ?AttributeRepository $attributeRepository = null,
        protected ?AttributeValueRepository $attributeValueRepository = null
    ) {
        parent::__construct();

        // Scribe (and some tooling) may instantiate FormRequests without using the container,
        // so ensure dependencies are available even when constructor args are omitted.
        $this->attributeRepository ??= app(AttributeRepository::class);
        $this->attributeValueRepository ??= app(AttributeValueRepository::class);
    }

    /**
     * Determine if the product is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // First handle lead attributes
        $attributes = $this->attributeRepository->scopeQuery(function ($query) {
            $query = $query->whereIn('code', array_keys(request()->all()))
                ->where('entity_type', 'leads');

            if (request()->has('quick_add')) {
                $query = $query->where('quick_add', 1);
            }

            return $query;
        })->get();

        foreach ($attributes as $attribute) {
            $validations = [];

            if ($attribute->type == 'boolean') {
                continue;
            } elseif ($attribute->type == 'address') {
                if (! $attribute->is_required) {
                    continue;
                }

                $validations = [
                    $attribute->code.'.address'  => 'required',
                    $attribute->code.'.country'  => 'required',
                    $attribute->code.'.state'    => 'required',
                    $attribute->code.'.city'     => 'required',
                    $attribute->code.'.postcode' => 'required',
                ];
            } elseif ($attribute->type == 'email') {
                $validations = [
                    $attribute->code              => [$attribute->is_required ? 'required' : 'nullable'],
                    $attribute->code.'.*.value'   => [$attribute->is_required ? 'required' : 'nullable', 'email'],
                    $attribute->code.'.*.label'   => $attribute->is_required ? 'required' : 'nullable',
                ];
            } elseif ($attribute->type == 'phone') {
                $validations = [
                    $attribute->code              => [$attribute->is_required ? 'required' : 'nullable'],
                    $attribute->code.'.*.value'   => [$attribute->is_required ? 'required' : 'nullable'],
                    $attribute->code.'.*.label'   => $attribute->is_required ? 'required' : 'nullable',
                ];
            } else {
                $validations[$attribute->code] = [$attribute->is_required ? 'required' : 'nullable'];

                if ($attribute->type == 'text' && $attribute->validation) {
                    array_push($validations[$attribute->code],
                        $attribute->validation == 'decimal'
                        ? new Decimal
                        : $attribute->validation
                    );
                }

                if ($attribute->type == 'price') {
                    array_push($validations[$attribute->code], new Decimal);
                }
            }

            // Enforce unique only for non-person attributes; allow duplicates for persons
            if ($attribute->is_unique) {
                array_push($validations[in_array($attribute->type, ['email', 'phone'])
                    ? $attribute->code.'.*.value'
                    : $attribute->code
                ], function ($field, $value, $fail) use ($attribute) {
                    // Skip uniqueness check for person entity_type
                    if ($attribute->entity_type === 'persons') {
                        return;
                    }
                    if (! $this->attributeValueRepository->isValueUnique(
                        $this->id,
                        $attribute->entity_type,
                        $attribute,
                        request($field)
                    )
                    ) {
                        $fail('The value has already been taken.');
                    }
                });
            }

            $this->rules = array_merge($this->rules, $validations);
        }

        // Then handle person attributes if person data is provided
        if (request()->has('person')) {
            $personAttributes = $this->attributeRepository->scopeQuery(function ($query) {
                $query = $query->whereIn('code', array_keys(request('person')))
                    ->where('entity_type', 'persons');

                if (request()->has('quick_add')) {
                    $query = $query->where('quick_add', 1);
                }

                return $query;
            })->get();

            foreach ($personAttributes as $attribute) {
                $attribute->code = 'person.'.$attribute->code;

                $validations = [];

                if ($attribute->type == 'boolean') {
                    continue;
                } elseif ($attribute->type == 'address') {
                    if (! $attribute->is_required) {
                        continue;
                    }

                    $validations = [
                        $attribute->code.'.address'  => 'required',
                        $attribute->code.'.country'  => 'required',
                        $attribute->code.'.state'    => 'required',
                        $attribute->code.'.city'     => 'required',
                        $attribute->code.'.postcode' => 'required',
                    ];
                } elseif ($attribute->type == 'email') {
                    $validations = [
                        $attribute->code              => ['nullable'],
                        $attribute->code.'.*.value'   => ['nullable', 'email'],
                        $attribute->code.'.*.label'   => 'nullable',
                    ];
                } elseif ($attribute->type == 'phone') {
                    $validations = [
                        $attribute->code              => ['nullable'],
                        $attribute->code.'.*.value'   => ['nullable'],
                        $attribute->code.'.*.label'   => 'nullable',
                    ];
                } else {
                    $validations[$attribute->code] = ['nullable'];

                    if ($attribute->type == 'text' && $attribute->validation) {
                        array_push($validations[$attribute->code],
                            $attribute->validation == 'decimal'
                            ? new Decimal
                            : $attribute->validation
                        );
                    }

                    if ($attribute->type == 'price') {
                        array_push($validations[$attribute->code], new Decimal);
                    }
                }

                // Do NOT enforce uniqueness for person attributes; duplicates allowed and handled via merge flow
                if ($attribute->is_unique) {
                    array_push($validations[in_array($attribute->type, ['email', 'phone'])
                        ? $attribute->code.'.*.value'
                        : $attribute->code
                    ], function ($field, $value, $fail) use ($attribute) {
                        // Skip uniqueness check for person entity_type
                        if ($attribute->entity_type === 'persons') {
                            return;
                        }
                        if (! $this->attributeValueRepository->isValueUnique(
                            request('person.id'),
                            $attribute->entity_type,
                            $attribute,
                            request($field)
                        )
                        ) {
                            $fail('The value has already been taken.');
                        }
                    });
                }

                $this->rules = array_merge($this->rules, $validations);
            }
        }



        return [
            ...$this->rules,
            'user_id'               => 'nullable|exists:users,id|active_user',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return [];
    }
}
