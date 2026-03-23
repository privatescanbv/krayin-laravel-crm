@php
    use App\Enums\LostReason;
    use Webkul\User\Models\User;
@endphp
@props([
    'overridePipeline',
    'overrideStage',
    'overrideUpdateUrl',
    'salesLead' => null,
    'order' => null
])
@php
    $isSalesLead = isset($salesLead);
    $isOrder = isset($order);
    $leadOrNull = $lead ?? null;

    // Allow override of pipeline and current stage for sales leads / orders
    $displayPipeline = $overridePipeline ?? $leadOrNull?->pipeline;
    $displayStage = $overrideStage ?? $leadOrNull?->stage;
    $updateUrl = $overrideUpdateUrl ?? ($leadOrNull ? route('admin.leads.stage.update', $leadOrNull->id) : '#');

    // Users for assignment dropdown (won stage)
    $assignableUsers = User::where('status', 1)->orderBy('first_name')->orderBy('last_name')->get();
    $currentUserId = $isOrder ? ($order->user_id ?? null) : ($leadOrNull?->user_id ?? null);
@endphp

    <!-- Stages Navigation -->

<!-- Stages Vue Component -->
<v-lead-stages>
    <x-admin::shimmer.leads.view.stages :count="$displayPipeline->stages->count() - 1"/>
</v-lead-stages>

@include('admin::leads.partials.open_activities_confirm_helper')

