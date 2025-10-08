@php
    $quote = app('\Webkul\Quote\Repositories\QuoteRepository')->getModel();

    if (isset($lead)) {
        $firstPerson = $lead->persons->first();
        $quote->fill([
            'person_id'       => $firstPerson?->id,
            'user_id'         => $lead->user_id,
            'billing_address' => $firstPerson?->organization?->address ? [
                'street' => $firstPerson->organization->address->street,
                'house_number' => $firstPerson->organization->address->house_number,
                'postal_code' => $firstPerson->organization->address->postal_code,
                'house_number_suffix' => $firstPerson->organization->address->house_number_suffix,
                'state' => $firstPerson->organization->address->state,
                'city' => $firstPerson->organization->address->city,
                'country' => $firstPerson->organization->address->country,
            ] : null
        ]);
    }
@endphp

<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.quotes.create.title')
    </x-slot>

    {!! view_render_event('admin.contacts.quotes.create.form_controls.before') !!}

    <x-admin::form
        :action="route('admin.quotes.store').'?'.http_build_query(array_merge(
            request()->route()->parameters(),
            request()->all()
        ))"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs
                        name="quotes.create"
                    />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.quotes.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <!-- Save button for person -->
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.contacts.quotes.create.save_button.before') !!}

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.quotes.create.save-btn')
                        </button>

                        {!! view_render_event('admin.contacts.quotes.create.save_button.after') !!}
                    </div>
                </div>
            </div>

            <v-quote :errors="errors">
                <x-admin::shimmer.quotes />
            </v-quote>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.contacts.quotes.create.form_controls.after') !!}

    @pushOnce('scripts')
        @verbatim
        <script
            type="text/x-template"
            id="v-quote-template"
        >
            <div class="box-shadow flex flex-col gap-4 rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex w-full gap-2 border-b border-gray-200 dark:border-gray-800">
                    {!! view_render_event('admin.contacts.quotes.create.tabs.before') !!}

                    <template
                        v-for="tab in tabs"
                        :key="tab.id"
                    >
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
                    </template>

                    {!! view_render_event('admin.contacts.quotes.create.tabs.after') !!}
                </div>

                <div class="flex flex-col gap-4 px-4 py-2">
                    {!! view_render_event('admin.contacts.quotes.create.quote_information.before') !!}

                    <!-- Quote information -->
                    <div
                        id="quote-info"
                        class="flex flex-col gap-4"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold text-gray-800 dark:text-white">
                                @lang('admin::app.quotes.create.quote-info')
                            </p>

                            <p class="text-sm text-gray-600 dark:text-white">
                                @lang('admin::app.quotes.create.quote-info-info')
                            </p>
                        </div>

                        {!! view_render_event('admin.contacts.quotes.create.attribute.form_controls.before') !!}

                        <div class="w-1/2 max-md:w-full">
                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                    'entity_type' => 'quotes',
                                    ['code', 'IN', ['subject']],
                                ])"

                                :custom-validations="[
                                    'expired_at' => [
                                        'required',
                                        'date_format:d-m-Y',
                                        'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                    ],
                                ]"
                            />

                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                        'entity_type' => 'quotes',
                                        ['code', 'IN', ['description']],
                                    ])"
                                :custom-validations="[
                                    'expired_at' => [
                                        'required',
                                        'date_format:d-m-Y',
                                        'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                    ],
                                ]"
                            />

                            <div class="flex gap-4">
                                <x-admin::attributes
                                    :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                        'entity_type' => 'quotes',
                                        ['code', 'IN', ['expired_at', 'user_id']],
                                    ])->sortBy('sort_order')"
                                    :custom-validations="[
                                        'expired_at' => [
                                            'required',
                                            'date_format:d-m-Y',
                                            'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                        ],
                                    ]"
                                    :entity="$quote"
                                />
                            </div>

                            <div class="flex gap-4">
                                <x-admin::attributes
                                    :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                        'entity_type' => 'quotes',
                                        ['code', 'IN', ['person_id']],
                                    ])->sortBy('sort_order')"
                                    :custom-validations="[
                                        'expired_at' => [
                                            'required',
                                            'date_format:d-m-Y',
                                            'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                        ],
                                    ]"
                                    :entity="$quote"
                                />

                                <x-admin::attributes.edit.lookup />

                                @php
                                    $lookUpEntityData = app('Webkul\Attribute\Repositories\AttributeRepository')->getLookUpEntity('leads', request('lead_id'));
                                @endphp

                                <x-admin::form.control-group class="w-full">
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.quotes.create.link-to-lead')
                                    </x-admin::form.control-group.label>

                                    <v-lookup-component
                                        :attribute="{'code': 'lead_id', 'name': 'Lead', 'lookup_type': 'leads'}"
                                        :value='@json($lookUpEntityData)'
                                        can-add-new="true"
                                    ></v-lookup-component>
                                </x-admin::form.control-group>
                            </div>

                            <!-- Custom Attributes -->
                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                    'entity_type'     => 'quotes',
                                    'is_user_defined' => 1,
                                ])->sortBy('sort_order')"
                                :custom-validations="[
                                    'expired_at' => [
                                        'required',
                                        'date_format:d-m-Y',
                                        'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                    ],
                                ]"
                                :entity="$quote"
                            />
                        </div>

                        {!! view_render_event('admin.contacts.quotes.create.attribute.form_controls.after') !!}
                    </div>

                    {!! view_render_event('admin.contacts.quotes.create.quote_information.after') !!}

                    {!! view_render_event('admin.contacts.quotes.create.address_information.before') !!}

                    <!-- Address information -->
                    <div
                        id="address-info"
                        class="flex flex-col gap-4"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold text-gray-800 dark:text-white">
                                @lang('admin::app.quotes.create.address-info')
                            </p>

                            <p class="text-sm text-gray-600 dark:text-white">@lang('admin::app.quotes.create.address-info-info')</p>
                        </div>

                        <div class="w-1/2 max-md:w-full">
                            {!! view_render_event('admin.contacts.quotes.create.address_information.attributes.before') !!}

                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                    'entity_type' => 'quotes',
                                    ['code', 'IN', ['billing_address', 'shipping_address']],
                                ])"
                                :custom-validations="[
                                    'billing_address' => [
                                        'max:100',
                                    ],
                                    'shipping_address' => [
                                        'max:100',
                                    ],
                                ]"
                                :entity="$quote"
                            />

                            {!! view_render_event('admin.contacts.quotes.create.address_information.attributes.after') !!}
                        </div>
                    </div>

                    {!! view_render_event('admin.contacts.quotes.create.address_information.after') !!}

                    {!! view_render_event('admin.contacts.quotes.create.quote_items.before') !!}

                    <!-- Quote Item Information -->
                    <div
                        id="quote-items"
                        class="flex flex-col gap-4"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold text-gray-800 dark:text-white">
                                @lang('admin::app.quotes.create.quote-items')
                            </p>

                            <p class="text-sm text-gray-600 dark:text-white">
                                @lang('admin::app.quotes.create.quote-item-info')
                            </p>
                        </div>

                        <!-- Quote Item List Vue Component -->
                        <v-quote-item-list :errors="errors"></v-quote-item-list>
                    </div>

                    {!! view_render_event('admin.contacts.quotes.create.quote_items.after') !!}
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-quote', {
                template: '#v-quote-template',

                props: ['errors'],

                data() {
                    return {
                        activeTab: 'quote-info',

                        tabs: [
                            { id: 'quote-info', label: '@lang('admin::app.quotes.create.quote-info')' },
                            { id: 'address-info', label: '@lang('admin::app.quotes.create.address-info')' },
                            { id: 'quote-items', label: '@lang('admin::app.quotes.create.quote-items')' }
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
                            section.scrollIntoView({ behavior: 'smooth' });
                        }
                    },
                },
            });

        </script>
        @endverbatim

        {{-- Items partial (create mode) --}}
        @include('admin::quotes.partials.items', ['isEdit' => false])
    @endPushOnce

    @pushOnce('styles')
        <style>
            html {
                scroll-behavior: smooth;
            }
        </style>
    @endPushOnce
</x-admin::layouts>
