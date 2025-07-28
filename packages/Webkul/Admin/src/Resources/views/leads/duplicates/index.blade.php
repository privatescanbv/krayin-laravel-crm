<x-admin::layouts>
    <x-slot:title>
        Merge Duplicates - {{ $lead->title }}
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Hidden form for CSRF token -->
        <form id="csrf-form" style="display: none;">
            @csrf
        </form>

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.leads.view', $lead->id) }}" class="icon-arrow-left text-2xl"></a>
                <h1 class="text-xl font-bold">Merge Duplicate Leads</h1>
            </div>
        </div>

        <!-- Primary Lead Info -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-lg font-semibold text-green-600">Primary Lead</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <span class="text-sm font-medium text-gray-600">Title:</span>
                    <p class="text-sm">{{ $lead->title }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600">Name:</span>
                    <p class="text-sm">{{ $lead->first_name }} {{ $lead->last_name }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600">Stage:</span>
                    <p class="text-sm">{{ $lead->stage ? $lead->stage->name : 'N/A' }}</p>
                </div>
            </div>
        </div>

        @if($duplicates->count() > 0)
            <!-- Duplicates Management Vue Component -->
            <v-duplicates-manager
                :primary-lead="{{ json_encode($leadData) }}"
                :duplicates="{{ json_encode($duplicatesData) }}"
                merge-url="{{ route('admin.leads.duplicates.merge', $lead->id) }}"
                redirect-url="{{ route('admin.leads.view', $lead->id) }}"
                csrf-token="{{ csrf_token() }}"
            >
                <!-- Loading State -->
                <div class="flex items-center justify-center p-8">
                    <div class="text-center">
                        <div class="mb-4 h-8 w-8 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
                        <p>Loading duplicates...</p>
                    </div>
                </div>
            </v-duplicates-manager>
        @else
            <!-- No Duplicates Found -->
            <div class="rounded-lg border border-gray-200 bg-white p-8 text-center dark:border-gray-800 dark:bg-gray-900">
                <div class="mx-auto mb-4 h-16 w-16 rounded-full bg-green-100 flex items-center justify-center">
                    <span class="icon-check text-2xl text-green-600"></span>
                </div>
                <h3 class="mb-2 text-lg font-semibold">No Duplicates Found</h3>
                <p class="text-gray-600">No potential duplicate leads were found for this lead.</p>
                <a href="{{ route('admin.leads.view', $lead->id) }}" class="mt-4 inline-block rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                    Back to Lead
                </a>
            </div>
        @endif
    </div>

    @pushOnce('scripts')
        <script>
            // Make CSRF token globally available
            window.csrfToken = '{{ csrf_token() }}';
        </script>

        <script type="text/x-template" id="v-duplicates-manager-template">
            <div class="flex flex-col gap-4">
                <!-- Duplicates List -->
                <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                        <h3 class="text-lg font-semibold text-orange-600">
                            Mogelijke duplicaten (@{{ duplicates.length }})
                        </h3>
                        <p class="text-sm text-gray-600">Selecteer leads om samen te voegen en kies welke veldwaarden behouden blijven.</p>
                    </div>

                    <div class="p-4">
                        <!-- Field Comparison Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse table-fixed">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="w-32 p-3 text-left font-semibold">Field</th>
                                        <th class="p-3 text-center text-green-600 min-w-48">
                                            <div class="flex flex-col items-center">
                                                <input
                                                    type="checkbox"
                                                    :checked="selectedLeads.includes(primaryLead.id)"
                                                    @change="toggleLeadSelection(primaryLead.id)"
                                                    class="mb-2"
                                                />
                                                <span class="font-semibold">Primaire lead</span>
                                                <span class="text-xs text-gray-500">ID: @{{ primaryLead.id }}</span>
                                            </div>
                                        </th>
                                        <th
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3 text-center min-w-48"
                                            :class="{ 'text-blue-600': selectedLeads.includes(duplicate.id) }"
                                        >
                                            <div class="flex flex-col items-center">
                                                <input
                                                    type="checkbox"
                                                    :checked="selectedLeads.includes(duplicate.id)"
                                                    @change="toggleLeadSelection(duplicate.id)"
                                                    class="mb-2"
                                                />
                                                <span class="font-semibold">Dubbele lead</span>
                                                <span class="text-xs text-gray-500">ID: @{{ duplicate.id }}</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Title Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Titel</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="title"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.title"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ primaryLead.title }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="title"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.title"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ duplicate.title }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Pipeline Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Pipeline</td>
                                        <td class="p-3 text-center">
                                            <span class="text-sm">@{{ primaryLead.pipeline?.name || 'N/A' }}</span>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3 text-center"
                                        >
                                            <span class="text-sm">@{{ duplicate.pipeline?.name || 'N/A' }}</span>
                                        </td>
                                    </tr>

                                    <!-- Stage Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Fase</td>
                                        <td class="p-3 text-center">
                                            <span class="text-sm">@{{ primaryLead.stage?.name || 'N/A' }}</span>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3 text-center"
                                        >
                                            <span class="text-sm">@{{ duplicate.stage?.name || 'N/A' }}</span>
                                        </td>
                                    </tr>

                                    <!-- First Name Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Voornaam</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="first_name"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.first_name"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ primaryLead.first_name }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="first_name"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.first_name"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ duplicate.first_name }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Last Name Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Achternaam</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="last_name"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.last_name"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ primaryLead.last_name }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="last_name"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.last_name"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ duplicate.last_name }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Emails Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">E-mailadressen</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="emails"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.emails"
                                                    class="mb-2"
                                                />
                                                <div class="text-xs text-center">
                                                    <div v-for="email in primaryLead.emails" :key="email.value" class="mb-1">
                                                        @{{ email.value }}
                                                    </div>
                                                    <span v-if="!primaryLead.emails || primaryLead.emails.length === 0" class="text-gray-400">Geen e-mails</span>
                                                </div>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="emails"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.emails"
                                                    class="mb-2"
                                                />
                                                <div class="text-xs text-center">
                                                    <div v-for="email in duplicate.emails" :key="email.value" class="mb-1">
                                                        @{{ email.value }}
                                                    </div>
                                                    <span v-if="!duplicate.emails || duplicate.emails.length === 0" class="text-gray-400">Geen e-mails</span>
                                                </div>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Phones Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Telefoonnummers</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="phones"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.phones"
                                                    class="mb-2"
                                                />
                                                <div class="text-xs text-center">
                                                    <div v-for="phone in primaryLead.phones" :key="phone.value" class="mb-1">
                                                        @{{ phone.value }}
                                                    </div>
                                                    <span v-if="!primaryLead.phones || primaryLead.phones.length === 0" class="text-gray-400">Geen telefoonnummers</span>
                                                </div>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="phones"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.phones"
                                                    class="mb-2"
                                                />
                                                <div class="text-xs text-center">
                                                    <div v-for="phone in duplicate.phones" :key="phone.value" class="mb-1">
                                                        @{{ phone.value }}
                                                    </div>
                                                    <span v-if="!duplicate.phones || duplicate.phones.length === 0" class="text-gray-400">Geen telefoonnummers</span>
                                                </div>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Married Name Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Gehuwde naam</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="married_name"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.married_name"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ primaryLead.married_name || 'N/A' }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="married_name"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.married_name"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ duplicate.married_name || 'N/A' }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Lastname Prefix Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Voorvoegsel achternaam</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="lastname_prefix"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.lastname_prefix"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ primaryLead.lastname_prefix || 'N/A' }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="lastname_prefix"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.lastname_prefix"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ duplicate.lastname_prefix || 'N/A' }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Married Name Prefix Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Voorvoegsel gehuwde naam</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="married_name_prefix"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.married_name_prefix"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ primaryLead.married_name_prefix || 'N/A' }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="married_name_prefix"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.married_name_prefix"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ duplicate.married_name_prefix || 'N/A' }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Initials Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Initialen</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="initials"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.initials"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ primaryLead.initials || 'N/A' }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="initials"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.initials"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ duplicate.initials || 'N/A' }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Date of Birth Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Geboortedatum</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="date_of_birth"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.date_of_birth"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ primaryLead.date_of_birth || 'N/A' }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="date_of_birth"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.date_of_birth"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ duplicate.date_of_birth || 'N/A' }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Gender Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Geslacht</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="gender"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.gender"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ primaryLead.gender || 'N/A' }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="gender"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.gender"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ duplicate.gender || 'N/A' }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Lead Value Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Lead waarde</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="lead_value"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.lead_value"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ primaryLead.lead_value || 'N/A' }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="lead_value"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.lead_value"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ duplicate.lead_value || 'N/A' }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Status Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Status</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="status"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.status"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ primaryLead.stage?.name || 'N/A' }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="status"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.status"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words">@{{ duplicate.stage?.name || 'N/A' }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Description Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Beschrijving</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="description"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.description"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words max-w-xs">@{{ primaryLead.description || 'N/A' }}</span>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="description"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.description"
                                                    class="mb-2"
                                                />
                                                <span class="text-sm text-center break-words max-w-xs">@{{ duplicate.description || 'N/A' }}</span>
                                            </label>
                                        </td>
                                    </tr>

                                    <!-- Address Row -->
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="p-3 font-medium bg-gray-50 dark:bg-gray-800">Adres</td>
                                        <td class="p-3">
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="address"
                                                    :value="primaryLead.id"
                                                    v-model="fieldMappings.address"
                                                    class="mb-2"
                                                />
                                                <div class="text-xs text-center">
                                                    <div v-if="primaryLead.address" class="mb-1">
                                                        <div>@{{ primaryLead.address.full_address || 'N/A' }}</div>
                                                    </div>
                                                    <span v-else class="text-gray-400">Geen adres</span>
                                                </div>
                                            </label>
                                        </td>
                                        <td
                                            v-for="duplicate in duplicates"
                                            :key="duplicate.id"
                                            class="p-3"
                                        >
                                            <label class="flex flex-col items-center">
                                                <input
                                                    type="radio"
                                                    name="address"
                                                    :value="duplicate.id"
                                                    v-model="fieldMappings.address"
                                                    class="mb-2"
                                                />
                                                <div class="text-xs text-center">
                                                    <div v-if="duplicate.address" class="mb-1">
                                                        <div>@{{ duplicate.address.full_address || 'N/A' }}</div>
                                                        <div v-if="duplicate.address.street && duplicate.address.house_number">
                                                            @{{ duplicate.address.street }} @{{ duplicate.address.house_number }}@{{ duplicate.address.house_number_suffix || '' }}
                                                        </div>
                                                        <div v-if="duplicate.address.postal_code || duplicate.address.city">
                                                            @{{ duplicate.address.postal_code || '' }} @{{ duplicate.address.city || '' }}
                                                        </div>
                                                        <div v-if="duplicate.address.state || duplicate.address.country">
                                                            @{{ duplicate.address.state || '' }} @{{ duplicate.address.country || '' }}
                                                        </div>
                                                    </div>
                                                    <span v-else class="text-gray-400">No address</span>
                                                </div>
                                            </label>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-medium">Selected:</span> @{{ selectedLeads.length }} lead(s) for merging
                                    <div v-if="selectedLeads.length < 2" class="mt-1 text-xs text-orange-600">
                                        Select at least one duplicate to merge
                                    </div>
                                </div>
                                <div class="flex gap-3">
                                    <a
                                        :href="redirectUrl"
                                        class="rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        Cancel
                                    </a>
                                    <button
                                        @click="mergeLeads"
                                        :disabled="selectedLeads.length < 2 || isLoading"
                                        class="rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                                    >
                                        <span v-if="isLoading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                                        <span v-if="isLoading">Merging...</span>
                                        <span v-else>Merge Selected Leads</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-duplicates-manager', {
                template: '#v-duplicates-manager-template',
                props: ['primaryLead', 'duplicates', 'mergeUrl', 'redirectUrl', 'csrfToken'],
                data() {
                    return {
                        selectedLeads: [this.primaryLead.id], // Primary lead is always selected
                        fieldMappings: {
                            title: this.primaryLead.id,
                            first_name: this.primaryLead.id,
                            last_name: this.primaryLead.id,
                            married_name: this.primaryLead.id,
                            lastname_prefix: this.primaryLead.id,
                            married_name_prefix: this.primaryLead.id,
                            initials: this.primaryLead.id,
                            date_of_birth: this.primaryLead.id,
                            gender: this.primaryLead.id,
                            lead_value: this.primaryLead.id,
                            status: this.primaryLead.id,
                            description: this.primaryLead.id,
                            address: this.primaryLead.id,
                            emails: this.primaryLead.id,
                            phones: this.primaryLead.id,
                        },
                        isLoading: false,
                    };
                },
                mounted() {
                    // Ensure all leads have proper structure
                    console.log('Primary lead:', this.primaryLead);
                    console.log('Duplicates:', this.duplicates);
                    console.log('CSRF token from props:', this.csrfToken);

                    // Test CSRF token availability
                    const metaToken = document.querySelector('meta[name="csrf-token"]');
                    console.log('Meta CSRF token element:', metaToken);
                    console.log('Meta CSRF token value:', metaToken ? metaToken.getAttribute('content') : 'not found');

                    const formToken = document.querySelector('#csrf-form input[name="_token"]');
                    console.log('Form CSRF token element:', formToken);
                    console.log('Form CSRF token value:', formToken ? formToken.value : 'not found');
                },
                methods: {
                    toggleLeadSelection(leadId) {
                        if (leadId === this.primaryLead.id) {
                            // Primary lead must always be selected
                            return;
                        }

                        const index = this.selectedLeads.indexOf(leadId);
                        if (index > -1) {
                            this.selectedLeads.splice(index, 1);
                        } else {
                            this.selectedLeads.push(leadId);
                        }
                    },
                    async mergeLeads() {
                        if (this.selectedLeads.length < 2) {
                            alert('Please select at least one duplicate lead to merge.');
                            return;
                        }

                        if (!confirm('Are you sure you want to merge these leads? This action cannot be undone.')) {
                            return;
                        }

                        this.isLoading = true;

                        try {
                            const duplicateIds = this.selectedLeads.filter(id => id !== this.primaryLead.id);

                            // Get CSRF token with fallback methods
                            let csrfToken = this.csrfToken;

                            // Fallback 1: Try meta tag
                            if (!csrfToken) {
                                const csrfTokenElement = document.querySelector('meta[name="csrf-token"]');
                                csrfToken = csrfTokenElement ? csrfTokenElement.getAttribute('content') : '';
                            }

                            // Fallback 2: Try Laravel.csrfToken if available
                            if (!csrfToken && typeof window.Laravel !== 'undefined' && window.Laravel.csrfToken) {
                                csrfToken = window.Laravel.csrfToken;
                            }

                            // Fallback 3: Try hidden form CSRF token
                            if (!csrfToken) {
                                const csrfInput = document.querySelector('#csrf-form input[name="_token"]');
                                csrfToken = csrfInput ? csrfInput.value : '';
                            }

                            // Fallback 4: Try global window.csrfToken
                            if (!csrfToken && window.csrfToken) {
                                csrfToken = window.csrfToken;
                            }

                            if (!csrfToken) {
                                throw new Error('CSRF token not found. Please refresh the page and try again.');
                            }

                            const response = await fetch(this.mergeUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    primary_lead_id: this.primaryLead.id,
                                    duplicate_lead_ids: duplicateIds,
                                    field_mappings: this.fieldMappings,
                                }),
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }

                            const result = await response.json();

                            if (result.success) {
                                window.location.href = this.redirectUrl;
                            } else {
                                alert('Error merging leads: ' + (result.message || 'Unknown error occurred'));
                            }
                        } catch (error) {
                            console.error('Merge error:', error);
                            alert('Error merging leads: ' + error.message);
                        } finally {
                            this.isLoading = false;
                        }
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
