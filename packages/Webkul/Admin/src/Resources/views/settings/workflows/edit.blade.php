<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.workflows.edit.title')
    </x-slot>

    {!! view_render_event('admin.activities.edit.form.before') !!}

    <x-admin::form
        :action="route('admin.settings.workflows.update', $workflow->id)"
        method="PUT"
    >
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    {!! view_render_event('admin.settings.workflows.edit.breadcrumbs.before', ['workflow' => $workflow]) !!}

                    <x-admin::breadcrumbs
                        name="settings.workflows.edit"
                        :entity="$workflow"
                    />

                    {!! view_render_event('admin.settings.workflows.edit.breadcrumbs.after', ['workflow' => $workflow]) !!}

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.settings.workflows.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.settings.workflows.edit.save_button.before', ['workflow' => $workflow]) !!}

                        <!-- Save button for person -->
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.settings.workflows.edit.save-btn')
                        </button>

                        {!! view_render_event('admin.settings.workflows.edit.save_button.after', ['workflow' => $workflow]) !!}
                    </div>
                </div>
            </div>

            <!-- Workflow Vue Component -->
            <v-workflow></v-workflow>

            <x-admin::attributes.edit.lookup />
        </div>
    </x-admin::form>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-workflow-template"
        >
            <div class="box-shadow flex flex-col gap-4 rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
                {!! view_render_event('admin.settings.workflows.edit.form_controls.before') !!}

                <!-- Tab Switcher -->
                <div class="flex w-full gap-2 border-b border-gray-200 dark:border-gray-800">
                    <!-- Tabs -->
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
                </div>

                <div class="flex flex-col gap-4 px-4 py-2">
                    {!! view_render_event('admin.settings.workflows.edit.basic_details.before', ['workflow' => $workflow]) !!}

                    <!-- Basic Details -->
                    <div
                        class="flex flex-col gap-4"
                        id="basic-details"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.settings.workflows.edit.basic-details')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.settings.workflows.edit.basic-details-info')
                            </p>
                        </div>

                        <div class="w-1/2 max-md:w-full">
                            <x-adminc::components.field
                                type="text"
                                name="name"
                                id="name"
                                :label="trans('admin::app.settings.workflows.edit.name')"
                                value="{{ old('name') ?? $workflow->name }}"
                                rules="required"
                                :placeholder="trans('admin::app.settings.workflows.edit.name')"
                            />

                            <x-adminc::components.field
                                type="textarea"
                                name="description"
                                id="description"
                                :label="trans('admin::app.settings.workflows.edit.description')"
                                value="{{ old('description') ?? $workflow->description }}"
                                rows="5"
                                :placeholder="trans('admin::app.settings.workflows.edit.description')"
                            />
                        </div>
                    </div>

                    {!! view_render_event('admin.settings.workflows.edit.basic_details.after', ['workflow' => $workflow]) !!}

                    {!! view_render_event('admin.settings.workflows.edit.event.before', ['workflow' => $workflow]) !!}

                    <!-- Event -->
                    <div
                        class="flex flex-col gap-4"
                        id="event"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.settings.workflows.edit.event')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.settings.workflows.edit.event-info')
                            </p>
                        </div>

                        <!-- Hidden Entity Type -->
                        <input
                            type="hidden"
                            name="entity_type"
                            :value="entityType"
                        />

                        <div class="w-1/2 max-md:w-full">
                            <x-adminc::components.field
                                type="select"
                                id="event"
                                name="event"
                                ::value="event"
                                :label="trans('admin::app.settings.workflows.create.event')"
                                rules="required"
                                v-model="event"
                            >
                                <optgroup
                                    v-for="entity in events"
                                    :label="entity.name"
                                >
                                    <option
                                        v-for="ev in entity.events"
                                        :value="ev.event"
                                    >
                                        @{{ ev.name }}
                                    </option>
                                </optgroup>
                            </x-adminc::components.field>
                        </div>
                    </div>

                    {!! view_render_event('admin.settings.workflows.edit.event.after', ['workflow' => $workflow]) !!}

                    {!! view_render_event('admin.settings.workflows.edit.condition.before', ['workflow' => $workflow]) !!}

                    <!-- Conditions -->
                    <div
                        class="flex flex-col gap-4"
                        id="conditions"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.settings.workflows.edit.conditions')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.settings.workflows.edit.conditions-info')
                            </p>
                        </div>

                        <div class="flex w-1/2 flex-col gap-2 max-md:w-full">
                            <x-adminc::components.field
                                type="select"
                                class="ltr:pr-10 rtl:pl-10"
                                id="condition_type"
                                name="condition_type"
                                v-model="conditionType"
                                rules="required"
                                :label="trans('admin::app.settings.workflows.create.condition-type')"
                                :placeholder="trans('admin::app.settings.workflows.create.condition-type')"
                            >
                                <option value="and">
                                    @lang('admin::app.settings.workflows.create.all-condition-are-true')
                                </option>

                                <option value="or">
                                    @lang('admin::app.settings.workflows.create.any-condition-are-true')
                                </option>
                            </x-adminc::components.field>

                            <!-- Workflow Condition Vue Component. -->
                            <template
                                v-for='(condition, index) in conditions'
                                :key="index"
                            >
                                <v-workflow-condition-item
                                    :entityType="entityType"
                                    :condition="condition"
                                    :index="index"
                                    @onRemoveCondition="removeCondition($event)"
                                ></v-workflow-condition-item>
                            </template>

                            <button
                                type="button"
                                class="flex max-w-max items-center gap-2 text-brandColor"
                                @click="addCondition"
                            >
                                <i class="icon-add text-md !text-brandColor"></i>

                                @lang('admin::app.settings.workflows.edit.add-condition')
                            </button>
                        </div>
                    </div>

                    {!! view_render_event('admin.settings.workflows.edit.condition.after', ['workflow' => $workflow]) !!}

                    {!! view_render_event('admin.settings.workflows.edit.action.before', ['workflow' => $workflow]) !!}

                    <!-- Actions -->
                    <div
                        class="flex flex-col gap-4"
                        id="actions"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.settings.workflows.edit.actions')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.settings.workflows.edit.actions-info')
                            </p>
                        </div>

                        <div class="block w-full overflow-x-auto">
                            <x-admin::table class="!w-1/2 !table-auto">
                                <!-- Table Head -->
                                <x-admin::table.thead>
                                    <x-admin::table.thead.tr>
                                        <x-admin::table.th class="text-center">
                                            @lang('admin::app.settings.workflows.edit.type')
                                        </x-admin::table.th>

                                        <x-admin::table.th class="text-center">
                                            @lang('admin::app.settings.workflows.edit.name')
                                        </x-admin::table.th>

                                        <x-admin::table.th></x-admin::table.th>
                                    </x-admin::table.thead.tr>
                                </x-admin::table.thead>

                                <!-- Table Body -->
                                <x-admin::table.tbody>
                                    <template
                                        v-for='(action, index) in actions'
                                        :key="index"
                                    >
                                        <v-workflow-action-item
                                            :entityType="entityType"
                                            :action="action"
                                            :index="index"
                                            @onRemoveAction="removeAction($event)"
                                        ></v-workflow-action-item>
                                    </template>
                                </x-admin::table.tbody>
                            </x-admin::table>
                        </div>

                        <button
                            type="button"
                            class="first-line:text-md flex max-w-max items-center gap-2 text-brandColor"
                            @click="addAction"
                        >
                            <i class="icon-add"></i>

                            @lang('admin::app.settings.workflows.edit.add-action')
                        </button>
                    </div>

                    {!! view_render_event('admin.settings.workflows.edit.action.after', ['workflow' => $workflow]) !!}
                </div>

                {!! view_render_event('admin.settings.workflows.edit.form_controls.after') !!}
            </div>
        </script>

        <script
            type="text/x-template"
            id="v-workflow-condition-item-template"
        >
            <div class="flex items-center justify-between gap-4">
                <div class="flex flex-1 gap-4 max-sm:flex-1 max-sm:flex-wrap">
                    <!-- Select main condition. -->
                    <select
                        :name="['conditions[' + index + '][attribute]']"
                        :id="['conditions[' + index + '][attribute]']"
                        class=" min:w-1/3 flex h-10 w-1/3 rounded-md border bg-white px-3 py-2.5 text-sm font-normal text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 max-sm:max-w-full max-sm:flex-auto"
                        v-model="condition.attribute"
                    >
                        <option
                            v-for="attribute in conditions[entityType]"
                            :value="attribute.id"
                            :text="attribute.name"
                        ></option>
                    </select>

                    <template v-if="matchedAttribute">
                        <select
                            :name="['conditions[' + index + '][operator]']"
                            :id="['conditions[' + index + '][operator]']"
                            class=" min:w-1/3 inline-flex h-10 w-1/3 items-center justify-between gap-x-1 rounded-md border bg-white px-3 py-2.5 text-sm font-normal text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 max-sm:max-w-full max-sm:flex-auto"
                            v-model="condition.operator"
                        >
                            <option
                                v-for='operator in conditionOperators[matchedAttribute.type]'
                                :value="operator.operator"
                                :text="operator.name"
                            ></option>
                        </select>
                    </template>

                    <template v-if="matchedAttribute">
                        <!-- Text, Price, Decimal, Integer, Email, Phone -->
                        <input
                            type="hidden"
                            :name="['conditions[' + index + '][attribute_type]']"
                            v-model="matchedAttribute.type"
                        >

                        <template
                            v-if="
                                matchedAttribute.type == 'text'
                                || matchedAttribute.type == 'price'
                                || matchedAttribute.type == 'decimal'
                                || matchedAttribute.type == 'integer'
                                || matchedAttribute.type == 'email'
                                || matchedAttribute.type == 'phone'
                            "
                        >
                            <v-field
                                :name="`conditions[${index}][value]`"
                                v-slot="{ field, errorMessage }"
                                label="Aanpassen"
                                :id="`conditions[${index}][value]`"
                                :rules="
                                    matchedAttribute.type == 'price' ? 'regex:^[0-9]+(\\.[0-9]+)?$' : ''
                                    || matchedAttribute.type == 'decimal' ? 'regex:^[0-9]+(\\.[0-9]+)?$' : ''
                                    || matchedAttribute.type == 'integer' ? 'regex:^[0-9]+$' : ''
                                    || matchedAttribute.type == 'text' ? 'regex:^.*$' : ''
                                    || matchedAttribute.type == 'email' ? 'email' : ''
                                "
                                v-model="condition.value"
                            >
                                <input
                                    type="text"
                                    v-bind="field"
                                    :class="{ 'border border-error': errorMessage }"
                                    class="min:w-1/3 flex h-10 w-1/3 rounded-md border px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                />
                            </v-field>

                            <v-error-message
                                :name="`conditions[${index}][value]`"
                                class="mt-1 text-xs italic text-red-500"
                                as="p"
                            >
                            </v-error-message>
                        </template>

                        <!-- Date -->
                        <template v-if="matchedAttribute.type == 'date'">
                            <x-admin::flat-picker.date
                                class="!w-1/3"
                                ::allow-input="false"
                            >
                                <input
                                    type="date"
                                    class="min:w-1/3 flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                    :name="['conditions[' + index + '][value]']"
                                    v-model="condition.value"
                                />
                            </x-admin::flat-picker.date>
                        </template>

                        <!-- Datetime -->
                        <template v-if="matchedAttribute.type == 'datetime'">
                            <x-admin::flat-picker.date
                                class="!w-1/3"
                                ::allow-input="false"
                            >
                                <input
                                    type="datetime"
                                    class="min:w-1/3 flex w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                    :name="['conditions[' + index + '][value]']"
                                    v-model="condition.value"
                                />
                            </x-admin::flat-picker.date>
                        </template>

                        <!-- Boolean -->
                        <template v-if="matchedAttribute.type == 'boolean'">
                            <select
                                :name="['conditions[' + index + '][value]']"
                                class=" inline-flex h-10 w-1/3 items-center justify-between gap-x-1 rounded-md border bg-white px-3 py-2.5 text-sm font-normal text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 max-sm:max-w-full max-sm:flex-auto"
                                v-model="condition.value"
                            >
                                <option value="1">
                                    @lang('admin::app.settings.workflows.edit.yes')
                                </option>

                                <option value="0">
                                    @lang('admin::app.settings.workflows.edit.no')
                                </option>
                            </select>
                        </template>

                        <!-- Lookup Type -->
                        <template
                            v-if="
                                matchedAttribute.type == 'select'
                                || matchedAttribute.type == 'radio'
                                || matchedAttribute.type == 'lookup'
                            "
                        >
                            <template v-if="! matchedAttribute.lookup_type">
                                <select
                                    :name="['conditions[' + index + '][value]']"
                                    class=" inline-flex h-10 w-1/3 items-center justify-between gap-x-1 rounded-md border bg-white px-3 py-2.5 text-sm font-normal text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                    v-model="condition.value"
                                >
                                    <option
                                        v-for='option in matchedAttribute.options'
                                        :value="option.id"
                                        :text="option.name"
                                    ></option>
                                </select>
                            </template>

                            <template v-else>
                                <div class="w-1/3">
                                    <v-lookup-component
                                        :attribute="{'code': 'conditions[' + index + '][value]', 'name': 'Email', 'lookup_type': matchedAttribute.lookup_type}"
                                        validations="required|email"
                                        :data="condition.value"
                                        can-add-new="true"
                                    ></v-lookup-component>
                                </div>
                            </template>
                        </template>

                        <!-- Multiselect and Checkbox -->
                        <template
                            v-if="matchedAttribute.type == 'multiselect'
                            || matchedAttribute.type == 'checkbox'"
                        >
                            <select
                                :name="['conditions[' + index + '][value][]']"
                                class="min:w-1/3 inline-flex h-20 w-1/3 items-center justify-between gap-x-1 rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                v-model="condition.value"
                                multiple
                            >
                                <option
                                    v-for='option in matchedAttribute.options'
                                    :value="option.id"
                                    :text="option.name"
                                ></option>
                            </select>
                        </template>

                        <!-- Textarea -->
                        <template v-if="matchedAttribute.type == 'textarea'">
                            <textarea
                                :name="['conditions[' + index + '][value]']"
                                :id="['conditions[' + index + '][value]']"
                                v-model="condition.value"
                                class="min:w-1/3 w-1/3 rounded-md border px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                            ></textarea>
                        </template>
                    </template>
                </div>

                <!-- Remove Conditions -->
                <span
                    class="icon-delete max-h-9 max-w-9 cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 max-sm:place-self-center"
                    @click="removeCondition"
                ></span>
            </div>
        </script>

        <script
            type="text/x-template"
            id="v-workflow-action-item-template"
        >
            <!-- Table Body -->
            <x-admin::table.thead.tr>
                <x-admin::table.td>
                    <select
                        :name="['actions[' + index + '][id]']"
                        :id="['actions[' + index + '][id]']"
                        class=" flex h-10 w-full rounded-md border bg-white px-3 py-2.5 text-sm font-normal text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 max-sm:max-w-full max-sm:flex-auto"
                        v-model="action.id"
                    >
                        <option
                            v-for='action in actions[entityType]'
                            :value="action.id"
                            :text="action.name"
                        ></option>
                    </select>
                </x-admin::table.td>

                <x-admin::table.td>
                        <div class="flex flex-col gap-2 w-full">
                            <div v-for="attr in matchedAction.attributes" :key="attr.id">
                                <label class="text-xs text-gray-600">@{{ attr.name }}</label>

                                <!-- TEXT -->
                                <input
                                    v-if="attr.type === 'text'"
                                    type="text"
                                    class="border px-2 py-1 w-full"
                                    :name="`actions[${index}][attributes][${attr.id}]`"
                                    v-model="action.attributes[attr.id]"
                                />

                                <!-- TEXTAREA -->
                                <textarea
                                    v-if="attr.type === 'textarea'"
                                    class="border px-2 py-1 w-full"
                                    :name="`actions[${index}][attributes][${attr.id}]`"
                                    v-model="action.attributes[attr.id]"
                                ></textarea>

                                <!-- SELECT -->
                                <select
                                    v-if="attr.type === 'select'"
                                    class="border px-2 py-1 w-full"
                                    :name="`actions[${index}][attributes][${attr.id}]`"
                                    v-model="action.attributes[attr.id]"
                                >
                                    <option v-for="opt in attr.options" :value="opt.id">@{{ opt.name }}</option>
                                </select>
                            </div>


                            <template v-if="matchedAction && matchedAction.options">
                                <select
                                    :name="`actions[${index}][value]`"
                                    class=" flex h-10 w-full rounded-md border bg-white px-3 py-2.5 text-sm font-normal text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 max-sm:max-w-full max-sm:flex-auto"
                                    v-model="action.value"
                                >
                                    <option
                                        v-for='option in matchedAction.options'
                                        :value="option.id"
                                        :text="option.name"
                                    ></option>
                                </select>
                            </template>

                            <template
                                v-if="
                                    matchedAction
                                    && ! matchedAction.attributes
                                    && ! matchedAction.options
                                    && ! matchedAction.request_methods
                                "
                            >
                                <v-field
                                    :name="`actions[${index}][value]`"
                                    :id="`actions[${index}][value]`"
                                    v-slot="{ field, errorMessage }"
                                    v-model="action.value"
                                >
                                    <input
                                        type="text"
                                        v-bind="field"
                                        :class="{ 'border border-error': errorMessage }"
                                        class="flex h-10 w-full rounded-md border px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                    />
                                </v-field>

                                <v-error-message
                                    :name="`actions[${index}][value]`"
                                    class="mt-1 text-xs italic text-red-500"
                                    as="p"
                                >
                                </v-error-message>
                            </template>
                        </div>
                </x-admin::table.td>

                <x-admin::table.td class="text-right">
                    <span
                        class="icon-delete cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950"
                        @click="removeAction"
                    ></span>
                </x-admin::table.td>
            </x-admin::table.thead.tr>
        </script>

        <script type="module">
            app.component('v-workflow', {
                template: '#v-workflow-template',

                data() {
                    return {
                        events: @json(app('\Webkul\Automation\Helpers\Entity')->getEvents()),

                        event: '{{ $workflow->event }}',

                        conditionType: '{{ $workflow->condition_type }}',

                        conditions: @json($workflow->conditions ?: []),


                        actions: @json($workflow->actions ?: []).map(a => ({
                            id: a.id ?? '',

                            // Nieuwe structuur
                            attributes: a.attributes ?? {
                                title: a.title ?? '',
                                description: a.description ?? '',
                                type: a.type ?? '',
                            },

                            // Belangrijk voor acties met 'options'
                            value: a.value ?? '',
                        })),


                        activeTab: 'basic-details',

                        tabs: [
                            { id: 'basic-details', label: '@lang('admin::app.settings.workflows.create.basic-details')' },
                            { id: 'event', label: '@lang('admin::app.settings.workflows.create.event')' },
                            { id: 'conditions', label: '@lang('admin::app.settings.workflows.create.conditions')' },
                            { id: 'actions', label: '@lang('admin::app.settings.workflows.create.actions')' }
                        ],
                    };
                },

                computed: {
                    /**
                     * Get the entity type.
                     *
                     * @return {String}
                     */
                     entityType: function () {
                        if (this.event == '') {
                            return '';
                        }

                        var entityType = '';

                        for (let id in this.events) {
                            this.events[id].events.forEach((eventTemp) => {
                                if (eventTemp.event == this.event) {
                                    entityType = id;
                                }
                            });
                        }

                        return entityType;
                    }
                },

                watch: {
                    /**
                     * Watch the entity Type.
                     *
                     * @return {void}
                     */
                    entityType(newValue, oldValue) {
                        this.conditions = [];

                        this.actions = [];
                    }
                },

                methods: {
                    /**
                     * Add the condition.
                     *
                     * @returns {void}
                     */
                    addCondition() {
                        this.conditions.push({
                            'attribute': '',
                            'operator': '==',
                            'value': '',
                        });
                    },

                    /**
                     * Remove the condition.
                     *
                     * @param {Object} condition
                     * @returns {void}
                     */
                    removeCondition(condition) {
                        let index = this.conditions.indexOf(condition);

                        this.conditions.splice(index, 1);
                    },

                    /**
                     * Add the action.
                     *
                     * @returns {void}
                     */
                    addAction() {
                        this.actions.push({
                            id: '',
                            attributes: {
                                title: '',
                                description: '',
                                type: '',
                            }
                        });
                    },


                    /**
                     * Remove the action.
                     *
                     * @param {Object} action
                     * @returns {void}
                     */
                    removeAction(action) {
                        let index = this.actions.indexOf(action)

                        this.actions.splice(index, 1);
                    },

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

        <script type="module">
            app.component('v-workflow-condition-item', {
                template: '#v-workflow-condition-item-template',

                props: ['index', 'entityType', 'condition'],

                emits: ['onRemoveCondition'],

                data() {
                    return {
                        conditions: @json(app('\Webkul\Automation\Helpers\Entity')->getConditions()),

                        conditionOperators: {
                            'price': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-not-equal-to')'
                                }, {
                                    'operator': '>=',
                                    'name': '@lang('admin::app.settings.workflows.edit.equals-or-greater-than')'
                                }, {
                                    'operator': '<=',
                                    'name': '@lang('admin::app.settings.workflows.edit.equals-or-less-than')'
                                }, {
                                    'operator': '>',
                                    'name': '@lang('admin::app.settings.workflows.edit.greater-than')'
                                }, {
                                    'operator': '<',
                                    'name': '@lang('admin::app.settings.workflows.edit.less-than')'
                                }],
                            'decimal': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-not-equal-to')'
                                }, {
                                    'operator': '>=',
                                    'name': '@lang('admin::app.settings.workflows.edit.equals-or-greater-than')'
                                }, {
                                    'operator': '<=',
                                    'name': '@lang('admin::app.settings.workflows.edit.equals-or-less-than')'
                                }, {
                                    'operator': '>',
                                    'name': '@lang('admin::app.settings.workflows.edit.greater-than')'
                                }, {
                                    'operator': '<',
                                    'name': '@lang('admin::app.settings.workflows.edit.less-than')'
                                }],
                            'integer': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-not-equal-to')'
                                }, {
                                    'operator': '>=',
                                    'name': '@lang('admin::app.settings.workflows.edit.equals-or-greater-than')'
                                }, {
                                    'operator': '<=',
                                    'name': '@lang('admin::app.settings.workflows.edit.equals-or-less-than')'
                                }, {
                                    'operator': '>',
                                    'name': '@lang('admin::app.settings.workflows.edit.greater-than')'
                                }, {
                                    'operator': '<',
                                    'name': '@lang('admin::app.settings.workflows.edit.less-than')'
                                }],
                            'text': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-not-equal-to')'
                                }, {
                                    'operator': '{}',
                                    'name': '@lang('admin::app.settings.workflows.edit.contain')'
                                }, {
                                    'operator': '!{}',
                                    'name': '@lang('admin::app.settings.workflows.edit.does-not-contain')'
                                }],
                            'boolean': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-not-equal-to')'
                                }],
                            'date': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-not-equal-to')'
                                }, {
                                    'operator': '>=',
                                    'name': '@lang('admin::app.settings.workflows.edit.equals-or-greater-than')'
                                }, {
                                    'operator': '<=',
                                    'name': '@lang('admin::app.settings.workflows.edit.equals-or-less-than')'
                                }, {
                                    'operator': '>',
                                    'name': '@lang('admin::app.settings.workflows.edit.greater-than')'
                                }, {
                                    'operator': '<',
                                    'name': '@lang('admin::app.settings.workflows.edit.less-than')'
                                }],
                            'datetime': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-not-equal-to')'
                                }, {
                                    'operator': '>=',
                                    'name': '@lang('admin::app.settings.workflows.edit.equals-or-greater-than')'
                                }, {
                                    'operator': '<=',
                                    'name': '@lang('admin::app.settings.workflows.edit.equals-or-less-than')'
                                }, {
                                    'operator': '>',
                                    'name': '@lang('admin::app.settings.workflows.edit.greater-than')'
                                }, {
                                    'operator': '<',
                                    'name': '@lang('admin::app.settings.workflows.edit.less-than')'
                                }],
                            'select': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-not-equal-to')'
                                }],
                            'radio': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-not-equal-to')'
                                }],
                            'multiselect': [{
                                    'operator': '{}',
                                    'name': '@lang('admin::app.settings.workflows.edit.contains')'
                                }, {
                                    'operator': '!{}',
                                    'name': '@lang('admin::app.settings.workflows.edit.does-not-contain')'
                                }],
                            'checkbox': [{
                                    'operator': '{}',
                                    'name': '@lang('admin::app.settings.workflows.edit.contains')'
                                }, {
                                    'operator': '!{}',
                                    'name': '@lang('admin::app.settings.workflows.edit.does-not-contain')'
                                }],
                            'email': [{
                                    'operator': '{}',
                                    'name': '@lang('admin::app.settings.workflows.edit.contains')'
                                }, {
                                    'operator': '!{}',
                                    'name': '@lang('admin::app.settings.workflows.edit.does-not-contain')'
                                }],
                            'phone': [{
                                    'operator': '{}',
                                    'name': '@lang('admin::app.settings.workflows.edit.contains')'
                                }, {
                                    'operator': '!{}',
                                    'name': '@lang('admin::app.settings.workflows.edit.does-not-contain')'
                                }],
                            'lookup': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.edit.is-not-equal-to')'
                                }],
                        }
                    };
                },

                computed: {
                    /**
                     * Get the matched attribute.
                     *
                     * @returns {Object}
                     */
                    matchedAttribute() {
                        if (this.condition.attribute == '') {
                            return;
                        }

                        let matchedAttribute = this.conditions[this.entityType].find(attribute => attribute.id == this.condition.attribute);

                        if (
                            matchedAttribute['type'] == 'multiselect'
                            || matchedAttribute['type'] == 'checkbox'
                        ) {
                            if (! this.condition.operator) {
                                this.condition.operator = '{}';
                            }

                            if (! this.condition.value) {
                                this.condition.value = [];
                            }
                        } else if (
                            matchedAttribute['type'] == 'email'
                            || matchedAttribute['type'] == 'phone'
                        ) {
                            this.condition.operator = '{}';
                        }

                        return matchedAttribute;
                    },
                },

                methods: {
                    /**
                     * Remove the condition.
                     *
                     * @returns {void}
                     */
                    removeCondition() {
                        this.$emit('onRemoveCondition', this.condition);
                    },
                }
            });
        </script>

        <script type="module">
            app.component('v-workflow-action-item', {
                template: '#v-workflow-action-item-template',

                props: ['index', 'entityType', 'action'],

                data() {
                    return {
                        actions: @json(app('\Webkul\Automation\Helpers\Entity')->getActions()),
                    };
                },

                computed: {
                    /**
                     * Get the matched action.
                     *
                     * @returns {Object}
                     */
                    matchedAction() {
                        if (!this.entityType || !this.actions[this.entityType]) return null;
                        return this.actions[this.entityType].find(a => a.id === this.action.id) || null;
                    },

                    /**
                     * Get the matched attribute.
                     *
                     * @return {void}
                     */
                    matchedAttribute() {
                        const action = this.matchedAction;
                        if (!action || !action.attributes) {
                            return null;
                        }

                        // Als gebruiker net actie heeft gekozen, nog geen attribute geselecteerd
                        if (!this.action.attribute) {
                            return null;
                        }

                        const attr = action.attributes.find(a => a.id === this.action.attribute);

                        if (!attr) {
                            return null;
                        }

                        // Initialiseer value voor diverse types
                        if (attr.type === 'text' && typeof this.action.attributes[attr.id] !== 'string') {
                            this.action.attributes[attr.id] = '';
                        }

                        if (attr.type === 'textarea' && typeof this.action.attributes[attr.id] !== 'string') {
                            this.action.attributes[attr.id] = '';
                        }

                        if (attr.type === 'select' && !this.action.attributes[attr.id]) {
                            this.action.attributes[attr.id] = '';
                        }

                        return attr;
                    },
                },
                methods: {
                    /**
                     * Remove the action.
                     *
                     * @returns {void}
                     */
                    removeAction() {
                        this.$emit('onRemoveAction', this.action);
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
