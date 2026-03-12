@props([
    'activitiesCount',
    'showOrders' => false,
    'showMarketing' => true,
    'showAnamnesis' => true,
    'showPartnerProducts' => false,
    'showResources' => false,
    'showLeads' => false,
    'showSales' => false,
    'showPatientMessages' => false,
    'showAfletteren' => false,
    'showPayments' => false,
])
<!-- Vertical Navigation Menu -->
<div class="border-t border-gray-200 p-4 dark:border-gray-800">
    <nav class="flex flex-col gap-1 text-sm font-medium">
        <button type="button" class="flex items-center gap-2 rounded-md px-3 py-2 text-left transition"
                :class="leadDetailSection === 'algemeen'
                            ? 'bg-brandColor text-white dark:bg-brandColor'
                            : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                @click="setSection('algemeen')"
        >
            <span class="icon-user text-xl"></span>
            Algemeen
        </button>

        @if ($showPatientMessages)
            <button type="button" class="flex items-center gap-2 rounded-md px-3 py-2 text-left transition"
                    :class="leadDetailSection === 'patient-berichten'
                                ? 'bg-brandColor text-white dark:bg-brandColor'
                                : 'text-gray-600 hover:bg-neutral-bg dark:text-gray-300 dark:hover:bg-gray-800'"
                    @click="setSection('patient-berichten')"
            >
                <span class="icon-patient-message text-xl"></span>
                Patientberichten
            </button>
        @endif

        <button type="button" class="flex justify-between items-center rounded-md px-3 py-2 text-left transition"
                :class="leadDetailSection === 'activiteiten'
                            ?
                            'bg-brandColor text-white dark:bg-brandColor' :
                            'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                @click="setSection('activiteiten')">
            <div class="flex items-center gap-2">
                <span class="icon-activity text-xl"></span>
                Activiteiten
            </div>
            <span class="flex items-center justify-center h-5 w-5 rounded text-xs font-semibold"
                  :class="leadDetailSection === 'activiteiten' ? 'bg-error text-white' : 'bg-red-100 text-red-600'"
            >{{ $activitiesCount }}</span>
        </button>

        @if ($showAfletteren)
            <button type="button" class="flex items-center gap-2 rounded-md px-3 py-2 text-left transition"
                    :class="leadDetailSection === 'afletteren'
                                ? 'bg-brandColor text-white dark:bg-brandColor'
                                : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                    @click="setSection('afletteren')"
            >
                <span class="icon-stats text-xl"></span>
                Afletteren
            </button>
        @endif

        @if ($showPayments)
            <button type="button" class="flex items-center gap-2 rounded-md px-3 py-2 text-left transition"
                    :class="leadDetailSection === 'betalingen'
                                ? 'bg-brandColor text-white dark:bg-brandColor'
                                : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                    @click="setSection('betalingen')"
            >
                <span class="icon-stats text-xl"></span>
                Betalingen Klant
            </button>
        @endif

        @if ($showPartnerProducts)
            <button type="button" class="flex items-center gap-2 rounded-md px-3 py-2 text-left transition"
                    :class="leadDetailSection === 'partner-products'
                                ? 'bg-brandColor text-white dark:bg-brandColor'
                                : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                    @click="setSection('partner-products')"
            >
                <span class="icon-product text-xl"></span>
                Partner Producten
            </button>
        @endif

        @if ($showResources)
            <button type="button" class="flex items-center gap-2 rounded-md px-3 py-2 text-left transition"
                    :class="leadDetailSection === 'resources'
                                ? 'bg-brandColor text-white dark:bg-brandColor'
                                : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                    @click="setSection('resources')"
            >
                <span class="icon-setting text-xl"></span>
                Resources
            </button>
        @endif

        @if ($showAnamnesis)
        <button type="button" class="flex items-center gap-2 rounded-md px-3 py-2 text-left transition"
                :class="leadDetailSection === 'anamnese'
                            ? 'bg-brandColor text-white dark:bg-brandColor'
                            : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                @click="setSection('anamnese')"
        >
            <span class="icon-anamnesis text-xl"></span>
            Anamnese
        </button>
        @endif
        @if ($showOrders)
            <button type="button" class="flex items-center gap-2 rounded-md px-3 py-2 text-left transition"
                    :class="leadDetailSection === 'orders'
                                ? 'bg-brandColor text-white dark:bg-brandColor'
                                : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                    @click="setSection('orders')"
            >
              <span class="icon-order text-xl"></span>
                Orders
            </button>
        @endif
        @if ($showMarketing)
        <button type="button" class="flex items-center gap-2 rounded-md px-3 py-2 text-left transition"
                :class="leadDetailSection === 'marketing'
                            ? 'bg-brandColor text-white dark:bg-brandColor'
                            : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                @click="setSection('marketing')"
        >
            <span class="icon-stats text-xl"></span>
            Marketing
        </button>
        @endif
        @if ($showLeads)
            <button type="button" class="flex items-center gap-2 rounded-md px-3 py-2 text-left transition"
                    :class="leadDetailSection === 'leads'
                            ? 'bg-brandColor text-white dark:bg-brandColor'
                            : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                    @click="setSection('leads')"
            >
                <span class="icon-stats text-xl"></span>
                Leads
            </button>
        @endif
        @if ($showSales)
            <button type="button" class="flex items-center gap-2 rounded-md px-3 py-2 text-left transition"
                    :class="leadDetailSection === 'sales'
                            ? 'bg-brandColor text-white dark:bg-brandColor'
                            : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                    @click="setSection('sales')"
            >
                <span class="icon-sales text-xl"></span>
                Sales
            </button>
        @endif
    </nav>
</div>
