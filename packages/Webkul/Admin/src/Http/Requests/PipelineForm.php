<?php

namespace Webkul\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Factory as ValidationFactory;

class PipelineForm extends FormRequest
{
    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct(ValidationFactory $validationFactory)
    {
        $this->validatorExtensions($validationFactory);
    }

    /**
     * Determine if the user is authorized to make this request.
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
        if (request('id')) {
            return [
                'name'          => 'required',
                'type'          => 'required|in:lead,workflow',
                'stages'        => 'exactly_one_default_stage',
                'stages.*.name' => 'unique_key',
                'stages.*.code' => 'unique_key',
            ];
        }

        return [
            'name'          => 'required',
            'type'          => 'required|in:lead,workflow',
            'rotten_days'   => 'required',
            'stages'        => 'exactly_one_default_stage',
            'stages.*.name' => 'unique_key',
            'stages.*.code' => 'unique_key',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'stages.*.name.unique_key'              => __('admin::app.settings.pipelines.duplicate-name'),
            'stages.exactly_one_default_stage'      => 'Elke pipeline moet precies één standaard-stage hebben.',
        ];
    }

    /**
     * Place all your validator extensions here.
     *
     * @return void
     */
    public function validatorExtensions(ValidationFactory $validationFactory)
    {
        $validationFactory->extend(
            'unique_key',
            function ($attribute, $value, $parameters) {
                $key = last(explode('.', $attribute));

                $stages = collect(request()->get('stages'))->filter(function ($stage) use ($key, $value) {
                    return $stage[$key] === $value;
                });

                if ($stages->count() > 1) {
                    return false;
                }

                return true;
            }
        );

        $validationFactory->extend(
            'exactly_one_default_stage',
            function ($attribute, $value, $parameters) {
                $defaults = collect(request()->get('stages'))
                    ->filter(fn ($stage) => ! empty($stage['is_default']));

                return $defaults->count() === 1;
            }
        );
    }
}
