@props([
    'entity'            => null,
    'entityControlName' => null,
])

<!-- Activity Button -->
<div>
    {!! view_render_event('admin.components.activities.actions.activity.create_btn.before') !!}

    <button
        class="flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-activity-task-bg font-medium text-activity-task-text transition-all hover:border-activity-task-border"
        @click="$refs.actionComponent.openModal('mail')"
    >
        <span class="icon-activity text-2xl dark:!text-activity-task-text"></span>

        @lang('admin::app.components.activities.actions.activity.btn')
    </button>

    {!! view_render_event('admin.components.activities.actions.activity.create_btn.after') !!}

    {!! view_render_event('admin.components.activities.actions.activity.before') !!}

    <!-- Note Action Vue Component -->
    <v-activity
        ref="actionComponent"
        :entity="{{ json_encode($entity) }}"
        entity-control-name="{{ $entityControlName }}"
    ></v-activity>

    {!! view_render_event('admin.components.activities.actions.activity.after') !!}
</div>


@pushOnce('scripts')
    <script type="text/x-template" id="v-activity-template">
        <Teleport to="body">
            {!! view_render_event('admin.components.activities.actions.activity.form_controls.before') !!}

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="modalForm"
            >
                <form @submit="handleSubmit($event, save)">
                    {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.before') !!}

                    <x-admin::modal
                        ref="activityModal"
                        position="bottom-right"
                    >
                        <x-slot:header>
                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.header.dropdown.before') !!}

                            <x-admin::dropdown>
                                <x-slot:toggle>
                                    <h3 class="flex cursor-pointer items-center gap-1 text-base font-semibold dark:text-white">
                                        @lang('admin::app.components.activities.actions.activity.title') - @{{ selectedType.label }}

                                        <span class="icon-down-arrow text-2xl"></span>
                                    </h3>
                                </x-slot>

                                <x-slot:menu>
                                    {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.header.dropdown.menu_item.before') !!}

                                    <x-admin::dropdown.menu.item
                                        ::class="{ 'bg-neutral-bg dark:bg-gray-950': selectedType.value === type.value }"
                                        v-for="type in availableTypes"
                                        @click="selectedType = type"
                                    >
                                        @{{ type.label }}
                                    </x-admin::dropdown.menu.item>

                                    {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.header.dropdown.menu_item.after') !!}
                                </x-slot>
                            </x-admin::dropdown>

                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.header.dropdown.after') !!}
                        </x-slot>

                        <x-slot:content>
                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.content.controls.before') !!}

                            <!-- Activity Type -->
                            <x-admin::form.control-group.control
                                type="hidden"
                                name="type"
                                v-model="selectedType.value"
                            />

                            <!-- Id -->
                            <x-admin::form.control-group.control
                                type="hidden"
                                ::name="entityControlName"
                                ::value="entity.id"
                            />

                            <!-- Title -->
                            <x-adminc::components.field
                                type="text"
                                name="title"
                                :label="trans('admin::app.components.activities.actions.activity.title-control')"
                                rules="required|max:80"
                            />

                            <!-- Description -->
                            <x-adminc::components.field
                                type="textarea"
                                name="comment"
                                :label="trans('admin::app.components.activities.actions.activity.description')"
                                rules="max:500"
                            />



                            <!-- User Assignment -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="select"
                                    name="user_id"
                                    :value="old('user_id', auth()->guard('user')->id())"
                                >
                                    <option value="">{{ __('admin::app.activities.select-user') }}</option>
                                    @foreach (app(Webkul\User\Repositories\UserRepository::class)->allActiveUsers() as $user)
                                        <option
                                            value="{{ $user->id }}"
                                            {{ auth()->guard('user')->id() == $user->id ? 'selected' : '' }}
                                        >
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </x-admin::form.control-group.control>
                            </x-admin::form.control-group>

                            <!-- Group -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="select"
                                    name="group_id"
                                    v-model="selectedGroupId"
                                >
                                    <option value="">{{ __('admin::app.activities.select-group') }}</option>
                                    @foreach (app(Webkul\User\Repositories\GroupRepository::class)->all() as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                    @endforeach
                                </x-admin::form.control-group.control>
                            </x-admin::form.control-group>

                            <!-- Schedule Date -->
                            <div class="flex gap-4">
                                <!-- Started From -->
                                <x-admin::form.control-group class="w-full">
                                    <x-admin::form.control-group.control
                                        type="datetime"
                                        name="schedule_from"
                                        rules="required"
                                        :label="trans('admin::app.components.activities.actions.activity.schedule-from')"
                                    />
                                    <x-admin::form.control-group.label class="required">
                                        @lang('admin::app.components.activities.actions.activity.schedule-from')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.label>
                                    @lang('admin::app.activities.assign-to')
                                </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.label>
                                    @lang('admin::app.activities.group')
                                </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.error control-name="schedule_from" />

                                </x-admin::form.control-group>

                                <!-- Started To -->
                                <x-admin::form.control-group class="w-full">
                                    <x-admin::form.control-group.control
                                        type="datetime"
                                        name="schedule_to"
                                        rules="required"
                                        :label="trans('admin::app.components.activities.actions.activity.schedule-to')"
                                    />
                                    <x-admin::form.control-group.label class="required">
                                        @lang('admin::app.components.activities.actions.activity.schedule-to')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.error control-name="schedule_to" />

                                </x-admin::form.control-group>
                            </div>

{{--                            <!-- Location -->--}}
{{--                            <x-admin::form.control-group class="!mb-0">--}}
{{----}}

{{--                                <x-admin::form.control-group.control--}}
{{--                                    type="text"--}}
{{--                                    name="location"--}}
{{--                                />--}}
{{--{{--
{{--
{{--                                <x-admin::form.control-group.label>--}}
{{--                                    @lang('admin::app.components.activities.actions.activity.location')--}}
{{--                                </x-admin::form.control-group.label>

{{--                            </x-admin::form.control-group>--}}

                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.content.controls.after') !!}
                        </x-slot>

                        <x-slot:footer>
                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.footer.save_button.before') !!}

                            <x-admin::button
                                class="primary-button"
                                :title="trans('admin::app.components.activities.actions.activity.save-btn')"
                                ::loading="isStoring"
                                ::disabled="isStoring"
                            />

                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.footer.save_button.after') !!}
                        </x-slot>
                    </x-admin::modal>

                    {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.after') !!}
                </form>
            </x-admin::form>

            {!! view_render_event('admin.components.activities.actions.activity.form_controls.after') !!}
        </Teleport>
    </script>

    <script type="module">
        app.component('v-activity', {
            template: '#v-activity-template',

            props: {
                entity: {
                    type: Object,
                    required: true,
                    default: () => {}
                },

                entityControlName: {
                    type: String,
                    required: true,
                    default: ''
                }
            },

            data: function () {
                return {
                    isStoring: false,

                    selectedType: {
                        label: "{{ trans('admin::app.activities.edit.call') }}",
                        value: 'call'
                    },

                    availableTypes: [
                        @foreach(\App\Enums\ActivityType::userSelectable() as $type)
                        {
                            label: "{{ trans('admin::app.activities.edit.' . $type->value) }}",
                            value: '{{ $type->value }}'
                        },
                        @endforeach
                    ],

                    selectedGroupId: null
                }
            },

            mounted() {
                // Auto-select group based on lead's department
                if (this.entity && this.entityControlName === 'lead_id' && this.entity.department_id) {
                    this.setDefaultGroupFromDepartment();
                }
            },

            methods: {
                openModal(type) {
                    this.$refs.activityModal.open();
                },

                setDefaultGroupFromDepartment() {
                    // Fetch the default group for this lead's department
                    this.$axios.get(`/admin/leads/${this.entity.id}/default-group`)
                        .then(response => {
                            if (response.data.group_id) {
                                this.selectedGroupId = response.data.group_id;
                            }
                        })
                        .catch(error => {
                            console.warn('Could not fetch default group for lead:', error);
                        });
                },

                save(params, { setErrors }) {
                    this.isStoring = true;

                    // Use entity-specific route based on entity control name
                    let url = "{{ route('admin.activities.store') }}";
                    if (this.entityControlName === 'lead_id' && this.entity.id) {
                        url = `/admin/leads/${this.entity.id}/activities`;
                    } else if (this.entityControlName === 'workflow_lead_id' && this.entity.id) {
                        url = `/admin/sales-leads/${this.entity.id}/activities`;
                    }

                    // Format dates to Y-m-d H:i:s format
                    if (params.schedule_from) {
                        const fromDate = new Date(params.schedule_from);
                        params.schedule_from = this.formatDateTime(fromDate);
                    }
                    if (params.schedule_to) {
                        const toDate = new Date(params.schedule_to);
                        params.schedule_to = this.formatDateTime(toDate);
                    }

                    this.$axios.post(url, params)
                        .then (response => {
                            this.isStoring = false;

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            this.$emitter.emit('on-activity-added', response.data.data);

                            this.$refs.activityModal.close();
                        })
                        .catch (error => {
                            this.isStoring = false;

                            if (error.response.status == 422) {
                                setErrors(error.response.data.errors);
                            } else {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });

                                this.$refs.activityModal.close();
                            }
                        });
                },

                formatDateTime(date) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    const seconds = String(date.getSeconds()).padStart(2, '0');
                    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
                },
            },
        });
    </script>
@endPushOnce
