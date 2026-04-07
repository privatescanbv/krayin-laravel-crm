<x-admin::layouts>
    <x-slot:title>
        Dagplanning
    </x-slot>

    <div class="flex items-center justify-between text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 backdrop-blur-md pt-4 sticky top-16 z-10">
        <div class="flex flex-col">
            <x-admin::breadcrumbs name="clinic-guide" />

            <div class="text-xl font-bold dark:text-white">
                Dagplanning
            </div>
        </div>
    </div>

    <v-clinic-guide></v-clinic-guide>

    <div class="hidden">
        <x-admin::activities.actions.activity
            :entity="(object)['id' => null]"
            entityControlName="person_id"
        />
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-clinic-guide-template"
        >
            <div class="mt-4">
                <!-- Date navigation -->
                <div class="flex items-center justify-center gap-4 mb-6 bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                    <button
                        @click="previousDay"
                        class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Vorige dag
                    </button>

                    <div class="flex items-center gap-3">
                        <input
                            type="date"
                            v-model="selectedDate"
                            @change="fetchData"
                            class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
{{--                        <span class="text-lg font-semibold text-gray-800 dark:text-gray-200">--}}
{{--                            @{{ formattedDate }}--}}
{{--                        </span>--}}
                    </div>

                    <button
                        @click="nextDay"
                        class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        Volgende dag
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <button
                        @click="goToToday"
                        class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        Vandaag
                    </button>
                </div>

                <!-- Loading state -->
                <div v-if="loading" class="flex items-center justify-center py-12">
                    <svg class="animate-spin h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span class="ml-3 text-gray-600 dark:text-gray-400">Laden...</span>
                </div>

                <!-- Empty state -->
                <div v-if="!loading && orders.length === 0" class="flex flex-col items-center justify-center py-16 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                    <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-lg font-medium text-gray-500 dark:text-gray-400">Geen afspraken op deze dag</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Selecteer een andere datum om afspraken te bekijken</p>
                </div>

                <!-- Orders count -->
                <div v-if="!loading && orders.length > 0" class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    @{{ orders.length }} afspra@{{ orders.length === 1 ? 'ak' : 'ken' }} gevonden
                </div>

                <!-- Orders grid -->
                <div v-if="!loading && orders.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div
                        v-for="item in orders"
                        :key="`${item.order.id}-${item.patient?.id ?? 'np'}`"
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow overflow-hidden flex flex-col"
                    >
                        <!-- Time header -->
                        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-750 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100">@{{ item.order.time }}</span>
                            </div>

                            <span
                                v-if="item.patient"
                                @click="openActivityModal(item.patient)"
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] text-activity-task-text bg-activity-task-bg font-semibold border cursor-pointer"
                            >
                                <span class="icon-activity text-sm text-activity-task-text mr-1 cursor-pointer"></span>
                                Activiteit
                            </span>
                        </div>

                        <div class="p-4 flex flex-col flex-1 gap-3">
                            <!-- Patient info -->
                            <div v-if="item.patient" class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-md font-bold text-gray-900 dark:text-gray-100 truncate">
                                        @{{ item.patient.name }}
                                    </h3>
                                    <span v-if="item.patient.age" class="text-sm text-gray-500 dark:text-gray-400">
                                        (@{{ item.patient.age }} jaar)
                                    </span>
                                </div>

                                <div v-if="item.patient.date_of_birth" class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    @{{ item.patient.date_of_birth }}
                                </div>

                                <div v-if="item.patient.phones && item.patient.phones.length" class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    <a :href="'tel:' + item.patient.phones[0].value" class="hover:text-indigo-600">
                                        @{{ item.patient.phones[0].value }}
                                    </a>
                                </div>


                            </div>

                            <div v-if="!item.patient" class="text-sm text-gray-400 dark:text-gray-500 italic">
                                Geen patiënt gekoppeld
                            </div>

                            <!-- Order items -->
                            <div v-if="item.order_items && item.order_items.length > 0">
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Producten</p>
                                <ul class="space-y-0.5">
                                    <li
                                        v-for="(oi, idx) in item.order_items"
                                        :key="idx"
                                        class="text-sm text-gray-700 dark:text-gray-300 flex items-center gap-1"
                                    >
                                        <span class="w-1 h-1 bg-gray-400 rounded-full flex-shrink-0"></span>
                                        <span>@{{ oi.product_name || 'Onbekend product' }}</span>
                                        <span v-if="oi.quantity > 1" class="text-gray-400">x@{{ oi.quantity }}</span>
                                        <span v-if="oi.start_time" class="text-gray-400 ml-1">(@{{ oi.start_time }})</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Links -->
                            <div>
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Links</p>
                                <template v-if="item.afb_documents && item.afb_documents.length">
                                    <div v-for="doc in item.afb_documents" :key="doc.url" class="flex items-center gap-1.5 text-sm">
                                        <i class="icon-activity"></i>
                                        <a :href="doc.url" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                            AFB formulier<template v-if="doc.label"> – @{{ doc.label }}</template>
                                        </a>
                                    </div>
                                </template>
                                <div v-else class="flex items-center gap-1.5 text-sm">
                                    <i class="icon-activity text-gray-400"></i>
                                    <span class="text-gray-400">AFB formulier (nog niet verzonden)</span>
                                </div>
                                <div v-if="item.gvl_form_link" class="flex items-center gap-1.5 text-sm">
                                    <i class="icon-activity"></i>
                                    <a :href="item.gvl_form_link" target="_blank" class="hover:text-indigo-600">
                                        GVL Formulier
                                    </a>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="pt-3 border-t border-gray-100 dark:border-gray-700 mt-auto flex items-center justify-between">
                                <a
                                    v-if="item.order_url"
                                    :href="item.order_url"
                                    class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors"
                                >
                                    Bekijk order
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Activiteit modal (gedeeld, één instantie) -->
                <v-activity
                    ref="activityModal"
                    :entity="activeActivityPerson"
                    entity-control-name="person_id"
                ></v-activity>
            </div>
        </script>

        <script type="module">
            app.component('v-clinic-guide', {
                template: '#v-clinic-guide-template',

                data() {
                    return {
                        selectedDate: new Date().toISOString().slice(0, 10),
                        orders: [],
                        loading: false,
                        activeActivityPerson: { id: null },
                    };
                },

                computed: {
                    formattedDate() {
                        const d = new Date(this.selectedDate + 'T00:00:00');
                        const days = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
                        const months = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
                        return `${days[d.getDay()]} ${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
                    },
                },

                mounted() {
                    this.fetchData();
                },

                methods: {
                    async fetchData() {
                        this.loading = true;
                        try {
                            const response = await fetch(`{{ route('admin.clinic-guide.get') }}?date=${this.selectedDate}`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });
                            const data = await response.json();
                            this.orders = data.orders || [];
                        } catch (error) {
                            console.error('Error fetching clinic guide data:', error);
                            this.orders = [];
                        } finally {
                            this.loading = false;
                        }
                    },

                    formatLocalDate(d) {
                        const y = d.getFullYear();
                        const m = String(d.getMonth() + 1).padStart(2, '0');
                        const day = String(d.getDate()).padStart(2, '0');
                        return `${y}-${m}-${day}`;
                    },

                    previousDay() {
                        const d = new Date(this.selectedDate + 'T12:00:00');
                        d.setDate(d.getDate() - 1);
                        this.selectedDate = this.formatLocalDate(d);
                        this.fetchData();
                    },

                    nextDay() {
                        const d = new Date(this.selectedDate + 'T12:00:00');
                        d.setDate(d.getDate() + 1);
                        this.selectedDate = this.formatLocalDate(d);
                        this.fetchData();
                    },

                    goToToday() {
                        this.selectedDate = this.formatLocalDate(new Date());
                        this.fetchData();
                    },

                    openActivityModal(person) {
                        this.activeActivityPerson = person;
                        this.$nextTick(() => {
                            this.$refs.activityModal.openModal();
                        });
                    },

                    stageBadgeClass(stage) {
                        if (stage.is_lost) {
                            return 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800';
                        }
                        if (stage.is_won) {
                            return 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800';
                        }
                        return 'bg-indigo-50 text-indigo-700 border-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800';
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
