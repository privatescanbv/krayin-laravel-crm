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
                            <x-adminc::components.field
                                type="text"
                                name="title"
                                label="Titel"
                            />

                            <!-- Description -->
                            <x-adminc::components.field
                                type="textarea"
                                name="comment"
                                :label="trans('admin::app.components.activities.actions.file.description')"
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

                            <!-- Publiceren in patiëntportaal -->
                            <div class="mt-4">
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="checkbox"
                                        v-model="publishToPortal"
                                        @change="onPublishChange"
                                        class="rounded border-gray-300"
                                    />
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Publiceren in patiëntportaal
                                    </span>
                                </label>

                                <!-- Person selector: shown when portal is enabled and entity has multiple persons -->
                                <div v-if="publishToPortal" class="mt-3">
                                    <div v-if="loadingPersons" class="text-sm text-gray-500">
                                        Personen laden...
                                    </div>
                                    <div v-else-if="persons.length > 1">
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Deel met persoon
                                        </label>
                                        <select
                                            v-model="selectedPersonId"
                                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >
                                            <option value="" disabled>Selecteer een persoon</option>
                                            <option v-for="person in persons" :key="person.id" :value="person.id">
                                                @{{ person.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

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
                    publishToPortal: false,
                    persons: [],
                    selectedPersonId: '',
                    loadingPersons: false,
                }
            },

            methods: {
                openModal(type) {
                    this.publishToPortal = false;
                    this.persons = [];
                    this.selectedPersonId = '';
                    this.$refs.fileActivityModal.open();
                },

                onPublishChange() {
                    this.persons = [];
                    this.selectedPersonId = '';

                    if (!this.publishToPortal) {
                        return;
                    }

                    // Derive entity type from entityControlName: "order_id" → "order", "sales_lead_id" → "sales_lead"
                    const entityType = this.entityControlName.replace('_id', '');

                    this.loadingPersons = true;

                    this.$axios.get("{{ route('admin.activities.persons-for-entity') }}", {
                        params: { entity_type: entityType, entity_id: this.entity.id }
                    }).then(response => {
                        this.persons = response.data.data ?? [];

                        // Auto-select if only one person
                        if (this.persons.length === 1) {
                            this.selectedPersonId = this.persons[0].id;
                        }
                    }).catch(() => {
                        this.persons = [];
                    }).finally(() => {
                        this.loadingPersons = false;
                    });
                },

                save(params, { setErrors }) {
                    this.isStoring = true;

                    // Build FormData explicitly so we can include reactive publish_to_portal and person_ids
                    const formData = new FormData();

                    Object.entries(params).forEach(([key, value]) => {
                        if (value === null || value === undefined) return;
                        if (value instanceof FileList) {
                            Array.from(value).forEach(f => formData.append(key, f));
                        } else if (value instanceof File || value instanceof Blob) {
                            formData.append(key, value);
                        } else {
                            formData.append(key, String(value));
                        }
                    });

                    // Set publish_to_portal from Vue reactive state
                    formData.set('publish_to_portal', this.publishToPortal ? '1' : '0');

                    // Attach selected person when publishing to portal
                    if (this.publishToPortal && this.selectedPersonId) {
                        formData.append('person_ids[]', String(this.selectedPersonId));
                    }

                    this.$axios.post("{{ route('admin.activities.store') }}", formData)
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
