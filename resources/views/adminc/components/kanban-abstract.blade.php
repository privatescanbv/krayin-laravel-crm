@php use App\Enums\LostReason; @endphp
@props([
    'type',
    'stages',
    'pipeline',
])

@php
    if($type == 'sales') {
        $routeNameIndex = 'admin.sales-leads.index';
        $routeNameViewEntity = 'admin.sales-leads.view';
        $RouteNameGetEntities = 'admin.sales-leads.get';
        $routeNameStageUpdate = 'admin.sales-leads.stage.update';
    } elseif ($type === 'orders') {
        $routeNameIndex = 'admin.orders.index';
        $routeNameViewEntity = 'admin.orders.view';
        $RouteNameGetEntities = 'admin.orders.get';
        $routeNameStageUpdate = 'admin.orders.stage.update';
    } else {
        $routeNameIndex = 'admin.leads.index';
        $routeNameViewEntity = 'admin.leads.view';
        $RouteNameGetEntities = 'admin.leads.get';
        $routeNameStageUpdate  = 'admin.leads.stage.update';
    }
    $pipelineId = request('pipeline_id') ?? $pipeline->id;
@endphp
{{-- type sales / leads--}}
{!! view_render_event('admin.leads.index.kanban.before') !!}

<!-- Kanban Vue Component -->
<v-leads-kanban ref="leadsKanban">
    <div class="flex flex-col gap-4">
        <!-- Shimmer -->
        <x-admin::shimmer.leads.index.kanban/>
    </div>
</v-leads-kanban>

{!! view_render_event('admin.leads.index.kanban.after') !!}

