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

                        <!-- Multiple Persons Component -->
                        <v-multiple-persons-component :data="persons" @update:data="persons = $event"></v-multiple-persons-component>

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

                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>
                                                Organisatie
                                            </x-admin::form.control-group.label>

                                            <x-admin::form.control-group.control
                                                type="text"
                                                name="organization_lookup"
                                                placeholder="Zoek organisatie..."
                                                v-model="formData.organization_lookup"
                                            />
                                            
                                            <input type="hidden" name="organization_id" v-model="formData.organization_id">
                                        </x-admin::form.control-group>
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
                        selectedPersons: [],
                        persons: [],
                        isSubmitting: false,
                        formData: {
                            title: '',
                            description: '',
                            lead_channel_id: '',
                            lead_source_id: '',
                            department_id: '',
                            lead_type_id: '',
                            // Personal fields for matching
                            first_name: '',
                            last_name: '',
                            email: '',
                            phone: '',
                            // Organization for billing
                            organization_id: '',
                            organization_lookup: '',
                        }
                    };
                },

                mounted() {
                    // Initialize with empty person if needed
                    if (this.persons.length === 0) {
                        this.persons.push({
                            id: null,
                            name: '',
                            email: '',
                            phone: ''
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



                    async submitForm() {
                        if (this.isSubmitting) return;

                        // Validate required fields
                        if (!this.formData.title || this.formData.title.trim() === '') {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'Titel is verplicht.'
                            });
                            return;
                        }

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

                            // Add persons data to form
                            if (this.persons && this.persons.length > 0) {
                                this.persons.forEach((person, index) => {
                                    if (person.id) {
                                        formData.set(`persons[${index}][id]`, person.id);
                                    }
                                    if (person.name) {
                                        formData.set(`persons[${index}][name]`, person.name);
                                    }
                                    if (person.email) {
                                        formData.set(`persons[${index}][email]`, person.email);
                                    }
                                    if (person.phone) {
                                        formData.set(`persons[${index}][phone]`, person.phone);
                                    }
                                });
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
