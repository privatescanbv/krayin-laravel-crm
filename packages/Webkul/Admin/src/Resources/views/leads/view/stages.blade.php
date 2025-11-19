@php
    // Allow override of pipeline and current stage for sales leads
    $displayPipeline = $overridePipeline ?? $lead->pipeline;
    $displayStage = $overrideStage ?? $lead->stage;
    $updateUrl = $overrideUpdateUrl ?? route('admin.leads.stage.update', $lead->id);
    $isSalesLead = isset($salesLead);
@endphp

<!-- Stages Navigation -->
{!! view_render_event('admin.leads.view.stages.before', ['lead' => $lead]) !!}

<!-- Stages Vue Component -->
<v-lead-stages>
    <x-admin::shimmer.leads.view.stages :count="$displayPipeline->stages->count() - 1" />
</v-lead-stages>

{!! view_render_event('admin.leads.view.stages.after', ['lead' => $lead]) !!}

@include('admin::leads.partials.open_activities_confirm_helper')

@pushOnce('scripts')
    <script type="text/x-template" id="v-lead-stages-template">
        <!-- Stages Container -->
        <div
            class="flex w-full max-w-full"
            :class="{'opacity-50 pointer-events-none': isUpdating}"
        >
            <!-- Stages Item -->
            <template v-for="stage in stages">
                {!! view_render_event('admin.leads.view.stages.items.before', ['lead' => $lead]) !!}

                <div
                    class="stage relative flex h-7 cursor-pointer items-center justify-center bg-white pl-7 pr-4 dark:bg-gray-900 ltr:first:rounded-l-lg rtl:first:rounded-r-lg"
                    :class="{
                        '!bg-succes text-white dark:text-gray-900 ltr:after:bg-succes rtl:before:bg-succes': currentStage.sort_order >= stage.sort_order,
                        '!bg-red-500 text-white dark:text-gray-900 ltr:after:bg-red-500 rtl:before:bg-red-500': currentStage?.code && String(currentStage.code).toLowerCase().startsWith('lost'),
                    }"
                    v-if="!(stage?.code && ['won','lost'].some(k => String(stage.code).toLowerCase().startsWith(k)))"
                    @click="update(stage)"
                >
                    <span class="z-20 whitespace-nowrap text-sm font-medium dark:text-white">
                        @{{ stage.name }}
                    </span>
                </div>

                {!! view_render_event('admin.leads.view.stages.items.after', ['lead' => $lead]) !!}
            </template>

            {!! view_render_event('admin.leads.view.stages.items.dropdown.before', ['lead' => $lead]) !!}

            <!-- Won/Lost Stage Item -->
            <x-admin::dropdown position="bottom-right">
                <x-slot:toggle>
                    {!! view_render_event('admin.leads.view.stages.items.dropdown.toggle.before', ['lead' => $lead]) !!}

                    <div
                        class="relative flex h-7 min-w-24 cursor-pointer items-center justify-center rounded-r-lg bg-white pl-7 pr-4 dark:bg-gray-900"
                        :class="{
                            '!bg-succes text-white dark:text-gray-900 after:bg-succes': (currentStage?.code && String(currentStage.code).toLowerCase().startsWith('won')),
                            '!bg-red-500 text-white dark:text-gray-900 after:bg-red-500': (currentStage?.code && String(currentStage.code).toLowerCase().startsWith('lost')),
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

                    {!! view_render_event('admin.leads.view.stages.items.dropdown.toggle.after', ['lead' => $lead]) !!}
                </x-slot>

                <x-slot:menu>
                    {!! view_render_event('admin.leads.view.stages.items.dropdown.menu_item.before', ['lead' => $lead]) !!}

                    <x-admin::dropdown.menu.item
                        @click="openModal(this.stages.find(stage => stage?.code && String(stage.code).toLowerCase().startsWith('won')))"
                    >
                        @lang('admin::app.leads.view.stages.won')
                    </x-admin::dropdown.menu.item>

                    <x-admin::dropdown.menu.item
                        @click="openModal(this.stages.find(stage => stage?.code && String(stage.code).toLowerCase().startsWith('lost')))"
                    >
                        @lang('admin::app.leads.view.stages.lost')
                    </x-admin::dropdown.menu.item>

                    {!! view_render_event('admin.leads.view.stages.items.dropdown.menu_item.after', ['lead' => $lead]) !!}
                </x-slot>
            </x-admin::dropdown>

            {!! view_render_event('admin.leads.view.stages.items.dropdown.after', ['lead' => $lead]) !!}

            {!! view_render_event('admin.leads.view.stages.form_controls.before', ['lead' => $lead]) !!}

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="stageUpdateForm"
            >
                <form @submit="handleSubmit($event, handleFormSubmit)">
                    {!! view_render_event('admin.leads.view.stages.form_controls.modal.before', ['lead' => $lead]) !!}

                    <x-admin::modal ref="stageUpdateModal">
                        <x-slot:header>
                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.header.before', ['lead' => $lead]) !!}

                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.view.stages.need-more-info')
                            </h3>

                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.header.after', ['lead' => $lead]) !!}
                        </x-slot>

                        <x-slot:content>
                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.content.before', ['lead' => $lead]) !!}

                            <!-- Won Value - Removed lead_value field -->
                            <template v-if="nextStage.code == 'won'">
                                <!-- Lead value field has been removed -->
                            </template>

                            <!-- Lost Reason -->
                            <template v-else>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.view.stages.lost-reason')
                                    </x-admin::form.control-group.label>

                                    <select
                                        name="lost_reason"
                                        class="!w-full min-h-[38px] border border-gray-300 dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900 text-sm"
                                        v-model="nextStage.lost_reason"
                                    >
                                        <option value="">Selecteer reden...</option>
                                        @foreach(\App\Enums\LostReason::cases() as $reason)
                                            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                        @endforeach
                                    </select>
                                </x-admin::form.control-group>
                            </template>

                            <!-- Closed At -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.leads.view.stages.closed-at')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="date"
                                    name="closed_at"
                                    v-model="nextStage.closed_at"
                                    :label="trans('admin::app.leads.view.stages.closed-at')"
                                />

                                <x-admin::form.control-group.error control-name="closed_at"/>
                            </x-admin::form.control-group>

                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.content.after', ['lead' => $lead]) !!}
                        </x-slot>

                        <x-slot:footer>
                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.footer.before', ['lead' => $lead]) !!}

                            <button
                                type="submit"
                                class="primary-button"
                            >
                                @lang('admin::app.leads.view.stages.save-btn')
                            </button>

                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.footer.after', ['lead' => $lead]) !!}
                        </x-slot>
                    </x-admin::modal>

                    {!! view_render_event('admin.leads.view.stages.form_controls.modal.after', ['lead' => $lead]) !!}
                </form>
            </x-admin::form>

            {!! view_render_event('admin.leads.view.stages.form_controls.after', ['lead' => $lead]) !!}
        </div>
    </script>

    <script type="module">
        app.component('v-lead-stages', {
            template: '#v-lead-stages-template',

            data() {
                return {
                    isUpdating: false,

                    currentStage: @json($displayStage),

                    nextStage: null,

                    stages: @json($displayPipeline->stages),

                    stageToggler: '',
                }
            },

            methods: {
                openModal(stage) {
                    if (!stage || !stage.code) {
                        return;
                    }

                    if (this.currentStage?.code == stage.code) {
                        return;
                    }

                    this.nextStage = stage;

                    // Default 'Gesloten op' (closed_at) to today in d-m-YYYY (nl-NL)
                    // - when transitioning to any 'lost*' stage
                    // - when transitioning to any 'won*' stage
                    if (this.nextStage?.code && !this.nextStage.closed_at) {
                        const code = String(this.nextStage.code).toLowerCase();
                        if (code.startsWith('lost') || code.startsWith('won')) {
                            this.nextStage.closed_at = new Date().toLocaleDateString('nl-NL');
                        }
                    }

                    this.$refs.stageUpdateModal.open();
                },

                handleFormSubmit(event) {
                    let params = {
                        'lead_pipeline_stage_id': this.nextStage.id
                    };

                    if (this.nextStage?.code && String(this.nextStage.code).toLowerCase().startsWith('won')) {
                        params.closed_at = this.nextStage.closed_at;
                    } else if (this.nextStage?.code && String(this.nextStage.code).toLowerCase().startsWith('lost')) {
                        params.lost_reason = this.nextStage.lost_reason;

                        params.closed_at = this.nextStage.closed_at;
                    }

                    this.update(this.nextStage, params);
                },

                async update(stage, params = null) {
                    if (this.currentStage?.code == stage.code) {
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
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        } catch (error) {
                            this.isUpdating = false;
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Bijwerken mislukt' });
                        }
                    };

                    try {
                        // Compute open activities depending on context (lead vs sales lead)
                        let openCount;
                        let entityIdForConfirm;
                        let confirmOptions;
                        @if ($isSalesLead)
                            // Strictly use sales lead activities; do not fallback to lead counters
                            entityIdForConfirm = {{ $salesLead->id }};
                            confirmOptions = { type: 'sales' };
                            const tmpl = "{{ route('admin.sales-leads.activities.index', $salesLead->id) }}";
                            const res = await this.$axios.get(tmpl, { params: { is_done: 0 } });
                            const all = Array.isArray(res?.data?.data) ? res.data.data : [];
                            openCount = all.length;
                        @else
                            // Regular lead context
                            openCount = Number({{ $lead->open_activities_count ?? $lead->openActivitiesCount ?? 0 }});
                            entityIdForConfirm = {{ $lead->id }};
                            confirmOptions = { type: 'lead' };
                        @endif
                        if (openCount > 0) {
                            const message = await window.buildOpenActivitiesConfirmMessage(this.$axios, entityIdForConfirm, openCount, confirmOptions);
                            const agree = window.confirm(message);
                            if (!agree) {
                                this.isUpdating = false;
                                return;
                            }
                            await performUpdate({ close_open_activities: true });
                            return;
                        }

                        await performUpdate();
                    } catch (e) {
                        console.error('Could not update lead stage', e);
                        this.isUpdating = false;
                    }
                },
            },
        });
    </script>
@endPushOnce
