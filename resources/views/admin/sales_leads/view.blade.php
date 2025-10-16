@php use App\Enums\LostReason; @endphp
<x-admin::layouts>
    <x-slot:title>
        {{ $lead->name }}
    </x-slot>

    <!-- Content -->
    <div class="relative flex gap-4 max-lg:flex-wrap">
        <!-- Left Panel -->
        {!! view_render_event('admin.leads.view.left.before', ['lead' => $lead]) !!}

        <div
            class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <!-- Lead Information -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <!-- Breadcrumb's -->
                <div class="flex items-center justify-between">
                    <x-admin::breadcrumbs
                        name="sales-leads.view"
                        :entity="$salesLead"
                    />
                </div>

                <div class="mb-2">
                    @if (($days = $lead->rotten_days) > 0)
                        @php
                            $lead->tags->prepend([
                                'name'  => '<span class="icon-rotten text-base"></span>' . trans('admin::app.leads.view.rotten-days', ['days' => $days]),
                                'color' => '#FEE2E2'
                            ]);
                        @endphp
                    @endif

{{--                    {!! view_render_event('admin.leads.view.tags.before', ['lead' => $lead]) !!}--}}

{{--                    <!-- Tags -->--}}
{{--                    <x-admin::tags--}}
{{--                        :attach-endpoint="route('admin.sales-leads.tags.attach', $lead->id)"--}}
{{--                        :detach-endpoint="route('admin.sales-leads.tags.detach', $lead->id)"--}}
{{--                        :added-tags="$lead->tags"--}}
{{--                    />--}}

