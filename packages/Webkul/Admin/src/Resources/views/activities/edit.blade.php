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
                                'done' => 'Afgerond',
                            ];
                            $baseClasses = 'px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer';
                            $inactiveClasses = 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700';
                            $activeMap = [
                                'in_progress' => 'bg-blue-100 text-blue-800 border-blue-400 ring-2 ring-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-700 dark:ring-blue-700',
                                'active' => 'bg-green-100 text-green-800 border-green-400 ring-2 ring-green-300 dark:bg-green-900 dark:text-green-300 dark:border-green-700 dark:ring-green-700',
                                'on_hold' => 'bg-yellow-100 text-yellow-800 border-yellow-400 ring-2 ring-yellow-300 dark:bg-yellow-900 dark:text-yellow-300 dark:border-yellow-700 dark:ring-yellow-700',
                                'expired' => 'bg-red-100 text-red-800 border-red-400 ring-2 ring-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-700 dark:ring-red-700',
                                'done' => 'bg-gray-200 text-gray-800 border-gray-400 ring-2 ring-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:ring-gray-600',
                            ];
                            $options = ['in_progress','active','on_hold','expired','done'];
                        @endphp

                        <script>
                            // Define global handler early so inline onclick works immediately
                            (function(){
                                var container = document.getElementById('activity-status-buttons');
                                // If container not yet in DOM, re-run after microtask
                                if (!container) {
                                    setTimeout(arguments.callee, 0);
                                    return;
                                }
                                var url = container.getAttribute('data-update-url');
                                var csrf = container.getAttribute('data-csrf');
                                var inactive = 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700';
                                var map = {
                                    in_progress: 'bg-blue-100 text-blue-800 border-blue-400 ring-2 ring-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-700 dark:ring-blue-700',
                                    active: 'bg-green-100 text-green-800 border-green-400 ring-2 ring-green-300 dark:bg-green-900 dark:text-green-300 dark:border-green-700 dark:ring-green-700',
                                    on_hold: 'bg-yellow-100 text-yellow-800 border-yellow-400 ring-2 ring-yellow-300 dark:bg-yellow-900 dark:text-yellow-300 dark:border-yellow-700 dark:ring-yellow-700',
                                    expired: 'bg-red-100 text-red-800 border-red-400 ring-2 ring-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-700 dark:ring-red-700',
                                    done: 'bg-gray-200 text-gray-800 border-gray-400 ring-2 ring-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:ring-gray-600'
                                };
                                window.updateActivityStatus = async function(status) {
                                    try {
                                        console.debug('[activity-status:inline] click', status);
                                        // optimistic UI
                                        container.querySelectorAll('button.status-btn').forEach(function(b){
                                            b.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + inactive;
                                        });
                                        var btn = container.querySelector('button.status-btn[data-status="' + status + '"]');
                                        if (btn) {
                                            btn.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + (map[status] || '');
                                        }

                                        var params = new URLSearchParams();
                                        params.append('_method', 'PUT');
                                        params.append('status', status);
                                        var res = await fetch(url, {
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': csrf,
                                                'X-Requested-With': 'XMLHttpRequest',
                                                'Accept': 'application/json',
                                                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                                            },
                                            body: params.toString()
                                        });
                                        var body = {};
                                        try { body = await res.json(); } catch(_) {}
                                        if (!res.ok) {
                                            var computed = body && body.status ? body.status : null;
                                            var message  = body && body.message ? body.message : 'Status bijwerken mislukt';
                                            if (computed) {
                                                container.querySelectorAll('button.status-btn').forEach(function(b){
                                                    b.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + inactive;
                                                });
                                                var cb = container.querySelector('button.status-btn[data-status="' + computed + '"]');
                                                if (cb) {
                                                    cb.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + (map[computed] || '');
                                                }
                                            }
                                            alert(message);
                                            return;
                                        }
                                        var newStatus = (body && body.status) ? body.status : status;
                                        container.querySelectorAll('button.status-btn').forEach(function(b){
                                            b.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + inactive;
                                        });
                                        var ab = container.querySelector('button.status-btn[data-status="' + newStatus + '"]');
                                        if (ab) {
                                            ab.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + (map[newStatus] || '');
                                        }
                                    } catch (err) {
                                        console.error('[activity-status:inline] error', err);
                                        alert(err.message || 'Kon status niet bijwerken');
                                    }
                                }
                            })();
                        </script>
                        <div class="flex items-center gap-2" id="activity-status-buttons" data-update-url="{{ route('admin.activities.update', $activity->id) }}" data-csrf="{{ csrf_token() }}">
                            @foreach ($options as $opt)
                                <button type="button"
                                        data-status="{{ $opt }}"
                                        onclick="window.updateActivityStatus && window.updateActivityStatus('{{ $opt }}')"
                                        class="status-btn {{ $baseClasses }} {{ $status === $opt ? ($activeMap[$opt] ?? '') : $inactiveClasses }}">
                                    {{ $statusLabels[$opt] }}
                                </button>
                            @endforeach
                        </div>
                        <script>
                            // Fallback inline binder (executes immediately in header)
                            (function(){
                                const container = document.getElementById('activity-status-buttons');
                                if (!container) return;
                                const url = container.getAttribute('data-update-url');
                                const csrf = container.getAttribute('data-csrf');
                                console.debug('[activity-status-inline] bound. url:', url);
                                document.addEventListener('click', async function(e){
                                    const btn = e.target && e.target.closest ? e.target.closest('button.status-btn') : null;
                                    if (!btn || !container.contains(btn)) return;
                                    e.preventDefault();
                                    e.stopPropagation();
                                    const status = btn.getAttribute('data-status');
                                    console.debug('[activity-status-inline] click', status);
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
                                            try { const data = await res.json(); if (data && data.message) message = data.message; } catch(_){}
                                            throw new Error(message);
                                        }
                                        const inactive = 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700';
                                        const map = {
                                            in_progress: 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-800',
                                            active: 'bg-green-100 text-green-800 border-green-300 dark:bg-green-900 dark:text-green-300 dark:border-green-800',
                                            on_hold: 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-900 dark:text-yellow-300 dark:border-yellow-800',
                                            expired: 'bg-red-100 text-red-800 border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-800',
                                            done: 'bg-gray-200 text-gray-800 border-gray-400 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600',
                                        };
                                        container.querySelectorAll('button.status-btn').forEach(b => {
                                            b.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + inactive;
                                        });
                                        btn.className = 'status-btn px-2 py-1 text-xs font-medium rounded-full border transition-colors cursor-pointer ' + (map[status] || '');
                                    } catch(err) {
                                        console.error('[activity-status-inline] error', err);
                                        alert(err.message || 'Kon status niet bijwerken');
                                    }
                                });
                            })();
                        </script>
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

                    <!-- Linked Emails Section -->
                    @if($activity->emails && $activity->emails->count() > 0)
                        <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Gekoppelde E-Mails
                                </h3>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $activity->emails->count() }} {{ $activity->emails->count() === 1 ? 'e-mail' : 'e-mails' }}
                                </span>
                            </div>
                            
                            <div class="space-y-3">
                                @foreach($activity->emails as $email)
                                    <div class="flex items-start gap-3 p-3 rounded-md border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                        <div class="flex-shrink-0">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-300">
                                                <span class="icon-mail text-sm"></span>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                                        <a
                                                            href="{{ route('admin.mail.view', ['route' => 'inbox', 'id' => $email->id]) }}"
                                                            target="_blank"
                                                            class="hover:underline"
                                                            title="E-mail bekijken"
                                                        >
                                                            {{ $email->subject ?: 'Geen onderwerp' }}
                                                        </a>
                                                    </h4>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                        {{ $email->created_at->format('d-m-Y H:i') }}
                                                    </p>
                                                    @if($email->from)
                                                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                                            Van: {{ is_array($email->from) ? implode(', ', $email->from) : $email->from }}
                                                        </p>
                                                    @endif
                                                </div>
                                                <a
                                                    href="{{ route('admin.mail.view', ['route' => 'inbox', 'id' => $email->id]) }}"
                                                    target="_blank"
                                                    class="flex-shrink-0 ml-2 flex h-6 w-6 items-center justify-center rounded-md text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                                                    title="E-mail bekijken"
                                                >
                                                    <span class="icon-right-arrow text-xs"></span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Compose email action (opens same dialog as in lead view) -->
                    @if ($activity->lead)
                        <div class="mt-4">
                            @include('admin::components.activities.actions.mail', [
                                'entity' => $activity->lead,
                                'entityControlName' => 'lead_id',
                                'activity' => $activity,
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </x-admin::form>

    <!-- Hidden form used by Afronden button -->
    <form id="activity-complete-form" action="{{ route('admin.activities.update', $activity->id) }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="_method" value="PUT" />
        <input type="hidden" name="is_done" value="1" />
        <input type="hidden" name="status" value="done" />
    </form>

    <!-- Hidden form used by Heropenen button -->
    <form id="activity-reopen-form" action="{{ route('admin.activities.update', $activity->id) }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="_method" value="PUT" />
        <input type="hidden" name="is_done" value="0" />
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
            // Inline status update without leaving the page (global function + immediate bind)
            (function(){
                const container = document.getElementById('activity-status-buttons');
                if (!container) return;
                const url = container.getAttribute('data-update-url');
                const csrf = container.getAttribute('data-csrf');

                const inactive = 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700';
                const map = {
                    in_progress: 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-800',
                    active: 'bg-green-100 text-green-800 border-green-300 dark:bg-green-900 dark:text-green-300 dark:border-green-800',
                    on_hold: 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-900 dark:text-yellow-300 dark:border-yellow-800',
                    expired: 'bg-red-100 text-red-800 border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-800',
                    done: 'bg-gray-200 text-gray-800 border-gray-400 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600',
                };

                window.updateActivityStatus = async function(status) {
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

                        const body = await (async () => { try { return await res.json(); } catch (_) { return {}; } })();

                        if (!res.ok) {
                            // Server-side validation: update UI to computed status and show message
                            const computed = body && body.status ? body.status : null;
                            const message  = body && body.message ? body.message : 'Status bijwerken mislukt';
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
        </script>
    @endPushOnce
</x-admin::layouts>
