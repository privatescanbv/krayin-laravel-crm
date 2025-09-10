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

                    <!-- Page Title + Horizontal Clickable Status List -->
                    <div class="text-xl font-bold dark:text-gray-300 flex items-center justify-between gap-3 w-full">
                        <span>@lang('admin::app.activities.edit.title')</span>

                        @php
                            $status = is_string($activity->status) ? $activity->status : ($activity->status?->value ?? 'active');
                            $statusLabels = [
                                'in_progress' => 'In behandeling',
                                'active' => 'Actief',
                                'on_hold' => 'On hold',
                                'expired' => 'Verlopen',
                            ];
                            $baseClasses = 'px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer';
                            $inactiveClasses = 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700';
                            $activeMap = [
                                'in_progress' => 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-800',
                                'active' => 'bg-green-100 text-green-800 border-green-300 dark:bg-green-900 dark:text-green-300 dark:border-green-800',
                                'on_hold' => 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-900 dark:text-yellow-300 dark:border-yellow-800',
                                'expired' => 'bg-red-100 text-red-800 border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-800',
                            ];
                            $options = ['in_progress','active','on_hold','expired'];
                        @endphp

                        <div class="flex items-center gap-2" id="activity-status-buttons" data-update-url="{{ route('admin.activities.update', $activity->id) }}" data-csrf="{{ csrf_token() }}">
                            @foreach ($options as $opt)
                                <button type="button"
                                        data-status="{{ $opt }}"
                                        class="status-btn {{ $baseClasses }} {{ $status === $opt ? ($activeMap[$opt] ?? '') : $inactiveClasses }}">
                                    {{ $statusLabels[$opt] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <!-- Create button for person -->
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.activities.edit.save_button.before') !!}

                        <!-- Afronden Button -->
                        <button
                            type="submit"
                            name="is_done"
                            value="1"
                            class="secondary-button"
                        >
                            Afronden
                        </button>

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
            <div class="flex gap-2.5 max-lg:flex-wrap-reverse">
                <!-- Left sub-component -->
                <div class="box-shadow flex-1 gap-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 max-lg:flex-auto">
                    {!! view_render_event('admin.activities.edit.form_controls.before') !!}

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

                    <!-- Type -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.activities.edit.type')
                        </x-admin::form.control-group.label>

                        @php
                            $currentTypeValue = old('type') ?? ($activity->type?->value ?? $activity->type);
                            $currentTypeLabel = trans('admin::app.activities.edit.' . $currentTypeValue);
                        @endphp

                        <div class="flex items-center justify-between rounded-md border px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <span>{{ $currentTypeLabel }}</span>
                        </div>
                        <input type="hidden" name="type" value="{{ $currentTypeValue }}" />

                        <x-admin::form.control-group.error control-name="type" />
                    </x-admin::form.control-group>

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
                    @if ($activity->type !== \App\Enums\ActivityType::CALL || !empty($activity->comment))
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
                    @endif

                    <!-- Toegewezen aan -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.activities.assign-to')
                        </x-admin::form.control-group.label>

                        <div class="flex items-center gap-2">
                            <x-admin::form.control-group.control
                                type="select"
                                name="user_id"
                                :value="old('user_id', $activity->user_id)"
                                class="flex-1"
                                :disabled="$activity->user_id && $activity->user_id != auth()->guard('user')->id() && !$canTakeover"
                            >
                                <option value="">{{ __('admin::app.activities.select-user') }}</option>
                                @foreach (app(Webkul\User\Repositories\UserRepository::class)->findWhere(['status' => 1]) as $user)
                                    <option
                                        value="{{ $user->id }}"
                                        {{ $activity->user_id == $user->id ? 'selected' : '' }}
                                    >
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </x-admin::form.control-group.control>

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

                        <x-admin::form.control-group.error control-name="user_id" />
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



                    <!-- is_done Checkbox removed in favor of Afronden button -->

                    {!! view_render_event('admin.activities.edit.form_controls.after') !!}
                </div>

                <!-- Right sub-component -->
                <div class="w-[360px] max-w-full gap-2 max-lg:w-full">
                    @if ($activity->type === \App\Enums\ActivityType::CALL)
                        @include('admin::components.activities.call-status', ['activity' => $activity, 'callStatuses' => $callStatuses ?? []])
                    @endif
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

                    // Success - update the user_id dropdown and hide takeover button
                    const responseData = await response.json();
                    const currentUserId = {{ auth()->guard('user')->id() ?? 'null' }};

                    // Update the user_id dropdown
                    const userSelect = document.querySelector('select[name="user_id"]');
                    if (userSelect) {
                        userSelect.value = currentUserId;
                        userSelect.disabled = false; // Enable the field since user now owns it
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
            document.addEventListener('DOMContentLoaded', function() {
                const userSelect = document.querySelector('select[name="user_id"]');
                const takeoverButton = document.querySelector('button[onclick*="takeoverActivity"]');
                const currentUserId = {{ auth()->guard('user')->id() ?? 'null' }};
                const canTakeover = {{ $canTakeover ? 'true' : 'false' }};

                if (userSelect) {
                    userSelect.addEventListener('change', function() {
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
            // Inline status update without leaving the page
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.getElementById('activity-status-buttons');
                if (!container) {
                    console.warn('[activity-status] container not found');
                    return;
                }

                const url = container.getAttribute('data-update-url');
                const csrf = container.getAttribute('data-csrf');
                console.debug('[activity-status] ready. url:', url);

                // Delegate on document to survive DOM updates
                document.addEventListener('click', async function(e) {
                    const btn = e.target.closest && e.target.closest('button.status-btn');
                    if (!btn || !document.body.contains(btn)) return;
                    if (!container.contains(btn)) return;
                    e.preventDefault();
                    e.stopPropagation();
                    const status = btn.getAttribute('data-status');
                    console.debug('[activity-status] click ->', status);
                    try {
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

                        if (!res.ok) {
                            let message = 'Status bijwerken mislukt';
                            try { const data = await res.json(); if (data && data.message) message = data.message; } catch (_) {}
                            throw new Error(message);
                        }

                        const inactive = 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700';
                        const map = {
                            in_progress: 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-800',
                            active: 'bg-green-100 text-green-800 border-green-300 dark:bg-green-900 dark:text-green-300 dark:border-green-800',
                            on_hold: 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-900 dark:text-yellow-300 dark:border-yellow-800',
                            expired: 'bg-red-100 text-red-800 border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-800',
                        };

                        container.querySelectorAll('button.status-btn').forEach(b => {
                            b.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + inactive;
                        });
                        btn.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + (map[status] || '');
                    } catch (err) {
                        console.error('[activity-status] error', err);
                        alert(err.message || 'Kon status niet bijwerken');
                    }
                });
            });
        </script>
    @endPushOnce
</x-admin::layouts>
