@php
    use App\Enums\ActivityType;
    use App\Enums\LostReason;

    $isReadOnly = (bool) $activity->is_done;
@endphp
<x-admin::layouts>
    <x-slot:title>
        {{ $activity->title ?: __('admin::app.activities.edit.title') }}
    </x-slot>

    {!! view_render_event('admin.activities.edit.form.before') !!}

    <x-admin::form
        :action="route('admin.activities.update', $activity->id) . (request('return_url') ? ('?return_url=' . urlencode(request('return_url'))) : '')"
        method="PUT"
    >
        @include('adminc.components.validation-errors')

        <!-- Header Bar -->
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="activities.edit" :entity="$activity" />
                <div class="text-xl font-bold dark:text-gray-300 flex items-center gap-2">
                    <x-admin::activities.icon :type="$activity->type"/>
                    <span>{{ $activity->title ?: __('admin::app.activities.edit.title') }}</span>
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (!$activity->is_done && bouncer()->hasPermission('activities.delete'))
                    <form method="POST"
                          action="{{ route('admin.activities.delete', $activity->id) }}@if(request('return_url'))?return_url={{ urlencode(request('return_url')) }}@endif"
                          onsubmit="return confirm('Weet je zeker dat je deze activiteit wilt verwijderen?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="secondary-button" title="Verwijderen">
                            <span class="icon-delete text-2xl"></span>
                        </button>
                    </form>
                @endif

                @if (!$activity->is_done && $activity->lead && bouncer()->hasPermission('leads.edit'))
                    <button type="button" class="secondary-button" @click="openLeadAfvoerenModal">
                        Lead afvoeren
                    </button>
                @endif

                {!! view_render_event('admin.activities.edit.save_button.before') !!}

                @if($activity->is_done)
                    <button type="submit" form="activity-reopen-form" class="secondary-button">
                        Heropenen
                    </button>
                @else
                    <button type="submit" form="activity-complete-form" class="secondary-button">
                        Afronden
                    </button>
                @endif

                <button
                    type="button"
                    class="secondary-button"
                    onclick="(function(){var p=new URLSearchParams(window.location.search),r=p.get('return_url');window.location.href=r?r:'{{ route('admin.activities.index') }}';})()"
                >
                    Annuleren
                </button>

                @if(! $isReadOnly)
                    <button type="submit" class="primary-button" data-activity-save-button>
                        @lang('admin::app.activities.edit.save-btn')
                    </button>
                @endif

                {!! view_render_event('admin.activities.edit.save_button.after') !!}
            </div>
        </div>

        @if($isReadOnly)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                Deze activiteit is afgerond en daarom alleen-lezen. Heropen de activiteit om gegevens of acties te wijzigen.
            </div>
        @endif

        <!-- Three Column Layout -->
        <div class="relative flex gap-4 max-lg:flex-wrap mt-4">

            <!-- Left Panel (sticky, form fields) -->
            <div class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">

                <!-- Koppeling -->
                @if(bouncer()->hasPermission('activities.edit') && ! $isReadOnly)
                    @include('admin::activities.partials.link-panel')
                    <div class="p-4 border-b border-gray-200 dark:border-gray-800">
                        <v-activity-link-panel></v-activity-link-panel>
                    </div>
                @else
                    <div class="p-4 border-b border-gray-200 dark:border-gray-800">
                        @include('admin::activities.partials.relation_display', ['activity' => $activity])
                    </div>
                @endif

                <!-- Form Fields -->
                <div class="p-4 flex flex-col gap-0">
                    <fieldset class="contents" @if($isReadOnly) disabled @endif>
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

                        <!-- Type (read-only) -->
                        <x-admin::form.control-group>
                            @php
                                $currentTypeValue = old('type') ?? ($activity->type?->value ?? $activity->type);
                                $currentTypeLabel = $activity->type->label();
                            @endphp
                            <div class="flex items-center justify-between rounded-md border px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                <span>{{ $currentTypeLabel }}</span>
                            </div>
                            <input type="hidden" name="type" value="{{ $currentTypeValue }}"/>
                            <x-admin::form.control-group.label class="required">
                                @lang('admin::app.activities.edit.type')
                            </x-admin::form.control-group.label>
                            <x-admin::form.control-group.error control-name="type"/>
                        </x-admin::form.control-group>

                        <!-- Deadline -->
                        @if($activity->type->hasDeadline())
                        <x-admin::form.control-group>
                            @php
                                $scheduleToValue   = old('schedule_to') ?? optional($activity->schedule_to)->format('Y-m-d\TH:i');
                            @endphp
                            <x-adminc::components.field
                                type="datetime"
                                name="schedule_to"
                                id="schedule_to"
                                :label="trans('admin::app.components.activities.actions.activity.schedule-to')"
                                rules="required"
                                :value="$scheduleToValue"
                                class="w-full"
                            />
                        </x-admin::form.control-group>
                        @endif

                        <!-- Description -->
                        @php
                            $commentRestrictedTypes = [ActivityType::CALL, ActivityType::TASK];
                            $isCommentReadOnly = in_array($activity->type, $commentRestrictedTypes)
                                && ! auth()->guard('user')->user()?->isGlobalAdmin();
                        @endphp
                        @if($isCommentReadOnly)
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    {{ trans('admin::app.components.activities.actions.activity.description') }}
                                </x-admin::form.control-group.label>
                                <textarea
                                    class="w-full rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                                    rows="3"
                                    disabled
                                >{{ $activity->comment }}</textarea>
                                <input type="hidden" name="comment" value="{{ $activity->comment }}">
                            </x-admin::form.control-group>
                        @else
                            <x-adminc::components.field
                                type="textarea"
                                name="comment"
                                id="comment"
                                :label="trans('admin::app.components.activities.actions.activity.description')"
                                value="{{ old('comment') ?? $activity->comment }}"
                                :placeholder="trans('admin::app.components.activities.actions.activity.description')"
                            />
                        @endif

                        <!-- Toegewezen aan -->
                        <x-admin::form.control-group>
                            <div class="flex items-center gap-2">
                                <x-admin::form.control-group.control
                                    type="select"
                                    name="user_id"
                                    id="user_id_select"
                                    :value="old('user_id', $activity->user_id)"
                                    class="flex-1"
                                    :disabled="$isReadOnly || ($activity->user_id && $activity->user_id != auth()->guard('user')->id() && !$canTakeover)"
                                >
                                    <option value="">{{ __('admin::app.activities.select-user') }}</option>
                                    @foreach (app(Webkul\User\Repositories\UserRepository::class)->allActiveUsers() as $user)
                                        <option value="{{ $user->id }}" {{ $activity->user_id == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </x-admin::form.control-group.control>

                                @if(! $isReadOnly && $activity->user_id && $activity->user_id != auth()->guard('user')->id() && !$canTakeover)
                                    <input type="hidden" name="user_id" id="user_id_hidden" value="{{ $activity->user_id }}" />
                                @endif

                                @if(! $isReadOnly && $activity->user_id && $activity->user_id != auth()->guard('user')->id() && $canTakeover)
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
                            rules="required"
                        >
                            <option value="">{{ __('admin::app.activities.select-group') }}</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" {{ $activity->group_id == $group->id ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </x-adminc::components.field>

                        <!-- Portal publishing (only for FILE/PATIENT_MESSAGE) -->
                        @if(in_array($activity->type, [ActivityType::FILE, ActivityType::PATIENT_MESSAGE]))
                            <input type="hidden" name="publish_to_portal" value="0" />
                            <x-adminc::components.field
                                type="checkbox"
                                name="publish_to_portal"
                                label="Publiceren in patiëntportaal"
                                value="1"
                                :checked="old('publish_to_portal', $activity->portalPersons->isNotEmpty())"
                            />
                        @endif

                        {!! view_render_event('admin.activities.edit.form_controls.after') !!}
                    </fieldset>
                </div>

<!-- Additional data (hidden for SYSTEM activities — handled by the center panel) -->
                @if (is_array($activity->additional) && !empty($activity->additional) && $activity->type !== \App\Enums\ActivityType::SYSTEM)
                    <div class="p-4 border-t border-gray-200 dark:border-gray-800">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Extra gegevens</div>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                            @foreach ($activity->additional as $key => $value)
                                <dt class="text-xs text-gray-400 dark:text-gray-500">{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $key)) }}</dt>
                                <dd class="text-xs text-gray-900 dark:text-gray-100 break-all">
                                    {{ is_array($value) ? json_encode($value) : $value }}
                                </dd>
                            @endforeach
                        </dl>
                    </div>
                @endif

                <!-- Footer timestamps -->
                <div class="flex w-full flex-col gap-2 p-4 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-800">
                    <div class="flex justify-between">
                        <span>Aangemaakt op:</span>
                        <span>{{ $activity->created_at->format('d-m-Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Bijgewerkt op:</span>
                        <span>{{ $activity->updated_at->format('d-m-Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Bijgewerkt door:</span>
                        <span>{{ ($activity->updater ?? $activity->creator)?->name ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- Center Panel (type-specific content) -->
            <div class="flex flex-1 flex-col gap-4">
                @if(in_array($activity->type, [ActivityType::CALL, ActivityType::TASK]))
                    @include('admin::activities.partials.actions')
                @elseif($activity->type == ActivityType::PATIENT_MESSAGE)
                    @include('admin::activities.partials.patient-message')
                @elseif($activity->type == ActivityType::FILE)
                    @include('admin::activities.partials.file')
                @elseif($activity->type == ActivityType::SYSTEM)
                    @include('admin::activities.partials.system')
                @endif
            </div>


        </div>
    </x-admin::form>

    <!-- Hidden forms (outside main form to avoid nesting) -->
    <form id="activity-complete-form"
          action="{{ route('admin.activities.mark-done', $activity->id) }}@if(request('return_url'))?return_url={{ urlencode(request('return_url')) }}@endif"
          method="POST" class="hidden">
        @csrf
    </form>

    <form id="activity-reopen-form"
          action="{{ route('admin.activities.reopen', $activity->id) }}@if(request('return_url'))?return_url={{ urlencode(request('return_url')) }}@endif"
          method="POST" class="hidden">
        @csrf
    </form>

    {!! view_render_event('admin.activities.edit.form.after') !!}

    @php
        // Resolve the best entity for the mail dialog — works for any linked entity type,
        // not just leads. The mail component handles lead_id, person_id, sales_lead_id,
        // order_id and clinic_id separately in its save() route logic.
        $mailEntity = $relatedEntity ?? $activity->lead ?? null;
        $mailEntityControlName = $relatedEntityType?->getForeignKey() ?? 'lead_id';
    @endphp
    @if($mailEntity && ! $isReadOnly)
        <!-- Mail dialog (listens for open-email-dialog event from actions panel) -->
        <x-admin::activities.actions.mail
            :entity="$mailEntity"
            entity-control-name="{{ $mailEntityControlName }}"
            :show-button="false"
            :activity-id="$activity->id"
        />
    @endif

    @if($activity->lead)
        <x-admin::modal ref="leadAfvoerenModal">
            <x-slot:header>
                <h3 class="text-base font-semibold dark:text-white">Lead afvoeren</h3>
            </x-slot>

            <x-slot:content>
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Weet je zeker dat je deze lead wilt afvoeren? Dit zal de lead op status "Verloren" zetten en
                        alle opstaande activiteiten afronden.
                    </p>
                </div>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.control
                        type="date"
                        name="closed_at"
                        v-model="leadAfvoerenData.closed_at"
                    />

                    <select
                        name="lost_reason"
                        class="!w-full min-h-[38px] border border-gray-300 dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900 text-sm"
                        v-model="leadAfvoerenData.lost_reason"
                        required
                    >
                        <option value="">Selecteer reden...</option>
                        @foreach(LostReason::cases() as $reason)
                            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                        @endforeach
                    </select>
                    <x-admin::form.control-group.label>Reden van verlies</x-admin::form.control-group.label>
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>Gesloten op</x-admin::form.control-group.label>
                </x-admin::form.control-group>
            </x-slot>

            <x-slot:footer>
                <button type="button" class="secondary-button" @click="$refs.leadAfvoerenModal.close()">
                    Annuleren
                </button>
                <button
                    type="button"
                    class="primary-button"
                    @click="submitLeadAfvoeren"
                    :disabled="!leadAfvoerenData.lost_reason || isSubmitting"
                >
                    <span v-if="isSubmitting">Bezig...</span>
                    <span v-else>Lead afvoeren</span>
                </button>
            </x-slot>
        </x-admin::modal>
    @endif

    @pushOnce('scripts')
        <script>
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
                    const responseData = await response.json();
                    const currentUserId = {{ auth()->guard('user')->id() ?? 'null' }};
                    const userSelect = document.getElementById('user_id_select');
                    if (userSelect) {
                        userSelect.value = currentUserId;
                        userSelect.disabled = false;
                    }
                    const hiddenInput = document.getElementById('user_id_hidden');
                    if (hiddenInput) hiddenInput.remove();
                    const takeoverButton = document.querySelector('button[onclick*="takeoverActivity"]');
                    if (takeoverButton) takeoverButton.style.display = 'none';
                    if (responseData.message) alert(responseData.message);
                } catch (error) {
                    alert(error.message);
                }
            };

            document.addEventListener('DOMContentLoaded', function() {
                const userSelect = document.querySelector('select[name="user_id"]');
                const takeoverButton = document.querySelector('button[onclick*="takeoverActivity"]');
                const currentUserId = {{ auth()->guard('user')->id() ?? 'null' }};
                const canTakeover = {{ $canTakeover ? 'true' : 'false' }};

                if (userSelect) {
                    userSelect.addEventListener('change', function() {
                        const selectedUserId = parseInt(this.value);
                        if (takeoverButton) {
                            takeoverButton.style.display = (selectedUserId && selectedUserId !== currentUserId && canTakeover)
                                ? 'inline-block'
                                : 'none';
                        }
                    });
                }
            });

            document.addEventListener('DOMContentLoaded', function() {
                if (typeof app !== 'undefined') {
                    app.mixin({
                        mounted() {
                            this.$emitter.on('on-activity-added', () => { window.location.reload(); });
                        },
                        data() {
                            return {
                                leadAfvoerenData: {
                                    lost_reason: '',
                                    closed_at: new Date().toISOString().split('T')[0]
                                },
                                isSubmitting: false
                            };
                        },
                        methods: {
                            openLeadAfvoerenModal() {
                                this.leadAfvoerenData = {
                                    lost_reason: '',
                                    closed_at: new Date().toISOString().split('T')[0]
                                };
                                this.$refs.leadAfvoerenModal.open();
                            },
                            submitLeadAfvoeren() {
                                if (!this.leadAfvoerenData.lost_reason.trim()) {
                                    this.$emitter.emit('add-flash', { type: 'error', message: 'Reden van verlies is verplicht' });
                                    return;
                                }
                                this.isSubmitting = true;
                                const leadId = {{ (int) ($activity->lead_id ?? 0) }};
                                if (!leadId) {
                                    this.isSubmitting = false;
                                    this.$emitter.emit('add-flash', { type: 'error', message: 'Geen gekoppelde lead om af te voeren.' });
                                    return;
                                }
                                const lostUrl = "{{ route('admin.leads.lost', 'REPLACE_ID') }}".replace('REPLACE_ID', String(leadId));
                                this.$axios.put(lostUrl, {
                                    lost_reason: this.leadAfvoerenData.lost_reason,
                                    closed_at: this.leadAfvoerenData.closed_at,
                                })
                                    .then(response => {
                                        this.isSubmitting = false;
                                        this.$refs.leadAfvoerenModal.close();
                                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                        const viewUrl = "{{ route('admin.leads.view', 'REPLACE_ID') }}".replace('REPLACE_ID', String(leadId));
                                        window.location.href = viewUrl;
                                    })
                                    .catch(error => {
                                        this.isSubmitting = false;
                                        this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Er is een fout opgetreden' });
                                    });
                            }
                        }
                    });
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
