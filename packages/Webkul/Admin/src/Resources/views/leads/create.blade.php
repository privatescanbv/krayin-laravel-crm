@php use App\Models\Department;use Webkul\Lead\Models\Channel;use Webkul\Lead\Models\Source;use Webkul\Lead\Models\Type; @endphp
<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.create.title')
    </x-slot>

    {!! view_render_event('admin.leads.create.form.before') !!}

    <!-- Create Lead Form -->
    <x-admin::form :action="route('admin.leads.store')">
        <div class="flex flex-col gap-4">
            <div
                class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="leads.create"/>

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.leads.create.title')
                    </div>
                </div>

                {!! view_render_event('admin.leads.create.save_button.before') !!}

                <div class="flex items-center gap-x-2.5">
                    <!-- Save button for person -->
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.leads.create.form_buttons.before') !!}

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.leads.create.save-btn')
                        </button>

                        {!! view_render_event('admin.leads.create.form_buttons.after') !!}
                    </div>
                </div>

                {!! view_render_event('admin.leads.create.save_button.after') !!}
            </div>

            @if (request('stage_id'))
                <input
                    type="hidden"
                    id="lead_pipeline_stage_id"
                    name="lead_pipeline_stage_id"
                    value="{{ request('stage_id') }}"
                />
            @endif

            <!-- Lead Create Component -->
            <v-lead-create>
                <x-admin::shimmer.leads.datagrid/>
            </v-lead-create>

        </div>
    </x-admin::form>

    {!! view_render_event('admin.leads.create.form.after') !!}

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-lead-create-template"
        >
            <div
                class="box-shadow flex flex-col gap-4 rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                {!! view_render_event('admin.leads.edit.form_controls.before') !!}

                <div class="flex w-full gap-2 border-b border-gray-200 dark:border-gray-800">
                    <!-- Tabs -->
                    <template
                        v-for="tab in tabs"
                        :key="tab.id"
                    >
                        {!! view_render_event('admin.leads.create.tabs.before') !!}

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
                        >
                        </a>

                        {!! view_render_event('admin.leads.create.tabs.after') !!}
                    </template>
                </div>

                <div class="flex flex-col gap-4 px-4 py-2">
                    {!! view_render_event('admin.leads.create.details.before') !!}

                    <!-- Details section -->
                    <div
                        class="flex flex-col gap-4"
                        id="lead-details"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.create.details')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.leads.create.details-info')
                            </p>
                        </div>

                        <div class="w-1/2 max-md:w-full">
                            {!! view_render_event('admin.leads.create.details.attributes.before') !!}

                            <!-- Lead Details Title and Description -->
                            <div class="mb-4">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        @lang('admin::app.leads.create.title')
                                    </x-admin::form.control-group.label>
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="title"
                                        value="{{ old('title') }}"
                                        rules="required"
                                        :label="trans('admin::app.leads.create.title')"
                                        :placeholder="trans('admin::app.leads.create.title')"
                                    />
                                    <x-admin::form.control-group.error control-name="title"/>
                                </x-admin::form.control-group>
                            </div>
                            <div class="mb-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.create.description')
                                    </x-admin::form.control-group.label>
                                    <x-admin::form.control-group.control
                                        type="textarea"
                                        name="description"
                                        value="{{ old('description') }}"
                                        :label="trans('admin::app.leads.create.description')"
                                        :placeholder="trans('admin::app.leads.create.description')"
                                        class="min-h-[80px]"
                                    />
                                    <x-admin::form.control-group.error control-name="description"/>
                                </x-admin::form.control-group>
                            </div>


                            <!-- LEAD CHANNEL DROPDOWN -->
                            <div class="mb-0.5">
                                @php
                                    $channelOptions = Channel::query()->pluck('name', 'id')->toArray();
                                    $sourceOptions = Source::query()->pluck('name', 'id')->toArray();
                                    $departmentOptions = Department::query()->pluck('name', 'id')->toArray();
                                    $typeOptions = Type::query()->pluck('name', 'id')->toArray();
                                @endphp
                                    <!-- KANAAL EN BRON NAAST ELKAAR -->
                                <div class="flex gap-4 mb-0.5">
                                    <div class="flex-1">
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>
                                                Kanaal
                                            </x-admin::form.control-group.label>
                                            <x-admin::form.control-group.control
                                                type="select"
                                                name="lead_channel_id"
                                                value="{{ old('lead_channel_id', $lead->lead_channel_id ?? '') }}"
                                                :label="'Kanaal'"
                                            >
                                                <option value="">-- Kies kanaal --</option>
                                                @foreach ($channelOptions as $id => $name)
                                                    <option
                                                        value="{{ $id }}" {{ (old('lead_channel_id', $lead->lead_channel_id ?? '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                                                @endforeach
                                            </x-admin::form.control-group.control>
                                            <x-admin::form.control-group.error control-name="lead_channel_id"/>
                                        </x-admin::form.control-group>
                                    </div>
                                    <div class="flex-1">
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>
                                                Bron
                                            </x-admin::form.control-group.label>
                                            <x-admin::form.control-group.control
                                                type="select"
                                                name="lead_source_id"
                                                value="{{ old('lead_source_id', $lead->lead_source_id ?? '') }}"
                                                :label="'Bron'"
                                            >
                                                <option value="">-- Kies bron --</option>
                                                @foreach ($sourceOptions as $id => $name)
                                                    <option
                                                        value="{{ $id }}" {{ (old('lead_source_id', $lead->lead_source_id ?? '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                                                @endforeach
                                            </x-admin::form.control-group.control>
                                            <x-admin::form.control-group.error control-name="lead_source_id"/>
                                        </x-admin::form.control-group>
                                    </div>
                                </div>
                            </div>

                            <!-- DEPARTMENT EN TYPE NAAST ELKAAR -->
                            <div class="flex gap-4 mb-0.5">
                                <div class="flex-1">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            Afdeling
                                        </x-admin::form.control-group.label>
                                        <x-admin::form.control-group.control
                                            type="select"
                                            name="department_id"
                                            value="{{ old('department_id', $lead->department_id ?? '') }}"
                                            rules="required"
                                            :label="'Afdeling'"
                                        >
                                            <option value="">-- Kies afdeling --</option>
                                            @foreach ($departmentOptions as $id => $name)
                                                <option
                                                    value="{{ $id }}" {{ (old('department_id', $lead->department_id ?? '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                                            @endforeach
                                        </x-admin::form.control-group.control>
                                        <x-admin::form.control-group.error control-name="department_id"/>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label>
                                            Type
                                        </x-admin::form.control-group.label>
                                        <x-admin::form.control-group.control
                                            type="select"
                                            name="lead_type_id"
                                            value="{{ old('lead_type_id', $lead->lead_type_id ?? '') }}"
                                            :label="'Type'"
                                        >
                                            <option value="">-- Kies type --</option>
                                            @foreach ($typeOptions as $id => $name)
                                                <option
                                                    value="{{ $id }}" {{ (old('lead_type_id', $lead->lead_type_id ?? '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                                            @endforeach
                                        </x-admin::form.control-group.control>
                                        <x-admin::form.control-group.error control-name="lead_type_id"/>
                                    </x-admin::form.control-group>
                                </div>
                            </div>


                            <!-- Lead Details Other input fields -->
                            <div class="flex gap-4 max-sm:flex-wrap">
                                <div class="w-full">
                                    <x-admin::attributes
                                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                            ['code', 'IN', ['lead_value', 'lead_type_id', 'lead_source_id']],
                                            'entity_type' => 'leads',
                                            'quick_add'   => 1
                                        ])"
                                        :custom-validations="[
                                            'expected_close_date' => [
                                                'date_format:yyyy-MM-dd',
                                                'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                            ],
                                        ]"
                                    />
                                </div>

                                <div class="w-full">
                                    <x-admin::attributes
                                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                            ['code', 'IN', ['expected_close_date', 'user_id']],
                                            'entity_type' => 'leads',
                                            'quick_add'   => 1
                                        ])"
                                        :custom-validations="[
                                            'expected_close_date' => [
                                                'date_format:yyyy-MM-dd',
                                                'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                            ],
                                        ]"
                                    />
                                </div>
                            </div>

                            {!! view_render_event('admin.leads.create.details.attributes.after') !!}
                        </div>
                    </div>

                    {!! view_render_event('admin.leads.create.details.after') !!}

                    {!! view_render_event('admin.leads.create.contact_person.before') !!}

                    <!-- Contact Person -->
                    <div
                        class="flex flex-col gap-4"
                        id="contact-person"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.create.contact-person')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.leads.create.contact-info')
                            </p>
                        </div>

                        <div class="w-1/2 max-md:w-full">
                            <!-- Contact Person Component -->
                            @include('admin::leads.common.contact')
                        </div>
                    </div>

                    {!! view_render_event('admin.leads.create.contact_person.after') !!}

                    {!! view_render_event('admin.leads.create.personal_fields.before') !!}

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
                            @include('admin::leads.common.personal-fields', ['entity' => null])
                        </div>
                    </div>

                    {!! view_render_event('admin.leads.create.personal_fields.after') !!}

                    {!! view_render_event('admin.leads.create.address.before') !!}

                    <!-- Address Section -->
                    <div
                        class="flex flex-col gap-4"
                        id="address"
                    >
                        <div class="w-1/2 max-md:w-full">
                            @include('admin::components.address', ['entity' => null])
                        </div>
                    </div>

                    {!! view_render_event('admin.leads.create.address.after') !!}

                </div>

                {!! view_render_event('admin.leads.form_controls.after') !!}
            </div>
        </script>

        <script type="module">
            app.component('v-lead-create', {
                template: '#v-lead-create-template',

                data() {
                    return {
                        activeTab: 'lead-details',

                        tabs: [
                            {id: 'lead-details', label: '@lang('admin::app.leads.create.details')'},
                            {id: 'contact-person', label: '@lang('admin::app.leads.create.contact-person')'},
                            {id: 'personal-fields', label: 'Persoonsgegevens'},
                            {id: 'address', label: 'Adres'},
                            {id: 'products', label: '@lang('admin::app.leads.create.products')'}
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
