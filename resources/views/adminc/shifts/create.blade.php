<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.shifts.create.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.resources.shifts.store', $resource->id)" method="POST">
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.resources.shifts.create" :entity="$resource" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        {{ $resource->name }} — @lang('admin::app.settings.shifts.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.shifts.create.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @if ($errors->any())
                    <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <x-admin::form.control-group>
                    <x-admin::form.control-group.control
                        type="select"
                        name="resource_id"
                        rules="required|numeric"
                        value="{{ old('resource_id', $resource->id) }}"
                        :label="trans('admin::app.settings.resources.index.create.title')"
                    >
                        @foreach ($resources as $res)
                            <option value="{{ $res->id }}" @selected(old('resource_id', $resource->id) == $res->id)>{{ $res->name }}</option>
                        @endforeach
                    </x-admin::form.control-group.control>
                    <x-admin::form.control-group.label class="required">
                        @lang('admin::app.settings.resources.index.create.title')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.error control-name="resource_id" />

                </x-admin::form.control-group>
                <x-adminc::shifts.partials.period
                    :periodStart="old('period_start')"
                    :periodEnd="old('period_end')"/>
                <x-adminc::shifts.partials.time-blocks :weekdayBlocks="old('weekday_time_blocks', [])"/>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.control
                        type="textarea"
                        name="notes"
                        :label="trans('admin::app.settings.shifts.fields.notes')"
                    />
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.shifts.fields.notes')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.error control-name="notes" />

                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <input type="hidden" name="available" value="0" />
                    <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-white">
                        <input
                            type="checkbox"
                            name="available"
                            value="1"
                            class="h-4 w-4 rounded border-gray-300 text-brandColor focus:ring-brandColor"
                            @checked(old('available', true))
                        />
                        <span>@lang('admin::app.settings.shifts.fields.available')</span>
                    </label>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.shifts.fields.available')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.error control-name="available" />

                </x-admin::form.control-group>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>


