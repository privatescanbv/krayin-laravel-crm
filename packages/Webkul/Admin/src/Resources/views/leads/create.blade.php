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
                            Contactpersoon zoeken
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
                                Stap 1: Contactpersoon zoeken
                            </p>
                            <p class="text-gray-600 dark:text-white">
                                Zoek eerst of de contactpersoon al bestaat in het systeem
                            </p>
                        </div>

                        <!-- Use the reusable contact matcher component -->
                        @include('admin::leads.common.contactmatcher', ['lead' => (object)['id' => null]])

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

                                <!-- Show selected person info if available -->
                                <div v-if="selectedPerson"
                                     class="mt-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span class="font-medium text-green-800">Contactpersoon gekoppeld:</span>
                                        <span class="text-green-700">@{{ selectedPerson.name }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Lead Details -->
                            <div class="flex flex-col gap-4">
                                <div class="w-1/2 max-md:w-full">
                                    <!-- Title -->
                                    <div class="mb-4">
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label class="required">
                                                @lang('admin::app.leads.create.title')
                                            </x-admin::form.control-group.label>
                                            <x-admin::form.control-group.control
                                                type="text"
                                                name="title"
                                                v-model="formData.title"
                                                rules="required"
                                                label="Titel"
                                                placeholder="Titel"
                                            />
                                            <x-admin::form.control-group.error control-name="title"/>
                                        </x-admin::form.control-group>
                                    </div>

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

                                    <!-- Personal Fields -->
                                    <div class="flex flex-col gap-4 mb-4">
                                        <p class="text-base font-semibold dark:text-white">Persoons gegevens</p>

                                        <!-- Salutation -->
                                        <div class="flex gap-4">
                                            <x-admin::form.control-group class="w-40">
                                                <x-admin::form.control-group.label>Aanhef
                                                </x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="select"
                                                    name="salutation"
                                                    v-model="formData.salutation"
                                                >
                                                    <option value="">Selecteer aanhef</option>
                                                    <option value="Dhr.">Dhr.</option>
                                                    <option value="Mevr.">Mevr.</option>
                                                </x-admin::form.control-group.control>
                                            </x-admin::form.control-group>
                                        </div>

                                        <!-- Initials and First Name Row -->
                                        <div class="flex gap-4">
                                            <x-admin::form.control-group class="w-20">
                                                <x-admin::form.control-group.label>Initialen
                                                </x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="initials"
                                                    v-model="formData.initials"
                                                    placeholder="J.A."
                                                />
                                            </x-admin::form.control-group>

                                            <x-admin::form.control-group class="flex-1">
                                                <x-admin::form.control-group.label class="required">Voornaam
                                                </x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="first_name"
                                                    v-model="formData.first_name"
                                                    placeholder="Voornaam"
                                                    rules="required"
                                                />
                                                <x-admin::form.control-group.error control-name="first_name"/>
                                            </x-admin::form.control-group>
                                        </div>

                                        <!-- Last Name Row -->
                                        <div class="flex gap-4">
                                            <x-admin::form.control-group class="w-25">
                                                <x-admin::form.control-group.label>Tussenvoegsel
                                                </x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="lastname_prefix"
                                                    v-model="formData.lastname_prefix"
                                                    placeholder="van, de, den, etc."
                                                />
                                            </x-admin::form.control-group>

                                            <x-admin::form.control-group class="flex-1">
                                                <x-admin::form.control-group.label
                                                    class="required">@lang('admin::app.leads.merge.field-last-name-birth')</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="last_name"
                                                    v-model="formData.last_name"
                                                    rules="required"
                                                />
                                                <x-admin::form.control-group.error control-name="last_name"/>
                                            </x-admin::form.control-group>
                                        </div>

                                        <!-- Married Name Row -->
                                        <div class="flex gap-4">
                                            <x-admin::form.control-group class="w-25">
                                                <x-admin::form.control-group.label>Tussenvoegsel
                                                </x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="married_name_prefix"
                                                    v-model="formData.married_name_prefix"
                                                    placeholder="van, de, den, etc."
                                                />
                                            </x-admin::form.control-group>

                                            <x-admin::form.control-group class="flex-1">
                                                <x-admin::form.control-group.label>@lang('admin::app.leads.merge.field-last-name-married')</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="married_name"
                                                    v-model="formData.married_name"
                                                />
                                            </x-admin::form.control-group>
                                        </div>

                                        <!-- Date of Birth -->
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>Geboortedatum
                                            </x-admin::form.control-group.label>
                                            <x-admin::form.control-group.control
                                                type="date"
                                                name="date_of_birth"
                                                v-model="formData.date_of_birth"
                                            />
                                        </x-admin::form.control-group>

                                        <!-- Gender -->
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>Geslacht
                                            </x-admin::form.control-group.label>
                                            <x-admin::form.control-group.control
                                                type="select"
                                                name="gender"
                                                v-model="formData.gender"
                                            >
                                                <option value="">Selecteer geslacht</option>
                                                <option value="Man">Man</option>
                                                <option value="Vrouw">Vrouw</option>
                                                <option value="Anders">Anders</option>
                                            </x-admin::form.control-group.control>
                                        </x-admin::form.control-group>
                                    </div>

                                    <!-- Emails and Phones -->
                                    <div class="mb-4">
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-2">E-mailadressen</label>
                                        <div v-for="(email, index) in formData.emails" :key="'email-' + index"
                                             class="flex items-center space-x-2 mb-2">
                                            <input
                                                type="email"
                                                :name="'emails[' + index + '][value]'"
                                                v-model="email.value"
                                                placeholder="E-mailadres"
                                                class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                            <select
                                                :name="'emails[' + index + '][label]'"
                                                v-model="email.label"
                                                class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            >
                                                <option value="work">Werk</option>
                                                <option value="home">Thuis</option>
                                                <option value="other">Anders</option>
                                            </select>
                                            <div class="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    :name="'emails[' + index + '][is_default]'"
                                                    v-model="email.is_default"
                                                    @change="handleEmailDefaultChange(index, $event)"
                                                    class="mr-1"
                                                />
                                                <label class="text-xs text-gray-600">Standaard</label>
                                            </div>
                                            <button type="button" @click="removeEmail(index)"
                                                    class="text-red-600 hover:text-red-800">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <button type="button" @click="addEmail"
                                                class="text-blue-600 hover:text-blue-800 text-sm">
                                            + E-mailadres toevoegen
                                        </button>
                                    </div>

                                    <div class="mb-4">
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-2">Telefoonnummers</label>
                                        <div v-for="(phone, index) in formData.phones" :key="'phone-' + index"
                                             class="flex items-center space-x-2 mb-2">
                                            <input
                                                type="tel"
                                                :name="'phones[' + index + '][value]'"
                                                v-model="phone.value"
                                                placeholder="Telefoonnummer"
                                                class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                            <select
                                                :name="'phones[' + index + '][label]'"
                                                v-model="phone.label"
                                                class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            >
                                                <option value="work">Werk</option>
                                                <option value="home">Thuis</option>
                                                <option value="mobile">Mobiel</option>
                                                <option value="other">Anders</option>
                                            </select>
                                            <div class="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    :name="'phones[' + index + '][is_default]'"
                                                    v-model="phone.is_default"
                                                    @change="handlePhoneDefaultChange(index, $event)"
                                                    class="mr-1"
                                                />
                                                <label class="text-xs text-gray-600">Standaard</label>
                                            </div>
                                            <button type="button" @click="removePhone(index)"
                                                    class="text-red-600 hover:text-red-800">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <button type="button" @click="addPhone"
                                                class="text-blue-600 hover:text-blue-800 text-sm">
                                            + Telefoonnummer toevoegen
                                        </button>
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

                                    <!-- Other attributes -->
                                    <div class="flex gap-4 max-sm:flex-wrap">
                                        <div class="w-full">
                                            <x-admin::attributes
                                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                                    ['code', 'IN', ['lead_value', 'expected_close_date', 'user_id']],
                                                    'entity_type' => 'leads',
                                                    'quick_add'   => 1
                                                ])"
                                                :custom-validations="[
                                                    'expected_close_date' => [
                                                        'date_format:d-m-Y',
                                                        'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                                    ],
                                                ]"
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
                        selectedPerson: null,
                        isSubmitting: false,
                        personWatcherInterval: null,
                        lastPersonId: null,
                        formData: {
                            title: '',
                            description: '',
                            lead_channel_id: '',
                            lead_source_id: '',
                            department_id: '',
                            lead_type_id: '',
                            // Personal fields will be populated from selectedPerson
                            first_name: '',
                            last_name: '',
                            lastname_prefix: '',
                            married_name: '',
                            married_name_prefix: '',
                            initials: '',
                            date_of_birth: '',
                            gender: '',
                            salutation: '',
                            // Contact arrays
                            emails: [{value: '', label: 'work', is_default: true}],
                            phones: [{value: '', label: 'work', is_default: true}],
                        }
                    };
                },

                mounted() {
                    // Set up a watcher to detect person selection from the contact matcher
                    this.setupPersonWatcher();
                },

                beforeUnmount() {
                    if (this.personWatcherInterval) {
                        clearInterval(this.personWatcherInterval);
                    }
                },

                methods: {
                    setupPersonWatcher() {
                        // Watch for changes in the person_id hidden input from the contact matcher
                        this.personWatcherInterval = setInterval(() => {
                            const personIdInput = document.querySelector('input[name="person_id"]');
                            const currentPersonId = personIdInput ? personIdInput.value : null;
                            
                            if (currentPersonId && currentPersonId !== this.lastPersonId) {
                                this.lastPersonId = currentPersonId;
                                this.fetchPersonDetails(currentPersonId);
                            } else if (!currentPersonId && this.lastPersonId) {
                                // Person was deselected
                                this.lastPersonId = null;
                                this.selectedPerson = null;
                                this.clearFormData();
                            }
                        }, 500);
                    },

                    async fetchPersonDetails(personId) {
                        try {
                            const response = await axios.get(`/admin/contacts/persons/${personId}`);
                            const person = response.data.data;
                            this.handlePersonSelected(person);
                        } catch (error) {
                            console.warn('Could not fetch person details:', error);
                        }
                    },

                    clearFormData() {
                        // Clear personal fields
                        this.formData.first_name = '';
                        this.formData.last_name = '';
                        this.formData.lastname_prefix = '';
                        this.formData.married_name = '';
                        this.formData.married_name_prefix = '';
                        this.formData.initials = '';
                        this.formData.date_of_birth = '';
                        this.formData.gender = '';
                        this.formData.salutation = '';
                        // Reset contact arrays
                        this.formData.emails = [{value: '', label: 'work', is_default: true}];
                        this.formData.phones = [{value: '', label: 'work', is_default: true}];
                    },

                    goToStep(step) {
                        this.currentStep = step;
                    },

                    handlePersonSelected(person) {
                        this.selectedPerson = person;
                        if (person) {
                            // Pre-fill form data with person information
                            this.formData.first_name = person.first_name || '';
                            this.formData.last_name = person.last_name || '';
                            this.formData.lastname_prefix = person.lastname_prefix || '';
                            this.formData.married_name = person.married_name || '';
                            this.formData.married_name_prefix = person.married_name_prefix || '';
                            this.formData.initials = person.initials || '';
                            this.formData.date_of_birth = person.date_of_birth || '';
                            this.formData.gender = person.gender || '';
                            this.formData.salutation = person.salutation || '';

                            // Pre-populate emails and phones if available
                            if (person.emails && person.emails.length > 0) {
                                // Ensure each email has is_default property
                                this.formData.emails = person.emails.map((email, index) => ({
                                    value: email.value || '',
                                    label: this.normalizeLabel(email.label) || 'work',
                                    is_default: email.is_default !== undefined ? Boolean(email.is_default) : (index === 0)
                                }));
                            } else {
                                // Reset to default if no emails
                                this.formData.emails = [{value: '', label: 'work', is_default: true}];
                            }

                            if (person.phones && person.phones.length > 0) {
                                // Ensure each phone has is_default property
                                this.formData.phones = person.phones.map((phone, index) => ({
                                    value: phone.value || '',
                                    label: this.normalizeLabel(phone.label) || 'work',
                                    is_default: phone.is_default !== undefined ? Boolean(phone.is_default) : (index === 0)
                                }));
                            } else {
                                // Reset to default if no phones
                                this.formData.phones = [{value: '', label: 'work', is_default: true}];
                            }

                            // Pre-populate address if available
                            this.$nextTick(() => {
                                if (person.address) {
                                    this.populateAddressFields(person.address);
                                }
                            });
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
                                // Skip emails and phones as they are handled by the HTML form inputs
                                if (key === 'emails' || key === 'phones') {
                                    return;
                                } else if (this.formData[key] !== null && this.formData[key] !== '' && this.formData[key] !== undefined) {
                                    // Special handling for dates
                                    if (key === 'date_of_birth' && this.formData[key]) {
                                        // Ensure date is in correct format
                                        formData.set(key, this.formData[key]);
                                    } else if (key !== 'date_of_birth') {
                                        formData.set(key, this.formData[key]);
                                    }
                                }
                            });

                            // Get person_id from the contact matcher component (hidden input)
                            const personIdInput = document.querySelector('input[name="person_id"]');
                            if (personIdInput && personIdInput.value) {
                                formData.set('person_id', personIdInput.value);
                            }

                            const response = await axios.post('{{ route('admin.leads.store') }}', formData, {
                                headers: {'Content-Type': 'multipart/form-data'}
                            });

                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: 'Lead succesvol aangemaakt!'
                            });

                            // Redirect to leads index
                            window.location.href = '{{ route('admin.leads.index') }}';

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

                    addEmail() {
                        this.formData.emails.push({value: '', label: 'work', is_default: false});
                    },

                    removeEmail(index) {
                        if (this.formData.emails.length > 1) {
                            const wasDefault = this.formData.emails[index].is_default;
                            this.formData.emails.splice(index, 1);

                            // If we removed the default email, make the first one default
                            if (wasDefault && this.formData.emails.length > 0) {
                                this.formData.emails[0].is_default = true;
                            }
                        }
                    },

                    addPhone() {
                        this.formData.phones.push({value: '', label: 'work', is_default: false});
                    },

                    removePhone(index) {
                        if (this.formData.phones.length > 1) {
                            const wasDefault = this.formData.phones[index].is_default;
                            this.formData.phones.splice(index, 1);

                            // If we removed the default phone, make the first one default
                            if (wasDefault && this.formData.phones.length > 0) {
                                this.formData.phones[0].is_default = true;
                            }
                        }
                    },

                    handleEmailDefaultChange(index, event) {
                        const isChecked = event.target.checked;

                        // Uncheck all other checkboxes
                        this.formData.emails.forEach((email, i) => {
                            if (i !== index) {
                                email.is_default = false;
                            }
                        });

                        // Set the current email's default status
                        this.formData.emails[index].is_default = isChecked;

                        // If no email is checked, make the first one default
                        if (!isChecked && this.formData.emails.length > 0) {
                            this.formData.emails[0].is_default = true;
                        }
                    },

                    handlePhoneDefaultChange(index, event) {
                        const isChecked = event.target.checked;

                        // Uncheck all other checkboxes
                        this.formData.phones.forEach((phone, i) => {
                            if (i !== index) {
                                phone.is_default = false;
                            }
                        });

                        // Set the current phone's default status
                        this.formData.phones[index].is_default = isChecked;

                        // If no phone is checked, make the first one default
                        if (!isChecked && this.formData.phones.length > 0) {
                            this.formData.phones[0].is_default = true;
                        }
                    },

                    normalizeLabel(label) {
                        if (!label) return 'work';

                        // Convert to lowercase and map common variations
                        const normalizedLabel = label.toLowerCase();
                        const labelMap = {
                            'work': 'work',
                            'werk': 'work',
                            'home': 'home',
                            'thuis': 'home',
                            'mobile': 'mobile',
                            'mobiel': 'mobile',
                            'other': 'other',
                            'anders': 'other'
                        };

                        return labelMap[normalizedLabel] || 'work';
                    },

                    populateAddressFields(address) {
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