{{--                    {!! view_render_event('admin.leads.view.tags.after', ['lead' => $lead]) !!}--}}
                </div>

                <!-- Duplicate Detection -->
{{--                @if ($lead->hasPotentialDuplicates())--}}
{{--                    <div--}}
{{--                        class="mb-4 rounded-lg border border-orange-200 bg-orange-50 p-3 dark:border-orange-800 dark:bg-orange-900/20">--}}
{{--                        <div class="flex items-center justify-between">--}}
{{--                            <div class="flex items-center gap-2">--}}
{{--                                <span class="icon-warning text-orange-600"></span>--}}
{{--                                <span class="text-sm font-medium text-orange-800 dark:text-orange-200">--}}
{{--                                    Potentiële duplicaten gevonden ({{ $lead->getPotentialDuplicatesCount() }} leads{{ $lead->getPotentialDuplicatesCount() > 1 ? 's' : '' }})--}}
{{--                                </span>--}}
{{--                            </div>--}}
{{--                            <a--}}
{{--                                href="{{ route('admin.leads.duplicates.index', $lead->id) }}"--}}
{{--                                class="rounded bg-orange-600 px-3 py-1 text-xs text-white hover:bg-orange-700"--}}
{{--                            >--}}
{{--                                Duplicaten samenvoegen--}}
{{--                            </a>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                @endif--}}

                <!-- No Open Activities Warning for sales -->
                @php
                    $isWonOrLost = ($salesLead->pipelineStage->is_won ?? false) || ($salesLead->pipelineStage->is_lost ?? false);
                @endphp
                @if (($salesLead->open_activities_count ?? 0) === 0 && ! $isWonOrLost)
                    <div
                        class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                        <div class="flex items-center gap-2">
                            <span class="icon-warning text-red-600"></span>
                            <span class="text-sm font-medium text-red-800 dark:text-red-200">
                                Geen open activiteiten voor deze sales lead
                            </span>
                        </div>
                    </div>
                @endif

                <!-- Activity Actions -->
                <div class="flex flex-wrap gap-2">
                    {!! view_render_event('admin.leads.view.actions.before', ['lead' => $lead]) !!}

                    @if (bouncer()->hasPermission('mail.compose'))
                        <!-- Mail Activity Action -->
                        <x-admin::activities.actions.mail
                            :entity="$salesLead"
                            entity-control-name="sales_lead_id"
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

                    @if (bouncer()->hasPermission('sales-leads.edit'))
                        <button
                            type="button"
                            class="secondary-button"
                            @click="$refs.salesLeadAfvoerenModal.open()"
                        >
                            sales afvoeren
                        </button>
                    @endif

                    {!! view_render_event('admin.leads.view.actions.after', ['lead' => $lead]) !!}
                </div>
            </div>

            @include('admin::leads.common.card', ['lead' => $lead, 'show_actions'=>false])

            <!-- Lead Overview (compact overview with all information) -->
            @include('admin.sales_leads.view.compact-overview')

            <!-- Contact Person -->
            @include('admin::leads.view.person')

            <!-- Footer with creation and modification dates -->
            <div
                class="flex w-full flex-col gap-2 p-4 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-800">
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

        <!-- Right Panel -->
        <div class="flex w-full flex-col gap-4 rounded-lg">
            <!-- Stages Navigation -->
            @include('admin::leads.view.stages',[
                'overridePipeline' => $salesLead->pipelineStage->pipeline ?? $lead->pipeline,
                'overrideStage' => $salesLead->pipelineStage ?? $lead->stage,
                'overrideUpdateUrl' => route('admin.sales-leads.stage.update', $salesLead->id),
                'salesLead' => $salesLead,
            ])

            <!-- Activities -->
            {!! view_render_event('admin.leads.view.activities.before', ['lead' => $lead]) !!}

            <x-admin::activities
                :endpoint="route('admin.sales-leads.activities.index', $salesLead->id)"
                :email-detach-endpoint="route('admin.sales-leads.emails.detach', ['id' => $salesLead->id, 'emailId' => '__EMAIL_ID__'])"
                :activeType="request()->query('from') === 'quotes' ? 'quotes' : 'planned'"
                :extra-types="[
                    ['name' => 'description', 'label' => trans('admin::app.leads.view.tabs.description')],
                    ['name' => 'orders', 'label' => 'Orders'],
                ]"
                ref="activities"
            >

                <!-- Orders -->
                <x-slot:orders>
                    <div class="p-4 dark:text-white">
                        @if (($orders ?? collect())->isEmpty())
                            <div class="text-sm text-gray-500">Geen orders gekoppeld.</div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                    <tr class="text-left border-b dark:border-gray-800">
                                        <th class="py-2 pr-4">ID</th>
                                        <th class="py-2 pr-4">Titel</th>
                                        <th class="py-2 pr-4">Sales Order ID</th>
                                        <th class="py-2 pr-4">Totale prijs</th>
                                        <th class="py-2 pr-4"></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($orders as $order)
                                        <tr class="border-b dark:border-gray-800">
                                            <td class="py-2 pr-4">{{ $order->id }}</td>
                                            <td class="py-2 pr-4">{{ $order->title }}</td>
                                            <td class="py-2 pr-4">
                                                € {{ number_format((float) $order->total_price, 2, ',', '.') }}</td>
                                            <td class="py-2 pr-4">
                                                <a href="{{ route('admin.orders.edit', $order->id) }}"
                                                   class="text-blue-600 hover:underline">Bewerken</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </x-slot:orders>

                <!-- Description -->
                <x-slot:description>
                    <div class="p-4 dark:text-white">
                        {{ $lead->description }}
                    </div>
                </x-slot>
            </x-admin::activities>

            {!! view_render_event('admin.leads.view.activities.after', ['lead' => $lead]) !!}
        </div>

        {!! view_render_event('admin.leads.view.right.after', ['lead' => $lead]) !!}
    </div>

    @pushOnce('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof app !== 'undefined') {
                    app.mixin({
                        data() {
                            return {
                                salesLeadAfvoerenData: {
                                    lost_reason: '',
                                    closed_at: new Date().toISOString().split('T')[0],
                                },
                                isSubmittingSalesLead: false,
                            };
                        },
                        methods: {
                            submitSalesLeadAfvoeren() {
                                if (!this.salesLeadAfvoerenData.lost_reason.trim()) {
                                    this.$emitter.emit('add-flash', {
                                        type: 'error',
                                        message: 'Reden van verlies is verplicht'
                                    });
                                    return;
                                }

                                this.isSubmittingSalesLead = true;
                                const url = "{{ route('admin.sales-leads.lost', $salesLead->id) }}";
                                this.$axios.put(url, {
                                    lost_reason: this.salesLeadAfvoerenData.lost_reason,
                                    closed_at: this.salesLeadAfvoerenData.closed_at,
                                })
                                    .then(() => {
                                        this.isSubmittingSalesLead = false;
                                        this.$refs.salesLeadAfvoerenModal.close();
                                        this.$emitter.emit('add-flash', {
                                            type: 'success',
                                            message: 'sales is afgevoerd.'
                                        });
                                        window.location.reload();
                                    })
                                    .catch((error) => {
                                        this.isSubmittingSalesLead = false;
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

    <!-- sales Afvoeren Modal -->
    <x-admin::modal ref="salesLeadAfvoerenModal">
        <x-slot:header>
            <h3 class="text-base font-semibold dark:text-white">
                Sales afvoeren
            </h3>
        </x-slot>

        <x-slot:content>
            <div class="mb-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Weet je zeker dat je deze sales wilt afvoeren? Dit zet de sales op status "Verloren" en
                    slaat de gekozen reden op. De gekoppelde lead blijft ongewijzigd.
                </p>
            </div>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    Reden van verlies
                </x-admin::form.control-group.label>

                <select
                    name="lost_reason"
                    class="!w-full min-h-[38px] border border-gray-300 dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900 text-sm"
                    v-model="salesLeadAfvoerenData.lost_reason"
                    required
                >
                    <option value="">Selecteer reden...</option>
                    @foreach (LostReason::cases() as $reason)
                        <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                    @endforeach
                </select>
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    Gesloten op
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="date"
                    name="closed_at"
                    v-model="salesLeadAfvoerenData.closed_at"
                />
            </x-admin::form.control-group>
        </x-slot>

        <x-slot:footer>
            <button
                type="button"
                class="secondary-button"
                @click="$refs.salesLeadAfvoerenModal.close()"
            >
                Annuleren
            </button>

            <button
                type="button"
                class="primary-button"
                @click="submitSalesLeadAfvoeren"
                :disabled="!salesLeadAfvoerenData.lost_reason || isSubmittingSalesLead"
            >
                <span v-if="isSubmittingSalesLead">Bezig...</span>
                <span v-else>sales afvoeren</span>
            </button>
        </x-slot>
    </x-admin::modal>
</x-admin::layouts>
