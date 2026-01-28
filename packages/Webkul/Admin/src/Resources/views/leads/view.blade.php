@php use Webkul\Admin\Http\Controllers\Lead\ActivityController; @endphp
<x-admin::layouts>
    <x-slot:title>
        {{ $lead->name }}
    </x-slot>

    <!-- Content -->
    <div class="relative flex flex-col gap-4 pt-3 max-lg:flex-wrap lg:grid"
         :class="isRightColumnCollapsed ? 'lg:grid-cols-[394px,minmax(0,1fr),0px]' : 'lg:grid-cols-[394px,minmax(0,1fr),280px]'">
        <!-- Left Panel -->
        {!! view_render_event('admin.leads.view.left.before', ['lead' => $lead]) !!}

        <div
            class="flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 max-lg:min-w-full max-lg:max-w-full lg:sticky lg:top-[73px] [&>div:last-child]:border-b-0">
            <div class="flex flex-1 flex-col">
                <!-- Lead Information -->
                <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                    <!-- Breadcrumb's -->
                    <div class="flex items-center justify-between">
                        <x-admin::breadcrumbs name="leads.view" :entity="$lead"/>
                    </div>

                    <!-- Lead Person info's -->
                    <x-adminc::leads.card :lead="$lead" show_actions="false"/>

                    <div class="mb-2">
                        @if (($days = $lead->rotten_days) > 0)
                            @php
                                $lead->tags->prepend([
                                    'name' =>
                                        '<span class="icon-rotten text-base"></span>' .
                                        trans('admin::app.leads.view.rotten-days', ['days' => $days]),
                                    'color' => '#FEE2E2'
                                ]);
                            @endphp
                        @endif
                    </div>

                    {!! view_render_event('admin.leads.view.title.before', ['lead' => $lead]) !!}

                    {!! view_render_event('admin.leads.view.title.after', ['lead' => $lead]) !!}

                    <!-- Duplicate Detection -->
                    @if ($lead->hasPotentialDuplicates())
                        <div
                            class="mb-4 rounded-lg border border-orange-200 bg-orange-50 p-3 dark:border-orange-800 dark:bg-orange-900/20">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="icon-warning text-orange-600"></span>
                                    <span class="text-sm font-medium text-activity-note-text dark:text-orange-200">
                                        Potentiële duplicaten gevonden ({{ $lead->getPotentialDuplicatesCount() }}
                                        leads{{ $lead->getPotentialDuplicatesCount() > 1 ? 's' : '' }})
                                    </span>
                                </div>
                                <a href="{{ route('admin.leads.duplicates.index', $lead->id) }}"
                                   class="rounded bg-orange-600 px-3 py-1 text-xs text-white hover:bg-orange-700">
                                    Duplicaten samenvoegen
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- No Open Activities Warning (shown directly below duplicate block) -->
                    @php
                        $isWonOrLost = ($lead->stage->is_won ?? false) || ($lead->stage->is_lost ?? false);
                    @endphp
                    @if (
                        ($lead->open_activities_count ?? ($lead->openActivitiesCount ?? $lead->open_activities_count)) === 0 &&
                            !$isWonOrLost
                    )
                        <div
                            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                            <div class="flex items-center gap-2">
                                <span class="icon-warning text-status-expired-text"></span>
                                <span class="text-sm font-medium text-red-800 dark:text-red-200">
                                    Geen open activiteiten voor deze lead
                                </span>
                            </div>
                        </div>
                    @endif

                    <!-- Activity Actions -->
                    <div class="flex flex-wrap gap-2">
                        {!! view_render_event('admin.leads.view.actions.before', ['lead' => $lead]) !!}

                        @if (bouncer()->hasPermission('mail.compose'))
                            <!-- Mail Activity Action -->
                            <x-admin::activities.actions.mail :entity="$lead" entity-control-name="lead_id"/>
                        @endif

                        @if (bouncer()->hasPermission('activities.create'))
                            <!-- File Activity Action -->
                            <x-admin::activities.actions.file :entity="$lead" entity-control-name="lead_id"/>

                            <!-- Note Activity Action -->
                            <x-admin::activities.actions.note :entity="$lead" entity-control-name="lead_id"/>

                            <!-- Activity Action -->
                            <x-admin::activities.actions.activity :entity="$lead" entity-control-name="lead_id"/>
                        @endif

                        {!! view_render_event('admin.leads.view.actions.after', ['lead' => $lead]) !!}
                    </div>
                </div>
            </div>
            @if($lead->has_diagnosis_form || $lead->mri_status)
            <div class="flex flex-col border-t border-gray-200 dark:border-gray-800">
                <div class="flex items-center gap-3 p-4">
                    <span class="icon-file text-2xl text-gray-400"></span>

                    <div class="flex flex-col">
                        <span class="font-semibold text-gray-800 dark:text-white">Diagnoseformulier</span>

                        <span class="text-xs {{ ($lead->has_diagnosis_form ?? false) ? 'text-green-600' : 'text-red-600' }}">
                            {{ ($lead->has_diagnosis_form ?? false) ? 'Aanwezig' : 'Niet aanwezig' }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-4 pt-0">
                    <span class="icon-file text-2xl text-gray-400"></span>

                    <div class="flex flex-col">
                        <span class="font-semibold text-gray-800 dark:text-white">MRI scans</span>

                        <span class="text-xs {{ ($lead->mri_status?->value ?? 'geen') !== 'geen' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $lead->mri_status?->value ?? 'geen' }}
                        </span>
                    </div>
                </div>
            </div>
            @endif
            <x-adminc::components.entity-navigation-menu :activitiesCount="$activitiesCount"/>

            <!-- Footer with creation and modification dates -->
            <div
                class="flex w-full flex-col gap-2 border-t border-gray-200 p-4 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <!-- Suite CRM link -->
                @if (!empty($lead->sugar_link))
                    <div class="flex justify-between">
                        <span>Sugar Link:</span>
                        <span>
                            <a href="{{ $lead->sugar_link }}" target="_blank">{{ $lead->external_id }}</a>
                        </span>
                    </div>
                @endif

                <div class="flex justify-between">
                    <span>Aangemaakt:</span>
                    <span>{{ $lead->created_at->format('d-m-Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Laatst gewijzigd:</span>
                    <span>{{ $lead->updated_at->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        {!! view_render_event('admin.leads.view.left.after', ['lead' => $lead]) !!}

        {!! view_render_event('admin.leads.view.right.before', ['lead' => $lead]) !!}

        <!-- Middle Panel -->
        <div class="flex w-full flex-col gap-4">

            <div v-if="leadDetailSection === 'algemeen'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::leads.view.tab-general', ['lead' => $lead])
            </div>

            <div v-else-if="leadDetailSection === 'activiteiten'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::activities.partials.tab-activities', ['entityId' => $lead->id])
            </div>

            <div v-else-if="leadDetailSection === 'anamnese'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::leads.view.anamnese', ['anamneses' => $lead->anamnesis, 'persons' => $lead->persons])
            </div>

            <div v-else-if="leadDetailSection === 'marketing'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::leads.view.marketing', ['lead' => $lead])
            </div>
        </div>

        <!-- Right Panel Container -->
        <div class="relative overflow-visible transition-all duration-300 ease-in-out">

            <button type="button"
                    class="absolute top-0 z-50 flex h-8 w-8 -translate-x-full items-center justify-center rounded-l-lg border border-r-0 bg-white text-gray-600 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="isRightColumnCollapsed = !isRightColumnCollapsed"
                    :class="isRightColumnCollapsed ? 'left-4 ' : '-right-12'" title="Toggle rechterkolom">
                <i class="text-xl transition-transform duration-200"
                   :class="isRightColumnCollapsed ? 'icon-left-arrow' : 'icon-right-arrow'"></i>
            </button>

            <div
                class="relative flex min-h-full w-full flex-col gap-4 rounded-lg border text-sm text-gray-500 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                :class="isRightColumnCollapsed ? 'translate-x-full opacity-0 pointer-events-none overflow-hidden' :
                    'translate-x-0 opacity-100'">
                @include('admin::leads.view.right_panel', ['lead' => $lead])
            </div>
        </div>

        {!! view_render_event('admin.leads.view.right.after', ['lead' => $lead]) !!}
    </div>

    @pushOnce('scripts', 'lead-view-delete-action')
        <script type="text/x-template" id="v-lead-delete-template">
            <button
                type="button"
                class="secondary-button border border-red-100 text-status-expired-text hover:border-error hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950 flex items-center gap-1"
                :class="{ 'opacity-50 pointer-events-none': isDeleting }"
                :disabled="isDeleting"
                @click="confirmDelete"
            >
                <span class="icon-delete text-base"></span>

                <span>@lang('admin::app.leads.view.delete-btn')</span>
            </button>
        </script>

        <script type="module">
            app.component('v-lead-delete', {
                template: '#v-lead-delete-template',

                props: {
                    deleteUrl: {
                        type: String,
                        required: true,
                    },
                    redirectUrl: {
                        type: String,
                        required: true,
                    },
                    leadName: {
                        type: String,
                        default: '',
                    },
                },

                data() {
                    return {
                        isDeleting: false,

                        translations: {
                            title: @json(__('admin::app.leads.view.delete-confirm.title')),
                            messageTemplate: @json(__('admin::app.leads.view.delete-confirm.message')),
                            confirm: @json(__('admin::app.leads.view.delete-confirm.confirm')),
                            cancel: @json(__('admin::app.leads.view.delete-confirm.cancel')),
                            failed: @json(__('admin::app.leads.view.delete-failed')),
                        },
                    };
                },

                methods: {
                    confirmDelete() {
                        if (this.isDeleting) {
                            return;
                        }

                        this.$emitter.emit('open-confirm-modal', {
                            title: this.translations.title,
                            message: this.translations.messageTemplate.replace(':name', this.leadName ? this
                                .leadName : ''),
                            options: {
                                btnDisagree: this.translations.cancel,
                                btnAgree: this.translations.confirm,
                            },
                            agree: () => {
                                this.isDeleting = true;

                                this.$axios.delete(this.deleteUrl)
                                    .then((response) => {
                                        this.$emitter.emit('add-flash', {
                                            type: 'success',
                                            message: response.data.message
                                        });

                                        window.location.href = this.redirectUrl;
                                    })
                                    .catch((error) => {
                                        let message = this.translations.failed;

                                        if (error && error.response && error.response.data && error
                                            .response.data.message) {
                                            message = error.response.data.message;
                                        }

                                        this.$emitter.emit('add-flash', {
                                            type: 'error',
                                            message
                                        });
                                    })
                                    .finally(() => {
                                        this.isDeleting = false;
                                    });
                            },
                            disagree: () => {
                                this.isDeleting = false;
                            },
                        });
                    },
                },
            });
        </script>
    @endPushOnce

    @pushOnce('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof app !== 'undefined') {
                    app.mixin({
                        data() {
                            return {
                                leadDetailSection: 'algemeen',
                                isRightColumnCollapsed: true,
                            };
                        },

                        mounted() {
                            if (window.location.hash) {
                                let hash = window.location.hash.substring(1); // Remove '#'

                                // Valid sections
                                const validSections = ['algemeen', 'activiteiten', 'anamnese', 'marketing'];

                                if (validSections.includes(hash)) {
                                    this.leadDetailSection = hash;
                                }
                            }
                        },

                        methods: {
                            setSection(section) {
                                this.leadDetailSection = section;
                                window.location.hash = section;
                            }
                        }
                    });
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
