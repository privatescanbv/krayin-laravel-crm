<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.workflows.create.title')
    </x-slot>

    {!! view_render_event('admin.settings.workflow.form.before') !!}

    <x-admin::form :action="route('admin.settings.workflows.store')">
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    {!! view_render_event('admin.settings.workflow.breadcrumbs.before') !!}

                    <x-admin::breadcrumbs name="settings.workflows.create" />

                    {!! view_render_event('admin.settings.webhooks.breadcrumbs.after') !!}

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.settings.workflows.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.settings.workflow.save_button.before') !!}

                        <!-- Save button for person -->
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.settings.workflows.create.save-btn')
                        </button>

                        {!! view_render_event('admin.settings.workflow.save_button.after') !!}
                    </div>
                </div>
            </div>

            <!-- Workflow Vue Component -->
            <v-workflow></v-workflow>

            <x-admin::attributes.edit.lookup />
        </div>
    </x-admin::form>

    {!! view_render_event('admin.settings.workflow.form.after') !!}

    @pushOnce('scripts')
        <script type="text/x-template" id="v-workflow-template">
            <div class="box-shadow flex flex-col gap-4 rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">

                <!-- Tabs -->
                <div class="flex w-full gap-2 border-b border-gray-200 dark:border-gray-800">
                    <template v-for="tab in tabs" :key="tab.id">
                        <a
                            :href="'#' + tab.id"
                            :class="[
                        'inline-block px-3 py-2.5 border-b-2 text-sm font-medium',
                        activeTab === tab.id
                            ? 'text-brandColor border-brandColor'
                            : 'text-gray-600 border-transparent hover:text-gray-800 hover:border-gray-400'
                    ]"
                            @click="scrollToSection(tab.id)"
                        >
                            @{{ tab.label }}
                        </a>
                    </template>
                </div>

                <div class="flex flex-col gap-4 px-4 py-2">

                    <!-- BASIC DETAILS -->
                    <div id="basic-details">
                        <p class="text-base font-semibold dark:text-white mb-2">
                            @lang('admin::app.settings.workflows.create.basic-details')
                        </p>

                        <div class="w-1/2">
                            <div class="mt-6">
                                <x-adminc::components.field
                                    type="text"
                                    name="name"
                                    id="name"
                                    :label="trans('admin::app.settings.workflows.create.name')"
                                    value="{{ old('name') }}"
                                    rules="required"
                                />

                                <x-adminc::components.field
                                    type="textarea"
                                    name="description"
                                    id="description"
                                    :label="trans('admin::app.settings.workflows.create.description')"
                                    value="{{ old('description') }}"
                                    rows="5"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- EVENT -->
                    <div id="event">
                        <p class="text-base font-semibold dark:text-white mb-2">
                            @lang('admin::app.settings.workflows.create.event')
                        </p>

                        <input type="hidden" name="entity_type" :value="entityType" />

                        <div class="w-1/2">
                            <div class="mt-6">
                                <x-adminc::components.field
                                    type="select"
                                    id="event"
                                    name="event"
                                    v-model="event"
                                    rules="required"
                                    :label="trans('admin::app.settings.workflows.create.event')">

                                    <optgroup v-for="entity in events" :label="entity.name">
                                        <option v-for="ev in entity.events" :value="ev.event">@{{ ev.name }}</option>
                                    </optgroup>

                                </x-adminc::components.field>
                            </div>
                        </div>
                    </div>

                    <!-- CONDITIONS -->
                    <div id="conditions">
                        <p class="text-base font-semibold dark:text-white mb-2">
                            @lang('admin::app.settings.workflows.create.conditions')
                        </p>

                        <div class="w-1/2">
                            <div class="mt-6">
                            <x-adminc::components.field
                                type="select"
                                name="condition_type"
                                id="condition_type"
                                v-model="conditionType"
                                rules="required"
                                :label="trans('admin::app.settings.workflows.create.condition-type')"
                            >
                                <option value="and">
                                    @lang('admin::app.settings.workflows.create.all-condition-are-true')
                                </option>

                                <option value="or">
                                    @lang('admin::app.settings.workflows.create.any-condition-are-true')
                                </option>
                            </x-adminc::components.field>

                            <template v-for="(condition, index) in conditions" :key="index">
                                <v-workflow-condition-item
                                    :entityType="entityType"
                                    :condition="condition"
                                    :index="index"
                                    @onRemoveCondition="removeCondition($event)"
                                />
                            </template>

                            <button class="text-brandColor flex items-center gap-2" type="button" @click="addCondition">
                                <i class="icon-add"></i>
                                @lang('admin::app.settings.workflows.create.add-condition')
                            </button>
                            </div>
                        </div>
                    </div>

                    <!-- ACTIONS -->
                    <div id="actions">
                        <p class="text-base font-semibold dark:text-white">
                            @lang('admin::app.settings.workflows.create.actions')
                        </p>

                        <x-admin::table class="!w-1/2">
                            <x-admin::table.tbody>
                                <template v-for="(action, index) in actions" :key="index">
                                    <v-workflow-action-item
                                        :entityType="entityType"
                                        :action="action"
                                        :index="index"
                                        @onRemoveAction="removeAction($event)"
                                    />
                                </template>
                            </x-admin::table.tbody>
                        </x-admin::table>

                        <button class="text-brandColor flex items-center gap-2" type="button" @click="addAction">
                            <i class="icon-add"></i>
                            @lang('admin::app.settings.workflows.create.add-action')
                        </button>
                    </div>

                </div>
            </div>
        </script>

        <script
            type="text/x-template"
            id="v-workflow-condition-item-template"
        >
            <div class="flex justify-between gap-4">
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
                                label="Aanmaken"
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
                                    @lang('admin::app.settings.workflows.create.yes')
                                </option>

                                <option value="0">
                                    @lang('admin::app.settings.workflows.create.no')
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

        <script type="text/x-template" id="v-workflow-action-item-template">
            <tr>
                <td>
                    <select
                        :name="`actions[${index}][id]`"
                        class="border px-2 py-1 w-full"
                        v-model="action.id"
                    >
                        <option
                            v-for="opt in actions[entityType]"
                            :value="opt.id"
                            :text="opt.name">
                        </option>
                    </select>
                </td>

                <td>

                    <!-- MULTI-FIELD (attributes > 1) -->
                    <div v-if="matchedAction && matchedAction.attributes && matchedAction.attributes.length > 1"
                         class="flex flex-col gap-2 w-full">

                        <div v-for="attr in matchedAction.attributes" :key="attr.id">
                            <label class="text-xs text-gray-600">@{{ attr.name }}</label>

                            <input v-if="attr.type === 'text'"
                                   type="text"
                                   class="border px-2 py-1 w-full"
                                   :name="`actions[${index}][attributes][${attr.id}]`"
                                   v-model="action.attributes[attr.id]" />

                            <textarea v-if="attr.type === 'textarea'"
                                      class="border px-2 py-1 w-full"
                                      :name="`actions[${index}][attributes][${attr.id}]`"
                                      v-model="action.attributes[attr.id]">
                    </textarea>

                            <select v-if="attr.type === 'select'"
                                    class="border px-2 py-1 w-full"
                                    :name="`actions[${index}][attributes][${attr.id}]`"
                                    v-model="action.attributes[attr.id]">
                                <option v-for="opt in attr.options" :value="opt.id">@{{ opt.name }}</option>
                            </select>

                        </div>
                    </div>

                    <!-- SINGLE ATTRIBUTE -->
                    <div v-else-if="matchedAction && matchedAction.attributes && matchedAction.attributes.length === 1">
                        <label class="text-xs text-gray-600">@{{ matchedAction.attributes[0].name }}</label>
                        <input type="text"
                               class="border px-2 py-1 w-full"
                               :name="`actions[${index}][attributes][${matchedAction.attributes[0].id}]`"
                               v-model="action.attributes[matchedAction.attributes[0].id]" />
                    </div>

                    <!-- SELECT OP BASIS VAN OPTIONS -->
                    <div v-else-if="matchedAction && matchedAction.options">
                        <label class="text-xs text-gray-600">Kies optie</label>
                        <select class="border px-2 py-1 w-full"
                                :name="`actions[${index}][value]`"
                                v-model="action.value">

                            <option v-for="opt in matchedAction.options" :value="opt.id">@{{ opt.name }}</option>

                        </select>
                    </div>

                    <!-- DEFAULT TEXT INPUT -->
                    <div v-else>
                        <label class="text-xs text-gray-600">Waarde</label>
                        <input type="text"
                               class="border px-2 py-1 w-full"
                               :name="`actions[${index}][value]`"
                               v-model="action.value" />
                    </div>

                </td>

                <td class="text-right">
                    <span class="icon-delete cursor-pointer" @click="removeAction"></span>
                </td>
            </tr>
        </script>



        <script type="module">
            app.component('v-workflow', {
                template: '#v-workflow-template',

                data() {
                    return {
                        events: @json(app('\Webkul\Automation\Helpers\Entity')->getEvents()),

                        event: '',

                        conditionType: '1',

                        conditions: [],

                        actions: [],

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
                            'id': '',
                            'attribute': '',
                            'value': '',
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
                                    'name': '@lang('admin::app.settings.workflows.create.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.create.is-not-equal-to')'
                                }, {
                                    'operator': '>=',
                                    'name': '@lang('admin::app.settings.workflows.create.equals-or-greater-than')'
                                }, {
                                    'operator': '<=',
                                    'name': '@lang('admin::app.settings.workflows.create.equals-or-less-than')'
                                }, {
                                    'operator': '>',
                                    'name': '@lang('admin::app.settings.workflows.create.greater-than')'
                                }, {
                                    'operator': '<',
                                    'name': '@lang('admin::app.settings.workflows.create.less-than')'
                                }],
                            'decimal': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.create.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.create.is-not-equal-to')'
                                }, {
                                    'operator': '>=',
                                    'name': '@lang('admin::app.settings.workflows.create.equals-or-greater-than')'
                                }, {
                                    'operator': '<=',
                                    'name': '@lang('admin::app.settings.workflows.create.equals-or-less-than')'
                                }, {
                                    'operator': '>',
                                    'name': '@lang('admin::app.settings.workflows.create.greater-than')'
                                }, {
                                    'operator': '<',
                                    'name': '@lang('admin::app.settings.workflows.create.less-than')'
                                }],
                            'integer': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.create.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.create.is-not-equal-to')'
                                }, {
                                    'operator': '>=',
                                    'name': '@lang('admin::app.settings.workflows.create.equals-or-greater-than')'
                                }, {
                                    'operator': '<=',
                                    'name': '@lang('admin::app.settings.workflows.create.equals-or-less-than')'
                                }, {
                                    'operator': '>',
                                    'name': '@lang('admin::app.settings.workflows.create.greater-than')'
                                }, {
                                    'operator': '<',
                                    'name': '@lang('admin::app.settings.workflows.create.less-than')'
                                }],
                            'text': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.create.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.create.is-not-equal-to')'
                                }, {
                                    'operator': '{}',
                                    'name': '@lang('admin::app.settings.workflows.create.contain')'
                                }, {
                                    'operator': '!{}',
                                    'name': '@lang('admin::app.settings.workflows.create.does-not-contain')'
                                }],
                            'boolean': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.create.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.create.is-not-equal-to')'
                                }],
                            'date': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.create.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.create.is-not-equal-to')'
                                }, {
                                    'operator': '>=',
                                    'name': '@lang('admin::app.settings.workflows.create.equals-or-greater-than')'
                                }, {
                                    'operator': '<=',
                                    'name': '@lang('admin::app.settings.workflows.create.equals-or-less-than')'
                                }, {
                                    'operator': '>',
                                    'name': '@lang('admin::app.settings.workflows.create.greater-than')'
                                }, {
                                    'operator': '<',
                                    'name': '@lang('admin::app.settings.workflows.create.less-than')'
                                }],
                            'datetime': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.create.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.create.is-not-equal-to')'
                                }, {
                                    'operator': '>=',
                                    'name': '@lang('admin::app.settings.workflows.create.equals-or-greater-than')'
                                }, {
                                    'operator': '<=',
                                    'name': '@lang('admin::app.settings.workflows.create.equals-or-less-than')'
                                }, {
                                    'operator': '>',
                                    'name': '@lang('admin::app.settings.workflows.create.greater-than')'
                                }, {
                                    'operator': '<',
                                    'name': '@lang('admin::app.settings.workflows.create.less-than')'
                                }],
                            'select': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.create.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.create.is-not-equal-to')'
                                }],
                            'radio': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.create.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.create.is-not-equal-to')'
                                }],
                            'multiselect': [{
                                    'operator': '{}',
                                    'name': '@lang('admin::app.settings.workflows.create.contains')'
                                }, {
                                    'operator': '!{}',
                                    'name': '@lang('admin::app.settings.workflows.create.does-not-contain')'
                                }],
                            'checkbox': [{
                                    'operator': '{}',
                                    'name': '@lang('admin::app.settings.workflows.create.contains')'
                                }, {
                                    'operator': '!{}',
                                    'name': '@lang('admin::app.settings.workflows.create.does-not-contain')'
                                }],
                            'email': [{
                                    'operator': '{}',
                                    'name': '@lang('admin::app.settings.workflows.create.contains')'
                                }, {
                                    'operator': '!{}',
                                    'name': '@lang('admin::app.settings.workflows.create.does-not-contain')'
                                }],
                            'phone': [{
                                    'operator': '{}',
                                    'name': '@lang('admin::app.settings.workflows.create.contains')'
                                }, {
                                    'operator': '!{}',
                                    'name': '@lang('admin::app.settings.workflows.create.does-not-contain')'
                                }],
                            'lookup': [{
                                    'operator': '==',
                                    'name': '@lang('admin::app.settings.workflows.create.is-equal-to')'
                                }, {
                                    'operator': '!=',
                                    'name': '@lang('admin::app.settings.workflows.create.is-not-equal-to')'
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
                    matchedAttribute: function () {
                        if (this.condition.attribute == '') {
                            return;
                        }

                        var self = this;

                        let matchedAttribute = this.conditions[this.entityType].filter(function (attribute) {
                            return attribute.id == self.condition.attribute;
                        });

                        if (matchedAttribute[0]['type'] == 'multiselect' || matchedAttribute[0]['type'] == 'checkbox') {
                            this.condition.operator = '{}';

                            this.condition.value = [];
                        } else if (matchedAttribute[0]['type'] == 'email' || matchedAttribute[0]['type'] == 'phone') {
                            this.condition.operator = '{}';
                        }

                        return matchedAttribute[0];
                    }
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
                    matchedAction() {
                        return this.actions[this.entityType]?.find(a => a.id === this.action.id);
                    }
                },

                watch: {
                    'action.id'(newId) {
                        const a = this.matchedAction;

                        if (!a?.attributes?.length) return;

                        // Initialiseer fields zoals voorheen
                        if (!this.action.fields) {
                            this.action.fields = {};
                        }

                        a.attributes.forEach(attr => {
                            if (!(attr.id in this.action.fields)) {
                                this.action.fields[attr.id] = '';
                            }
                        });

                        // *** BELANGRIJK ***
                        // Normalizeer fields → attributes, zodat edit / backend consistent blijft
                        this.action.attributes = { ...this.action.fields };
                    }
                },

                methods: {
                    removeAction() {
                        this.$emit('onRemoveAction', this.action);
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
