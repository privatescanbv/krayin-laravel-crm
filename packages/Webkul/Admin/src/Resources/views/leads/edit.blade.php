@php use Carbon\Carbon;use Webkul\Lead\Models\Channel;use Webkul\Lead\Models\Source;use Webkul\Lead\Models\Type;use App\Models\Department;use App\Models\User; @endphp
<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.leads.edit.title')
    </x-slot>

    {!! view_render_event('admin.leads.edit.form_controls.before', ['lead' => $lead]) !!}

    <!-- Edit Lead Form -->
    <x-admin::form
        :action="route('admin.leads.update', $lead->id)"
        method="PUT"
    >
        @if($errors->has('error'))
            <div
                class="mb-4 rounded border border-red-400 bg-red-100 px-4 py-3 text-red-800 dark:bg-red-900 dark:text-red-200">
                {{ $errors->first('error') }}
            </div>
        @endif
        <div class="flex flex-col gap-4">
            <div
                class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs
                        name="leads.edit"
                        :entity="$lead"
                    />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.leads.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    {!! view_render_event('admin.leads.edit.save_button.before', ['lead' => $lead]) !!}

                    <!-- Save button for Editing Lead -->
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.leads.edit.form_buttons.before') !!}

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.leads.edit.save-btn')
                        </button>

                        {!! view_render_event('admin.leads.edit.form_buttons.after') !!}
                    </div>

                    {!! view_render_event('admin.leads.edit.save_button.after', ['lead' => $lead]) !!}
                </div>
            </div>

            <input type="hidden" id="lead_pipeline_stage_id" name="lead_pipeline_stage_id"
                   value="{{ $lead->lead_pipeline_stage_id }}"/>


            <!-- Lead Edit Component -->
            <v-lead-edit :lead="{{ json_encode($lead) }}">
                <x-admin::shimmer.leads.datagrid/>
            </v-lead-edit>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.leads.edit.form_controls.after', ['lead' => $lead]) !!}

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-lead-edit-template"
        >
            <div
                class="box-shadow flex flex-col gap-4 rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex gap-2 border-b border-gray-200 dark:border-gray-800">
                    <!-- Tabs -->
                    <template v-for="tab in tabs" :key="tab.id">
                        {!! view_render_event('admin.leads.edit.tabs.before', ['lead' => $lead]) !!}

                        <a
                            :href="'#' + tab.id"
                            :class="[
                                'inline-block px-3 py-2.5 border-b-2  text-sm font-medium ',
                                activeTab === tab.id
                                ? 'text-brandColor border-brandColor dark:brandColor dark:brandColor'
                                : 'text-gray-600 dark:text-gray-300  border-transparent hover:text-gray-800 hover:border-gray-400 dark:hover:border-gray-400  dark:hover:text-white'
                            ]"
                            @click="scrollToSection(tab.id)"
                            :text="tab.label"
                        ></a>

                        {!! view_render_event('admin.leads.edit.tabs.after', ['lead' => $lead]) !!}
                    </template>
                </div>

                <div class="flex flex-col gap-4 px-4 py-2">
                    {!! view_render_event('admin.leads.edit.lead_details.before', ['lead' => $lead]) !!}

                    <!-- Details section -->
                    <div
                        class="flex flex-col gap-4"
                        id="lead-details"
                    >
                        <div class="flex flex-col gap-1">
                        </div>


                    {!! view_render_event('admin.leads.edit.personal_fields.before', ['lead' => $lead]) !!}

                    <!-- Personal Fields Section -->
                    <div
                        class="flex flex-col gap-4"
                        id="personal-fields"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                Persoons gegevens
                            </p>
                        </div>

                        <div class="w-1/2 max-md:w-full">
                            <!-- Personal Fields Component -->
                            @include('admin::leads.common.personal-fields', ['entity' => $lead])
                        </div>
                    </div>

                    {!! view_render_event('admin.leads.edit.personal_fields.after', ['lead' => $lead]) !!}


                    {!! view_render_event('admin.leads.edit.emails.before', ['lead' => $lead]) !!}

                    <!-- Emails Section -->
                    @include('admin::leads.common.sections.emails', ['name' => 'emails', 'value' => ($lead->emails ?? []), 'readonly' => !$lead->mayEditPersonFields()])

                    {!! view_render_event('admin.leads.edit.emails.after', ['lead' => $lead]) !!}

                    {!! view_render_event('admin.leads.edit.phones.before', ['lead' => $lead]) !!}

                    <!-- Phones Section -->
                    @include('admin::leads.common.sections.phones', ['name' => 'phones', 'value' => ($lead->phones ?? []), 'readonly' => !$lead->mayEditPersonFields()])

                    {!! view_render_event('admin.leads.edit.phones.after', ['lead' => $lead]) !!}

                    {!! view_render_event('admin.leads.edit.contact_person.before', ['lead' => $lead]) !!}

                    <!-- Contact Person Selection -->
                    <div
                        class="flex flex-col gap-4"
                        id="contact-person-selection"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                Contactpersoon
                            </p>
                            <p class="text-gray-600 dark:text-gray-300">
                                Selecteer de contactpersoon voor deze lead
                            </p>
                        </div>

                        <div class="w-1/2 max-md:w-full">
                            <x-adminc::components.entity-selector
                                name="contact_person_id"
                                label="Contactpersoon"
                                placeholder="Selecteer .."
                                search-route="{{ route('admin.contacts.persons.search') }}"
                                :current-value="$lead->contact_person_id"
                                :current-label="$lead->contactPerson ? $lead->contactPerson->name : null"
                                :can-add-new="true"
                            />
                        </div>
                    </div>

                    <!-- Contact Persons -->
                    <div
                        class="flex flex-col gap-4"
                        id="contact-person"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                Contactpersonen
                            </p>

                            <p class="text-gray-600 dark:text-gray-300">
                                Koppel een of meerdere contactpersonen aan deze lead
                            </p>
                        </div>

                        <x-adminc::components.multi-contactmatcher
                            :lead="$lead"
                            :persons="$lead->persons"
                        />
                    </div>

                    {!! view_render_event('admin.leads.edit.contact_person.after', ['lead' => $lead]) !!}

                    {!! view_render_event('admin.leads.edit.address.before', ['lead' => $lead]) !!}

                    <!-- Address Section -->
                    <div
                        class="flex flex-col gap-4"
                        id="address"
                    >
                        <div class="w-1/2 max-md:w-full">
                            @include('admin::components.address', ['entity' => $lead, 'readonly' => !$lead->mayEditPersonFields()])
                        </div>
                    </div>

                    {!! view_render_event('admin.leads.edit.address.after', ['lead' => $lead]) !!}

                    @include('admin::leads.common.sections.channel-to-owner', [
                        'entity' => $lead,
                        'defaults' => [],
                        'useVueModel' => false,
                    ])

                    <!-- Lead Details Description -->
                    <div class="mb-0.5">
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>
                                @lang('admin::app.leads.edit.description')
                            </x-admin::form.control-group.label>
                            <x-admin::form.control-group.control
                                type="textarea"
                                name="description"
                                value="{{ old('description', $lead->description) }}"
                                :label="trans('admin::app.leads.edit.description')"
                                :placeholder="trans('admin::app.leads.edit.description')"
                                class="min-h-[80px]"
                            />
                            <x-admin::form.control-group.error control-name="description"/>
                        </x-admin::form.control-group>
                    </div>

                    {!! view_render_event('admin.leads.edit.organization.before', ['lead' => $lead]) !!}

                    <!-- Organization Section -->
                    <div
                        class="flex flex-col gap-4"
                        id="organization"
                    >
                        <!-- Organization Component -->
                        @include('admin::leads.common.organization', ['organization' => $lead->organization])
                    </div>

                    {!! view_render_event('admin.leads.edit.organization.after', ['lead' => $lead]) !!}
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
                            $currentUserId = $lead->user_id;
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



                        <div class="w-1/2 max-md:w-full">
                            {!! view_render_event('admin.leads.edit.lead_details.attributes.before', ['lead' => $lead]) !!}

                            <!-- afdeling and other custom fields -->
                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                    ['code', 'NOTIN', ['lead_type_id', 'lead_source_id', 'user_id', 'lead_pipeline_id', 'lead_pipeline_stage_id', 'lead_channel_id']],
                                    'entity_type' => 'leads',
                                    'quick_add'   => 1
                                ])"
                                :custom-validations="[]"
                                :entity="$lead"
                            />

                            <!-- Lead Details Other input fields -->
                            <div class="flex gap-4 max-sm:flex-wrap">
                                <div class="w-full">
                                    <x-admin::attributes
                                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                            ['code', 'IN', ['lead_type_id', 'lead_source_id']],
                                            'entity_type' => 'leads',
                                            'quick_add'   => 1
                                        ])"
                                        :custom-validations="[]"
                                        :entity="$lead"
                                    />
                                </div>
                            </div>

                            {!! view_render_event('admin.leads.edit.lead_details.attributes.after', ['lead' => $lead]) !!}
                        </div>
                    </div>


                    {!! view_render_event('admin.leads.edit.lead_details.after', ['lead' => $lead]) !!}
                </div>

                {!! view_render_event('admin.leads.form_controls.after') !!}
            </div>
        </script>

        <script type="module">
            app.component('v-lead-edit', {
                template: '#v-lead-edit-template',

                data() {
                    return {
                        activeTab: 'lead-details',

                        lead: {
                            id: {{ $lead->id }},
                            title: "{{ addslashes($lead->name) }}"
                            // Simplified lead data to avoid JSON parsing errors
                        },

                        {{--products: @json($lead->products),--}}

                        tabs: [
                            {id: 'lead-details', label: '@lang('admin::app.leads.edit.details')'},
                            {id: 'emails', label: '@lang('admin::app.leads.common.emails.title')'},
                            {id: 'phones', label: 'Telefoonnummers'},
                            {id: 'contact-person', label: '@lang('admin::app.leads.edit.contact-person')'},
                            {id: 'personal-fields', label: 'Persoonsgegevens'},
                            {id: 'address', label: 'Adres'}
                            {{--{ id: 'products', label: '@lang('admin::app.leads.edit.products')' }--}}
                        ],
                    };
                },

                methods: {
                    /**
                     * Scroll to the section.
                     *
                     * @param {String} tabId
                     *
                     * @returns {void}
                     */
                    scrollToSection(tabId) {
                        const section = document.getElementById(tabId);

                        if (section) {
                            section.scrollIntoView({behavior: 'smooth'});
                        }
                    },
                },
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
