@php use App\Enums\ContactLabel;use App\Models\Department;use App\Models\User; @endphp
<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.create.title')
    </x-slot>

    {!! view_render_event('admin.leads.create.form.before') !!}

    <!-- Two-Step Lead Form -->
    <v-two-step-lead-form :initial-persons='@json($prefilledPersons ?? [])'
                          :initial-lead-person='@json($prefilledLeadPerson ?? null)'
                          :user-defaults='@json((object) ($userDefaults ?? []))'></v-two-step-lead-form>

    {!! view_render_event('admin.leads.create.form.after') !!}

    @pushOnce('scripts')
        <script type="text/x-template" id="v-multiple-persons-component-template">
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold dark:text-white">
                        Contactpersonen (@{{ persons.length }})
                    </h3>
                    <button
                        v-on:click="addPerson"
                        type="button"
                        class="secondary-button"
                    >
                        <i class="icon-plus text-xs"></i>
                        Toevoegen
                    </button>
                </div>

                <!-- Persons List -->
                <div v-if="persons.length > 0" class="space-y-2">
                    <div
                        v-for="(person, index) in persons"
                        :key="index"
                        class="flex items-center justify-between p-3 rounded-lg border"
                        :class="getPersonCardClass(person)"
                    >
                        <div class="flex items-center gap-3 flex-1">
                            <!-- Person Avatar -->
                            <div
                                class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-semibold"
                                :class="getAvatarClass(person)"
                            >
                                @{{ getPersonInitials(person) }}
                            </div>

                            <!-- Person Info -->
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-sm dark:text-white">
                                        @{{ person.name || 'Nieuwe persoon' }}
                                    </span>

                                    <!-- Match Percentage -->
                                    <span
                                        v-if="person.match_percentage"
                                        class="px-2 py-1 text-xs rounded-full font-medium"
                                        :class="getMatchBadgeClass(person.match_percentage)"
                                    >
                                        @{{ Math.round(person.match_percentage || 0) }}% match
                                    </span>
                                </div>

                                <!-- Organization -->
                                <div v-if="person.organization" class="text-xs text-gray-500 dark:text-gray-400">
                                    @{{ person.organization.name }}
                                </div>
                            </div>

                            <!-- Person Lookup (for new persons) -->
                            <div v-if="!person.id" class="flex-1 max-w-xs">
                                <x-admin::lookup
                                    ::src="`{{ route('admin.contacts.persons.search') }}`"
                                    ::name="`person_ids[${index}]`"
                                    :label="'Naam'"
                                    placeholder="Zoek persoon..."
                                    v-on:on-selected="onLookupSelected(index, $event)"
                                    :can-add-new="true"
                                />
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <!-- View Person (if existing) -->
                            <a
                                v-if="person.id"
                                :href="`/admin/contacts/persons/view/${person.id}`"
                                target="_blank"
                                class="text-blue-600 hover:text-blue-800 p-1"
                                title="Bekijk persoon"
                            >
                                <i class="icon-eye text-sm"></i>
                            </a>

                            <!-- Remove Person -->
                            <button
                                v-on:click="removePerson(index)"
                                type="button"
                                class="text-red-600 hover:text-red-800 p-1"
                                title="Verwijder persoon"
                            >
                                <i class="icon-trash text-sm"></i>
                            </button>
                        </div>

                        <!-- Hidden form fields -->
                        <input
                            type="hidden"
                            ::name="`person_ids[${index}]`"
                            ::value="person.id"
                            v-if="person.id"
                        />
                        <input
                            type="hidden"
                            ::name="`persons[${index}][id]`"
                            ::value="person.id"
                            v-if="person.id"
                        />
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="persons.length === 0"
                     class="text-center py-6 text-gray-500 dark:text-gray-400 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                    <i class="icon-users text-3xl mb-2"></i>
                    <p class="font-medium">Geen contactpersonen gekoppeld</p>
                    <p class="text-sm">Klik op "Toevoegen" om contactpersonen te koppelen</p>
                </div>
            </div>
        </script>

        <script type="text/x-template" id="v-two-step-lead-form-template">
            <div class="flex flex-col gap-4" v-cloak>
                <!-- Header -->
                <div
                    class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <div class="flex flex-col gap-2">
                        <x-admin::breadcrumbs name="leads.create"/>
                        <div class="text-xl font-bold dark:text-white">
                            @lang('admin::app.leads.create.title')
                        </div>
                    </div>

                    <div class="flex items-center gap-x-2.5" v-show="currentStep === 2">
                        <button
                            v-on:click="goToStep(1)"
                            type="button"
                            class="secondary-button"
                        >
                            Terug
                        </button>

                        <button
                            v-on:click="submitForm"
                            type="button"
                            class="primary-button"
                            :disabled="isSubmitting"
                        >
                            @{{ isSubmitting ? 'Bezig...' : 'Opslaan' }}
                        </button>
                    </div>
                </div>

                <!-- Step Indicator -->
                <div class="flex items-center justify-center space-x-4 py-4">
                    <div class="flex items-center">
                        <div
                            class="flex items-center justify-center w-8 h-8 rounded-full text-white text-sm font-medium"
                            :class="currentStep >= 1 ? 'bg-blue-600' : 'bg-gray-400'">
                            1
                        </div>
                        <span class="ml-2 text-sm font-medium"
                              :class="currentStep >= 1 ? 'text-blue-600' : 'text-gray-500'">
                            Contactpersonen koppelen
                        </span>
                    </div>
                    <div class="w-16 h-0.5" :class="currentStep >= 2 ? 'bg-blue-600' : 'bg-gray-300'"></div>
                    <div class="flex items-center">
                        <div
                            class="flex items-center justify-center w-8 h-8 rounded-full text-white text-sm font-medium"
                            :class="currentStep >= 2 ? 'bg-blue-600' : 'bg-gray-400'">
                            2
                        </div>
                        <span class="ml-2 text-sm font-medium"
                              :class="currentStep >= 2 ? 'text-blue-600' : 'text-gray-500'">
                            Lead gegevens
                        </span>
                    </div>
                </div>

                <!-- Step 1: Contact Matcher -->
                <div v-show="currentStep === 1"
                     class="box-shadow rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 p-6">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-1">
                            <p class="text-xl font-semibold dark:text-white">
                                Stap 1: Contactpersonen koppelen
                            </p>
                            <p class="text-gray-600 dark:text-white">
                                Koppel een of meerdere contactpersonen aan deze lead (optioneel)
                            </p>
                        </div>

                        <!-- Multi Contact Matcher (based on original contactmatcher) -->
                        <div>
                            <x-adminc::components.multi-contactmatcher
                                :lead="new Webkul\Lead\Models\Lead()"
                                :persons="$prefilledPersons ?? []"
                            />
                        </div>

                        <div class="flex justify-end pt-4">
                            <button
                                v-on:click="goToStep(2)"
                                type="button"
                                class="primary-button"
                            >
                                Verder naar stap 2
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Full Lead Form -->
                <div v-show="currentStep === 2" style="display: none;">
                    <form @submit.prevent="submitForm" ref="leadForm">
                        @csrf
                        <input type="hidden" name="lead_pipeline_stage_id" value="{{ request('stage_id') }}"/>


                        <div
                            class="box-shadow flex flex-col gap-4 rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 p-6">
                            <div class="flex flex-col gap-1">
                                <p class="text-xl font-semibold dark:text-white">
                                    Stap 2: Lead gegevens
                                </p>

                                <!-- Show selected persons info if available -->
                                <div v-if="hasSelectedPersons"
                                     class="mt-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span class="font-medium text-green-800">Contactpersonen gekoppeld:</span>
                                        <span class="text-green-700">@{{ joinedPersonNames }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Lead Details -->
                            <div class="flex flex-col gap-4">
                                <div class="w-1/2 max-md:w-full">

                                    <!-- Personal Fields (full, same as edit) -->
                                    <div class="flex flex-col gap-4 mb-4">
                                        <p class="text-base font-semibold dark:text-white">Persoonsgegevens</p>
                                        @php
                                            $__defaults = [
                                                'salutation' => null,
                                                'initials' => null,
                                                'first_name' => null,
                                                'lastname_prefix' => null,
                                                'last_name' => null,
                                                'married_name_prefix' => null,
                                                'married_name' => null,
                                                'date_of_birth' => null,
                                                'gender' => null,
                                            ];
                                            $__entityPrefill = (object) array_merge($__defaults, ($prefilledLeadPerson ?? []));
                                        @endphp
                                        @include('admin::leads.common.personal-fields', ['entity' => $__entityPrefill, 'bindModel' => 'formData'])
                                    </div>

                                    <!-- Contact Person Selection (aligned with edit view) -->
                                    <div class="mt-4">
                                        @include('adminc.components.contact-person-selector')
                                        <v-contact-person-selector
                                            name="contact_person_id"
                                            label="Contactpersoon"
                                            placeholder="Selecteer contactpersoon..."
                                            :current-value="formData.contact_person_id || null"
                                            :current-label="formData.contact_person_label || ''"
                                            :can-add-new="true"
                                            @change="onContactPersonChange"
                                            @update:value="val => { formData.contact_person_id = val; }"
                                        />
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                            Selecteer de contactpersoon voor deze lead. Deze persoon kan ook voorkomen in de gekoppelde personen.
                                        </p>
                                    </div>

                                    <!-- Other attributes -->
                                    <div class="flex gap-4 max-sm:flex-wrap">
                                        <div class="w-full">
                                            <!-- Additional quick add lead attrs can be added here if needed -->
                                        </div>
                                    </div>

                                    <!-- Emails -->
                                    <div class="mt-4">
                                        @php
                                            $__emailsVal = old('emails', $prefilledLeadPerson['emails'] ?? []);
                                            if (!is_array($__emailsVal)) { $__emailsVal = []; }
                                            $__emailsVal = array_map(function($e) {
                                                if (!is_array($e)) { $e = []; }
                                                if (!isset($e['label']) || trim((string)$e['label']) === '') {
                                                    $e['label'] = ContactLabel::default()->value;
                                                }
                                                return $e;
                                            }, $__emailsVal);
                                        @endphp
                                        @include('admin::leads.common.sections.emails', ['name' => 'emails', 'value' => $__emailsVal, 'widthClass' => 'w-full'])
                                    </div>

                                    <!-- Phones -->
                                    <div class="mt-4">
                                        @php
                                            $__phonesVal = old('phones', $prefilledLeadPerson['phones'] ?? []);
                                            if (!is_array($__phonesVal)) { $__phonesVal = []; }
                                            $__phonesVal = array_map(function($p) {
                                                if (!is_array($p)) { $p = []; }
                                                if (!isset($p['label']) || trim((string)$p['label']) === '') {
                                                    $p['label'] = ContactLabel::default()->value;
                                                }
                                                return $p;
                                            }, $__phonesVal);
                                        @endphp
                                        @include('admin::leads.common.sections.phones', ['name' => 'phones', 'value' => $__phonesVal, 'widthClass' => 'w-full'])
                                    </div>

                                    <!-- Address -->
                                    <div class="mt-4">
                                        @include('admin::components.address', ['entity' => null])
                                    </div>

                                    <!-- Anamnese -->
                                    <div class="mt-6 pb-[20px]">
                                        <p class="text-base font-semibold dark:text-white">Anamnese</p>

                                        <!-- Heeft u metalen? -->
                                        <div class="mt-3">
                                            <x-admin::form.control-group>
                                                <x-admin::form.control-group.label>
                                                    Heeft u metalen?
                                                </x-admin::form.control-group.label>
                                                <div class="flex gap-4">
                                                    <label class="flex items-center">
                                                        <input type="radio" name="metals" value="1" required
                                                               @change="() => {$refs.metals_notes_container.style.display='block'; const n=$refs.metals_notes_container.querySelector('input[name=\'metals_notes\']'); if(n){n.setAttribute('required','required');}}"
                                                               class="mr-2"> Ja
                                                    </label>
                                                    <label class="flex items-center">
                                                        <input type="radio" name="metals" value="0" required
                                                               @change="() => {$refs.metals_notes_container.style.display='none'; const n=$refs.metals_notes_container.querySelector('input[name=\'metals_notes\']'); if(n){n.removeAttribute('required');}}"
                                                               class="mr-2"> Nee
                                                    </label>
                                                </div>
                                                <div ref="metals_notes_container" style="display: none" class="mt-2">
                                                    <x-admin::form.control-group.control
                                                        type="text"
                                                        name="metals_notes"
                                                        placeholder="Toelichting"
                                                    />
                                                </div>
                                            </x-admin::form.control-group>
                                        </div>

                                        <!-- Claustrofobisch? -->
                                        <div class="mt-3">
                                            <x-admin::form.control-group>
                                                <x-admin::form.control-group.label>
                                                    Claustrofobisch?
                                                </x-admin::form.control-group.label>
                                                <div class="flex gap-4">
                                                    <label class="flex items-center">
                                                        <input type="radio" name="claustrophobia" value="1" required
                                                               class="mr-2"> Ja
                                                    </label>
                                                    <label class="flex items-center">
                                                        <input type="radio" name="claustrophobia" value="0" required
                                                               class="mr-2"> Nee
                                                    </label>
                                                </div>
                                            </x-admin::form.control-group>
                                        </div>

                                        <!-- Allergieën? bij ja uitleg -->
                                        <div class="mt-3">
                                            <x-admin::form.control-group>
                                                <x-admin::form.control-group.label>
                                                    Allergieën?
                                                </x-admin::form.control-group.label>
                                                <div class="flex gap-4">
                                                    <label class="flex items-center">
                                                        <input type="radio" name="allergies" value="1" required
                                                               @change="() => {$refs.allergies_notes_container.style.display='block'; const n=$refs.allergies_notes_container.querySelector('input[name=\'allergies_notes\']'); if(n){n.setAttribute('required','required');}}"
                                                               class="mr-2"> Ja
                                                    </label>
                                                    <label class="flex items-center">
                                                        <input type="radio" name="allergies" value="0" required
                                                               @change="() => {$refs.allergies_notes_container.style.display='none'; const n=$refs.allergies_notes_container.querySelector('input[name=\'allergies_notes\']'); if(n){n.removeAttribute('required');}}"
                                                               class="mr-2"> Nee
                                                    </label>
                                                </div>
                                                <div ref="allergies_notes_container" style="display: none" class="mt-2">
                                                    <x-admin::form.control-group.control
                                                        type="text"
                                                        name="allergies_notes"
                                                        placeholder="Toelichting"
                                                    />
                                                </div>
                                            </x-admin::form.control-group>
                                        </div>

                                        <!-- Lengte en Gewicht (optioneel) -->
                                        <div class="mt-3 flex gap-4">
                                            <div class="w-1/2">
                                                <x-admin::form.control-group>
                                                    <x-admin::form.control-group.label>
                                                        Lengte (cm)
                                                    </x-admin::form.control-group.label>
                                                    <x-admin::form.control-group.control
                                                        type="number"
                                                        name="height"
                                                        placeholder="Bijv. 175"
                                                        min="100"
                                                        max="250"
                                                        step="1"
                                                    />
                                                    <x-admin::form.control-group.error control-name="height"/>
                                                </x-admin::form.control-group>
                                            </div>
                                            <div class="w-1/2">
                                                <x-admin::form.control-group>
                                                    <x-admin::form.control-group.label>
                                                        Gewicht (kg)
                                                    </x-admin::form.control-group.label>
                                                    <x-admin::form.control-group.control
                                                        type="number"
                                                        name="weight"
                                                        placeholder="Bijv. 75"
                                                        min="20"
                                                        max="300"
                                                        step="1"
                                                    />
                                                    <x-admin::form.control-group.error control-name="weight"/>
                                                </x-admin::form.control-group>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /Anamnese -->

                                    @include('admin::leads.common.sections.channel-to-owner', [
                                        'entity' => null,
                                        'defaults' => [
                                            'department_id' => $userDefaults['department_id'] ?? $defaultDepartmentId ?? '',
                                            'lead_channel_id' => $userDefaults['lead_channel_id'] ?? '1',
                                            'lead_source_id' => $userDefaults['lead_source_id'] ?? 32,
                                            'lead_type_id' => $userDefaults['lead_type_id'] ?? 1,
                                        ],
                                        'useVueModel' => false,
                                    ])

                                    <!-- Description -->
                                    <div class="mb-4">
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>
                                                @lang('admin::app.leads.create.description')
                                            </x-admin::form.control-group.label>
                                            <x-admin::form.control-group.control
                                                type="textarea"
                                                name="description"
                                                v-model="formData.description"
                                                label="Beschrijving"
                                                placeholder="Beschrijving"
                                                class="min-h-[80px]"
                                            />
                                            <x-admin::form.control-group.error control-name="description"/>
                                        </x-admin::form.control-group>
                                    </div>

                                    <!-- Organization Section -->
                                    <div class="flex flex-col gap-4 mb-4">
                                        @include('admin::leads.common.organization', ['organization' => null])
                                    </div>
                                    <!-- Owner -->
                                    <div class="flex-1">
                                        @php
                                            $userOptions = User::query()
                                                ->where('status', 1)
                                                ->selectRaw("id, TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) as full_name")
                                                ->orderBy('first_name')
                                                ->orderBy('last_name')
                                                ->get()
                                                ->pluck('full_name', 'id')
                                                ->toArray();
                                            $currentUserId = $currentUserId ?? null;
                                        @endphp
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>
                                                Toegewezen gebruiker
                                            </x-admin::form.control-group.label>
                                            <x-admin::form.control-group.control
                                                type="select"
                                                name="user_id"
                                                value="{{ $currentUserId }}"
                                            >
                                                <option value="">-- Kies gebruiker --</option>
                                                @foreach ($userOptions as $id => $name)
                                                    <option
                                                        value="{{ $id }}" {{ ($currentUserId == $id) ? 'selected' : '' }}>{{ $name }}</option>
                                                @endforeach
                                            </x-admin::form.control-group.control>
                                        </x-admin::form.control-group>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </script>

        <script type="module">
            // Main Two Step Form Component
            app.component('v-two-step-lead-form', {
                template: '#v-two-step-lead-form-template',

                props: {
                    initialPersons: {
                        type: Array,
                        default: () => []
                    },
                    initialLeadPerson: {
                        type: Object,
                        default: null
                    },
                    userDefaults: {
                        type: Object,
                        default: () => ({})
                    }
                },

                data() {
                    return {
                        currentStep: 1,
                        selectedPersons: [],
                        persons: [...this.initialPersons],
                        isSubmitting: false,
                        formData: {
                            description: '',
                            lead_channel_id: this.userDefaults.lead_channel_id || '1', // Default: Telefoon
                            lead_source_id: this.userDefaults.lead_source_id || 32, // Default: Anders
                            department_id: this.userDefaults.department_id || '{{ $defaultDepartmentId ?? "" }}', // Set based on pipeline or user groups
                            lead_pipeline_id: '{{ $defaultPipelineId ?? "" }}', // Set based on department or URL param
                            lead_pipeline_stage_id: '{{ $defaultStageId ?? "" }}', // Set based on pipeline or URL param
                            mri_status: this.userDefaults.mri_status || '',
                            lead_type_id: this.userDefaults.lead_type_id || '1', // Default: Preventie
                            user_id: '{{ $currentUserId ?? "" }}', // Default: Current logged-in user
                            // Personal fields for matching
                            salutation: this.initialLeadPerson?.salutation?.value || this.userDefaults.salutation || '',
                            initials: this.initialLeadPerson?.initials || this.userDefaults.initials || '',
                            first_name: this.initialLeadPerson?.first_name || this.userDefaults.first_name || '',
                            lastname_prefix: this.initialLeadPerson?.lastname_prefix || this.userDefaults.lastname_prefix || '',
                            last_name: this.initialLeadPerson?.last_name || this.userDefaults.last_name || '',
                            married_name_prefix: this.initialLeadPerson?.married_name_prefix || this.userDefaults.married_name_prefix || '',
                            married_name: this.initialLeadPerson?.married_name || this.userDefaults.married_name || '',
                            email: this.userDefaults.email || '',
                            phone: this.userDefaults.phone || '',
                                    // Contact person selector binding
                                    contact_person_id: null,
                                    contact_person_label: '',
                        },
                        hasSelectedPersons: false,
                        joinedPersonNames: ''
                    };
                },

                mounted() {
                    // Initialize global variable for persons data
                    window.leadFormPersons = this.persons;

                    // Set up global reference to this component for cross-component communication
                    window.leadFormComponent = this;

                    // If prefilled persons exist, sync into the matcher and prefill fields
                    if (this.persons.length > 0) {
                        // Prefill emails/phones if available
                        const p = this.persons[0];
                        if (!this.formData.email && p.emails && p.emails.length) {
                            this.formData.email = p.emails[0].value || '';
                        }
                        if (!this.formData.phone && p.phones && p.phones.length) {
                            this.formData.phone = p.phones[0].value || '';
                        }
                        // Ensure we start on step 1 regardless of prefill
                        this.currentStep = 1;
                        this.updateSelectedPersonsSummary();
                        this.$nextTick(() => this.syncPersonalFieldsToForm());
                    } else {
                        // Initialize with one empty slot for ease of use
                        this.persons.push({id: null, name: '', match_percentage: null, organization: null});
                    }
                },

                methods: {
                    onContactPersonChange(selectedPerson) {
                        if (selectedPerson) {
                            this.formData.contact_person_id = selectedPerson.id;
                            this.formData.contact_person_label = selectedPerson.name;
                        } else {
                            this.formData.contact_person_id = null;
                            this.formData.contact_person_label = '';
                        }
                    },
                    onLookupSelected(index, selectedPerson) {
                        this.updatePerson(index, selectedPerson);
                        // After selecting a person, update lead fields and contacts
                        this.updateFormDataFromPersons();
                        this.$nextTick(() => this.populateContactsFromFirstPerson());
                    },
                    goToStep(step) {
                        this.currentStep = step;

                        // If going to step 2 and we have selected persons, populate address from first person
                        if (step === 2) {
                            this.$nextTick(() => {
                                if (this.persons.length > 0 && this.persons[0].address) {
                                    this.populateAddressFields(this.persons[0].address);
                                }
                                // Ensure personal fields are synced when entering step 2
                                this.syncPersonalFieldsToForm();
                                // Also populate email/phone fields from first selected person
                                this.populateContactsFromFirstPerson();
                            });
                        }
                    },


                    updateFormDataFromPersons() {
                        // If we have at least one person selected, use the first person's data
                        if (this.persons.length > 0) {
                            const firstPerson = this.persons[0];

                            // Always populate first_name and last_name from the selected person
                            // This ensures the lead data matches the selected person
                            if (firstPerson.first_name || firstPerson.last_name) {
                                this.formData.first_name = firstPerson.first_name || '';
                                this.formData.last_name = firstPerson.last_name || '';
                            } else if (firstPerson.name && (!this.formData.first_name || !this.formData.last_name)) {
                                const parts = String(firstPerson.name).trim().split(/\s+/);
                                this.formData.first_name = this.formData.first_name || (parts[0] || '');
                                this.formData.last_name = this.formData.last_name || (parts.slice(1).join(' ') || '');
                            }

                            // Also populate other personal fields if available and form fields are empty
                            if (!this.formData.initials && firstPerson.initials) {
                                this.formData.initials = firstPerson.initials;
                            }
                            if (!this.formData.married_name_prefix && firstPerson.married_name_prefix) {
                                this.formData.married_name_prefix = firstPerson.married_name_prefix;
                            }
                            if (!this.formData.married_name && firstPerson.married_name) {
                                this.formData.married_name = firstPerson.married_name;
                            }

                            // Also populate email and phone if available and form fields are empty
                            if (!this.formData.email && firstPerson.emails && firstPerson.emails.length > 0) {
                                this.formData.email = firstPerson.emails[0].value || '';
                            }

                            if (!this.formData.phone && firstPerson.phones && firstPerson.phones.length > 0) {
                                this.formData.phone = firstPerson.phones[0].value || '';
                            }
                            this.updateSelectedPersonsSummary();
                            this.$nextTick(() => this.syncPersonalFieldsToForm());
                        } else {
                            // If no persons selected, keep the fields as they are to allow manual entry
                            this.hasSelectedPersons = false;
                            this.joinedPersonNames = '';
                        }
                    },
                    updateSelectedPersonsSummary() {
                        const list = (this.persons || []).filter(p => p && (p.id || p.name));
                        this.hasSelectedPersons = list.length > 0;
                        this.joinedPersonNames = list.map(p => p.name).join(', ');
                    },
                    syncPersonalFieldsToForm() {
                        if (!this.$refs.leadForm) return;
                        const firstPerson = (this.persons && this.persons[0]) || {};
                        const fields = {
                            first_name: this.formData.first_name || firstPerson.first_name || '',
                            last_name: this.formData.last_name || firstPerson.last_name || '',
                            lastname_prefix: this.formData.lastname_prefix || firstPerson.lastname_prefix || ''
                        };
                        Object.keys(fields).forEach(name => {
                            const input = this.$refs.leadForm.querySelector(`[name="${name}"]`);
                            if (input) {
                                input.value = fields[name] || '';
                                input.dispatchEvent(new Event('input', {bubbles: true}));
                                input.dispatchEvent(new Event('change', {bubbles: true}));
                            }
                        });
                    },

                    // Populate first email/phone inputs in the form from the first selected person
                    populateContactsFromFirstPerson() {
                        if (!this.$refs.leadForm) return;
                        const first = (this.persons && this.persons[0]) || {};

                        // Emails
                        try {
                            const emailValueInputs = this.$refs.leadForm.querySelectorAll('input[name^="emails"][name$="[value]"]');
                            const emailLabelInputs = this.$refs.leadForm.querySelectorAll('input[name^="emails"][name$="[label]"]');
                            if (first.emails && Array.isArray(first.emails) && first.emails.length > 0) {
                                const primaryEmail = first.emails[0];
                                if (emailValueInputs[0]) {
                                    emailValueInputs[0].value = primaryEmail.value || '';
                                    emailValueInputs[0].dispatchEvent(new Event('input', {bubbles: true}));
                                    emailValueInputs[0].dispatchEvent(new Event('change', {bubbles: true}));
                                }
                                if (emailLabelInputs[0]) {
                                    emailLabelInputs[0].value = (primaryEmail.label || 'eigen');
                                    emailLabelInputs[0].dispatchEvent(new Event('input', {bubbles: true}));
                                    emailLabelInputs[0].dispatchEvent(new Event('change', {bubbles: true}));
                                }
                            }
                        } catch (e) { /* no-op */
                        }

                        // Phones
                        try {
                            const phoneValueInputs = this.$refs.leadForm.querySelectorAll('input[name^="phones"][name$="[value]"]');
                            const phoneLabelInputs = this.$refs.leadForm.querySelectorAll('input[name^="phones"][name$="[label]"]');
                            if (first.phones && Array.isArray(first.phones) && first.phones.length > 0) {
                                const primaryPhone = first.phones[0];
                                if (phoneValueInputs[0]) {
                                    phoneValueInputs[0].value = primaryPhone.value || '';
                                    phoneValueInputs[0].dispatchEvent(new Event('input', {bubbles: true}));
                                    phoneValueInputs[0].dispatchEvent(new Event('change', {bubbles: true}));
                                }
                                if (phoneLabelInputs[0]) {
                                    phoneLabelInputs[0].value = (primaryPhone.label || 'eigen');
                                    phoneLabelInputs[0].dispatchEvent(new Event('input', {bubbles: true}));
                                    phoneLabelInputs[0].dispatchEvent(new Event('change', {bubbles: true}));
                                }
                            }
                        } catch (e) { /* no-op */
                        }
                    },


                    async submitForm() {
                        if (this.isSubmitting) return;

                        this.isSubmitting = true;

                        try {
                            const formData = new FormData(this.$refs.leadForm);

                            // Add our Vue form data to the FormData
                            Object.keys(this.formData).forEach(key => {
                                if (this.formData[key] !== null && this.formData[key] !== '' && this.formData[key] !== undefined) {
                                    formData.set(key, this.formData[key]);
                                }
                            });

                            // Add persons data from multi-contact-matcher component
                            const personIdInputs = document.querySelectorAll('input[name^="person_ids["]');

                            // Add person_ids from multi-contact-matcher hidden inputs
                            personIdInputs.forEach((input, index) => {
                                if (input.value) {
                                    formData.set(input.name, input.value);
                                }
                            });

                            const response = await axios.post('{{ route('admin.leads.store') }}', formData, {
                                headers: {'Content-Type': 'multipart/form-data'}
                            });

                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: 'Lead succesvol aangemaakt!'
                            });

                            // Redirect to lead view when backend provides redirect
                            if (response?.data?.redirect) {
                                window.location.href = response.data.redirect;
                            } else {
                                window.location.href = '{{ route('admin.leads.view', ['id' => 'REPLACE']) }}'.replace('REPLACE', response?.data?.id || '');
                            }

                        } catch (error) {
                            console.error('Error submitting form:', error);

                            let errorMessage = 'Er is een fout opgetreden bij het aanmaken van de lead.';
                            if (error.response?.data?.message) {
                                errorMessage = error.response.data.message;
                            } else if (error.response?.data?.errors) {
                                const errors = Object.values(error.response.data.errors).flat();
                                errorMessage = errors.join(', ');
                            }

                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: errorMessage
                            });
                        } finally {
                            this.isSubmitting = false;
                        }
                    },

                    populateAddressFields(address) {
                        // Only populate if we're in step 2 and the form exists
                        if (this.currentStep !== 2 || !this.$refs.leadForm) {
                            return;
                        }

                        // Populate address form fields by setting their values directly
                        const addressFields = {
                            'address[postal_code]': address.postal_code || '',
                            'address[house_number]': address.house_number || '',
                            'address[house_number_suffix]': address.house_number_suffix || '',
                            'address[street]': address.street || '',
                            'address[city]': address.city || '',
                            'address[state]': address.state || '',
                            'address[country]': address.country || 'Nederland'
                        };

                        // Set values in the actual form inputs
                        Object.keys(addressFields).forEach(fieldName => {
                            const input = this.$refs.leadForm.querySelector(`[name="${fieldName}"]`);
                            if (input && addressFields[fieldName]) {
                                input.value = addressFields[fieldName];
                                // Trigger change event to ensure any listeners are notified
                                input.dispatchEvent(new Event('change', {bubbles: true}));
                            }
                        });
                    },

                    hasValidEmail() {
                        if (!this.$refs.leadForm) return false;

                        const emailInputs = this.$refs.leadForm.querySelectorAll('input[name^="emails"][name$="[value]"]');
                        for (let input of emailInputs) {
                            if (input.value && input.value.trim() !== '') {
                                return true;
                            }
                        }
                        return false;
                    },

                    hasValidPhone() {
                        if (!this.$refs.leadForm) return false;

                        const phoneInputs = this.$refs.leadForm.querySelectorAll('input[name^="phones"][name$="[value]"]');
                        for (let input of phoneInputs) {
                            if (input.value && input.value.trim() !== '') {
                                return true;
                            }
                        }
                        return false;
                    }
                }
            });

            // Multiple Persons Component
            app.component('v-multiple-persons-component', {
                template: '#v-multiple-persons-component-template',

                props: ['data', 'leadId'],

                data() {
                    return {
                        persons: this.data || []
                    };
                },

                async mounted() {
                    // Calculate match percentages for existing persons
                    if (this.leadId) {
                        for (let i = 0; i < this.persons.length; i++) {
                            if (this.persons[i].id && !this.persons[i].match_percentage) {
                                const matchPercentage = await this.calculateMatchPercentage(this.persons[i]);
                                if (matchPercentage !== null) {
                                    this.$set(this.persons[i], 'match_percentage', matchPercentage);
                                }
                            }
                        }
                    }
                },

                watch: {
                    persons: {
                        handler(newValue) {
                            this.$emit('update:data', newValue);
                        },
                        deep: true
                    }
                },

                methods: {
                    addPerson() {
                        this.persons.push({
                            id: null,
                            name: '',
                            match_percentage: null,
                            organization: null
                        });
                    },

                    removePerson(index) {
                        this.persons.splice(index, 1);
                    },

                    updatePerson(index, selectedPerson) {
                        this.$set(this.persons, index, {
                            id: selectedPerson.id,
                            name: selectedPerson.name,
                            match_percentage: selectedPerson.match_percentage || null,
                            organization: selectedPerson.organization || null
                        });
                    },

                    async calculateMatchPercentage(person) {
                        if (!person.id || !this.leadId) return null;

                        try {
                            // Call the person search API with lead_id to get match score
                            const response = await fetch(`/admin/contacts/persons/search?lead_id=${this.leadId}&person_id=${person.id}`);
                            const data = await response.json();

                            if (data.data && data.data.length > 0) {
                                const matchedPerson = data.data.find(p => p.id === person.id);
                                return matchedPerson?.match_score_percentage || null;
                            }
                        } catch (error) {
                            console.warn('Could not calculate match percentage:', error);
                        }

                        return null;
                    },

                    getPersonInitials(person) {
                        if (!person.name) return '?';

                        const names = person.name.split(' ');
                        if (names.length >= 2) {
                            return (names[0][0] + names[names.length - 1][0]).toUpperCase();
                        }
                        return person.name[0].toUpperCase();
                    },

                    getPersonCardClass(person) {
                        if (!person.id) {
                            return 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900';
                        }

                        if (person.match_percentage >= 90) {
                            return 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900';
                        } else if (person.match_percentage >= 70) {
                            return 'border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900';
                        } else if (person.match_percentage >= 50) {
                            return 'border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-900';
                        } else {
                            return 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800';
                        }
                    },

                    getAvatarClass(person) {
                        if (!person.id) {
                            return 'bg-blue-600';
                        }

                        if (person.match_percentage >= 90) {
                            return 'bg-green-600';
                        } else if (person.match_percentage >= 70) {
                            return 'bg-yellow-600';
                        } else if (person.match_percentage >= 50) {
                            return 'bg-orange-600';
                        } else {
                            return 'bg-gray-600';
                        }
                    },

                    getScoreBarClass(percentage) {
                        if (percentage >= 80) {
                            return 'bg-green-500';
                        } else if (percentage >= 60) {
                            return 'bg-yellow-500';
                        } else if (percentage >= 40) {
                            return 'bg-orange-500';
                        } else {
                            return 'bg-red-500';
                        }
                    },

                    /**
                     * Helper method to get cookie value
                     */
                    getCookieValue(name) {
                        const value = `; ${document.cookie}`;
                        const parts = value.split(`; ${name}=`);
                        if (parts.length === 2) return parts.pop().split(';').shift();
                        return null;
                    }
                }
            });
        </script>
    @endPushOnce

    @pushOnce('styles')
        <style>
            [v-cloak] {
                display: none;
            }

            html {
                scroll-behavior: smooth;
            }
        </style>
    @endPushOnce
</x-admin::layouts>
