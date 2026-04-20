@php use App\Enums\LostReason; @endphp
<x-admin::layouts>
    <x-slot:title>
        {{ $salesLead->name }}
    </x-slot>

    <!-- Content -->
    <div class="relative flex flex-col gap-4 pt-3 max-lg:flex-wrap lg:grid"
         :class="isRightColumnCollapsed ? 'lg:grid-cols-[394px,minmax(0,1fr),0px]' : 'lg:grid-cols-[394px,minmax(0,1fr),280px]'">
        <!-- Left Panel -->
        {!! view_render_event('admin.sales.view.left.before', ['sales' => $salesLead]) !!}

        <div
            class="flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 max-lg:min-w-full max-lg:max-w-full lg:sticky lg:top-[73px] [&>div:last-child]:border-b-0">
            <div class="flex flex-1 flex-col">
                <!-- Lead Information -->
                <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                    <!-- Breadcrumb's -->
                    <div class="flex items-center justify-between">
                        <x-admin::breadcrumbs
                            name="sales-leads.view"
                            :entity="$salesLead"
                        />
                    </div>

                    <x-adminc::sales_leads.card :sales="$salesLead" show_actions="false" />

                    <div class="mb-2">
                        @if (($days = $salesLead->rotten_days) > 0)
                            @php
                                $salesLead->tags->prepend([
                                    'name' =>
                                        '<span class="icon-rotten text-base"></span>' .
                                        trans('admin::app.leads.view.rotten-days', ['days' => $days]),
                                    'color' => '#FEE2E2'
                                ]);
                            @endphp
                        @endif
                    </div>

                    {!! view_render_event('admin.sales.view.title.before', ['sales' => $salesLead]) !!}

                    {!! view_render_event('admin.sales.view.title.after', ['sales' => $salesLead]) !!}

                    <!-- No Open Activities Warning (shown directly below duplicate block) -->
                    @php
                        $isWonOrLost = ($salesLead->stage->is_won ?? false) || ($salesLead->stage->is_lost ?? false);
                    @endphp
                    @if (
                        ($salesLead->open_activities_count ?? ($salesLead->openActivitiesCount ?? $salesLead->open_activities_count)) === 0 &&
                            !$isWonOrLost
                    )
                        <div
                            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                            <div class="flex items-center gap-2">
                                <span class="icon-warning text-status-expired-text"></span>
                                <span class="text-sm font-medium text-red-800 dark:text-red-200">
                                    Geen open activiteiten voor deze sales
                                </span>
                            </div>
                        </div>
                    @endif

                    <!-- Activity Actions -->
                    <div class="flex flex-wrap gap-2">
                        {!! view_render_event('admin.leads.view.actions.before', ['lead' => $lead]) !!}

                        @if (bouncer()->hasPermission('mail.create'))
                            <!-- Mail Activity Action -->
                            <x-admin::activities.actions.mail
                                :entity="$salesLead"
                                entity-control-name="sales_lead_id"
                                :emails="$emails"
                            />
                        @endif

                        @if (bouncer()->hasPermission('activities.create'))
                            <!-- File Activity Action -->
                            <x-admin::activities.actions.file
                                :entity="$salesLead"
                                entity-control-name="sales_lead_id"
                            />

                            <!-- Note Activity Action -->
                            <x-admin::activities.actions.note
                                :entity="$salesLead"
                                entity-control-name="sales_lead_id"
                            />

                            <!-- Activity Action -->
                            <x-admin::activities.actions.activity
                                :entity="$salesLead"
                                entity-control-name="sales_lead_id"
                            />
                        @endif

                        {!! view_render_event('admin.sales.view.actions.after', ['sales' => $salesLead]) !!}
                    </div>
                </div>
            </div>

            <x-adminc::components.entity-navigation-menu :activitiesCount="$activitiesCount" show-orders="true"/>

            <!-- Footer with creation and modification dates -->
            <div
                class="flex w-full flex-col gap-2 border-t border-gray-200 p-4 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">

                <div class="flex justify-between">
                    <span>Toegewezen aan:</span>
                    <span>{{ $salesLead->user?->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Aangemaakt:</span>
                    <span>{{ $salesLead->created_at->format('d-m-Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Laatst gewijzigd:</span>
                    <span>{{ $salesLead->updated_at->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        {!! view_render_event('admin.sales.view.left.after', ['sales' => $salesLead]) !!}

        {!! view_render_event('admin.sales.view.right.before', ['sales' => $salesLead]) !!}

        <!-- Middle Panel -->
        <div class="flex w-full flex-col gap-4">

            <div v-if="leadDetailSection === 'algemeen'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::sales.view.tab-general', ['sales' => $salesLead])
            </div>

            <div v-else-if="leadDetailSection === 'activiteiten'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::activities.partials.tab-activities', ['entityId' => $salesLead->id, 'entityType' => 'sales-leads'])
            </div>

            <div v-else-if="leadDetailSection === 'anamnese'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::leads.view.anamnese', ['anamneses' => $salesLead->anamnesis, 'persons' => $salesLead->persons])
            </div>

            <div v-else-if="leadDetailSection === 'marketing'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::leads.view.marketing', ['lead' => $lead])
            </div>

            <div v-else-if="leadDetailSection === 'orders'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('adminc.sales_leads.view.orders', ['salesLead' => $salesLead])
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
        <script type="text/x-template" id="v-sales-delete-template">
            <button
                type="button"
                class="secondary-button border border-red-100 text-status-expired-text hover:border-error hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950 flex items-center gap-1"
                :class="{ 'opacity-50 pointer-events-none': isDeleting }"
                :disabled="isDeleting"
                @click="confirmDelete"
            >
                <span class="icon-delete text-base"></span>

                <span>@lang('admin::app.sales.view.delete-btn')</span>
            </button>
        </script>

        <script type="module">
            app.component('v-sales-delete', {
                template: '#v-sales-delete-template',

                props: {
                    deleteUrl: {
                        type: String,
                        required: true,
                    },
                    redirectUrl: {
                        type: String,
                        required: true,
                    },
                    salesName: {
                        type: String,
                        default: '',
                    },
                },

                data() {
                    return {
                        isDeleting: false,

                        translations: {
                            title: @json(__('admin::app.sales.view.delete-confirm.title')),
                            messageTemplate: @json(__('admin::app.sales.view.delete-confirm.message')),
                            confirm: @json(__('admin::app.sales.view.delete-confirm.confirm')),
                            cancel: @json(__('admin::app.sales.view.delete-confirm.cancel')),
                            failed: @json(__('admin::app.sales.view.delete-failed')),
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
                            message: this.translations.messageTemplate.replace(':name', this.salesName ? this
                                .salesName : ''),
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
                                const validSections = ['algemeen', 'activiteiten', 'anamnese', 'marketing', 'orders'];

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
