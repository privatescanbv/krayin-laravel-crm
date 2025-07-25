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
                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
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
                        <div class="flex items-center justify-center w-8 h-8 rounded-full text-white text-sm font-medium"
                             :class="currentStep >= 1 ? 'bg-blue-600' : 'bg-gray-400'">
                            1
                        </div>
                        <span class="ml-2 text-sm font-medium" :class="currentStep >= 1 ? 'text-blue-600' : 'text-gray-500'">
                            Contactpersoon zoeken
                        </span>
                    </div>
                    <div class="w-16 h-0.5" :class="currentStep >= 2 ? 'bg-blue-600' : 'bg-gray-300'"></div>
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full text-white text-sm font-medium"
                             :class="currentStep >= 2 ? 'bg-blue-600' : 'bg-gray-400'">
                            2
                        </div>
                        <span class="ml-2 text-sm font-medium" :class="currentStep >= 2 ? 'text-blue-600' : 'text-gray-500'">
                            Lead gegevens
                        </span>
                    </div>
                </div>

                <!-- Step 1: Contact Matcher -->
                <div v-show="currentStep === 1" class="box-shadow rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 p-6">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-1">
                            <p class="text-xl font-semibold dark:text-white">
                                Stap 1: Contactpersoon zoeken
                            </p>
                            <p class="text-gray-600 dark:text-white">
                                Zoek eerst of de contactpersoon al bestaat in het systeem
                            </p>
                        </div>

                        <!-- Contact Matcher Component -->
                        <v-step-one-contact-matcher 
                            @person-selected="handlePersonSelected"
                            @person-not-found="handlePersonNotFound"
                        ></v-step-one-contact-matcher>

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
                            <input type="hidden" name="lead_pipeline_stage_id" value="{{ request('stage_id') }}" />
                        @endif

                        <!-- Hidden person_id field -->
                        <input type="hidden" name="person_id" :value="selectedPerson?.id || ''" />

                        <div class="box-shadow flex flex-col gap-4 rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 p-6">
                            <div class="flex flex-col gap-1">
                                <p class="text-xl font-semibold dark:text-white">
                                    Stap 2: Lead gegevens
                                </p>
                                <p class="text-gray-600 dark:text-white">
                                    Vul de lead informatie in
                                </p>
                                
                                <!-- Show selected person info if available -->
                                <div v-if="selectedPerson" class="mt-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
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
                                                <x-admin::form.control-group.label>Aanhef</x-admin::form.control-group.label>
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
                                                <x-admin::form.control-group.label>Initialen</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="initials"
                                                    v-model="formData.initials"
                                                    placeholder="J.A."
                                                />
                                            </x-admin::form.control-group>

                                            <x-admin::form.control-group class="flex-1">
                                                <x-admin::form.control-group.label>Voornaam</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="first_name"
                                                    v-model="formData.first_name"
                                                    placeholder="Voornaam"
                                                />
                                            </x-admin::form.control-group>
                                        </div>

                                        <!-- Last Name Row -->
                                        <div class="flex gap-4">
                                            <x-admin::form.control-group class="w-25">
                                                <x-admin::form.control-group.label>Tussenvoegsel</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="lastname_prefix"
                                                    v-model="formData.lastname_prefix"
                                                    placeholder="van, de, den, etc."
                                                />
                                            </x-admin::form.control-group>

                                            <x-admin::form.control-group class="flex-1">
                                                <x-admin::form.control-group.label>Achternaam bij geboorte</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="last_name"
                                                    v-model="formData.last_name"
                                                    placeholder="Achternaam"
                                                />
                                            </x-admin::form.control-group>
                                        </div>

                                        <!-- Married Name Row -->
                                        <div class="flex gap-4">
                                            <x-admin::form.control-group class="w-25">
                                                <x-admin::form.control-group.label>Tussenvoegsel</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="married_name_prefix"
                                                    v-model="formData.married_name_prefix"
                                                    placeholder="van, de, den, etc."
                                                />
                                            </x-admin::form.control-group>

                                            <x-admin::form.control-group class="flex-1">
                                                <x-admin::form.control-group.label>Aangetrouwde naam</x-admin::form.control-group.label>
                                                <x-admin::form.control-group.control
                                                    type="text"
                                                    name="married_name"
                                                    v-model="formData.married_name"
                                                />
                                            </x-admin::form.control-group>
                                        </div>

                                        <!-- Date of Birth -->
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>Geboortedatum</x-admin::form.control-group.label>
                                            <x-admin::form.control-group.control
                                                type="date"
                                                name="date_of_birth"
                                                v-model="formData.date_of_birth"
                                            />
                                        </x-admin::form.control-group>

                                        <!-- Gender -->
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>Geslacht</x-admin::form.control-group.label>
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
                                         <label class="block text-sm font-medium text-gray-700 mb-2">E-mailadressen</label>
                                         <div v-for="(email, index) in formData.emails" :key="'email-' + index" class="flex items-center space-x-2 mb-2">
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
                                             <button type="button" @click="removeEmail(index)" class="text-red-600 hover:text-red-800">
                                                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                 </svg>
                                             </button>
                                         </div>
                                         <button type="button" @click="addEmail" class="text-blue-600 hover:text-blue-800 text-sm">
                                             + E-mailadres toevoegen
                                         </button>
                                     </div>

                                     <div class="mb-4">
                                         <label class="block text-sm font-medium text-gray-700 mb-2">Telefoonnummers</label>
                                         <div v-for="(phone, index) in formData.phones" :key="'phone-' + index" class="flex items-center space-x-2 mb-2">
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
                                             <button type="button" @click="removePhone(index)" class="text-red-600 hover:text-red-800">
                                                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                 </svg>
                                             </button>
                                         </div>
                                         <button type="button" @click="addPhone" class="text-blue-600 hover:text-blue-800 text-sm">
                                             + Telefoonnummer toevoegen
                                         </button>
                                     </div>

                                    <!-- Channel and Source -->
                                    <div class="flex gap-4 mb-4">
                                        <div class="flex-1">
                                            @php $channelOptions = Channel::query()->pluck('name', 'id')->toArray(); @endphp
                                            <x-admin::form.control-group>
                                                <x-admin::form.control-group.label>Kanaal</x-admin::form.control-group.label>
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
                                                <x-admin::form.control-group.label>Bron</x-admin::form.control-group.label>
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
                                                <x-admin::form.control-group.label class="required">Afdeling</x-admin::form.control-group.label>
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
                                                <x-admin::form.control-group.label>Type</x-admin::form.control-group.label>
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
                                         <p class="text-base font-semibold dark:text-white mb-4">Adresgegevens</p>
                                         
                                         <div class="flex gap-4 mb-4">
                                             <x-admin::form.control-group class="flex-1">
                                                 <x-admin::form.control-group.label>Postcode</x-admin::form.control-group.label>
                                                 <x-admin::form.control-group.control
                                                     type="text"
                                                     name="address[postal_code]"
                                                     placeholder="1234 AB"
                                                 />
                                             </x-admin::form.control-group>

                                             <x-admin::form.control-group class="flex-1">
                                                 <x-admin::form.control-group.label>Huisnummer</x-admin::form.control-group.label>
                                                 <x-admin::form.control-group.control
                                                     type="text"
                                                     name="address[house_number]"
                                                     placeholder="123"
                                                 />
                                             </x-admin::form.control-group>
                                         </div>

                                         <div class="flex gap-4 mb-4">
                                             <x-admin::form.control-group class="flex-1">
                                                 <x-admin::form.control-group.label>Straat</x-admin::form.control-group.label>
                                                 <x-admin::form.control-group.control
                                                     type="text"
                                                     name="address[street]"
                                                     placeholder="Straatnaam"
                                                 />
                                             </x-admin::form.control-group>

                                             <x-admin::form.control-group class="flex-1">
                                                 <x-admin::form.control-group.label>Plaats</x-admin::form.control-group.label>
                                                 <x-admin::form.control-group.control
                                                     type="text"
                                                     name="address[city]"
                                                     placeholder="Plaatsnaam"
                                                 />
                                             </x-admin::form.control-group>
                                         </div>

                                         <x-admin::form.control-group>
                                             <x-admin::form.control-group.label>Land</x-admin::form.control-group.label>
                                             <x-admin::form.control-group.control
                                                 type="text"
                                                 name="address[country]"
                                                 value="Nederland"
                                                 placeholder="Land"
                                             />
                                         </x-admin::form.control-group>
                                     </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </script>

        <!-- Step 1 Contact Matcher Component -->
        <script type="text/x-template" id="v-step-one-contact-matcher-template">
            <div class="flex flex-col gap-4">
                <!-- Search Input -->
                <div class="max-w-md">
                    <label class="block font-semibold mb-2">Zoek contactpersoon</label>
                    <input
                        v-model="search"
                        @input="onSearch"
                        placeholder="Zoek op naam, e-mail, telefoon..."
                        class="input w-full"
                        autocomplete="off"
                    />
                </div>

                <!-- Search Results -->
                <div v-if="suggestions.length" class="max-w-2xl">
                    <h4 class="font-semibold mb-2">Gevonden contactpersonen:</h4>
                    <ul class="border rounded bg-white shadow">
                        <li
                            v-for="person in suggestions"
                            :key="person.id"
                            @click="selectPerson(person)"
                            class="px-4 py-3 cursor-pointer hover:bg-gray-100 border-b last:border-b-0"
                            :class="{ 'bg-blue-50 border-blue-200': selectedPersonId === person.id }"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <div class="font-medium">@{{ person.name }}</div>
                                        <div v-if="selectedPersonId === person.id" class="ml-2 text-blue-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        <span v-if="person.email">@{{ person.email }}</span>
                                        <span v-if="person.phone && person.email"> • </span>
                                        <span v-if="person.phone">@{{ person.phone }}</span>
                                    </div>
                                </div>
                                <div v-if="person.match_score_percentage" class="ml-3 flex-shrink-0">
                                    <div class="flex items-center">
                                        <div class="text-xs font-medium text-gray-700 mr-2">
                                            @{{ person.match_score_percentage }}% match
                                        </div>
                                        <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all duration-300"
                                                :class="getScoreColorClass(person.match_score_percentage)"
                                                :style="{ width: person.match_score_percentage + '%' }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- No results message -->
                <div v-else-if="search && hasSearched" class="max-w-2xl">
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <span class="font-medium text-yellow-800">Geen contactpersoon gevonden</span>
                        </div>
                        <p class="text-sm text-yellow-700 mt-1">
                            Er is geen bestaande contactpersoon gevonden met deze zoekterm. U kunt doorgaan naar stap 2 om een nieuwe lead aan te maken.
                        </p>
                    </div>
                </div>

                <!-- Selected person display -->
                <div v-if="selectedPersonId && selectedPerson" class="max-w-2xl">
                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="font-medium text-green-800">Contactpersoon geselecteerd:</span>
                                </div>
                                <div class="mt-1">
                                    <div class="font-medium text-green-900">@{{ selectedPerson.name }}</div>
                                    <div class="text-sm text-green-700">
                                        <span v-if="selectedPerson.email">@{{ selectedPerson.email }}</span>
                                        <span v-if="selectedPerson.phone && selectedPerson.email"> • </span>
                                        <span v-if="selectedPerson.phone">@{{ selectedPerson.phone }}</span>
                                    </div>
                                </div>
                            </div>
                            <button
                                @click="clearSelection"
                                type="button"
                                class="text-green-600 hover:text-green-800"
                                title="Selectie wissen"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            // Step One Contact Matcher Component
            app.component('v-step-one-contact-matcher', {
                template: '#v-step-one-contact-matcher-template',

                data() {
                    return {
                        search: '',
                        suggestions: [],
                        selectedPersonId: null,
                        selectedPerson: null,
                        searchTimeout: null,
                        hasSearched: false,
                    };
                },

                methods: {
                    onSearch() {
                        clearTimeout(this.searchTimeout);
                        this.hasSearched = false;

                        if (!this.search) {
                            this.suggestions = [];
                            return;
                        }

                        this.searchTimeout = setTimeout(() => {
                            this.fetchSuggestions(this.search);
                        }, 300);
                    },

                    async fetchSuggestions(query) {
                        try {
                            const response = await axios.get('/admin/contacts/persons/search', {
                                params: { query: query }
                            });

                            this.suggestions = response.data.data || [];
                            this.hasSearched = true;
                        } catch (e) {
                            console.warn('Zoekopdracht mislukt:', e);
                            this.suggestions = [];
                            this.hasSearched = true;
                        }
                    },

                    selectPerson(person) {
                        this.selectedPersonId = person.id;
                        this.selectedPerson = person;
                        this.$emit('person-selected', person);
                    },

                    clearSelection() {
                        this.selectedPersonId = null;
                        this.selectedPerson = null;
                        this.$emit('person-selected', null);
                    },

                    getScoreColorClass(score) {
                        if (score >= 80) return 'bg-green-500';
                        if (score >= 60) return 'bg-yellow-500';
                        if (score >= 40) return 'bg-orange-500';
                        return 'bg-red-500';
                    },
                }
            });

            // Main Two Step Form Component
            app.component('v-two-step-lead-form', {
                template: '#v-two-step-lead-form-template',

                data() {
                    return {
                        currentStep: 1,
                        selectedPerson: null,
                        isSubmitting: false,
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
                             emails: [{ value: '', label: 'work' }],
                             phones: [{ value: '', label: 'work' }],
                         }
                    };
                },

                                methods: {
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
                                this.formData.emails = [...person.emails];
                            }
                            if (person.phones && person.phones.length > 0) {
                                this.formData.phones = [...person.phones];
                            }
                        }
                    },

                    handlePersonNotFound() {
                        this.selectedPerson = null;
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
                        this.formData.emails = [{ value: '', label: 'work' }];
                        this.formData.phones = [{ value: '', label: 'work' }];
                    },

                     async submitForm() {
                         if (this.isSubmitting) return;

                         this.isSubmitting = true;

                         try {
                             const formData = new FormData(this.$refs.leadForm);
                             
                             // Add our Vue form data to the FormData
                             Object.keys(this.formData).forEach(key => {
                                 if (this.formData[key] !== null && this.formData[key] !== '') {
                                     formData.set(key, this.formData[key]);
                                 }
                             });

                             // Ensure person_id is set if we have a selected person
                             if (this.selectedPerson && this.selectedPerson.id) {
                                 formData.set('person_id', this.selectedPerson.id);
                             }

                             const response = await axios.post('{{ route('admin.leads.store') }}', formData, {
                                 headers: { 'Content-Type': 'multipart/form-data' }
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
                         this.formData.emails.push({ value: '', label: 'work' });
                     },

                     removeEmail(index) {
                         if (this.formData.emails.length > 1) {
                             this.formData.emails.splice(index, 1);
                         }
                     },

                     addPhone() {
                         this.formData.phones.push({ value: '', label: 'work' });
                     },

                     removePhone(index) {
                         if (this.formData.phones.length > 1) {
                             this.formData.phones.splice(index, 1);
                         }
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
