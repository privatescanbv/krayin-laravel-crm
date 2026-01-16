
@php use App\Enums\ActivityType; @endphp
<x-admin::layouts>
    <x-slot:title>
        {{ $activity->title ?: __('admin::app.activities.view.title') }}
    </x-slot>

    <!-- Header Bar (like edit activity) -->
    <div
        class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex flex-col gap-2">
            <!-- Breadcrumbs -->
            <x-admin::breadcrumbs
                name="activities.view"
                :entity="$activity"
            />

            <!-- Title -->
            <div class="text-xl font-bold dark:text-gray-300 flex items-center gap-2">
                <x-admin::activities.icon :type="$activity->type" />
                <span>{{ $activity->title ?: __('admin::app.activities.edit.title') }}</span>
            </div>

            <!-- Status Bar hidden per requirement -->
        </div>

        <div class="flex items-center gap-x-2.5">

            @if (!$activity->is_done && bouncer()->hasPermission('activities.delete'))
                <form method="POST" action="{{ route('admin.activities.delete', $activity->id) }}"
                      onsubmit="return confirm('Weet je zeker dat je deze activiteit wilt verwijderen?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="secondary-button"
                            title="Verwijderen">
                        <span class="icon-delete text-2xl"></span>
                    </button>
                </form>
            @endif
            @if (!$activity->is_done && bouncer()->hasPermission('activities.edit'))
                <a
                    href="{{ route('admin.activities.edit', $activity->id) }}@if(request('return'))?return={{ urlencode(request('return')) }}@endif"
                    class="secondary-button"
                >
                    <span class="icon-edit text-2xl"></span>
                </a>
            @endif

            @if (!$activity->is_done && $activity->lead && bouncer()->hasPermission('leads.edit'))
                <button
                    type="button"
                    class="secondary-button"
                    @click="openLeadAfvoerenModal"
                >
                    Lead afvoeren
                </button>
            @endif

            @if(!$activity->is_done)
                <button
                    type="submit"
                    form="activity-complete-form"
                    class="secondary-button"
                >
                    Afronden
                </button>
            @endif

        </div>
    </div>

    <!-- Three Column Layout -->
    <div class="relative flex gap-4 max-lg:flex-wrap mt-4">
        <!-- Left Panel (sticky, like lead view) -->
        <div
            class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
            <!-- Actions (same as lead, except file add) executed on related lead via popup -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-800">


                <!-- Activity Relations -->
                   @include('admin::activities.partials.relation_display', ['activity' => $activity])
            </div>

            <!-- Compact details -->
            <div class="p-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-3">
                        <div
                            class="text-xs text-gray-400 dark:text-gray-500 mb-1">@lang('admin::app.activities.edit.type'):
                        </div>
                        <div class="text-sm text-gray-900 dark:text-gray-100">
                            {{ $activity->type?->label() }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-xs text-gray-400 dark:text-gray-500 mb-1">Toegewezen aan</div>
                        <div class="text-sm text-gray-900 dark:text-gray-100">
                            {{ $activity->user?->name ?? '-' }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <div
                            class="text-xs text-gray-400 dark:text-gray-500 mb-1">@lang('admin::app.activities.edit.schedule_from')
                            :
                        </div>
                        <div class="text-sm text-gray-900 dark:text-gray-100">
                            {{ $activity->schedule_from }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <div
                            class="text-xs text-gray-400 dark:text-gray-500 mb-1">@lang('admin::app.activities.edit.schedule_to')
                            :
                        </div>
                        <div class="text-sm text-gray-900 dark:text-gray-100">
                            {{ $activity->schedule_to}}
                        </div>
                    </div>

                    <!-- Suite CRM link -->
                    @if (!empty($activity->sugar_link))
                        <div class="mb-3 pt-[10px]">
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Sugar Link</div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                <a href="{{ $activity->sugar_link }}" target="_blank">{{ $activity->external_id }}</a>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            @if($activity->lead)
                <x-adminc::leads.card :lead="$activity->lead" />
            @endif

            <div class="p-4 text-sm text-gray-700 dark:text-gray-300">
                <!-- Comment section -->
                @if ($activity->comment)
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800">
                        <div class="text-sm">
                            <span class="font-medium">Opmerking:</span>
                        </div>
                        <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                            {!! nl2br(e($activity->comment)) !!}
                        </div>
                    </div>
                @endif
            </div>

            <!-- Footer with creation and modification dates -->
            <div
                class="flex w-full flex-col gap-2 p-4 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-800">
                <div class="flex justify-between">
                    <span>Aangemaakt op:</span>
                    <span>{{ $activity->created_at->format('d-m-Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Bijgewerkt op:</span>
                    <span>{{ $activity->updated_at->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        <!-- Right Panel -->
        @if($activity->type == ActivityType::CALL)
            @include('admin::activities.partials.call')
        @elseif($activity->type == ActivityType::PATIENT_MESSAGE)
            @include('admin::activities.partials.patient-message')
        @endif
    </div>
    @pushOnce('scripts')
        <script>
            (function () {
                var container = document.getElementById('activity-view-actions');
                if (!container) return;
                var shown = false;
                container.addEventListener('click', function (e) {
                    if (shown) return;
                    shown = true;
                    try {
                        alert('Let op: acties worden uitgevoerd op de gekoppelde lead.');
                    } catch (_) {
                    }
                }, {capture: true});
            })();

            // Vue.js component for Lead Afvoeren functionality
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof app !== 'undefined') {
                    app.mixin({
                        data() {
                            return {
                                leadAfvoerenData: {
                                    lost_reason: '',
                                    closed_at: new Date().toISOString().split('T')[0]
                                },
                                isSubmitting: false
                            }
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
                                    this.$emitter.emit('add-flash', {
                                        type: 'error',
                                        message: 'Reden van verlies is verplicht'
                                    });
                                    return;
                                }

                                this.isSubmitting = true;

                                // Submit to the lead lost route (only when a lead is linked)
                                const leadId = {{ (int) ($activity->lead_id ?? 0) }};
                                if (!leadId) {
                                    this.isSubmitting = false;
                                    this.$emitter.emit('add-flash', {
                                        type: 'error',
                                        message: 'Geen gekoppelde lead om af te voeren.'
                                    });
                                    return;
                                }

                                const lostUrl = "{{ route('admin.leads.lost', 'REPLACE_ID') }}".replace('REPLACE_ID', String(leadId));

                                this.$axios.put(lostUrl, {
                                    lost_reason: this.leadAfvoerenData.lost_reason,
                                    closed_at: this.leadAfvoerenData.closed_at
                                })
                                .then(response => {
                                    this.isSubmitting = false;
                                    this.$refs.leadAfvoerenModal.close();

                                    this.$emitter.emit('add-flash', {
                                        type: 'success',
                                        message: response.data.message
                                    });

                                    // Redirect to lead view
                                    const viewUrl = "{{ route('admin.leads.view', 'REPLACE_ID') }}".replace('REPLACE_ID', String(leadId));
                                    window.location.href = viewUrl;
                                })
                                .catch(error => {
                                    this.isSubmitting = false;

                                    this.$emitter.emit('add-flash', {
                                        type: 'error',
                                        message: error.response?.data?.message || 'Er is een fout opgetreden'
                                    });
                                });
                            }
                        }
                    });
                }
            });
        </script>
    @endPushOnce

    <!-- Hidden form used by Afronden button in view -->
    <form id="activity-complete-form" action="{{ route('admin.activities.update', $activity->id) }}" method="POST"
          class="hidden">
        @csrf
        <input type="hidden" name="_method" value="PUT"/>
        <input type="hidden" name="is_done" value="1"/>
        <input type="hidden" name="status" value="done"/>
    </form>

    @if($activity->lead)
    <!-- Lead Afvoeren Modal -->
    <x-admin::modal ref="leadAfvoerenModal">
        <x-slot:header>
            <h3 class="text-base font-semibold dark:text-white">
                Lead afvoeren
            </h3>
        </x-slot>

        <x-slot:content>
            <div class="mb-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Weet je zeker dat je deze lead wilt afvoeren? Dit zal de lead op status "Verloren" zetten en alle opstaande activiteiten afronden.
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
                    @foreach(\App\Enums\LostReason::cases() as $reason)
                        <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                    @endforeach
                </select>
                <x-admin::form.control-group.label>
                    Reden van verlies
                </x-admin::form.control-group.label>

            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    Gesloten op
                </x-admin::form.control-group.label>

            </x-admin::form.control-group>
        </x-slot>

        <x-slot:footer>
            <button
                type="button"
                class="secondary-button"
                @click="$refs.leadAfvoerenModal.close()"
            >
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
</x-admin::layouts>

