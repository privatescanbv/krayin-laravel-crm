@php use Webkul\Admin\Http\Controllers\clinic\ActivityController; @endphp
<x-admin::layouts>
    <x-slot:title>
        {{ $clinic->name }}
    </x-slot>

    <!-- Content -->
    <div class="relative flex flex-col gap-4 pt-3 max-lg:flex-wrap lg:grid"
         :class="isRightColumnCollapsed ? 'lg:grid-cols-[394px,minmax(0,1fr),0px]' : 'lg:grid-cols-[394px,minmax(0,1fr),280px]'">
        <!-- Left Panel -->
        {!! view_render_event('admin.clinics.view.left.before', ['clinic' => $clinic]) !!}

        <div
            class="flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 max-lg:min-w-full max-lg:max-w-full lg:sticky lg:top-[73px] [&>div:last-child]:border-b-0">
            <div class="flex flex-1 flex-col">
                <!-- clinic Information -->
                <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                    <!-- Breadcrumb's -->
                    <div class="flex items-center justify-between">
                        <x-admin::breadcrumbs name="settings.clinics.view" :entity="$clinic"/>
                    </div>

                    <!-- clinic Person info's -->
                    <x-adminc::clinics.card :clinic="$clinic" show_actions="false"/>

                    <!-- Activity Actions -->
                    <div class="flex flex-wrap gap-2">
                        {!! view_render_event('admin.clinics.view.actions.before', ['clinic' => $clinic]) !!}

                        @if (bouncer()->hasPermission('mail.create'))
                            <!-- Mail Activity Action -->
                            <x-admin::activities.actions.mail :entity="$clinic" entity-control-name="clinic_id"/>
                        @endif

                        @if (bouncer()->hasPermission('activities.create'))
                            <!-- File Activity Action -->
                            <x-admin::activities.actions.file :entity="$clinic" entity-control-name="clinic_id" :show-publish-to-portal="false"/>

                            <!-- Note Activity Action -->
                            <x-admin::activities.actions.note :entity="$clinic" entity-control-name="clinic_id"/>

                            <!-- Activity Action -->
                            <x-admin::activities.actions.activity :entity="$clinic" entity-control-name="clinic_id"/>
                        @endif

                        {!! view_render_event('admin.clinics.view.actions.after', ['clinic' => $clinic]) !!}
                    </div>
                </div>
            </div>

            <x-adminc::components.entity-navigation-menu
                :activitiesCount="$activitiesCount"
                :showOrders="false"
                :showAnamnesis="false"
                :showMarketing="false"
                :showPartnerProducts="true"
                :showResources="true"
                :showAfbDispatches="true"
                :showDepartments="true"
            />

            <!-- Footer with creation and modification dates -->
            <div
                class="flex w-full flex-col gap-2 border-t border-gray-200 p-4 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <!-- Suite CRM link -->
                @if (!empty($clinic->sugar_link))
                    <div class="flex justify-between">
                        <span>Sugar Link:</span>
                        <span>
                            <a href="{{ $clinic->sugar_link }}" target="_blank">{{ $clinic->external_id }}</a>
                        </span>
                    </div>
                @endif

                <div class="flex justify-between">
                    <span>Aangemaakt:</span>
                    <span>{{ $clinic->created_at->format('d-m-Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Laatst gewijzigd:</span>
                    <span>{{ $clinic->updated_at->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        {!! view_render_event('admin.clinics.view.left.after', ['clinic' => $clinic]) !!}

        {!! view_render_event('admin.clinics.view.right.before', ['clinic' => $clinic]) !!}

        <!-- Middle Panel -->
        <div class="flex w-full flex-col gap-4">

            <div v-if="leadDetailSection === 'algemeen'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('adminc::clinics.partials.tab-general', ['clinic' => $clinic])
            </div>

            <div v-else-if="leadDetailSection === 'activiteiten'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::activities.partials.tab-activities', ['entityId' => $clinic->id, 'entityType' => 'clinics'])
            </div>

            <div v-else-if="leadDetailSection === 'partner-products'" class="flex w-full flex-col gap-4 rounded-lg">
                <x-adminc::clinics.partials.partner-products :clinic="$clinic"/>
            </div>

            <div v-else-if="leadDetailSection === 'resources'" class="flex w-full flex-col gap-4 rounded-lg">
                <x-adminc::clinics.partials.resources :clinic="$clinic"/>
            </div>

            <div v-else-if="leadDetailSection === 'afb-verzendingen'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('adminc::clinics.partials.tab-afb-dispatches', ['clinic' => $clinic])
            </div>

            <div v-else-if="leadDetailSection === 'afdelingen'" class="flex w-full flex-col gap-4 rounded-lg">
                <x-adminc::clinics.partials.departments :clinic="$clinic"/>
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
                @include('admin::leads.view.right_panel', ['clinic' => $clinic])
            </div>
        </div>

        {!! view_render_event('admin.clinics.view.right.after', ['clinic' => $clinic]) !!}
    </div>

    @pushOnce('scripts', 'clinic-view-delete-action')
        <script type="text/x-template" id="v-clinic-delete-template">
            <button
                type="button"
                class="secondary-button border border-red-100 text-status-expired-text hover:border-error hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950 flex items-center gap-1"
                :class="{ 'opacity-50 pointer-events-none': isDeleting }"
                :disabled="isDeleting"
                @click="confirmDelete"
            >
                <span class="icon-delete text-base"></span>

                <span>@lang('admin::app.clinics.view.delete-btn')</span>
            </button>
        </script>

        <script type="module">
            app.component('v-clinic-delete', {
                template: '#v-clinic-delete-template',

                props: {
                    deleteUrl: {
                        type: String,
                        required: true,
                    },
                    redirectUrl: {
                        type: String,
                        required: true,
                    },
                    clinicName: {
                        type: String,
                        default: '',
                    },
                },

                data() {
                    return {
                        isDeleting: false,

                        translations: {
                            title: @json(__('admin::app.clinics.view.delete-confirm.title')),
                            messageTemplate: @json(__('admin::app.clinics.view.delete-confirm.message')),
                            confirm: @json(__('admin::app.clinics.view.delete-confirm.confirm')),
                            cancel: @json(__('admin::app.clinics.view.delete-confirm.cancel')),
                            failed: @json(__('admin::app.clinics.view.delete-failed')),
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
                            message: this.translations.messageTemplate.replace(':name', this.clinicName ? this
                                .clinicName : ''),
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
                                const validSections = ['algemeen', 'activiteiten', 'partner-products', 'resources', 'afb-verzendingen', 'afdelingen'];

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
