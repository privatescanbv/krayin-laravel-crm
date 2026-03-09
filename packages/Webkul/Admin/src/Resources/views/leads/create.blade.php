@php
use App\Enums\ContactLabel;
use App\Enums\PersonSalutation;
use App\Enums\PersonGender;
use App\Models\Department;
use App\Models\User;

// Define salutation to gender mapping based on enum definitions
$salutationToGenderMapping = [
    PersonSalutation::Dhr->value => PersonGender::Man->value,
    PersonSalutation::Mevr->value => PersonGender::Female->value,
];
@endphp
<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.create.title')
    </x-slot>

    {!! view_render_event('admin.leads.create.form.before') !!}

    <!-- Two-Step Lead Form -->
    <v-two-step-lead-form :initial-persons='@json($prefilledPersons ?? [])'
                          :initial-lead-person='@json($prefilledLeadPerson ?? null)'
                          :user-defaults='@json((object) ($userDefaults ?? []))'
                          :salutation-to-gender-mapping='@json($salutationToGenderMapping)'></v-two-step-lead-form>

    {!! view_render_event('admin.leads.create.form.after') !!}

    @pushOnce('scripts')
        <script>
            // for anamnesis fields
            function toggleCommentField(fieldName, showField) {
                const commentDiv = document.getElementById(fieldName + '_comment');
                if (commentDiv) {
                    commentDiv.style.display = showField ? 'block' : 'none';

                    // Find inputs within the comment container
                    const inputs = commentDiv.querySelectorAll('input, textarea, select');
                    inputs.forEach(input => {
                        if (showField) {
                            input.setAttribute('required', 'required');
                        } else {
                            input.removeAttribute('required');
                        }
                    });
                }
            }
        </script>
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
                                class="text-activity-note-text hover:text-activity-task-text p-1"
                                title="Bekijk persoon"
                            >
                                <i class="icon-eye text-sm"></i>
                            </a>

                            <!-- Remove Person -->
                            <button
                                v-on:click="removePerson(index)"
                                type="button"
                                class="text-status-expired-text hover:text-red-800 p-1"
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
                    class="flex items-center border bg-white p-2 border-radius-sm justify-between text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 backdrop-blur-md sticky top-16 z-10">
                    <div class="flex flex-col gap-2">
                        <x-admin::breadcrumbs name="leads.create"/>
                        <div class="text-xl font-bold dark:text-white">
                            @lang('admin::app.leads.create.title')
                        </div>
                    </div>

                    <div class="flex items-center gap-x-2.5" v-show="currentStep === 2">
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

                <!-- Single-Step: Full Lead Form -->
                <div>
                    <form @submit.prevent="submitForm" ref="leadForm">
                        @csrf
                        @include('adminc.components.validation-errors')
                        @if ($returnUrl = request()->input('return_url') ?? request()->query('return_url'))
                            <input type="hidden" name="return_url" value="{{ $returnUrl }}"/>
                        @endif
                        <input type="hidden" name="lead_pipeline_stage_id" value="{{ request('stage_id') }}"/>
                        @if(! empty($linkEmailId))
                            <input type="hidden" name="link_email_id" value="{{ $linkEmailId }}"/>
                        @endif
                        <!-- Hidden selected person id to link to lead on save -->
                        <input type="hidden" name="person_ids[0]" :value="selectedPersonId || ''" />


                        <div class="flex gap-6 max-md:flex-col">
                            <div class="w-2/3 max-md:w-full">
                                <div
                                    class="box-shadow flex flex-col gap-4 rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 p-6">
                            <div class="flex flex-col gap-1">
                                <p class="text-xl font-semibold dark:text-white">
                                    Lead gegevens
                                </p>

                                <!-- Show selected persons info if available -->
                                <div v-if="hasSelectedPersons"
                                     class="mt-2 p-3 bg-status-active-bg border border-status-active-border rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-status-active-text" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span class="font-medium text-green-800">Vooraf ingevuld vanuit persoon:</span>
                                        <span class="text-green-700">@{{ joinedPersonNames }}</span>

                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-green-700"><i>Kies een suggestie om deze persoon te koppelen aan de lead.</i></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Lead Details -->
                            <div class="flex flex-col">
                                <!-- Left: Form -->
                                <div class="w-full">

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
                                                'national_identification_number' => null,
                                            ];
                                            $__entityPrefill = (object) array_merge($__defaults, ($prefilledLeadPerson ?? []));
                                        @endphp
                                        @include('admin::leads.common.personal-fields', [
                                            'entity' => $__entityPrefill,
                                            'bindModel' => 'formData',
                                            'showPortalFields' => false,
                                        ])
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

                                    <!-- Create person from lead data (create flow only) -->
                                    <div class="mt-4">
                                        <x-adminc::components.field
                                            type="switch"
                                            name="create_person_from_lead"
                                            label="Tevens persoon aanmaken met deze lead gegevens en koppelen"
                                            v-model="formData.create_person_from_lead"
                                        />
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
                                        <x-adminc::components.address />
                                    </div>

                                    <!-- Anamnese -->
                                    <div class="mt-6 pb-[20px]">
                                        <p class="text-base font-semibold dark:text-white">Anamnese</p>

                                        <!-- Heeft u metalen? -->
                                        <div class="mt-3">
                                            <x-adminc::components.yes-no
                                                name="metals"
                                                label="Heeft u metalen?"
                                                rules="required"
                                                comment-field="metals"
                                            />

                                            <div id="metals_comment" class="mt-2" style="display: none">
                                                <x-adminc::components.field
                                                    type="text"
                                                    name="metals_notes"
                                                    label="Toelichting"
                                                    placeholder="Toelichting"
                                                />
                                            </div>
                                        </div>

                                        <!-- Claustrofobisch? -->
                                        <div class="mt-3">
                                            <x-adminc::components.yes-no
                                                name="claustrophobia"
                                                label="Claustrofobisch?"
                                                rules="required"
                                            />
                                        </div>

                                        <!-- Allergieën? bij ja uitleg -->
                                        <div class="mt-3">
                                            <x-adminc::components.yes-no
                                                name="allergies"
                                                label="Allergieën?"
                                                rules="required"
                                                comment-field="allergies"
                                            />

                                            <div id="allergies_comment" class="mt-2" style="display: none">
                                                <x-adminc::components.field
                                                    type="text"
                                                    name="allergies_notes"
                                                    label="Toelichting"
                                                    placeholder="Toelichting"
                                                />
                                            </div>
                                        </div>

                                        <!-- Lengte en Gewicht (optioneel) -->
                                        <div class="mt-3 flex gap-4">
                                            <div class="w-1/2">
                                                <x-adminc::components.field
                                                    type="number"
                                                    name="height"
                                                    label="Lengte (cm)"
                                                    placeholder="Bijv. 175"
                                                    min="100"
                                                    max="250"
                                                    step="1"
                                                />
                                            </div>
                                            <div class="w-1/2">
                                                <x-adminc::components.field
                                                    type="number"
                                                    name="weight"
                                                    label="Gewicht (kg)"
                                                    placeholder="Bijv. 75"
                                                    min="20"
                                                    max="300"
                                                    step="1"
                                                />
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
                                        // Important: the create form submit handler overrides FormData with `formData.*`,
                                        // so these selects must be bound to `formData` to reflect user changes.
                                        'useVueModel' => true,
                                    ])

                                    <!-- Description -->
                                    <div class="mb-4">
                                        <x-adminc::components.field
                                            type="textarea"
                                            name="description"
                                            v-model="formData.description"
                                            label="Beschrijving"
                                            placeholder="Beschrijving"
                                            class="min-h-[80px]"
                                        />
                                    </div>

                                    <!-- Organization Section -->
                                    <div class="flex flex-col gap-4 mb-4">
                                        @include('admin::leads.common.organization', ['organization' => null])
                                    </div>
                                    <!-- Owner -->
                                    <div class="flex-1">
                                        @php
                                            $userOptions = app(Webkul\User\Repositories\UserRepository::class)->allActiveUsers();
                                            $currentUserId = $currentUserId ?? null;
                                        @endphp
                                        <x-adminc::components.field
                                            type="select"
                                            name="user_id"
                                            value="{{ $currentUserId }}"
                                            label="Toegewezen gebruiker"
                                        >
                                            <option value="">-- Kies gebruiker --</option>
                                            @foreach ($userOptions as $user)
                                                <option
                                                    value="{{ $user->id }}" {{ ($currentUserId == $user->id) ? 'selected' : '' }}>{{ $user->name }}</option>
                                            @endforeach
                                        </x-adminc::components.field>
                                    </div>
                                </div>
                            </div>
                                </div> <!-- close box-shadow card -->
                            </div> <!-- close left column -->

                            <!-- Right: Suggestions as separate panel (outside white card) -->
                            <div class="w-1/3 max-md:w-full">
                                <div class="sticky top-36">
                                    <div class="rounded-lg border border-activity-note-border bg-activity-note-bg dark:border-blue-800 dark:bg-blue-900 p-4" v-if="suggestions.length > 0">
                                        <x-adminc::components.person-suggestions-panel :button-handler="'selectSuggestion'" :button-text="'Koppelen'" />
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
                    },
                    salutationToGenderMapping: {
                        type: Object,
                        default: () => ({})
                    }
                },

                data() {
                    return {
                        currentStep: 2,
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
                            national_identification_number: this.initialLeadPerson?.national_identification_number || '',
                            email: (this.initialLeadPerson?.emails && this.initialLeadPerson.emails.length > 0)
                                ? (this.initialLeadPerson.emails[0].value || '')
                                : (this.userDefaults.email || ''),
                            phone: (this.initialLeadPerson?.phones && this.initialLeadPerson.phones.length > 0)
                                ? (this.initialLeadPerson.phones[0].value || '')
                                : (this.userDefaults.phone || ''),
                                    // Contact person selector binding
                                    contact_person_id: null,
                                    contact_person_label: '',

                                    // Create person from lead data (create flow only)
                                    create_person_from_lead: false,
                        },
                        hasSelectedPersons: false,
                        joinedPersonNames: '',
                        selectedPersonId: null,
                        suggestions: [],
                        _debounceTimer: null,
                        suggestionsDisabled: false
                    };
                },

                watch: {
                    'formData.salutation'(newSalutation) {
                        // Automatically set gender based on salutation if gender is not already set
                        // Uses mapping from PHP enums (PersonSalutation -> PersonGender)
                        if (newSalutation && !this.formData.gender) {
                            // Use the mapping passed from PHP (based on enum definitions)
                            const mapping = this.salutationToGenderMapping || {};

                            if (mapping[newSalutation]) {
                                this.formData.gender = mapping[newSalutation];
                                // Also update the form field if it exists
                                this.$nextTick(() => {
                                    const genderField = this.$refs.leadForm?.querySelector('[name="gender"]');
                                    if (genderField) {
                                        genderField.value = this.formData.gender;
                                        // Trigger change event to ensure form validation
                                        genderField.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                });
                            }
                        }
                    }
                },

                mounted() {
                    // Initialize global variable for persons data
                    window.leadFormPersons = this.persons;

                    // Set up global reference to this component for cross-component communication
                    window.leadFormComponent = this;

                    // If initialLeadPerson exists but no persons, prefill from initialLeadPerson
                    if (this.initialLeadPerson && this.persons.length === 0) {
                        // Prefill email and phone from initialLeadPerson if formData is empty
                        if (!this.formData.email && this.initialLeadPerson.emails && this.initialLeadPerson.emails.length > 0) {
                            this.formData.email = this.initialLeadPerson.emails[0].value || '';
                        }
                        if (!this.formData.phone && this.initialLeadPerson.phones && this.initialLeadPerson.phones.length > 0) {
                            this.formData.phone = this.initialLeadPerson.phones[0].value || '';
                        }
                        // Sync to form fields
                        this.$nextTick(() => {
                            this.syncPersonalFieldsToForm();
                            // Trigger suggestions immediately when phone is prefilled
                            if (this.formData.phone && String(this.formData.phone).trim().length > 0) {
                                this.fetchSuggestions();
                            }
                        });

                    }

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
                        this.updateSelectedPersonsSummary();
                        this.$nextTick(() => {
                            this.syncPersonalFieldsToForm();
                            // Trigger suggestions immediately when phone is present
                            if (this.formData.phone && String(this.formData.phone).trim().length > 0) {
                                this.fetchSuggestions();
                            }
                        });
                    } else {
                        // Initialize with one empty slot for ease of use
                        this.persons.push({id: null, name: '', match_percentage: null, organization: null});
                    }

                    // Attach blur/change listeners to trigger suggestions
                    this.$nextTick(() => {
                        const blurFields = ['first_name','last_name','emails[0][value]','phones[0][value]'];
                        blurFields.forEach(name => {
                            const input = this.$refs.leadForm?.querySelector(`[name="${name}"]`);
                            if (input) {
                                input.addEventListener('blur', () => this.onFieldBlur());
                                input.addEventListener('change', () => this.onFieldBlur());
                            }
                        });

                        // Listen for salutation changes to auto-set gender
                        const salutationField = this.$refs.leadForm?.querySelector('[name="salutation"]');
                        if (salutationField) {
                            salutationField.addEventListener('change', (e) => {
                                const newSalutation = e.target.value;
                                // Update formData
                                this.formData.salutation = newSalutation;
                                // Auto-set gender if not already set
                                this.autoSetGenderFromSalutation(newSalutation);
                            });
                        }

                        // call initial suggestion, when creating lead from person
                        if(this.hasSelectedPersons) {
                            this.onFieldBlur();
                        }
                    });
                },

                methods: {
                    autoSetGenderFromSalutation(salutation) {
                        // Automatically set gender based on salutation if gender is not already set
                        // Uses mapping from PHP enums (PersonSalutation -> PersonGender)
                        if (salutation && !this.formData.gender) {
                            // Use the mapping passed from PHP (based on enum definitions)
                            const mapping = this.salutationToGenderMapping || {};

                            if (mapping[salutation]) {
                                this.formData.gender = mapping[salutation];
                                // Also update the form field
                                this.$nextTick(() => {
                                    const genderField = this.$refs.leadForm?.querySelector('[name="gender"]');
                                    if (genderField) {
                                        genderField.value = this.formData.gender;
                                        // Trigger change event to ensure form validation
                                        genderField.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                });
                            }
                        }
                    },

                    formatDate(value) {
                        if (!value) return '';
                        try {
                            const d = new Date(value);
                            if (!isNaN(d.getTime())) {
                                const day = String(d.getDate()).padStart(2, '0');
                                const month = String(d.getMonth() + 1).padStart(2, '0');
                                const year = d.getFullYear();
                                return `${day}-${month}-${year}`;
                            }
                        } catch (e) {}
                        // Fallback: try first 10 chars or raw
                        const raw = String(value);
                        if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
                            const [y,m,dd] = raw.slice(0,10).split('-');
                            return `${dd}-${m}-${y}`;
                        }
                        return raw;
                    },
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
                    // Stepper removed in single-step variant


                    updateFormDataFromPersons() {
                        // If we have at least one person selected, use the first person's data
                        if (this.persons.length > 0) {
                            const firstPerson = this.persons[0] || {};

                            // Only populate fields that are empty in the form
                            if (!this.formData.first_name || String(this.formData.first_name).trim() === '') {
                                if (firstPerson.first_name) {
                                    this.formData.first_name = firstPerson.first_name;
                                } else if (firstPerson.name) {
                                    const parts = String(firstPerson.name).trim().split(/\s+/);
                                    this.formData.first_name = parts[0] || '';
                                }
                            }
                            if (!this.formData.last_name || String(this.formData.last_name).trim() === '') {
                                if (firstPerson.last_name) {
                                    this.formData.last_name = firstPerson.last_name;
                                } else if (firstPerson.name) {
                                    const parts = String(firstPerson.name).trim().split(/\s+/);
                                    this.formData.last_name = parts.slice(1).join(' ') || '';
                                }
                            }

                            if (!this.formData.initials && firstPerson.initials) {
                                this.formData.initials = firstPerson.initials;
                            }
                            if (!this.formData.married_name_prefix && firstPerson.married_name_prefix) {
                                this.formData.married_name_prefix = firstPerson.married_name_prefix;
                            }
                            if (!this.formData.married_name && firstPerson.married_name) {
                                this.formData.married_name = firstPerson.married_name;
                            }
                            if (!this.formData.salutation && (firstPerson.salutation && (firstPerson.salutation.value || firstPerson.salutation))) {
                                this.formData.salutation = firstPerson.salutation.value || firstPerson.salutation || '';
                            }
                            if (!this.formData.gender && (firstPerson.gender && (firstPerson.gender.value || firstPerson.gender))) {
                                this.formData.gender = firstPerson.gender.value || firstPerson.gender || '';
                            }
                            if (!this.formData.date_of_birth && firstPerson.date_of_birth) {
                                // normalized later by syncPersonalFieldsToForm
                                this.formData.date_of_birth = firstPerson.date_of_birth;
                            }
                            if (!this.formData.national_identification_number && firstPerson.national_identification_number) {
                                this.formData.national_identification_number = firstPerson.national_identification_number;
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
                    // Method synchronized the chosen person to attach to this lead.
                    syncPersonalFieldsToForm() {
                        if (!this.$refs.leadForm) return;
                        const firstPerson = (this.persons && this.persons[0]) || {};
                        const fields = {
                            first_name: this.formData.first_name || firstPerson.first_name || '',
                            last_name: this.formData.last_name || firstPerson.last_name || '',
                            lastname_prefix: this.formData.lastname_prefix || firstPerson.lastname_prefix || '',
                            married_name_prefix: this.formData.married_name_prefix || firstPerson.married_name_prefix || '',
                            married_name: this.formData.married_name || firstPerson.married_name || '',
                            salutation: this.formData.salutation || (firstPerson.salutation && (firstPerson.salutation.value || firstPerson.salutation)) || '',
                            gender: this.formData.gender || (firstPerson.gender && (firstPerson.gender.value || firstPerson.gender)) || '',
                            national_identification_number: this.formData.national_identification_number || firstPerson.national_identification_number || '',
                            date_of_birth: (() => {
                                const val = this.formData.date_of_birth || firstPerson.date_of_birth || '';
                                if (!val) return '';
                                try {
                                    // Normalize to YYYY-MM-DD for input[type=date]
                                    const d = new Date(val);
                                    if (!isNaN(d.getTime())) {
                                        const y = d.getFullYear();
                                        const m = String(d.getMonth() + 1).padStart(2, '0');
                                        const day = String(d.getDate()).padStart(2, '0');
                                        return `${y}-${m}-${day}`;
                                    }
                                } catch (_) {}
                                return String(val).slice(0, 10);
                            })()
                        };
                        Object.entries(fields).forEach(([name, value]) => {
                            const input = this.$refs.leadForm.querySelector(`[name="${name}"]`);
                            if (input) {
                                input.value = value || '';
                                input.dispatchEvent(new Event('input', {bubbles: true}));
                                input.dispatchEvent(new Event('change', {bubbles: true}));
                            }
                        });
                    },

                    // Populate first email/phone inputs in the form from the first selected person (only if empty)
                    populateContactsFromFirstPerson() {
                        if (!this.$refs.leadForm) return;
                        const first = (this.persons && this.persons[0]) || {};

                        // Emails
                        try {
                            const emailValueInputs = this.$refs.leadForm.querySelectorAll('input[name^="emails"][name$="[value]"]');
                            const emailLabelInputs = this.$refs.leadForm.querySelectorAll('input[name^="emails"][name$="[label]"]');
                            if (first.emails && Array.isArray(first.emails) && first.emails.length > 0) {
                                const primaryEmail = first.emails[0];
                                if (emailValueInputs[0] && (!emailValueInputs[0].value || emailValueInputs[0].value.trim() === '')) {
                                    emailValueInputs[0].value = primaryEmail.value || '';
                                    emailValueInputs[0].dispatchEvent(new Event('input', {bubbles: true}));
                                    emailValueInputs[0].dispatchEvent(new Event('change', {bubbles: true}));
                                }
                                if (emailLabelInputs[0] && (!emailLabelInputs[0].value || emailLabelInputs[0].value.trim() === '')) {
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
                                if (phoneValueInputs[0] && (!phoneValueInputs[0].value || phoneValueInputs[0].value.trim() === '')) {
                                    phoneValueInputs[0].value = primaryPhone.value || '';
                                    phoneValueInputs[0].dispatchEvent(new Event('input', {bubbles: true}));
                                    phoneValueInputs[0].dispatchEvent(new Event('change', {bubbles: true}));
                                }
                                if (phoneLabelInputs[0] && (!phoneLabelInputs[0].value || phoneLabelInputs[0].value.trim() === '')) {
                                    phoneLabelInputs[0].value = (primaryPhone.label || 'eigen');
                                    phoneLabelInputs[0].dispatchEvent(new Event('input', {bubbles: true}));
                                    phoneLabelInputs[0].dispatchEvent(new Event('change', {bubbles: true}));
                                }
                            }
                        } catch (e) { /* no-op */
                        }
                    },

                    // Debounced field blur handler
                    onFieldBlur() {
                        if (this.suggestionsDisabled || this.selectedPersonId) return;
                        if (this._debounceTimer) clearTimeout(this._debounceTimer);
                        this._debounceTimer = setTimeout(() => this.fetchSuggestions(), 300);
                    },

                    async fetchSuggestions() {
                        if (this.suggestionsDisabled || this.selectedPersonId) { this.suggestions = []; return; }
                        try {
                            const firstName = (this.$refs.leadForm?.querySelector('[name="first_name"]').value || '').trim();
                            const lastName = (this.$refs.leadForm?.querySelector('[name="last_name"]').value || '').trim();
                            const email = (this.$refs.leadForm?.querySelector('input[name^="emails"][name$="[value]"]').value || '').trim();
                            const phone = (this.$refs.leadForm?.querySelector('input[name^="phones"][name$="[value]"]').value || '').trim();

                            // Build a single fielded query string
                            let queryParts = [];
                            if (firstName) queryParts.push(`firstname:${firstName}`);
                            if (lastName) queryParts.push(`lastname:${lastName}`);
                            if (email) queryParts.push(`email:${email}`);
                            if (phone) {
                                const onlyDigits = phone.replace(/\D+/g, '');
                                if (onlyDigits) queryParts.push(`phone:${onlyDigits}`);
                            }

                            const composed = queryParts.join(';') + (queryParts.length ? ';' : '');
                            if (!composed) { this.suggestions = []; return; }

                            const fetcher = (window.adminc && typeof window.adminc.fetchPersons === 'function') ? window.adminc.fetchPersons : null;
                            const list = fetcher ? await fetcher(composed, {}, false) : [];

                            const scored = (list || []).map(p => this.calculateMatchScore(p, firstName, lastName, email, phone))
                                .filter(p => p._client_match > 0)
                                .sort((a,b) => (b._client_match||0) - (a._client_match||0))
                                .slice(0, 10);

                            this.suggestions = scored;
                        } catch (e) {
                            this.suggestions = [];
                        }
                    },

                    clearSuggestions() { this.suggestions = []; },

                    calculateMatchScore(p, firstName, lastName, email, phone) {
                        // We can't use the server side match score, because the lead hasn't been persisted yet.
                        let score = 0;
                        const pEmail = (p.emails && p.emails[0] && (p.emails[0].value||'').toLowerCase()) || '';
                        const pPhone = (p.phones && p.phones[0] && (p.phones[0].value||'')) || '';
                        const pFirst = (p.first_name||'').toLowerCase();
                        const pLast = (p.last_name||'').toLowerCase();
                        if (email && pEmail && pEmail === email.toLowerCase()) score += 60;
                        if (phone && pPhone && pPhone.replace(/\D/g,'') === phone.replace(/\D/g,'')) score += 40;
                        if (firstName && pFirst && pFirst === firstName.toLowerCase()) score += 20;
                        if (lastName && pLast && pLast === lastName.toLowerCase()) score += 20;
                        if (!score && (pEmail.includes((email||'').toLowerCase()) || pPhone.includes(phone||''))) score += 20;
                        p._client_match = Math.min(100, score);
                        return p;
                    },

                    selectSuggestion(person) {
                        this.clearSuggestions();
                        this.suggestionsDisabled = true;
                        this.selectedPersonId = person.id;
                        this.persons = [{ ...person }];
                        const p = person || {};
                        if (!this.formData.first_name && p.first_name) this.formData.first_name = p.first_name;
                        if (!this.formData.last_name && p.last_name) this.formData.last_name = p.last_name;
                        if (!this.formData.lastname_prefix && p.lastname_prefix) this.formData.lastname_prefix = p.lastname_prefix;
                        if (!this.formData.initials && p.initials) this.formData.initials = p.initials;
                        if (!this.formData.married_name_prefix && p.married_name_prefix) this.formData.married_name_prefix = p.married_name_prefix;
                        if (!this.formData.married_name && p.married_name) this.formData.married_name = p.married_name;
                        if (!this.formData.salutation && (p.salutation || (p.salutation && p.salutation.value))) this.formData.salutation = (p.salutation && (p.salutation.value || p.salutation)) || '';
                        if (!this.formData.gender && (p.gender || (p.gender && p.gender.value))) this.formData.gender = (p.gender && (p.gender.value || p.gender)) || '';
                        if (!this.formData.national_identification_number && p.national_identification_number) this.formData.national_identification_number = p.national_identification_number;
                        if (!this.formData.date_of_birth && p.date_of_birth) {
                            const raw = p.date_of_birth;
                            try {
                                const d = new Date(raw);
                                if (!isNaN(d.getTime())) {
                                    const y = d.getFullYear();
                                    const m = String(d.getMonth() + 1).padStart(2, '0');
                                    const day = String(d.getDate()).padStart(2, '0');
                                    this.formData.date_of_birth = `${y}-${m}-${day}`;
                                } else {
                                    this.formData.date_of_birth = String(raw).slice(0, 10);
                                }
                            } catch (_) {
                                this.formData.date_of_birth = String(raw).slice(0, 10);
                            }
                        }

                        this.$nextTick(() => {
                            this.syncPersonalFieldsToForm();
                            this.populateContactsFromFirstPerson();
                            if (p.address) this.populateAddressFields(p.address);
                        });
                        this.updateSelectedPersonsSummary();
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

                            // Ensure checkbox is always submitted as 0/1
                            formData.set('create_person_from_lead', this.formData.create_person_from_lead ? '1' : '0');

                            // Ensure selected person is submitted if chosen
                            if (this.selectedPersonId) {
                                formData.set('person_ids[0]', String(this.selectedPersonId));
                            }

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
                        // Single-step: only guard for form existence
                        if (!this.$refs.leadForm) { return; }

                        // Populate address form fields by setting their values directly (only when empty)
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
                            if (input && (!input.value || input.value.trim() === '') && addressFields[fieldName]) {
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
                            return 'border-activity-note-border bg-activity-note-bg dark:border-blue-800 dark:bg-blue-900';
                        }

                        if (person.match_percentage >= 90) {
                            return 'border-status-active-border bg-status-active-bg dark:border-green-800 dark:bg-green-900';
                        } else if (person.match_percentage >= 70) {
                            return 'border-status-on_hold-border bg-status-on_hold-bg dark:border-yellow-800 dark:bg-yellow-900';
                        } else if (person.match_percentage >= 50) {
                            return 'border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-900';
                        } else {
                            return 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800';
                        }
                    },

                    getAvatarClass(person) {
                        if (!person.id) {
                            return 'text-activity-note-text';
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
                            return 'bg-succes';
                        } else if (percentage >= 60) {
                            return 'bg-status-on_hold-text';
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
