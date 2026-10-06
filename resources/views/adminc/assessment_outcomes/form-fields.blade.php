<div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <x-adminc::components.field
        type="text"
        name="label"
        value="{{ old('label', $assessment_outcome->label ?? '') }}"
        rules="required|min:1|max:100"
        :label="trans('admin::app.settings.assessment_outcomes.index.create.label')"
        :placeholder="trans('admin::app.settings.assessment_outcomes.index.create.label')"
    />

    @isset($assessment_outcome)
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            @lang('admin::app.settings.assessment_outcomes.index.create.code'): <code>{{ $assessment_outcome->code }}</code>
        </p>
    @endisset

    <x-adminc::components.field
        type="switch"
        name="is_surgery_advice"
        value="1"
        :checked="(bool) old('is_surgery_advice', $assessment_outcome->is_surgery_advice ?? true)"
        :label="trans('admin::app.settings.assessment_outcomes.index.create.is_surgery_advice')"
    />
</div>
