@php use App\Models\Department;use Webkul\Lead\Models\Channel;use Webkul\Lead\Models\Source;use Webkul\Lead\Models\Type; @endphp
<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.create.title')
    </x-slot>

    {!! view_render_event('admin.leads.create.form.before') !!}

    <!-- Two-Step Lead Form -->
    <v-two-step-lead-form></v-two-step-lead-form>

    {!! view_render_event('admin.leads.create.form.after') !!}

    @pushOnce('scripts')
        <script type="text/x-template" id="v-multiple-persons-component-template">
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold dark:text-white">
                        Contactpersonen (@{{ persons.length }})
                    </h3>
                    <button
                        @click="addPerson"
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
                                    @on-selected="(selectedPerson) => updatePerson(index, selectedPerson)"
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
                                @click="removePerson(index)"
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
                <div v-if="persons.length === 0" class="text-center py-6 text-gray-500 dark:text-gray-400 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                    <i class="icon-users text-3xl mb-2"></i>
                    <p class="font-medium">Geen contactpersonen gekoppeld</p>
                    <p class="text-sm">Klik op "Toevoegen" om contactpersonen te koppelen</p>
                </div>
            </div>
        </script>

        <script type="text/x-template" id="v-two-step-lead-form-template">
            <div class="flex flex-col gap-4">
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
                            @click="goToStep(1)"
                            type="button"
                            class="secondary-button"
                        >
                            Terug
                        </button>

                        <button
                            @click="submitForm"
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
                            @include('admin::leads.common.multi-contactmatcher', ['lead' => (object)['id' => null], 'persons' => []])
                        </div>

                        <div class="flex justify-end pt-4">
                            <button
                                @click="goToStep(2)"
                                type="button"
                                class="primary-button"
                            >
                                Verder naar stap 2
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Full Lead Form -->
                <div v-show="currentStep === 2">
                    <form @submit.prevent="submitForm" ref="leadForm">
                        @csrf
                        @if (request('stage_id'))
                            <input type="hidden" name="lead_pipeline_stage_id" value="{{ request('stage_id') }}"/>
                        @endif



                        <div
                            class="box-shadow flex flex-col gap-4 rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 p-6">
                            <div class="flex flex-col gap-1">
                                <p class="text-xl font-semibold dark:text-white">
                                    Stap 2: Lead gegevens
                                </p>
                                <p class="text-gray-600 dark:text-white">
                                    Vul de lead informatie in
                                </p>

                                <!-- Show selected persons info if available -->
                                <div v-if="persons.length > 0 && persons.some(p => p.id || p.name)"
                                     class="mt-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span class="font-medium text-green-800">Contactpersonen gekoppeld:</span>
                                        <span class="text-green-700">
                                            @{{ persons.filter(p => p.id || p.name).map(p => p.name).join(', ') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Lead Details -->
                            <div class="flex flex-col gap-4">
                                <div class="w-1/2 max-md:w-full">
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

                                    <!-- Personal Fields (for matching) -->
                                    <div class="flex flex-col gap-4 mb-4">
                                        <p class="text-base font-semibold dark:text-white">Lead persoon gegevens (voor matching)</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Deze gegevens worden gebruikt om te matchen met bestaande personen
                                        </p>

                                        <!-- Name Fields -->
                                        <div class="flex gap-4">
                                            <x-admin::form.control-group class="flex-1">
                                                <x-admin::form.control-group.label class="required">Voornaam</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="first_name"
                                                    v-model="formData.first_name"
                                                    placeholder="Voornaam"
                                                    rules="required"
                                                />
                                                <x-admin::form.control-group.error control-name="first_name"/>
                                            </x-admin::form.control-group>

                                            <x-admin::form.control-group class="flex-1">
                                                <x-admin::form.control-group.label class="required">Achternaam</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="last_name"
                                                    v-model="formData.last_name"
                                                    placeholder="Achternaam"
                                                    rules="required"
                                                />
                                                <x-admin::form.control-group.error control-name="last_name"/>
                                            </x-admin::form.control-group>
                                        </div>

                                        <!-- Contact Fields -->
                                        <div class="flex gap-4">
                                            <x-admin::form.control-group class="flex-1">
                                                <x-admin::form.control-group.label>E-mail</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="email"
                                                    name="emails[0][value]"
                                                    v-model="formData.email"
                                                    placeholder="email@example.com"
                                                />
                                                <input type="hidden" name="emails[0][label]" value="work">
                                                <input type="hidden" name="emails[0][is_default]" value="1">
                                            </x-admin::form.control-group>

                                            <x-admin::form.control-group class="flex-1">
                                                <x-admin::form.control-group.label>Telefoon</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="phones[0][value]"
                                                    v-model="formData.phone"
                                                    placeholder="+31 6 12345678"
                                                />
                                                <input type="hidden" name="phones[0][label]" value="work">
                                                <input type="hidden" name="phones[0][is_default]" value="1">
                                            </x-admin::form.control-group>
                                        </div>
                                    </div>

                                    <!-- Organization Section -->
                                    <div class="flex flex-col gap-4 mb-4">
                                        <p class="text-base font-semibold dark:text-white">Organisatie (facturatie)</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Koppel een organisatie voor facturatie doeleinden (optioneel)
                                        </p>

                                        @include('admin::leads.common.organization', ['organization' => null])
                                    </div>

                                    <!-- Channel and Source -->
                                    <div class="flex gap-4 mb-4">
                                        <div class="flex-1">
                                            @php $channelOptions = Channel::query()->pluck('name', 'id')->toArray(); @endphp
                                            <x-admin::form.control-group>
                                                <x-admin::form.control-group.label>Kanaal
                                                </x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="select"
                                                    name="lead_channel_id"
                                                    v-model="formData.lead_channel_id"
                                                >
                                                    <option value="">-- Kies kanaal --</option>
                                                    @foreach ($channelOptions as $id => $name)
                                                        <option value="{{ $id }}">{{ $name }}</option>
                                                    @endforeach
                                                </x-admin::form.control-group.control>
                                            </x-admin::form.control-group>
                                        </div>
                                        <div class="flex-1">
                                            @php $sourceOptions = Source::query()->pluck('name', 'id')->toArray(); @endphp
                                            <x-admin::form.control-group>
                                                <x-admin::form.control-group.label>Bron
                                                </x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="select"
                                                    name="lead_source_id"
                                                    v-model="formData.lead_source_id"
                                                >
                                                    <option value="">-- Kies bron --</option>
                                                    @foreach ($sourceOptions as $id => $name)
                                                        <option value="{{ $id }}">{{ $name }}</option>
                                                    @endforeach
                                                </x-admin::form.control-group.control>
                                            </x-admin::form.control-group>
                                        </div>
                                    </div>

                                    <!-- Department and Type -->
                                    <div class="flex gap-4 mb-4">
                                        <div class="flex-1">
                                            @php $departmentOptions = Department::query()->pluck('name', 'id')->toArray(); @endphp
                                            <x-admin::form.control-group>
                                                <x-admin::form.control-group.label class="required">Afdeling
                                                </x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="select"
                                                    name="department_id"
                                                    v-model="formData.department_id"
                                                    rules="required"
                                                >
                                                    <option value="">-- Kies afdeling --</option>
                                                    @foreach ($departmentOptions as $id => $name)
                                                        <option value="{{ $id }}">{{ $name }}</option>
                                                    @endforeach
                                                </x-admin::form.control-group.control>
                                            </x-admin::form.control-group>
                                        </div>
                                        <div class="flex-1">
                                            @php $typeOptions = Type::query()->pluck('name', 'id')->toArray(); @endphp
                                            <x-admin::form.control-group>
                                                <x-admin::form.control-group.label>Type
                                                </x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="select"
                                                    name="lead_type_id"
                                                    v-model="formData.lead_type_id"
                                                >
                                                    <option value="">-- Kies type --</option>
                                                    @foreach ($typeOptions as $id => $name)
                                                        <option value="{{ $id }}">{{ $name }}</option>
                                                    @endforeach
                                                </x-admin::form.control-group.control>
                                            </x-admin::form.control-group>
                                        </div>
                                    </div>

                                    <!-- Combine Order Setting -->
                                    <div class="mb-4">
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>
                                                Orders combineren
                                            </x-admin::form.control-group.label>
                                            <x-admin::form.control-group.control
                                                type="select"
                                                name="combine_order"
                                                v-model="formData.combine_order"
                                            >
                                                <option value="1">Ja</option>
                                                <option value="0">Nee</option>
                                            </x-admin::form.control-group.control>
                                            <x-admin::form.control-group.error control-name="combine_order"/>
                                        </x-admin::form.control-group>
                                    </div>

                                    <!-- Other attributes -->
                                    <div class="flex gap-4 max-sm:flex-wrap">
                                        <div class="w-full">
                                            <x-admin::attributes
                                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                                    ['code', 'IN', ['user_id']],
                                                    'entity_type' => 'leads',
                                                    'quick_add'   => 1
                                                ])"
                                                :custom-validations="[]"
                                            />
                                        </div>
                                    </div>

                                    <!-- Address -->
                                    <div class="mt-4">
                                        @include('admin::components.address', ['entity' => null])
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

                data() {
                    return {
                        currentStep: 1,
                        selectedPersons: [],
                        persons: [],
                        isSubmitting: false,
                                                                                         formData: {
                        description: '',
                        lead_channel_id: '1', // Default: Telefoon
                        lead_source_id: 32, // Default: Anders
                        department_id: '{{ $defaultDepartmentId ?? "" }}', // Set based on pipeline or user groups
                        lead_pipeline_id: '{{ $defaultPipelineId ?? "" }}', // Set based on department or URL param
                        lead_pipeline_stage_id: '{{ $defaultStageId ?? "" }}', // Set based on pipeline or URL param
                        combine_order: 1,
                            lead_type_id: '1', // Default: Preventie
                            // Personal fields for matching
                            first_name: '',
                            last_name: '',
                            email: '',
                            phone: '',
                        }
                    };
                },

                mounted() {
                    // Initialize global variable for persons data
                    window.leadFormPersons = this.persons;

                    // Set up global reference to this component for cross-component communication
                    window.leadFormComponent = this;

                    // Initialize with empty person if needed
                    if (this.persons.length === 0) {
                        this.persons.push({
                            id: null,
                            name: '',
                            match_percentage: null,
                            organization: null
                        });
                    }
                },

                methods: {
                    goToStep(step) {
                        this.currentStep = step;

                        // If going to step 2 and we have selected persons, populate address from first person
                        if (step === 2 && this.persons.length > 0 && this.persons[0].address) {
                            this.$nextTick(() => {
                                this.populateAddressFields(this.persons[0].address);
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
                            }

                            // Also populate email and phone if available and form fields are empty
                            if (!this.formData.email && firstPerson.emails && firstPerson.emails.length > 0) {
                                this.formData.email = firstPerson.emails[0].value || '';
                            }

                            if (!this.formData.phone && firstPerson.phones && firstPerson.phones.length > 0) {
                                this.formData.phone = firstPerson.phones[0].value || '';
                            }
                        } else {
                            // If no persons selected, keep the fields as they are to allow manual entry
                        }
                    },



                    async submitForm() {
                        if (this.isSubmitting) return;

                        // Validate required fields


                        if (!this.formData.first_name || this.formData.first_name.trim() === '') {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'Voornaam is verplicht.'
                            });
                            return;
                        }

                        if (!this.formData.last_name || this.formData.last_name.trim() === '') {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'Achternaam is verplicht.'
                            });
                            return;
                        }

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

                            // Redirect to leads index with pipeline preservation
                            const pipelineId = this.getCookieValue('last_selected_pipeline_id');
                            const url = pipelineId 
                                ? '{{ route('admin.leads.index') }}?pipeline_id=' + pipelineId
                                : '{{ route('admin.leads.index') }}';
                            window.location.href = url;

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
            html {
                scroll-behavior: smooth;
            }
        </style>
    @endPushOnce
</x-admin::layouts>
