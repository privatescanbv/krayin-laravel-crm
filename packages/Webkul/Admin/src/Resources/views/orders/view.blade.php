@props([
    'order'
])
@php use Webkul\Admin\Http\Controllers\order\ActivityController; @endphp
<x-admin::layouts>
    <x-slot:title>
        {{ $order->name }}
    </x-slot>

    <!-- Content -->
    <div class="relative flex flex-col gap-4 pt-3 max-lg:flex-wrap lg:grid"
         :class="isRightColumnCollapsed ? 'lg:grid-cols-[394px,minmax(0,1fr),0px]' : 'lg:grid-cols-[394px,minmax(0,1fr),280px]'">
        <!-- Left Panel -->
        {!! view_render_event('admin.orders.view.left.before', ['order' => $order]) !!}

        <div
            class="flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 max-lg:min-w-full max-lg:max-w-full lg:sticky lg:top-[73px] [&>div:last-child]:border-b-0">
            <div class="flex flex-1 flex-col">
                <!-- order Information -->
                <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                    <!-- Breadcrumb's -->
                    <div class="flex items-center justify-between">
                        <x-admin::breadcrumbs name="orders.view" :entity="$order"/>
                    </div>


                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                            {{ $order->title }}
                        </h1>
                        @if(!empty($order->order_number))
                            <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                #{{ $order->order_number }}
                            </span>
                        @endif
                        @if($order->status)
                            <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full {{ $order->status->getStatusClass() }}">
                                {{ $order->status->label() }}
                            </span>
                        @endif
                    </div>
                    <!-- Order Details -->
                    <div class="mt-2 flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-400">
                        <div class="flex items-center gap-2">
                            <span class="icon-lead text-lg"></span>
                            <a href="{{ route('admin.sales-leads.view', $order->salesLead->id) }}" class="hover:text-brandColor hover:underline">
                                {{ $order->salesLead->name }} - <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full">
                                {{ $order->salesLead->stage->name }}
                                </span>
                            </a>
                        </div>
                        @if($order->first_examination_at)
                            <div class="flex items-center gap-2">
                                <span class="font-medium">Eerste onderzoek:</span>
                                <span>{{ $order->first_examination_at->format('d-m-Y H:i') }}</span>
                            </div>
                        @endif
                    </div>


                    {!! view_render_event('admin.orders.view.title.before', ['order' => $order]) !!}

                    {!! view_render_event('admin.orders.view.title.after', ['order' => $order]) !!}

                    <!-- Activity Actions -->
                    <div class="flex flex-wrap gap-2">
                        {!! view_render_event('admin.orders.view.actions.before', ['order' => $order]) !!}


                        @if (bouncer()->hasPermission('activities.create'))
                            <!-- File Activity Action -->
                            <x-admin::activities.actions.file :entity="$order" entity-control-name="order_id"/>

                            <!-- Note Activity Action -->
                            <x-admin::activities.actions.note :entity="$order" entity-control-name="order_id"/>

                            <!-- Activity Action -->
                            <x-admin::activities.actions.activity :entity="$order" entity-control-name="order_id"/>
                        @endif

                        {!! view_render_event('admin.orders.view.actions.after', ['order' => $order]) !!}
                    </div>
                </div>
            </div>

            <x-adminc::components.entity-navigation-menu
                :activitiesCount="$activitiesCount"
                :showOrders="false"
                :showAnamnesis="false"
                :showMarketing="false"
            />

            <!-- Footer with creation and modification dates -->
            <div
                class="flex w-full flex-col gap-2 border-t border-gray-200 p-4 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">

                <div class="flex justify-between">
                    <span>Toegewezen aan:</span>
                    <span>{{ $order->user?->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Aangemaakt:</span>
                    <span>{{ $order->created_at->format('d-m-Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Laatst gewijzigd:</span>
                    <span>{{ $order->updated_at->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        {!! view_render_event('admin.orders.view.left.after', ['order' => $order]) !!}

        {!! view_render_event('admin.orders.view.right.before', ['order' => $order]) !!}

        <!-- Middle Panel -->
        <div class="flex w-full flex-col gap-4">
            <div v-if="leadDetailSection === 'algemeen'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::orders.view.tab-general', ['order' => $order])
            </div>

            <div v-else-if="leadDetailSection === 'activiteiten'" class="flex w-full flex-col gap-4 rounded-lg">
                @include('admin::activities.partials.tab-activities', ['entityId' => $order->id, 'entityType' => 'orders'])
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
                @include('admin::orders.view.right_panel', ['order' => $order])
            </div>
        </div>

        {!! view_render_event('admin.orders.view.right.after', ['order' => $order]) !!}
    </div>

    @pushOnce('scripts', 'order-view-delete-action')
        <script type="text/x-template" id="v-order-delete-template">
            <button
                type="button"
                class="secondary-button border border-red-100 text-status-expired-text hover:border-error hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950 flex items-center gap-1"
                :class="{ 'opacity-50 pointer-events-none': isDeleting }"
                :disabled="isDeleting"
                @click="confirmDelete"
            >
                <span class="icon-delete text-base"></span>

                <span>@lang('admin::app.orders.view.delete-btn')</span>
            </button>
        </script>

        <script type="module">
            app.component('v-order-delete', {
                template: '#v-order-delete-template',

                props: {
                    deleteUrl: {
                        type: String,
                        required: true,
                    },
                    redirectUrl: {
                        type: String,
                        required: true,
                    },
                    orderName: {
                        type: String,
                        default: '',
                    },
                },

                data() {
                    return {
                        isDeleting: false,

                        translations: {
                            title: @json(__('admin::app.orders.view.delete-confirm.title')),
                            messageTemplate: @json(__('admin::app.orders.view.delete-confirm.message')),
                            confirm: @json(__('admin::app.orders.view.delete-confirm.confirm')),
                            cancel: @json(__('admin::app.orders.view.delete-confirm.cancel')),
                            failed: @json(__('admin::app.orders.view.delete-failed')),
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
                            message: this.translations.messageTemplate.replace(':name', this.orderName ? this
                                .orderName : ''),
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
                                const validSections = ['algemeen', 'activiteiten'];

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
