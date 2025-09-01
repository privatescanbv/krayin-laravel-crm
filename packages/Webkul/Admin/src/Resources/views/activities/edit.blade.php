<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.activities.edit.title')
    </x-slot>

    {!! view_render_event('admin.activities.edit.form.before') !!}

    <x-admin::form
        :action="route('admin.activities.update', $activity->id)"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <!-- Breadcrumbs -->
                    <x-admin::breadcrumbs
                        name="activities.edit"
                        :entity="$activity"
                    />

                    <!-- Page Title -->
                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.activities.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <!-- Takeover Button -->
                    @if($activity->user_id && $activity->user_id != auth()->guard('user')->id() && $canTakeover)
                        <button
                            type="button"
                            class="secondary-button bg-orange-500 hover:bg-orange-600 text-white"
                            onclick="takeoverActivity({{ $activity->id }})"
                            title="Overnemen van {{ $activity->user ? $activity->user->name : 'onbekend' }}"
                        >
                            Overnemen
                        </button>
                    @endif

                    <!-- Create button for person -->
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.activities.edit.save_button.before') !!}

                        <!-- Save Button -->
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.activities.edit.save-btn')
                        </button>

                        {!! view_render_event('admin.activities.edit.save_button.after') !!}
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <div class="flex gap-2.5 max-xl:flex-wrap-reverse">
                <!-- Left sub-component -->
                <div class="box-shadow flex-1 gap-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 max-xl:flex-auto">
                    {!! view_render_event('admin.activities.edit.form_controls.before') !!}

                    <!-- Schedule Date -->
                    <x-admin::form.control-group>
                        <div class="flex gap-2 max-sm:flex-wrap">
                            <div class="w-full">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.schedule_from')
                                </x-admin::form.control-group.label>

                                <x-admin::flat-picker.datetime class="!w-full" ::allow-input="true">
                                    <input
                                        name="schedule_from"
                                        value="{{ old('schedule_from') ?? $activity->schedule_from }}"
                                        class="flex w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                        placeholder="@lang('admin::app.activities.edit.schedule_from')"
                                    />
                                </x-admin::flat-picker.datetime>
                            </div>

                            <div class="w-full">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.schedule_to')
                                </x-admin::form.control-group.label>

                                <x-admin::flat-picker.datetime class="!w-full" ::allow-input="true">
                                    <input
                                        name="schedule_to"
                                        value="{{ old('schedule_to') ?? $activity->schedule_to }}"
                                        class="flex w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                        placeholder="@lang('admin::app.activities.edit.schedule_to')"
                                    />
                                </x-admin::flat-picker.datetime>
                            </div>
                        </div>
                    </x-admin::form.control-group>

                    <!-- Comment -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.activities.edit.comment')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="textarea"
                            name="comment"
                            id="comment"
                            :value="old('comment') ?? $activity->comment"
                            :label="trans('admin::app.activities.edit.comment')"
                            :placeholder="trans('admin::app.activities.edit.comment')"
                        />

                        <x-admin::form.control-group.error control-name="comment" />
                    </x-admin::form.control-group>


                    <!-- Group -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            {{ __('admin::app.activities.group') }}
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="select"
                            name="group_id"
                            :value="old('group_id', $activity->group_id)"
                        >
                            <option value="">{{ __('admin::app.activities.select-group') }}</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" {{ $activity->group_id == $group->id ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </x-admin::form.control-group.control>
                    </x-admin::form.control-group>

                    <!-- Related Entity Information -->
                    @if($relatedEntity && $relatedEntityName)
                        <x-admin::form.control-group class="!mb-0">
                            <x-admin::form.control-group.label>
                                Gerelateerd aan
                            </x-admin::form.control-group.label>

                            <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-md border dark:bg-gray-800 dark:border-gray-700">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    {{ $relatedEntityName }}:
                                </span>
                                <span class="text-sm text-gray-900 dark:text-gray-100">
                                    @if($relatedEntityName === 'Lead')
                                        <a href="{{ route('admin.leads.view', $relatedEntity->id) }}" class="text-blue-600 hover:text-blue-800 underline">
                                            {{ $relatedEntity->name ?? $relatedEntity->title ?? 'Onbekende lead' }}
                                        </a>
                                    @elseif($relatedEntityName === 'Person')
                                        <a href="{{ route('admin.contacts.persons.view', $relatedEntity->id) }}" class="text-blue-600 hover:text-blue-800 underline">
                                            {{ $relatedEntity->name ?? 'Onbekende persoon' }}
                                        </a>
                                    @elseif($relatedEntityName === 'Product')
                                        <a href="{{ route('admin.products.view', $relatedEntity->id) }}" class="text-blue-600 hover:text-blue-800 underline">
                                            {{ $relatedEntity->name ?? 'Onbekend product' }}
                                        </a>
                                    @elseif($relatedEntityName === 'Warehouse')
                                        <a href="{{ route('admin.warehouses.view', $relatedEntity->id) }}" class="text-blue-600 hover:text-blue-800 underline">
                                            {{ $relatedEntity->name ?? 'Onbekende warehouse' }}
                                        </a>
                                    @endif
                                </span>
                            </div>
                        </x-admin::form.control-group>
                    @endif

                    

                    <!-- is_done Checkbox -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.activities.edit.is_done')
                        </x-admin::form.control-group.label>
                        <input
                            type="checkbox"
                            name="is_done"
                            id="is_done"
                            value="1"
                            {{ old('is_done', $activity->is_done) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-brandColor shadow-sm focus:border-brandColor focus:ring focus:ring-brandColor focus:ring-opacity-50"
                        />
                        <label for="is_done" class="ml-2 text-sm text-gray-700 dark:text-gray-200">
                            @lang('admin::app.activities.edit.is_done-label')
                        </label>
                    </x-admin::form.control-group>

                    {!! view_render_event('admin.activities.edit.form_controls.after') !!}
                </div>

                <!-- Right sub-component -->
                <div class="w-[360px] max-w-full gap-2 max-xl:w-full">
                    {!! view_render_event('admin.activities.edit.accordion.general.before') !!}

                    <x-admin::accordion>
                        <x-slot:header>
                            <div class="flex items-center justify-between">
                                <p class="p-2.5 text-base font-semibold text-gray-800 dark:text-white">
                                    @lang('admin::app.activities.edit.general')
                                </p>
                            </div>
                        </x-slot>

                        <x-slot:content>
                            <!-- Title -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.title')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    name="title"
                                    id="title"
                                    rules="required"
                                    :value="old('title') ?? $activity->title"
                                    :label="trans('admin::app.activities.edit.title')"
                                    :placeholder="trans('admin::app.activities.edit.title')"
                                />

                                <x-admin::form.control-group.error control-name="title" />
                            </x-admin::form.control-group>

                            <!-- Edit Type -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.type')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    name="type"
                                    id="type"
                                    :value="old('type') ?? $activity->type"
                                    rules="required"
                                    :label="trans('admin::app.activities.edit.type')"
                                    :placeholder="trans('admin::app.activities.edit.type')"
                                >
                                    <option value="call">
                                        @lang('admin::app.activities.edit.call')
                                    </option>

                                    <option value="meeting">
                                        @lang('admin::app.activities.edit.meeting')
                                    </option>

                                    <option value="task">
                                        @lang('admin::app.activities.edit.task')
                                    </option>
                                </x-admin::form.control-group.control>

                                <x-admin::form.control-group.error control-name="type" />
                            </x-admin::form.control-group>

                            <!-- Location -->
                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.activities.edit.location')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    name="location"
                                    id="location"
                                    :value="old('location') ?? $activity->location"
                                    :label="trans('admin::app.activities.edit.location')"
                                    :placeholder="trans('admin::app.activities.edit.location')"
                                />

                                <x-admin::form.control-group.error control-name="location" />
                            </x-admin::form.control-group>
                        </x-slot>
                    </x-admin::accordion>

                    {!! view_render_event('admin.activities.edit.accordion.general.after') !!}
                </div>
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.activities.edit.form.after') !!}

    @pushOnce('scripts')

        <script>
            /**
             * Takeover activity from another user.
             *
             * @param {Number} activityId
             * @return {void}
             */
            window.takeoverActivity = async function(activityId) {
                if (!activityId) return;

                try {
                    const response = await fetch(`/admin/activities/${activityId}/takeover`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Er is een fout opgetreden bij het overnemen van de activiteit.');
                    }

                    // Success - show message and redirect
                    window.location.reload();

                } catch (error) {
                    // Show error message
                    alert(error.message);
                }
            };
        </script>
    @endPushOnce
</x-admin::layouts>
