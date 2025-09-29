<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">
            @lang('admin::app.settings.shifts.fields.period_start')
        </x-admin::form.control-group.label>

        <x-admin::flat-picker.date class="!w-full" :allow-input="false">
            <input
                name="period_start"
                value="{{ $periodStart ?? '' }}"
                placeholder="@lang('admin::app.settings.shifts.fields.period_start')"
            />
        </x-admin::flat-picker.date>

        <x-admin::form.control-group.error control-name="period_start" />
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            @lang('admin::app.settings.shifts.fields.period_end')
        </x-admin::form.control-group.label>

        <x-admin::flat-picker.date class="!w-full" :allow-input="false">
            <input
                name="period_end"
                value="{{ $periodEnd ?? '' }}"
                placeholder="@lang('admin::app.settings.shifts.fields.period_end')"
            />
        </x-admin::flat-picker.date>

        <x-admin::form.control-group.error control-name="period_end" />
    </x-admin::form.control-group>
</div>

