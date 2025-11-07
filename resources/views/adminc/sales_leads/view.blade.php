@php use App\Enums\LostReason; @endphp
<x-admin::layouts>
    <x-slot:title>
        {{ $lead->name }}
    </x-slot>

    <!-- Content -->
    <div class="relative flex flex-col gap-4 max-lg:flex-wrap lg:grid lg:grid-cols-[394px,1fr,280px]">
        <!-- Left Panel -->
        {!! view_render_event('admin.leads.view.left.before', ['lead' => $lead]) !!}

        <div class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
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

                    <div class="mb-2">
                        @if (($days = $lead->rotten_days) > 0)
                            @php
                                $lead->tags->prepend([
                                    'name'  => '<span class="icon-rotten text-base"></span>' . trans('admin::app.leads.view.rotten-days', ['days' => $days]),
                                    'color' => '#FEE2E2'
                                ]);
                            @endphp
                        @endif
                    </div>

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
                                    Geen open activiteiten voor deze sales
                                </span>
                            </div>
                        </div>
                    @endif

                    <!-- Activity Actions -->
                    <div class="flex flex-wrap gap-2">
                        {!! view_render_event('admin.sales-leads.view.actions.before', ['lead' => $lead]) !!}

                        @if (bouncer()->hasPermission('mail.compose'))
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

                        @if (bouncer()->hasPermission('sales-leads.edit'))
                            <button
                                type="button"
                                class="secondary-button"
                                @click="$refs.salesLeadAfvoerenModal.open()"
                            >
                                sales afvoeren
                            </button>
                        @endif

                        {!! view_render_event('admin.sales-leads.view.actions.after', ['lead' => $lead]) !!}
                    </div>
                </div>

                <x-adminc::persons.card :person="$salesLead->getContactPersonOrFirstPerson()" show_actions="false" />

                <!-- Lead Overview (compact overview with all information) -->
                <x-adminc::sales_leads.view.compact-overview :salesLead="$salesLead" :lead="$lead"/>

                <x-adminc::leads.persons
                :entity="$salesLead"
                entity-type="salesLead"
                :show-add-button="false"
                :show-sync-link="false"
                :show-match-score="false"
                :show-anamnesis="true"
            />
            </div>

            <div class="border-t border-gray-200 p-4 dark:border-gray-800">
                <nav class="flex flex-col gap-1 text-sm font-medium">
                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-left transition"
                        :class="leadDetailSection === 'algemeen'
                            ? 'bg-brandColor text-white dark:bg-brandColor'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800'"
                        @click="leadDetailSection = 'algemeen'"
                    >
                        Algemeen
                    </button>

                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-left transition"
                        :class="leadDetailSection === 'activiteiten'
                            ? 'bg-brandColor text-white dark:bg-brandColor'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800'"
                        @click="leadDetailSection = 'activiteiten'"
                    >
                        Activiteiten
                    </button>

                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-left transition"
                        :class="leadDetailSection === 'anamnese'
                            ? 'bg-brandColor text-white dark:bg-brandColor'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800'"
                        @click="leadDetailSection = 'anamnese'"
                    >
                        Anamnese
                    </button>

                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-left transition"
                        :class="leadDetailSection === 'marketing'
                            ? 'bg-brandColor text-white dark:bg-brandColor'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800'"
                        @click="leadDetailSection = 'marketing'"
                    >
                        Marketing
                    </button>
                </nav>
            </div>

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

        {!! view_render_event('admin.sales-leads.view.left.after', ['lead' => $lead]) !!}

        {!! view_render_event('admin.sales-leads.view.right.before', ['lead' => $lead]) !!}

        <!-- Middle Panel -->
        <div class="flex w-full flex-col gap-4">
            <div
                v-if="leadDetailSection === 'algemeen'"
                class="flex w-full flex-col gap-4 rounded-lg"
            >
                @include('admin::leads.view.stages',[
                    'overridePipeline' => $salesLead->pipelineStage->pipeline ?? $lead->pipeline,
                    'overrideStage' => $salesLead->pipelineStage ?? $lead->stage,
                    'overrideUpdateUrl' => route('admin.sales-leads.stage.update', $salesLead->id),
                    'salesLead' => $salesLead,
                ])

                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Algemene informatie</h3>

                        @if (bouncer()->hasPermission('sales-leads.edit'))
                            <a
                                href="{{ route('admin.sales-leads.edit', $salesLead->id) }}"
                                class="text-sm font-medium text-brandColor hover:underline"
                            >
                                Bewerk sales
                            </a>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Leadnaam</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $salesLead->name }}</div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Sales ID</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">#{{ $salesLead->id }}</div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Pipeline</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->pipelineStage->pipeline->name ?? $lead->pipeline->name ?? 'Onbekend' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Stage</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->pipelineStage->name ?? $lead->stage->name ?? 'Onbekend' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Toegewezen aan</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->user->name ?? 'Niet toegewezen' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Bron</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->source->name ?? 'Onbekend' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Kanaal</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->channel->name ?? 'Onbekend' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Afdeling</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->department->name ?? 'Onbekend' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">MRI status</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->mriStatusLabel ?? 'Onbekend' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Diagnoseformulier</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                @if ($salesLead->has_diagnosis_form)
                                    <span class="inline-flex items-center gap-1 text-green-700">
                                        <span class="icon-attachment text-xs"></span>
                                        Aanwezig
                                    </span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">Niet aanwezig</span>
                                @endif
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Organisatie</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                @if ($salesLead->organization)
                                    <a
                                        href="{{ route('admin.contacts.organizations.view', $salesLead->organization->id) }}"
                                        target="_blank"
                                        class="text-brandColor hover:underline"
                                    >
                                        {{ $salesLead->organization->name }}
                                        <span class="icon-external-link text-xs ml-1"></span>
                                    </a>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">Onbekend</span>
                                @endif
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">SuiteCRM</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                @if (!empty($salesLead->sugar_link))
                                    <a href="{{ $salesLead->sugar_link }}" target="_blank" class="text-brandColor hover:underline">
                                        {{ $salesLead->external_id ?? 'Open in SuiteCRM' }}
                                    </a>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">Geen koppeling</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Omschrijving</div>
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                            {{ $salesLead->description ?? 'Geen omschrijving beschikbaar.' }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-else-if="leadDetailSection === 'activiteiten'"
                class="flex w-full flex-col gap-4 rounded-lg"
            >
                @include('admin::leads.view.stages',[
                    'overridePipeline' => $salesLead->pipelineStage->pipeline ?? $lead->pipeline,
                    'overrideStage' => $salesLead->pipelineStage ?? $lead->stage,
                    'overrideUpdateUrl' => route('admin.sales-leads.stage.update', $salesLead->id),
                    'salesLead' => $salesLead,
                ])

                {!! view_render_event('admin.sales-leads.view.activities.before', ['lead' => $lead]) !!}

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

                {!! view_render_event('admin.sales-leads.view.activities.after', ['lead' => $lead]) !!}
            </div>

            <div
                v-else-if="leadDetailSection === 'anamnese'"
                class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                Anamnese informatie komt hier in een latere iteratie.
            </div>

            <div
                v-else-if="leadDetailSection === 'marketing'"
                class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                Marketing inzichten worden hier binnenkort toegevoegd.
            </div>
        </div>

        <!-- Right Panel -->
        <div class="flex min-h-full w-full flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <p>Deze kolom is gereserveerd voor aanvullende widgets en informatie.</p>
            <p class="text-xs text-gray-400 dark:text-gray-500">Placeholder content voor het nieuwe ontwerp.</p>
        </div>

        {!! view_render_event('admin.sales-leads.view.right.after', ['lead' => $lead]) !!}
    </div>

    @pushOnce('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof app !== 'undefined') {
                    app.mixin({
                        data() {
                            return {
                                leadDetailSection: 'algemeen',
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
