@php use App\Enums\ActivityType; @endphp
<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.activities.edit.title')
    </x-slot>

    {!! view_render_event('admin.activities.edit.form.before') !!}

    <x-admin::form
        :action="route('admin.activities.update', $activity->id) . (request('return_url') ? ('?return_url=' . urlencode(request('return_url'))) : '')"
        method="PUT"
    >
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div
                class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <!-- Breadcrumbs -->
                    <x-admin::breadcrumbs
                        name="activities.edit"
                        :entity="$activity"
                    />

                    <!-- Page Title; status UI hidden per requirement -->
                    <div class="text-xl font-bold dark:text-gray-300 flex items-center justify-between gap-3 w-full">
                        <div class="flex items-center gap-2">
                            <x-admin::activities.icon :type="$activity->type"/>
                            <span>@lang('admin::app.activities.edit.title')</span>
                        </div>


                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <!-- Create button for person -->
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.activities.edit.save_button.before') !!}

                        @if($activity->is_done)
                            <!-- Heropenen Button submits reopen form -->
                            <button
                                type="submit"
                                form="activity-reopen-form"
                                class="secondary-button"
                            >
                                Heropenen
                            </button>
                        @else
                            <!-- Afronden Button submits dedicated hidden form to avoid detached listeners -->
                            <button
                                type="submit"
                                id="activity-complete-button"
                                form="activity-complete-form"
                                class="secondary-button"
                            >
                                Afronden
                            </button>
                        @endif

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
            <div class="flex">
                <!-- Single full-width editor -->
                <div
                    class="box-shadow w-full gap-2 rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    {!! view_render_event('admin.activities.edit.form_controls.before') !!}

                    <!-- Title -->
                    <x-adminc::components.field
                        type="text"
                        name="title"
                        id="title"
                        :label="trans('admin::app.activities.edit.title')"
                        value="{{ old('title') ?? $activity->title }}"
                        rules="required"
                        :placeholder="trans('admin::app.activities.edit.title')"
                    />

                    <!-- Type -->
                    <x-admin::form.control-group>
                        @php
                            $currentTypeValue = old('type') ?? ($activity->type?->value ?? $activity->type);
                            $currentTypeLabel = $activity->type->label();
                        @endphp

                        <div
                            class="flex items-center justify-between rounded-md border px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <span>{{ $currentTypeLabel }}</span>
                        </div>
                        <input type="hidden" name="type" value="{{ $currentTypeValue }}"/>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.activities.edit.type')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.error control-name="type"/>

                    </x-admin::form.control-group>

                    <!-- Schedule Date -->
                    <x-admin::form.control-group>
                        @php
                            $scheduleFromValue = old('schedule_from') ?? optional($activity->schedule_from)->format('Y-m-d\TH:i');
                            $scheduleToValue   = old('schedule_to') ?? optional($activity->schedule_to)->format('Y-m-d\TH:i');
                        @endphp

                        <div class="flex gap-2 max-sm:flex-wrap">
                            <x-adminc::components.field
                                type="datetime"
                                name="schedule_from"
                                id="schedule_from"
                                :label="trans('admin::app.components.activities.actions.activity.schedule-from')"
                                rules="required"
                                :value="$scheduleFromValue"
                                class="w-full"
                            />

                            @if($activity->type !== ActivityType::CALL)
                                <x-adminc::components.field
                                    type="datetime"
                                    name="schedule_to"
                                    id="schedule_to"
                                    :label="trans('admin::app.components.activities.actions.activity.schedule-to')"
                                    rules="required"
                                    :value="$scheduleToValue"
                                    class="w-full"
                                />
                            @else
                                <!-- Hidden field for call type - keeps existing schedule_to; can be updated when schedule_from changes -->
                                <input
                                    type="hidden"
                                    name="schedule_to"
                                    id="schedule_to_hidden"
                                    value="{{ old('schedule_to') ?? optional($activity->schedule_to)->format('Y-m-d\TH:i') }}"
                                />
                            @endif
                        </div>

                    </x-admin::form.control-group>

                    <!-- Description -->
                    <x-adminc::components.field
                        type="textarea"
                        name="comment"
                        id="comment"
                        :label="trans('admin::app.components.activities.actions.activity.description')"
                        value="{{ old('comment') ?? $activity->comment }}"
                        :placeholder="trans('admin::app.components.activities.actions.activity.description')"
                    />

                    <!-- Toegewezen aan -->
                    <x-admin::form.control-group>
                        <div class="flex items-center gap-2">
                            <x-admin::form.control-group.control
                                type="select"
                                name="user_id"
                                id="user_id_select"
                                :value="old('user_id', $activity->user_id)"
                                class="flex-1"
                                :disabled="$activity->user_id && $activity->user_id != auth()->guard('user')->id() && !$canTakeover"
                            >
                                <option value="">{{ __('admin::app.activities.select-user') }}</option>
                                @foreach (app(Webkul\User\Repositories\UserRepository::class)->allActiveUsers() as $user)
                                    <option
                                        value="{{ $user->id }}"
                                        {{ $activity->user_id == $user->id ? 'selected' : '' }}
                                    >
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </x-admin::form.control-group.control>

                            {{-- Hidden fallback: disabled selects are not submitted by the browser.
                                 This ensures the current user_id is always included in the form data.
                                 The takeover JS updates this hidden input alongside the select. --}}
                            @if($activity->user_id && $activity->user_id != auth()->guard('user')->id() && !$canTakeover)
                                <input type="hidden" name="user_id" id="user_id_hidden" value="{{ $activity->user_id }}" />
                            @endif

                            <!-- Takeover Button -->
                            @if($activity->user_id && $activity->user_id != auth()->guard('user')->id() && $canTakeover)
                                <button
                                    type="button"
                                    class="secondary-button bg-orange-500 hover:bg-orange-600 text-white whitespace-nowrap"
                                    onclick="takeoverActivity({{ $activity->id }})"
                                    title="Overnemen van {{ $activity->user ? $activity->user->name : 'onbekend' }}"
                                >
                                    Overnemen
                                </button>
                            @endif
                        </div>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.activities.assign-to')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.error control-name="user_id"/>

                    </x-admin::form.control-group>

                    <!-- Group -->
                    <x-adminc::components.field
                        type="select"
                        name="group_id"
                        :label="__('admin::app.activities.group')"
                        :value="old('group_id', $activity->group_id)"
                    >
                        <option value="">{{ __('admin::app.activities.select-group') }}</option>
                        @foreach ($groups as $group)
                            <option
                                value="{{ $group->id }}" {{ $activity->group_id == $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </x-adminc::components.field>

                    <!-- Related Entity Information -->
                    @if($relatedEntity && $relatedEntityName)
                        <x-admin::form.control-group class="!mb-0">
                            <div
                                class="flex items-center gap-2 p-3 bg-gray-50 rounded-md border bg-white dark:bg-gray-800 dark:border-gray-700">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    {{ $relatedEntityName }}:
                                </span>
                                <span class="text-sm text-gray-900 dark:text-gray-100">
                                    @if($relatedEntityName === 'Lead')
                                        <a href="{{ route('admin.leads.view', $relatedEntity->id) }}"
                                           class="text-activity-note-text hover:text-activity-task-text underline">
                                            {{ $relatedEntity->name ?? $relatedEntity->title ?? 'Onbekende lead' }}
                                        </a>
                                    @elseif($relatedEntityName === 'Sales_lead')
                                        <a href="{{ route('admin.sales-lead.view', $relatedEntity->id) }}"
                                           class="text-activity-note-text hover:text-activity-task-text underline">
                                            {{ $relatedEntity->name ?? 'Onbekende sales' }}
                                        </a>
                                    @else
                                        Onbekende entiteit type: {{ $relatedEntityName }}
                                    @endif
                                </span>
                            </div>
                            <x-admin::form.control-group.label>
                                Gerelateerd aan
                            </x-admin::form.control-group.label>

                        </x-admin::form.control-group>
                    @endif



                    <!-- is_done Checkbox removed in favor of Afronden button -->

                    <!-- Publiceren in patiëntportaal (alleen voor file, meeting, patient_message) -->
                    @if(in_array($activity->type, [ActivityType::FILE, ActivityType::PATIENT_MESSAGE]))
                        <input type="hidden" name="publish_to_portal" value="0" />
                        <x-adminc::components.field
                            type="checkbox"
                            name="publish_to_portal"
                            label="Publiceren in patiëntportaal"
                            value="1"
                            :checked="old('publish_to_portal', $activity->publish_to_portal)"
                        />
                    @endif

                    {!! view_render_event('admin.activities.edit.form_controls.after') !!}
                </div>
            </div>
        </div>
    </x-admin::form>

    <!-- Hidden form used by Afronden button -->
    <form id="activity-complete-form"
          action="{{ route('admin.activities.update', $activity->id) }}@if(request('return_url'))?return_url={{ urlencode(request('return_url')) }}@endif"
          method="POST" class="hidden">
        @csrf
        <input type="hidden" name="_method" value="PUT"/>
        <input type="hidden" name="is_done" value="1"/>
        <input type="hidden" name="status" value="done"/>
    </form>

    <!-- Hidden form used by Heropenen button -->
    <form id="activity-reopen-form"
          action="{{ route('admin.activities.update', $activity->id) }}@if(request('return_url'))?return_url={{ urlencode(request('return_url')) }}@endif"
          method="POST" class="hidden">
        @csrf
        <input type="hidden" name="_method" value="PUT"/>
        <input type="hidden" name="is_done" value="0"/>
    </form>

    {!! view_render_event('admin.activities.edit.form.after') !!}

    @pushOnce('scripts')

        <script>
            /**
             * Takeover activity from another user.
             *
             * @param {Number} activityId
             * @return {void}
             */
            window.takeoverActivity = async function (activityId) {
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

                    // Success - update the user_id dropdown and hide takeover button
                    const responseData = await response.json();
                    const currentUserId = {{ auth()->guard('user')->id() ?? 'null' }};

                    // Update the user_id dropdown
                    const userSelect = document.getElementById('user_id_select');
                    if (userSelect) {
                        userSelect.value = currentUserId;
                        userSelect.disabled = false; // Enable the field since user now owns it
                    }

                    // Remove hidden fallback input (no longer needed once select is enabled)
                    const hiddenInput = document.getElementById('user_id_hidden');
                    if (hiddenInput) {
                        hiddenInput.remove();
                    }

                    // Hide the takeover button
                    const takeoverButton = document.querySelector('button[onclick*="takeoverActivity"]');
                    if (takeoverButton) {
                        takeoverButton.style.display = 'none';
                    }

                    // Show success message
                    if (responseData.message) {
                        // You can implement a flash message system here if available
                        alert(responseData.message);
                    }

                } catch (error) {
                    // Show error message
                    alert(error.message);
                }
            };

            /**
             * Handle user assignment dropdown change
             */
            document.addEventListener('DOMContentLoaded', function () {
                const userSelect = document.querySelector('select[name="user_id"]');
                const takeoverButton = document.querySelector('button[onclick*="takeoverActivity"]');
                const currentUserId = {{ auth()->guard('user')->id() ?? 'null' }};
                const canTakeover = {{ $canTakeover ? 'true' : 'false' }};

                if (userSelect) {
                    userSelect.addEventListener('change', function () {
                        const selectedUserId = parseInt(this.value);

                        // Show/hide takeover button based on selection and permissions
                        if (takeoverButton) {
                            if (selectedUserId && selectedUserId !== currentUserId && canTakeover) {
                                takeoverButton.style.display = 'inline-block';
                            } else {
                                takeoverButton.style.display = 'none';
                            }
                        }
                    });
                }
            });
        </script>



        <script>
            // Inline status update without leaving the page (global function + immediate bind)
            (function () {
                const container = document.getElementById('activity-status-buttons');
                if (!container) return;
                const url = container.getAttribute('data-update-url');
                const csrf = container.getAttribute('data-csrf');

                const inactive = 'bg-white text-gray-700 border-gray-300 hover:bg-neutral-bg dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700';
                const map = {
                    in_progress: 'bg-blue-100 text-activity-task-text border-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-800',
                    active: 'bg-green-100 text-green-800 border-green-300 dark:bg-green-900 dark:text-green-300 dark:border-green-800',
                    on_hold: 'bg-yellow-100 text-status-on_hold-text border-yellow-300 dark:bg-yellow-900 dark:text-yellow-300 dark:border-yellow-800',
                    expired: 'bg-red-100 text-red-800 border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-800',
                    done: 'bg-gray-200 text-gray-800 border-gray-400 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600',
                };

                window.updateActivityStatus = async function (status) {
                    try {
                        console.debug('[activity-status:global] update ->', status);
                        const params = new URLSearchParams();
                        params.append('_method', 'PUT');
                        params.append('status', status);

                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            },
                            body: params.toString()
                        });

                        const body = await (async () => {
                            try {
                                return await res.json();
                            } catch (_) {
                                return {};
                            }
                        })();

                        if (!res.ok) {
                            // Server-side validation: update UI to computed status and show message
                            const computed = body && body.status ? body.status : null;
                            const message = body && body.message ? body.message : 'Status bijwerken mislukt';
                            if (computed) {
                                container.querySelectorAll('button.status-btn').forEach(b => {
                                    b.classList.remove('ring-2');
                                    b.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + inactive;
                                });
                                const compBtn = container.querySelector('button.status-btn[data-status="' + computed + '"]');
                                if (compBtn) {
                                    compBtn.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + (map[computed] || '');
                                    compBtn.classList.add('ring-2');
                                }
                            }
                            alert(message);
                            return;
                        }

                        const newStatus = (body && body.status) ? body.status : status;
                        container.querySelectorAll('button.status-btn').forEach(b => {
                            b.classList.remove('ring-2');
                            b.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + inactive;
                        });
                        const activeBtn = container.querySelector('button.status-btn[data-status="' + newStatus + '"]');
                        if (activeBtn) {
                            activeBtn.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + (map[newStatus] || '');
                            activeBtn.classList.add('ring-2');
                        }
                    } catch (err) {
                        console.error('[activity-status:global] error', err);
                        alert(err.message || 'Kon status niet bijwerken');
                    }
                }
            })();

            /**
             * Update schedule_to for call activities (schedule_from + 1 hour)
             */
            window.updateScheduleToForCall = function () {
                const scheduleFromInput = document.getElementById('schedule_from');
                const scheduleToHidden = document.getElementById('schedule_to_hidden');

                if (scheduleFromInput && scheduleToHidden && scheduleFromInput.value) {
                    try {
                        const fromDate = new Date(scheduleFromInput.value);
                        const toDate = new Date(fromDate.getTime() + (60 * 60 * 1000)); // Add 1 hour

                        // Format the date for the hidden input (ISO format)
                        scheduleToHidden.value = toDate.toISOString().slice(0, 16);
                    } catch (error) {
                        console.error('Error updating schedule_to for call:', error);
                    }
                }
            };

            // Wire schedule_to updates for call activities when schedule_from changes
            document.addEventListener('DOMContentLoaded', function () {
                @if($activity->type === ActivityType::CALL)
                const scheduleFromInput = document.getElementById('schedule_from');
                if (scheduleFromInput) {
                    scheduleFromInput.addEventListener('change', updateScheduleToForCall);
                }
                @endif
            });
        </script>
    @endPushOnce
</x-admin::layouts>
