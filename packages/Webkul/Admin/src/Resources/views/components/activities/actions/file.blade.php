@props([
    'entity'            => null,
    'entityControlName' => null,
])

<!-- File Button -->
<div>
    {!! view_render_event('admin.components.activities.actions.file.create_btn.before') !!}

    <button
        class="flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-activity-file-bg font-medium text-activity-file-text transition-all hover:border-activity-file-border"
        @click="$refs.fileActionComponent.openModal('mail')"
    >
        <span class="icon-file text-2xl dark:!text-activity-file-text"></span>

        @lang('admin::app.components.activities.actions.file.btn')
    </button>

    {!! view_render_event('admin.components.activities.actions.file.create_btn.after') !!}

    {!! view_render_event('admin.components.activities.actions.file.before') !!}

    <!-- File Action Vue Component -->
    <v-file-activity
        ref="fileActionComponent"
        :entity="{{ json_encode($entity) }}"
        entity-control-name="{{ $entityControlName }}"
    ></v-file-activity>

    {!! view_render_event('admin.components.activities.actions.file.after') !!}
</div>

@pushOnce('scripts')
    <script type="text/x-template" id="v-file-activity-template">
        <Teleport to="body">
            {!! view_render_event('admin.components.activities.actions.file.form_controls.before') !!}

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="modalForm"
            >
                <form @submit="handleSubmit($event, save)">
                    {!! view_render_event('admin.components.activities.actions.file.form_controls.modal.before') !!}

                    <x-admin::modal
                        ref="fileActivityModal"
                        position="center"
                    >
                        <x-slot:header>
                            {!! view_render_event('admin.components.activities.actions.file.form_controls.modal.header.title.before') !!}

                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.components.activities.actions.file.title')
                            </h3>

                            {!! view_render_event('admin.components.activities.actions.file.form_controls.modal.header.title.after') !!}
                        </x-slot>

                        <x-slot:content>
                            {!! view_render_event('admin.components.activities.actions.file.form_controls.modal.content.controls.before') !!}

                            <!-- Activity Type -->
                            <x-admin::form.control-group.control
                                type="hidden"
                                name="type"
                                value="file"
                            />

                            <!-- Id -->
                            <x-admin::form.control-group.control
                                type="hidden"
                                ::name="entityControlName"
                                ::value="entity.id"
                            />

                            <!-- Title -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="text"
                                    name="title"
                                />
                            </x-admin::form.control-group>

                            <!-- Description -->
                            <x-adminc::components.field
                                type="textarea"
                                name="comment"
                                :label="trans('admin::app.components.activities.actions.file.description')"
                            />

                            <!-- File Name -->
                            <x-adminc::components.field
                                type="text"
                                name="name"
                                :label="trans('admin::app.components.activities.actions.file.name')"
                            />

                            <!-- File -->
                            <x-adminc::components.field
                                type="file"
                                id="file"
                                name="file"
                                :label="trans('admin::app.components.activities.actions.file.file')"
                                rules="required"
                                class="!mb-0"
                            />

                            {!! view_render_event('admin.components.activities.actions.file.form_controls.modal.content.controls.after') !!}
                        </x-slot>

                        <x-slot:footer>
                            {!! view_render_event('admin.components.activities.actions.file.form_controls.modal.footer.save_buton.before') !!}

                            <x-admin::button
                                class="primary-button"
                                :title="trans('admin::app.components.activities.actions.file.save-btn')"
                                ::loading="isStoring"
                                ::disabled="isStoring"
                            />

                            {!! view_render_event('admin.components.activities.actions.file.form_controls.modal.footer.save_buton.after') !!}
                        </x-slot>
                    </x-admin::modal>

                    {!! view_render_event('admin.components.activities.actions.file.form_controls.modal.after') !!}
                </form>
            </x-admin::form>

            {!! view_render_event('admin.components.activities.actions.file.form_controls.after') !!}
        </Teleport>
    </script>

    <script type="module">
        app.component('v-file-activity', {
            template: '#v-file-activity-template',

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
                }
            },

            methods: {
                openModal(type) {
                    this.$refs.fileActivityModal.open();
                },

                save(params, { setErrors }) {
                    this.isStoring = true;

                    this.$axios.post("{{ route('admin.activities.store') }}", params, {
                            headers: {
                                'Content-Type': 'multipart/form-data',
                            }
                        })
                        .then (response => {
                            this.isStoring = false;

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            this.$emitter.emit('on-activity-added', response.data.data);

                            this.$refs.fileActivityModal.close();
                        })
                        .catch (error => {
                            this.isStoring = false;

                            if (error.response.status == 422) {
                                setErrors(error.response.data.errors);
                            } else {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });

                                this.$refs.fileActivityModal.close();
                            }
                        });
                },
            },
        });
    </script>
@endPushOnce