@pushOnce('scripts')
    <script type="text/x-template" id="v-lead-stages-template">
        <!-- Stages Container -->
        <div
            class="flex w-full max-w-full flex-wrap gap-y-1"
            :class="{'opacity-50 pointer-events-none': isUpdating}"
        >
            <!-- Stages Item -->
            <template v-for="stage in stages">
                <div
                    class="stage relative flex h-7 cursor-pointer items-center justify-center bg-white pl-7 pr-4 dark:bg-gray-900 ltr:first:rounded-l-lg rtl:first:rounded-r-lg"
                    :class="{
                        '!bg-brandColor text-white dark:text-gray-900 ltr:after:bg-brandColor rtl:before:bg-brandColor': currentStage.sort_order >= stage.sort_order,
                        '!bg-red-500 text-white dark:text-gray-900 ltr:after:bg-red-500 rtl:before:bg-red-500': isLostStage(currentStage),
                    }"
                    v-if="!isWonOrLost(stage)"
                    @click="update(stage)"
                >
                    <span class="z-20 whitespace-nowrap text-sm font-medium dark:text-white">
                        @{{ stage.name }}
                    </span>
                </div>
            </template>

            <!-- Won/Lost Stage Item -->
            <x-admin::dropdown position="bottom-right">
                <x-slot:toggle>
                    <div
                        class="relative flex h-7 min-w-24 cursor-pointer items-center justify-center rounded-r-lg bg-white pl-7 pr-4 dark:bg-gray-900"
                        :class="{
                            '!bg-succes text-white dark:text-gray-900 after:bg-succes': isWonStage(currentStage),
                            '!bg-red-500 text-white dark:text-gray-900 after:bg-red-500': isLostStage(currentStage),
                        }"
                        @click="stageToggler = ! stageToggler"
                    >
                        <span class="z-20 whitespace-nowrap text-sm font-medium dark:text-white">
                            {{ __('admin::app.leads.view.stages.won-lost') }}
                        </span>

                        <span
                            class="text-2xl dark:text-gray-900"
                            :class="{'icon-up-arrow': stageToggler, 'icon-down-arrow': ! stageToggler}"
                        ></span>
                    </div>

                    </x-slot>

                    <x-slot:menu>
                        <x-admin::dropdown.menu.item
                            @click="openModal(this.stages.find(stage => isWonStage(stage)))"
                        >
                            @lang('admin::app.leads.view.stages.won')
                        </x-admin::dropdown.menu.item>

                        <x-admin::dropdown.menu.item
                            @click="openModal(this.stages.find(stage => isLostStage(stage)))"
                        >
                            @lang('admin::app.leads.view.stages.lost')
                        </x-admin::dropdown.menu.item>

                        </x-slot>
            </x-admin::dropdown>

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="stageUpdateForm"
            >
                <form @submit="handleSubmit($event, handleFormSubmit)">
                    <x-admin::modal ref="stageUpdateModal" @close="nextStage = null">
                        <x-slot:header>
                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.header.before', ['lead' => $leadOrNull]) !!}

                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.view.stages.need-more-info')
                            </h3>

                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.header.after', ['lead' => $leadOrNull]) !!}
                            </x-slot>

                            <x-slot:content>
                                {!! view_render_event('admin.leads.view.stages.form_controls.modal.content.before', ['lead' => $leadOrNull]) !!}

                                <!-- Won stage fields (Lead, SalesLead, Order) -->
                                <template v-if="isWonStage(nextStage)">
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label class="required">
                                                Toegewezen persoon
                                            </x-admin::form.control-group.label>
                                            <select
                                                name="user_id"
                                                class="!w-full min-h-[38px] border border-gray-300 dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900 text-sm"
                                                v-model="nextStage.user_id"
                                                required
                                            >
                                                <option value="">Selecteer medewerker...</option>
                                                @foreach($assignableUsers as $user)
                                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                                @endforeach
                                            </select>
                                            <x-admin::form.control-group.error control-name="user_id" />
                                        </x-admin::form.control-group>

                                        <x-adminc::components.field
                                            type="date"
                                            name="closed_at"
                                            v-model="nextStage.closed_at"
                                            :label="trans('admin::app.leads.view.stages.closed-at')"
                                        />
                                    </template>

                                    <!-- Lost Reason -->
                                    <template v-else-if="isLostStage(nextStage)">
                                        <x-adminc::components.field
                                            type="date"
                                            name="closed_at"
                                            v-model="nextStage.closed_at"
                                            :label="trans('admin::app.leads.view.stages.closed-at')"
                                        />

                                        <x-admin::form.control-group>
                                            <select
                                                name="lost_reason"
                                                class="!w-full min-h-[38px] border border-gray-300 dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900 text-sm"
                                                v-model="nextStage.lost_reason"
                                            >
                                                <option value="">Selecteer reden...</option>
                                                @foreach(LostReason::cases() as $reason)
                                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                                @endforeach
                                            </select>
                                            <x-admin::form.control-group.label>
                                                @lang('admin::app.leads.view.stages.lost-reason')
                                            </x-admin::form.control-group.label>
                                        </x-admin::form.control-group>
                                    </template>
                                    <template v-else>
                                        <!-- No extra fields required for regular stage transitions -->
                                    </template>

                                </x-slot>

                                <x-slot:footer>
                                    <button
                                        type="submit"
                                        class="primary-button"
                                    >
                                        @lang('admin::app.leads.view.stages.save-btn')
                                    </button>

                                    </x-slot>
                    </x-admin::modal>

                </form>
            </x-admin::form>

        </div>
    </script>

    <script type="module">
        app.component('v-lead-stages', {
            template: '#v-lead-stages-template',

            data() {
                return {
                    isUpdating: false,

                    isOrderContext: {{ $isOrder ? 'true' : 'false' }},

                    currentStage: @json($displayStage),

                    nextStage: null,

                    stages: @json($displayPipeline->stages),

                    stageToggler: '',

                    currentUserId: {{ $currentUserId ? $currentUserId : 'null' }},
                }
            },

            watch: {
                nextStage(val) {
                    if (val) {
                        this._enterHandler = (e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                this.$el.querySelector('form button[type="submit"]')?.click();
                            }
                        };
                        document.addEventListener('keydown', this._enterHandler);
                    } else if (this._enterHandler) {
                        document.removeEventListener('keydown', this._enterHandler);
                        this._enterHandler = null;
                    }
                }
            },

            methods: {
                isWonStage(stage) {
                    if (!stage) {
                        return false;
                    }

                    if (stage.is_won === true || stage.is_won === 1 || stage.is_won === '1') {
                        return true;
                    }

                    return !!(stage?.code && String(stage.code).toLowerCase().startsWith('won'));
                },

                isLostStage(stage) {
                    if (!stage) {
                        return false;
                    }

                    if (stage.is_lost === true || stage.is_lost === 1 || stage.is_lost === '1') {
                        return true;
                    }

                    return !!(stage?.code && String(stage.code).toLowerCase().startsWith('lost'));
                },

                isWonOrLost(stage) {
                    return this.isWonStage(stage) || this.isLostStage(stage);
                },

                openModal(stage) {
                    if (!stage) {
                        return;
                    }

                    if (this.currentStage?.id && stage?.id && this.currentStage.id == stage.id) {
                        return;
                    }

                    this.nextStage = stage;

                    // For orders: show modal for won/lost (same as Lead), skip for regular stages
                    if (this.isOrderContext) {
                        if (this.isWonStage(stage) || this.isLostStage(stage)) {
                            if (!this.nextStage.closed_at) {
                                this.nextStage.closed_at = new Date().toISOString().slice(0, 10);
                            }
                            if (this.isWonStage(this.nextStage) && !this.nextStage.user_id) {
                                this.nextStage.user_id = this.currentUserId ? String(this.currentUserId) : '';
                            }
                            if (this.isLostStage(this.nextStage)) {
                                this.nextStage.lost_reason = '';
                            }
                            this.$refs.stageUpdateModal.open();
                            return;
                        }
                        this.update(stage);
                        return;
                    }

                    // Default 'Gesloten op' (closed_at) to today in d-m-YYYY (nl-NL)
                    // - when transitioning to any 'lost*' stage
                    // - when transitioning to any 'won*' stage
                    if (!this.nextStage.closed_at) {
                        if (this.isLostStage(this.nextStage) || this.isWonStage(this.nextStage)) {
                            this.nextStage.closed_at = new Date().toISOString().slice(0, 10);
                        }
                    }

                    // Default user_id to current lead assignment for won stage
                    if (this.isWonStage(this.nextStage) && !this.nextStage.user_id) {
                        this.nextStage.user_id = this.currentUserId ? String(this.currentUserId) : '';
                    }

                    this.$refs.stageUpdateModal.open();
                },

                handleFormSubmit(event) {
                    let params = {
                        'lead_pipeline_stage_id': this.nextStage.id
                    };

                    if (this.isWonStage(this.nextStage)) {
                        params.closed_at = this.nextStage.closed_at;
                        params.user_id = this.nextStage.user_id;
                    } else if (this.isLostStage(this.nextStage)) {
                        params.lost_reason = this.nextStage.lost_reason;
                        params.closed_at = this.nextStage.closed_at;
                    }

                    this.update(this.nextStage, params);
                },

                async update(stage, params = null) {
                    if (this.currentStage?.id && stage?.id && this.currentStage.id == stage.id) {
                        return;
                    }

                    this.$refs.stageUpdateModal.close();

                    this.isUpdating = true;

                    const performUpdate = async (extra = {}) => {
                        try {
                            const response = await this.$axios.put("{!! $updateUrl !!}", params ?? {
                                'lead_pipeline_stage_id': stage.id,
                                ...extra,
                            });
                            this.isUpdating = false;
                            this.currentStage = stage;
                            if (this.$parent.$refs.activities) {
                                this.$parent.$refs.activities.get();
                            }
                            this.$emitter.emit('add-flash', {type: 'success', message: response.data.message});
                        } catch (error) {
                            this.isUpdating = false;
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || 'Bijwerken mislukt'
                            });
                        }
                    };

                    try {
                        // Compute open activities depending on context (lead vs sales lead vs order)
                        let openCount;
                        let entityIdForConfirm;
                        let confirmOptions;
                        let entityType = 'lead';
                        @if ($isOrder)
                        entityIdForConfirm = {{ $order->id }};
                        entityType = 'order';
                        const orderTmpl = "{{ route('admin.orders.activities.index', $order->id) }}";
                        const orderRes = await this.$axios.get(orderTmpl, {params: {is_done: 0}});
                        const orderAll = Array.isArray(orderRes?.data?.data) ? orderRes.data.data : [];
                        openCount = orderAll.length;
                        @elseif ($isSalesLead)
                        // Strictly use sales lead activities; do not fallback to lead counters
                        entityIdForConfirm = {{ $salesLead->id }};
                        entityType = 'sale';
                        const tmpl = "{{ route('admin.sales-leads.activities.index', $salesLead->id) }}";
                        const res = await this.$axios.get(tmpl, {params: {is_done: 0, hierarchy: false}});
                        const all = Array.isArray(res?.data?.data) ? res.data.data : [];
                        openCount = all.length;
                        @else
                        // Regular lead context
                        openCount = Number({{ $lead->open_activities_count ?? $lead->openActivitiesCount ?? 0 }});
                        entityIdForConfirm = {{ $lead->id }};
                        @endif
                        if (openCount > 0) {
                            const message = await window.buildOpenActivitiesConfirmMessage(this.$axios, entityIdForConfirm, openCount, entityType);
                            const agree = window.confirm(message);
                            if (!agree) {
                                this.isUpdating = false;
                                return;
                            }
                            await performUpdate({close_open_activities: true});
                            return;
                        }

                        await performUpdate();
                    } catch (e) {
                        console.error('Could not update stage', e);
                        this.isUpdating = false;
                    }
                },
            },
        });
    </script>
@endPushOnce