@pushOnce('scripts')
    @include('admin::leads.partials.open_activities_confirm_helper')
    <script
        type="text/x-template"
        id="v-leads-kanban-template">
        <template v-if="isLoading">
            <div class="flex flex-col gap-4">
                <x-admin::shimmer.leads.index.kanban/>
            </div>
        </template>

        <template v-else>

            <div
                class="[&>*>*>*.toolbarRight]:max-lg:w-full [&>*>*>*.toolbarRight]:max-lg:justify-between [&>*>*>*.toolbarRight]:max-md:gap-y-2 [&>*>*>*.toolbarRight]:max-md:flex-wrap mt-3.5 [&>*>*:nth-child(1)]:max-lg:!flex-wrap">
                <div class="flex flex-col gap-4">

                    {!! view_render_event('admin.leads.index.kanban.content.before') !!}

                    <div class="flex gap-4 overflow-x-auto">
                        <!-- Stage Cards -->
                        <div
                            class="flex min-w-[275px] basis-[20%] flex-col rounded-lg bg-neutral-border shadow-xs"
                            v-for="(stage, index) in stageLeads"
                        >
                            {!! view_render_event('admin.leads.index.kanban.content.stage.header.before') !!}

                            <!-- Stage Header -->
                            <div class="flex flex-col px-3 py-2 rounded-t-xl bg-brand-privatescan-main gap-y-2">
                                <!-- Stage Title and Action -->
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-white">
                                        @{{ stage.name }}
                                    </span>

                                    <div class="flex items-center gap-1">
                                        <span
                                            class="inline-flex items-center justify-center rounded-full bg-white text-xs leading-none min-w-[18px] h-[18px] px-1">
                                            @{{ stage.leads.meta.total }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">

                                    <div class="flex items-center gap-1">

                                        <button
                                            @click.stop="toggleStageSort(stage)"
                                            title="Sorteer"
                                            class="
                                                inline-flex
                                                items-center
                                                gap-1
                                                px-1.5
                                                py-0.5
                                                rounded
                                                bg-blue-800/30
                                                hover:bg-blue-800/50
                                                text-white
                                                text-[10px]
                                                leading-none
                                                cursor-pointer
                                            ">
                                            <span>
                                                @{{ isNewestFirst(stage) ? 'Nieuwste' : 'Oudste' }}
                                            </span>
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="w-3 h-3"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    v-if="isNewestFirst(stage)"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M19 9l-7 7-7-7"
                                                />
                                                <path
                                                    v-else
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M5 15l7-7 7 7"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {!! view_render_event('admin.leads.index.kanban.content.stage.header.after') !!}

                            {!! view_render_event('admin.leads.index.kanban.content.stage.body.before') !!}

                            <!-- Draggable Stage Lead Cards -->
                            <draggable
                                class="flex h-[calc(100vh-317px)] flex-col overflow-y-auto p-1 gap-y-1"
                                :class="{ 'justify-center': stage.leads.data.length === 0 }"
                                ghost-class="draggable-ghost"
                                handle=".lead-item"
                                v-bind="{animation: 200}"
                                :list="stage.leads.data"
                                item-key="id"
                                group="leads"
                                @scroll="handleScroll(stage, $event)"
                                @change="updateStage(stage, $event)"
                                :scroll-sensitivity="100"
                                :force-fallback="false"
                            >
                                <template #header>
                                    <div
                                        class="flex flex-col items-center justify-center"
                                        v-if="! stage.leads.data.length"
                                    >
                                        <img
                                            class="dark:mix-blend-exclusion dark:invert"
                                            src="{{ vite()->asset('images/empty-placeholders/pipedrive.svg') }}"
                                        >

                                        <div class="flex flex-col items-center gap-4">
                                            <div class="flex flex-col items-center gap-2">
                                                <p class="!text-base font-semibold dark:text-white">
                                                    @lang('admin::app.leads.index.kanban.empty-list')
                                                </p>

                                                <p class="!text-sm text-gray-400 dark:text-gray-400">
                                                    @lang('admin::app.leads.index.kanban.empty-list-description')
                                                </p>
                                            </div>

                                            @if (bouncer()->hasPermission('leads.create') && $type=='leads')
                                                <a
                                                    :href="'{{ route('admin.leads.create') }}' + '?stage_id=' + stage.id"
                                                    class="primary-button"
                                                >
                                                    @lang('admin::app.leads.index.kanban.create-lead-btn')
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </template>

                                <!-- Lead Card -->
                                <template #item="{ element, index }">
                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.before') !!}

                                    <a
                                        v-if="entityType !== 'orders'"
                                        class="lead-item flex cursor-pointer flex-col gap-2 rounded-md border border-neutral-border transition-shadow shadow-xs hover:z-10 hover:shadow-lg bg-white py-1 px-2 dark:border-gray-400 dark:bg-gray-400"
                                        :href="'{{ route($routeNameViewEntity, 'replaceId') }}'.replace('replaceId', element.id)"
                                        style="min-height:unset;"
                                    >
                                        {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.header.before') !!}

                                        <!-- Header -->
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-center gap-1 min-w-0 flex-1">
                                                <div class="flex flex-col gap-0.5 min-w-0">
                                                    <!-- Naam + gender op één regel -->
                                                    <div class="flex items-center gap-1 min-w-0">
                                                        <x-adminc::components.gender-icon
                                                            mode="vue"
                                                            gender-expr="element.gender"
                                                        />
                                                        <!-- Naam -->
                                                        <span class="text-sm font-medium truncate min-w-0">
                                                            @{{ element.persons && element.persons.length > 0
                                                                ? element.persons[0]?.name
                                                                : (element.first_name
                                                                    ? `${element.first_name} ${element.last_name}`
                                                                    : element.name)
                                                            }}
                                                        </span>
                                                    </div>
                                                    <span
                                                        class="text-xs leading-normal truncate"
                                                        v-if="element.has_multiple_persons"
                                                                >
                                                        +@{{ element.persons_count - 1 }} meer
                                                    </span>
                                                    <span
                                                        class="text-xs leading-normal truncate"
                                                        v-if="element.persons && element.persons.length > 0 && element.persons[0]?.organization?.name"
                                                    >
                                                        @{{ element.persons[0]?.organization?.name }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Date and Rotten Days Indicator -->
                                            <div class="flex items-center gap-1 flex-shrink-0">
                                                <!-- Date -->
                                                <span class="text-[9px] text-gray-500 whitespace-nowrap">
                                                   @{{ formatDate(element.created_at) }}
                                               </span>

                                                <!-- Rotten Days Indicator -->
                                                <div
                                                    class="group relative flex-shrink-0"
                                                    v-if="element.rotten_days > 0"
                                                >
                                                    <span
                                                        class="icon-rotten cursor-default text-sm text-rose-600"></span>
                                                    <div
                                                        class="absolute -top-1 right-7 hidden w-max flex-col items-center group-hover:flex">
                                                       <span
                                                           class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                           @{{ "@lang('admin::app.leads.index.kanban.rotten-days', ['days' => 'replaceDays'])".replace('replaceDays', element.rotten_days) }}
                                                       </span>
                                                        <div
                                                            class="absolute -right-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.header.after') !!}

                                        {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.title.before') !!}

                                        <!-- Lost Reason (only for lost status) -->
                                        <div
                                            class="text-[10px] text-red-700 dark:text-red-400 mt-1"
                                            v-if="isLostStage(getElementStage(element)) && element.lost_reason_label"
                                        >
                                            <span class="font-medium">Verliesreden:</span>
                                            @{{ element.lost_reason_label }}
                                        </div>

                                        {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.title.after') !!}

                                        <!-- Card Footer -->
                                        <div
                                            class="flex items-center justify-between"
                                            v-if="element.has_duplicates || element.open_activities_count === 0 || (element.open_activities_count && element.open_activities_count > 0) || (element.unread_emails_count && element.unread_emails_count > 0) || element.mri_status || element.has_diagnosis_form"
                                        >
                                            <div class="flex items-center gap-3">
                                                <!-- Open Activities Count -->
                                                <div
                                                    class="group relative flex items-center gap-1 text-[10px] text-gray-600 dark:text-gray-400">
                                                    <span class="icon-activity text-xs"></span>
                                                    <span>@{{ element.open_activities_count || 0 }}</span>
                                                    <div
                                                        class="absolute -top-1 left-0 hidden w-max flex-col items-center group-hover:flex">
                                                        <span
                                                            class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                            Openstaande activiteiten
                                                        </span>
                                                        <div
                                                            class="absolute -left-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                    </div>
                                                </div>

                                                <!-- Unread Emails Count (includes nested activity emails) -->
                                                <div
                                                    class="group relative flex items-center gap-1 text-[10px] text-gray-600 dark:text-gray-400">
                                                    <span class="icon-mail text-xs"></span>
                                                    <span>@{{ element.unread_emails_count || 0 }}</span>
                                                    <div
                                                        class="absolute -top-1 left-0 hidden w-max flex-col items-center group-hover:flex">
                                                        <span
                                                            class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                            Ongelezen e-mails
                                                        </span>
                                                        <div
                                                            class="absolute -left-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                    </div>
                                                </div>

                                                <!-- Duplicate Indicator -->
                                                <div
                                                    class="group relative flex items-center gap-1"
                                                    v-if="element.has_duplicates"
                                                >
                                                    <span
                                                        class="icon-warning cursor-default text-xs text-orange-600"></span>
                                                    <div
                                                        class="absolute -top-1 left-0 hidden w-max flex-col items-center group-hover:flex">
                                                        <span
                                                            class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                            Mogelijke duplicate gevonden (@{{ element.duplicates_count }} gelijkenissen)
                                                        </span>
                                                        <div
                                                            class="absolute -left-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                    </div>
                                                </div>

                                                <!-- No Open Activities Warning -->
                                                <div
                                                    class="group relative flex items-center gap-1"
                                                    v-if="element.open_activities_count === 0 && !isWonOrLost(getElementStage(element))"
                                                >
                                                    <span
                                                        class="icon-warning cursor-default text-xs text-status-expired-text"></span>
                                                    <div
                                                        class="absolute -top-1 left-0 hidden w-max flex-col items-center group-hover:flex">
                                                        <span
                                                            class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                            Geen open activiteiten
                                                        </span>
                                                        <div
                                                            class="absolute -left-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Days Until Due Date -->
                                            <div class="relative text-[10px] text-gray-600 dark:text-gray-400">
                                                <span
                                                    v-if="element.days_until_due_date === null"
                                                    class="text-gray-500"
                                                >
                                                    -
                                                </span>
                                                <span
                                                    v-else-if="element.days_until_due_date > 0"
                                                    class="text-status-active-text"
                                                >
                                                    @{{ element.days_until_due_date }}d
                                                </span>
                                                <span
                                                    v-else-if="element.days_until_due_date === 0"
                                                    class="text-orange-600 font-medium"
                                                >
                                                    Vandaag
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-status-expired-text font-medium"
                                                >
                                                    @{{ Math.abs(element.days_until_due_date) }}d over
                                                </span>

                                                <!-- Diagnosis Form Icon bottom-right (to the left of MRI) -->
                                                <div v-if="element.has_diagnosis_form"
                                                     class="absolute -bottom-1 right-4 group">
                                                    <span class="icon-attachment text-xs"></span>
                                                    <div
                                                        class="absolute -top-1 right-5 hidden w-max flex-col items-center group-hover:flex">
                                                        <span
                                                            class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                            Diagnoseformulier aanwezig
                                                        </span>
                                                        <div
                                                            class="absolute -right-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                    </div>
                                                </div>

                                                <!-- MRI Status Icon bottom-right -->
                                                <div v-if="element.mri_status"
                                                     class="absolute -bottom-1 -right-1 group">
                                                    <span class="icon-image text-xs"></span>
                                                    <div
                                                        class="absolute -top-1 right-5 hidden w-max flex-col items-center group-hover:flex">
                                                        <span
                                                            class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                            @{{ element.mri_status_label }}
                                                        </span>
                                                        <div
                                                            class="absolute -right-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </a>

                                    <a
                                        v-else
                                        class="lead-item flex cursor-pointer flex-col gap-2 rounded-md border border-neutral-border transition-shadow shadow-xs hover:z-10 hover:shadow-lg bg-white py-2 px-2 dark:border-gray-400 dark:bg-gray-400"
                                        :href="'{{ route($routeNameViewEntity, 'replaceId') }}'.replace('replaceId', element.id)"
                                        style="min-height:unset;"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex flex-col min-w-0 flex-1">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <span class="text-[10px] text-gray-500 whitespace-nowrap">
                                                        #@{{ element.id }}
                                                    </span>
                                                    <span class="text-sm font-medium truncate min-w-0">
                                                        @{{ element.title || ('Order #' + element.id) }}
                                                    </span>
                                                </div>

                                                <span
                                                    class="text-xs leading-normal text-gray-600 truncate"
                                                    v-if="element.patient_name"
                                                >
                                                    @{{ element.patient_name }}
                                                </span>
                                                <span
                                                    class="text-xs leading-normal text-gray-600 truncate"
                                                    v-else-if="element.sales_lead && element.sales_lead.name"
                                                >
                                                    @{{ element.sales_lead.name }}
                                                </span>
                                            </div>

                                            <div class="flex flex-col items-end gap-0.5 flex-shrink-0">
                                                <span class="text-[9px] text-gray-500 whitespace-nowrap">
                                                    @{{ formatDate(element.created_at) }}
                                                </span>

                                                <span
                                                    class="text-xs font-semibold text-gray-800"
                                                    v-if="element.total_price !== null && element.total_price !== undefined"
                                                >
                                                    € @{{ Number(element.total_price).toFixed(2) }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between">
                                            <div
                                                class="group relative flex items-center gap-1 text-[10px] text-gray-600 dark:text-gray-400"
                                            >
                                                <span class="icon-activity text-xs"></span>
                                                <span>@{{ element.open_activities_count || 0 }}</span>
                                            </div>
                                        </div>
                                    </a>

                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.after') !!}
                                </template>
                            </draggable>

                            {!! view_render_event('admin.leads.index.kanban.content.stage.body.after') !!}
                        </div>
                    </div>

                    {!! view_render_event('admin.leads.index.kanban.content.after') !!}
                </div>
            </div>

            <!-- Lost Stage Modal -->
            <x-admin::modal ref="lostStageModal">
                <x-slot:header>
                    <h3 class="text-base font-semibold dark:text-white">
                        Meer details nodig
                    </h3>
                    </x-slot>

                    <x-slot:content>
                        <div v-if="currentStageUpdate">
                            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                Lead "<strong>@{{ getLeadName(currentStageUpdate.lead) }}</strong>" wordt verplaatst
                                naar status "Verloren"
                            </p>

                            <!-- Lost Reason -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="text"
                                    name="closed_at"
                                    v-model="currentStageUpdate.closed_at"
                                    placeholder="dd-mm-yyyy"
                                    required
                                />

                                <select
                                    name="lost_reason"
                                    class="!w-full min-h-[38px] border border-gray-300 dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900 text-sm"
                                    v-model="currentStageUpdate.lost_reason"
                                    required
                                >
                                    <option value="">Selecteer reden...</option>
                                    @foreach (LostReason::cases() as $reason)
                                        <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                    @endforeach
                                </select>
                                <x-admin::form.control-group.label>
                                    Reden van verlies
                                </x-admin::form.control-group.label>

                            </x-admin::form.control-group>

                            <!-- Closed At -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    Gesloten op
                                </x-admin::form.control-group.label>

                            </x-admin::form.control-group>
                        </div>
                        <div v-else>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Laden...
                            </p>
                        </div>
                        </x-slot>

                        <x-slot:footer>
                            <button
                                type="button"
                                class="secondary-button mr-2"
                                @click="cancelLostStage"
                            >
                                Annuleren
                            </button>

                            <button
                                type="button"
                                class="primary-button"
                                @click="handleLostStageSubmit"
                            >
                                Opslaan
                            </button>
                            </x-slot>
            </x-admin::modal>
        </template>
    </script>

    <script type="module">
        document.addEventListener('DOMContentLoaded', function () {
            app.component('v-leads-kanban', {
                template: '#v-leads-kanban-template',

                data() {
                    return {
                        applied: {
                            filters: {
                                columns: [],
                            }
                        },

                        stages: @json($pipeline->stages->toArray()),

                        stageLeads: {},

                        isLoading: true,
                        entityType: @json($type),

                        tagTextColor: {
                            '#FEE2E2': '#DC2626',
                            '#FFEDD5': '#EA580C',
                            '#FEF3C7': '#D97706',
                            '#FEF9C3': '#CA8A04',
                            '#ECFCCB': '#65A30D',
                            '#DCFCE7': '#16A34A',
                        },
                        hideWonLost: true,
                        wonLostLabel: 'Toon gewonnen/verloren',
                        showDuplicates: false,
                        currentStageUpdate: null,
                        scrollTimeouts: {},
                        stageSorts: {},
                    };
                },

                computed: {
                    totalStagesAmount() {
                        return 0;
                    },

                    /**
                     * Generate unique src identifier including pipeline for localStorage
                     */
                    src() {
                        const pipelineId = "{{ $pipelineId }}";
                        return `{{ route($routeNameIndex) }}${pipelineId ? '?pipeline_id=' + pipelineId : ''}`;
                    },

                    /**
                     * Get the current pipeline ID for localStorage key
                     */
                    currentPipelineId() {
                        return "{{ $pipelineId }}";
                    }
                },

                mounted() {
                    this.boot();
                    this.setWonLostButtonText();
                },

                methods: {
                    getElementStage(element) {
                        return element?.stage || element?.pipeline_stage || null;
                    },

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

                    isNewestFirst(stage) {
                        return this.stageSorts[stage.id] !== 'created_at|asc'
                    },

                    toggleStageSort(stage) {
                        this.stageSorts[stage.id] =
                            this.isNewestFirst(stage)
                                ? 'created_at|asc'
                                : 'created_at|desc'

                        this.refreshStage(stage)
                    },

                    /**
                     * Format date to a more readable format
                     *
                     * @param {string} dateString - The date string to format
                     * @returns {string} Formatted date string
                     */
                    formatDate(dateString) {
                        if (!dateString) return '';

                        const date = new Date(dateString);
                        const now = new Date();
                        const diffTime = Math.abs(now - date);
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                        if (diffDays === 1) {
                            return 'Vandaag';
                        } else if (diffDays === 2) {
                            return 'Gisteren';
                        } else if (diffDays <= 7) {
                            return `${diffDays - 1} dagen geleden`;
                        } else {
                            return date.toLocaleDateString('nl-NL', {
                                day: '2-digit',
                                month: '2-digit',
                                year: '2-digit'
                            });
                        }
                    },


                    /**
                     * Initialization: This function checks for any previously saved filters in local storage and applies them as needed.
                     *
                     * @returns {void}
                     */
                    boot() {
                        // Initialize defaults for all stages
                        this.stages.forEach(stage => {
                            if (!this.stageSorts[stage.id]) {
                                this.stageSorts[stage.id] = 'created_at|desc';
                            }
                        });

                        let kanbans = this.getKanbans();

                        if (kanbans?.length) {
                            const currentKanban = kanbans.find(({
                                                                    src
                                                                }) => src === this.src);

                            if (currentKanban) {
                                this.applied.filters = currentKanban.applied.filters;

                                if (typeof currentKanban.hideWonLost === 'boolean') {
                                    this.hideWonLost = currentKanban.hideWonLost;
                                }

                                if (typeof currentKanban.showDuplicates === 'boolean') {
                                    this.showDuplicates = currentKanban.showDuplicates;
                                }

                                if (currentKanban.stageSorts) {
                                    this.stageSorts = currentKanban.stageSorts;
                                }

                                this.setWonLostButtonText();

                                this.get()
                                    .then(response => {
                                        if (response && response.data) {
                                            for (let [sortOrder, data] of Object.entries(response
                                                .data)) {
                                                this.stageLeads[sortOrder] = data;
                                            }
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error loading kanban data:', error);
                                        this.isLoading = false;
                                    });

                                return;
                            }
                        }

                        // Check for pipeline-specific won/lost setting
                        const pipelineSpecificKey = `kanban_hideWonLost_pipeline_${this.currentPipelineId}`;
                        const pipelineSpecificSetting = localStorage.getItem(pipelineSpecificKey);
                        if (pipelineSpecificSetting !== null) {
                            this.hideWonLost = JSON.parse(pipelineSpecificSetting);
                        } else {
                            // Default to hidden for performance (70k leads)
                            this.hideWonLost = true;
                        }
                        this.setWonLostButtonText();

                        this.get()
                            .then(response => {
                                if (response && response.data) {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        this.stageLeads[sortOrder] = data;
                                    }
                                }
                                this.setWonLostButtonText();
                            })
                            .catch(error => {
                                console.error('Error loading kanban data:', error);
                                this.isLoading = false;
                            });
                    },

                    /**
                     * Fetches the leads based on the applied filters.
                     *
                     * @param {object} requestedParams - The requested parameters.
                     * @returns {Promise} The promise object representing the request.
                     */
                    get(requestedParams = {}) {
                        let params = {
                            search: '',
                            searchFields: '',
                            pipeline_id: "{{ $pipelineId }}",
                            limit: 10,
                            exclude_won_lost: this.hideWonLost,
                            include_duplicates: this.showDuplicates,
                        };

                        return this.$axios
                            .get("{{ route($RouteNameGetEntities) }}", {
                                params: {
                                    ...params,

                                    ...requestedParams,
                                }
                            })
                            .then(response => {
                                this.isLoading = false;

                                this.updateKanbans();

                                return response;
                            })
                            .catch(error => {
                                console.error('Error fetching leads:', error);
                            });
                    },

                    /**
                     * Refresh a specific stage with current sort options
                     */
                    refreshStage(stage) {
                        const sortVal = this.stageSorts[stage.id] || 'created_at|desc';
                        const [sort, order] = sortVal.split('|');

                        this.updateKanbans();

                        this.$axios
                            .get("{{ route($RouteNameGetEntities) }}", {
                                params: {
                                    pipeline_id: "{{ $pipelineId }}",
                                    pipeline_stage_id: stage.id,
                                    sort: sort,
                                    order: order,
                                    exclude_won_lost: this.hideWonLost,
                                    include_duplicates: this.showDuplicates,
                                }
                            })
                            .then(response => {
                                if (response && response.data) {
                                    for (let [key, data] of Object.entries(response.data)) {
                                        this.stageLeads[key] = data;
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error refreshing stage:', error);
                            });
                    },

                    /**
                     * Filters the leads based on the applied filters.
                     * Clears existing data and refetches with new filters and current exclude_won_lost state.
                     *
                     * @param {object} filters - The filters to be applied.
                     * @returns {void}
                     */
                    filter(filters) {
                        // Clear existing data before applying new filters
                        this.stageLeads = {};
                        this.get()
                            .then(response => {
                                if (response && response.data) {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        this.stageLeads[sortOrder] = data;
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error applying filters:', error);
                            });
                    },

                    /**
                     * Searches the leads based on the applied filters.
                     * Clears existing data and refetches with new search criteria and current exclude_won_lost state.
                     *
                     * @param {object} filters - The filters to be applied.
                     * @returns {void}
                     */
                    search(filters) {
                        console.log('disable search')
                    },

                    /**
                     * Appends the leads to the stage.
                     * Ensures the exclude_won_lost parameter is included for performance optimization.
                     *
                     * @param {object} params - The parameters to be appended.
                     * @returns {void}
                     */
                    append(params) {
                        // Ensure exclude_won_lost parameter is included for performance optimization
                        const paramsWithExclude = {
                            ...params,
                            exclude_won_lost: this.hideWonLost,
                            include_duplicates: this.showDuplicates,
                        };

                        this.get(paramsWithExclude)
                            .then(response => {
                                if (response && response.data) {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        if (!this.stageLeads[sortOrder]) {
                                            this.stageLeads[sortOrder] = data;
                                        } else {
                                            this.stageLeads[sortOrder].leads.data = this.stageLeads[
                                                sortOrder].leads.data.concat(data.leads.data);

                                            this.stageLeads[sortOrder].leads.meta = data.leads.meta;
                                        }
                                    }
                                }
                            });
                    },

                    /**
                     * Updates the stage with the latest lead data.
                     *
                     * @param {object} stage - The stage object.
                     * @param {object} event - The event object.
                     * @returns {void}
                     */
                    updateStage(stage, event) {
                        if (event.moved) {
                            return;
                        }

                        if (event.removed) {
                            this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage
                                .sort_order].leads.meta.total - 1;

                            return;
                        }

                        // Check if moving to any lost stage (leads/sales require extra details)
                        if (this.entityType !== 'orders' && this.isLostStage(stage)) {
                            this.showLostModal(stage, event.added.element);
                            return;
                        }

                        // Update stage counters for non-lost stages
                        this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage
                            .sort_order].leads.meta.total + 1;

                        this.updateLeadStageWithChecks(event.added.element, stage);
                    },

                    /**
                     * Show modal for lost stage with required fields
                     */
                    showLostModal(stage, lead) {
                        this.currentStageUpdate = {
                            stage: stage,
                            lead: lead,
                            lost_reason: '',
                            closed_at: new Date().toLocaleDateString('nl-NL')
                        };

                        // Use nextTick to ensure the modal is rendered before opening
                        this.$nextTick(() => {
                            this.$refs.lostStageModal.open();
                        });
                    },

                    /**
                     * Handle form submission for lost stage
                     */
                    handleLostStageSubmit() {
                        if (!this.currentStageUpdate.lost_reason.trim()) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'Reden van verlies is verplicht'
                            });
                            return;
                        }

                        this.updateLeadStage(
                            this.currentStageUpdate.lead.id,
                            this.currentStageUpdate.stage.id, {
                                lost_reason: this.currentStageUpdate.lost_reason,
                                closed_at: this.currentStageUpdate.closed_at
                            }
                        );

                        if (this.$refs.lostStageModal) {
                            this.$refs.lostStageModal.close();
                        }
                        this.currentStageUpdate = null;
                    },

                    /**
                     * Update lead stage with optional additional data
                     */
                    async updateLeadStage(leadId, stageId, additionalData = {}) {
                        const data = {
                            'lead_pipeline_stage_id': stageId,
                            ...additionalData
                        };
                        this.$axios
                            .put("{{ route($routeNameStageUpdate, 'replace') }}".replace('replace',
                                leadId), data)
                            .then(response => {
                                this.$emitter.emit('add-flash', {
                                    type: 'success',
                                    message: response.data.message
                                });

                                // Update stage counters after successful update
                                const stage = this.stages.find(s => s.id === stageId);
                                if (stage) {
                                    this.stageLeads[stage.sort_order].leads.meta.total = this
                                        .stageLeads[stage.sort_order].leads.meta.total + 1;
                                }
                            })
                            .catch(error => {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error.response.data.message
                                });
                            });
                    },

                    /**
                     * Update stage but first confirm if there are open activities
                     */
                    async updateLeadStageWithChecks(lead, stage) {
                        try {
                            const openCount = Number(lead.open_activities_count || 0);

                            if (openCount > 0) {
                                const activityType = this.entityType === 'sales'
                                    ? 'sales'
                                    : (this.entityType === 'orders' ? 'order' : 'lead');
                                const message = await window.buildOpenActivitiesConfirmMessage(this
                                    .$axios, lead.id, openCount, activityType);
                                const confirmClose = await new Promise((resolve) => {
                                    resolve(window.confirm(message));
                                });

                                if (!confirmClose) {
                                    // Revert UI count since we optimistically incremented earlier
                                    this.stageLeads[stage.sort_order].leads.meta.total = this
                                        .stageLeads[stage.sort_order].leads.meta.total - 1;
                                    return;
                                }

                                await this.updateLeadStage(lead.id, stage.id, {
                                    close_open_activities: true
                                });
                                return;
                            }

                            await this.updateLeadStage(lead.id, stage.id);
                        } catch (e) {
                            // No-op; errors are handled in updateLeadStage
                        }
                    },

                    /**
                     * Get lead name for display
                     */
                    getLeadName(lead) {
                        if (!lead) {
                            return 'Onbekende lead/Sales';
                        }

                        if (lead.name) {
                            return String(lead.name);
                        }

                        if (lead.title) {
                            return String(lead.title);
                        }

                        const firstName = lead.first_name || '';
                        const lastName = lead.last_name || '';

                        const full = (firstName + ' ' + lastName).trim();

                        return full || 'Onbekende lead/Sales';
                    },

                    /**
                     * Cancel lost stage modal
                     */
                    cancelLostStage() {
                        if (this.$refs.lostStageModal) {
                            this.$refs.lostStageModal.close();
                        }
                        this.currentStageUpdate = null;
                    },

                    /**
                     * Handles the scroll event on the stage leads with debouncing for performance.
                     *
                     * @param {object} stage - The stage object.
                     * @param {object} event - The scroll event.
                     * @returns {void}
                     */
                    handleScroll(stage, event) {
                        // Clear existing timeout for this stage
                        if (this.scrollTimeouts && this.scrollTimeouts[stage.id]) {
                            clearTimeout(this.scrollTimeouts[stage.id]);
                        }

                        // Initialize scrollTimeouts if not exists
                        if (!this.scrollTimeouts) {
                            this.scrollTimeouts = {};
                        }

                        // Debounce scroll handling
                        this.scrollTimeouts[stage.id] = setTimeout(() => {
                            const element = event.target;
                            const bottom = Math.abs(element.scrollHeight - element.scrollTop -
                                element.clientHeight) < 1;

                            if (!bottom) {
                                return;
                            }

                            if (this.stageLeads[stage.sort_order].leads.meta.current_page == this
                                .stageLeads[stage.sort_order].leads.meta.last_page) {
                                return;
                            }

                            const sortVal = this.stageSorts[stage.id] || 'created_at|desc';
                            const [sort, order] = sortVal.split('|');

                            this.append({
                                pipeline_stage_id: stage.id,
                                pipeline_id: stage.lead_pipeline_id,
                                page: this.stageLeads[stage.sort_order].leads.meta
                                    .current_page + 1,
                                limit: 10,
                                sort: sort,
                                order: order,
                            });
                        }, 150); // 150ms debounce
                    },

                    //=======================================================================================
                    // Support for previous applied values in kanban's. All code is based on local storage.
                    //=======================================================================================

                    /**
                     * Updates the kanban's stored in local storage with the latest data.
                     *
                     * @returns {void}
                     */
                    updateKanbans() {
                        let kanbans = this.getKanbans();

                        if (kanbans?.length) {
                            const currentKanban = kanbans.find(({
                                                                    src
                                                                }) => src === this.src);

                            if (currentKanban) {
                                kanbans = kanbans.map(kanban => {
                                    if (kanban.src === this.src) {
                                        return {
                                            ...kanban,
                                            requestCount: ++kanban.requestCount,
                                            applied: this.applied,
                                            hideWonLost: this.hideWonLost,
                                            showDuplicates: this.showDuplicates,
                                            stageSorts: this.stageSorts,
                                        };
                                    }

                                    return kanban;
                                });
                            } else {
                                kanbans.push(this.getKanbanInitialProperties());
                            }
                        } else {
                            kanbans = [this.getKanbanInitialProperties()];
                        }

                        this.setKanbans(kanbans);
                    },

                    /**
                     * Returns the initial properties for a kanban.
                     *
                     * @returns {object} Initial properties for a kanban.
                     */
                    getKanbanInitialProperties() {
                        return {
                            src: this.src,
                            requestCount: 0,
                            applied: this.applied,
                            hideWonLost: this.hideWonLost,
                            showDuplicates: this.showDuplicates,
                            stageSorts: this.stageSorts,
                        };
                    },

                    /**
                     * Returns the storage key for kanban's in local storage.
                     *
                     * @returns {string} Storage key for kanban's.
                     */
                    getKanbansStorageKey() {
                        return 'kanbans';
                    },

                    /**
                     * Retrieves the kanban's stored in local storage.
                     *
                     * @returns {Array} Kanban's stored in local storage.
                     */
                    getKanbans() {
                        let kanbans = localStorage.getItem(
                            this.getKanbansStorageKey()
                        );

                        return JSON.parse(kanbans) ?? [];
                    },

                    /**
                     * Sets the kanban's in local storage.
                     *
                     * @param {Array} kanbans - Kanban's to be stored in local storage.
                     * @returns {void}
                     */
                    setKanbans(kanbans) {
                        localStorage.setItem(
                            this.getKanbansStorageKey(),
                            JSON.stringify(kanbans)
                        );
                    },

                    /**
                     * Toggle the visibility of won/lost stages and refetch data accordingly
                     * This method optimizes performance by only fetching data for visible stages
                     */
                    toggleWonLost() {
                        this.hideWonLost = !this.hideWonLost;
                        this.updateKanbans();

                        // Store pipeline-specific setting
                        const pipelineSpecificKey = `kanban_hideWonLost_pipeline_${this.currentPipelineId}`;
                        localStorage.setItem(pipelineSpecificKey, JSON.stringify(this.hideWonLost));

                        // Update button text
                        this.setWonLostButtonText();

                        // Clear existing data and refetch with new exclude_won_lost parameter
                        this.stageLeads = {};
                        this.get()
                            .then(response => {
                                if (response && response.data) {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        this.stageLeads[sortOrder] = data;
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error toggling won/lost stages:', error);
                                // Revert the toggle if there's an error
                                this.hideWonLost = !this.hideWonLost;
                                this.updateKanbans();

                                // Update button text back
                                this.setWonLostButtonText();
                            });
                    },

                    /**
                     * Sync toggle button label with state.
                     */
                    setWonLostButtonText() {
                        this.$emitter.emit('kanban-wonlost-updated', this.hideWonLost);
                    },

                    /**
                     * Toggle duplicate detection and refetch data
                     */
                    toggleDuplicates() {
                        this.showDuplicates = !this.showDuplicates;
                        this.updateKanbans();
                        this.$emitter.emit('kanban-duplicates-updated');

                        this.stageLeads = {};
                        this.get()
                            .then(response => {
                                if (response && response.data) {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        this.stageLeads[sortOrder] = data;
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error toggling duplicates:', error);
                                this.showDuplicates = !this.showDuplicates;
                                this.updateKanbans();
                                this.$emitter.emit('kanban-duplicates-updated');
                            });
                    },
                }
            });
        });
    </script>
@endPushOnce
