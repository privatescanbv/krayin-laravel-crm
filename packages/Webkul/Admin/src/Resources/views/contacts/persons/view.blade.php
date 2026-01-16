@php use Webkul\Admin\Http\Controllers\person\ActivityController; @endphp
<x-admin::layouts>
    <x-slot:title>
        {{ $person->name }}
    </x-slot>

    <!-- Content -->
    <div class="relative flex flex-col gap-4 pt-3 max-lg:flex-wrap lg:grid"
         :class="isRightColumnCollapsed ? 'lg:grid-cols-[394px,minmax(0,1fr),0px]' : 'lg:grid-cols-[394px,minmax(0,1fr),280px]'">
        <!-- Left Panel -->
        {!! view_render_event('admin.persons.view.left.before', ['person' => $person]) !!}

        <div
            class="flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 max-lg:min-w-full max-lg:max-w-full lg:sticky lg:top-[73px] [&>div:last-child]:border-b-0">
            <div class="flex flex-1 flex-col">
                <!-- person Information -->
                <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                    <!-- Breadcrumb's -->
                    <div class="flex items-center justify-between">
                        <x-admin::breadcrumbs name="contacts.persons.view" :entity="$person"/>
                    </div>

                    <!-- person Person info's -->
                    <x-adminc::persons.card :person="$person" show_actions="false"/>

                    <!-- Activity Actions -->
{{--                     Disable all create activities for now, maybe we remove the complete activies for persons in the future. Only patient message for now--}}
                    <div class="flex flex-wrap gap-2">
                        {!! view_render_event('admin.persons.view.actions.before', ['person' => $person]) !!}

{{--                        @if (bouncer()->hasPermission('mail.compose'))--}}
{{--                            <!-- Mail Activity Action -->--}}
{{--                            <x-admin::activities.actions.mail :entity="$person" entity-control-name="person_id"/>--}}
{{--                        @endif--}}

{{--                        @if (bouncer()->hasPermission('activities.create'))--}}
{{--                            <!-- File Activity Action -->--}}
{{--                            <x-admin::activities.actions.file :entity="$person" entity-control-name="person_id"/>--}}

{{--                            <!-- Note Activity Action -->--}}
{{--                            <x-admin::activities.actions.note :entity="$person" entity-control-name="person_id"/>--}}

{{--                            <!-- Activity Action -->--}}
{{--                            <x-admin::activities.actions.activity :entity="$person" entity-control-name="person_id"/>--}}
{{--                        @endif--}}

                        {!! view_render_event('admin.persons.view.actions.after', ['person' => $person]) !!}
                        @if (bouncer()->hasPermission('activities.create'))
                            <x-admin::activities.actions.activity :entity="$person" entity-control-name="person_id" :allowed-types="[App\Enums\ActivityType::PATIENT_MESSAGE]"/>
                        @endif
                    </div>


                </div>
            </div>

            <x-adminc::components.entity-navigation-menu
                :activitiesCount="$activitiesCount"
                :showOrders="false"
                :showAnamnesis="true"
                :showMarketing="false"
                :showPartnerProducts="false"
                :showResources="false"
                :showLeads="true"
                :showSales="false"
            />

            <!-- Footer with creation and modification dates -->
            <div
                class="flex w-full flex-col gap-2 border-t border-gray-200 p-4 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <!-- Suite CRM link -->
                @if (!empty($person->sugar_link))
                    <div class="flex justify-between">
                        <span>Sugar Link:</span>
                        <span>
                            <a href="{{ $person->sugar_link }}" target="_blank">{{ $person->external_id }}</a>
                        </span>
                    </div>
                @endif

                <div class="flex justify-between">
                    <span>Aangemaakt:</span>
                    <span>{{ $person->created_at->format('d-m-Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Laatst gewijzigd:</span>
                    <span>{{ $person->updated_at->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        {!! view_render_event('admin.persons.view.left.after', ['person' => $person]) !!}

        {!! view_render_event('admin.persons.view.right.before', ['person' => $person]) !!}

        <!-- Middle Panel -->
        <div class="flex w-full flex-col gap-4">

            <div v-if="leadDetailSection === 'algemeen'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('adminc::persons.partials.tab-general', ['person' => $person])
            </div>

            <div v-else-if="leadDetailSection === 'activiteiten'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::activities.partials.tab-activities', ['entityId' => $person->id, 'entityType' => 'contacts.persons'])
            </div>
            <div v-else-if="leadDetailSection === 'anamnese'" class="flex w-full flex-col gap-4 rounded-lg">
                <x-adminc::anamnesis.index :anamnesis="$person->anamnesis"/>
            </div>
            <div v-else-if="leadDetailSection === 'leads'" class="flex w-full flex-col gap-4 rounded-lg">
                <x-admin::leads :person="$person"/>
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
                @include('admin::leads.view.right_panel', ['person' => $person])
            </div>
        </div>

        {!! view_render_event('admin.persons.view.right.after', ['person' => $person]) !!}
    </div>

    @pushOnce('scripts', 'person-view-delete-action')
        <script type="text/x-template" id="v-person-delete-template">
            <button
                type="button"
                class="secondary-button border border-red-100 text-status-expired-text hover:border-error hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950 flex items-center gap-1"
                :class="{ 'opacity-50 pointer-events-none': isDeleting }"
                :disabled="isDeleting"
                @click="confirmDelete"
            >
                <span class="icon-delete text-base"></span>

                <span>@lang('admin::app.persons.view.delete-btn')</span>
            </button>
        </script>

        <script type="module">
            app.component('v-person-delete', {
                template: '#v-person-delete-template',

                props: {
                    deleteUrl: {
                        type: String,
                        required: true,
                    },
                    redirectUrl: {
                        type: String,
                        required: true,
                    },
                    personName: {
                        type: String,
                        default: '',
                    },
                },

                data() {
                    return {
                        isDeleting: false,

                        translations: {
                            title: @json(__('admin::app.persons.view.delete-confirm.title')),
                            messageTemplate: @json(__('admin::app.persons.view.delete-confirm.message')),
                            confirm: @json(__('admin::app.persons.view.delete-confirm.confirm')),
                            cancel: @json(__('admin::app.persons.view.delete-confirm.cancel')),
                            failed: @json(__('admin::app.persons.view.delete-failed')),
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
                            message: this.translations.messageTemplate.replace(':name', this.personName ? this
                                .personName : ''),
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
                                const validSections = ['algemeen', 'activiteiten', 'anamnese', 'leads', 'partner-products', 'resources'];

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
