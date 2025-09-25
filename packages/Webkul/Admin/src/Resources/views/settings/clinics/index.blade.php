<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.clinics.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.clinics" />

                <div class="text-xl font-bold dark:text-gray-300">
                    @lang('admin::app.settings.clinics.index.title')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('settings.clinics.create'))
                    <button
                        type="button"
                        class="primary-button"
                        @click="$refs.clinicSettings.openModal()"
                    >
                        @lang('admin::app.settings.clinics.index.create-btn')
                    </button>
                @endif
            </div>
        </div>

        <v-clinics-settings ref="clinicSettings">
            <x-admin::shimmer.datagrid />
        </v-clinics-settings>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="clinics-settings-template">
            <x-admin::datagrid :src="route('admin.settings.clinics.index')" ref="datagrid">
                <template #body="{ isLoading, available, applied, selectAll, sort, performAction }">
                    <template v-if="isLoading">
                        <x-admin::shimmer.datagrid.table.body />
                    </template>

                    <template v-else>
                        <div
                            v-for="record in available.records"
                            class="row grid items-center gap-2.5 border-b px-4 py-4 text-gray-600 transition-all hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-950 max-lg:hidden"
                            :style="`grid-template-columns: repeat(${gridsCount}, minmax(0, 1fr))`"
                        >
                            <p>@{{ record.id }}</p>
                            <p>@{{ record.name }}</p>

                            <div class="flex justify-end">
                                <a @click="selectedClinic=true; editModal(record.actions.find(action => action.index === 'edit')?.url)">
                                    <span :class="record.actions.find(action => action.index === 'edit')?.icon" class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center"></span>
                                </a>
                                <a @click="performAction(record.actions.find(action => action.index === 'delete'))">
                                    <span :class="record.actions.find(action => action.index === 'delete')?.icon" class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center"></span>
                                </a>
                            </div>
                        </div>

                        <div class="hidden border-b px-4 py-4 text-black dark:border-gray-800 dark:text-gray-300 max-lg:block" v-for="record in available.records">
                            <div class="mb-2 flex items-center justify-between">
                                <div class="flex w-full items-center justify-end" v-if="available.actions.length">
                                    <a @click="selectedClinic=true; editModal(record.actions.find(action => action.index === 'edit')?.url)">
                                        <span :class="record.actions.find(action => action.index === 'edit')?.icon" class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center"></span>
                                    </a>
                                    <a @click="performAction(record.actions.find(action => action.index === 'delete'))">
                                        <span :class="record.actions.find(action => action.index === 'delete')?.icon" class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center"></span>
                                    </a>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <template v-for="column in available.columns">
                                    <div class="flex flex-wrap items-baseline gap-x-2">
                                        <span class="text-slate-600 dark:text-gray-300" v-html="column.label + ':'"></span>
                                        <span class="break-words font-medium text-slate-900 dark:text-white" v-html="record[column.index]"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </template>
            </x-admin::datagrid>

            <x-admin::form v-slot="{ meta, errors, handleSubmit }" as="div" ref="modalForm">
                <form @submit="handleSubmit($event, updateOrCreate)">
                    <x-admin::modal ref="clinicUpdateAndCreateModal">
                        <x-slot:header>
                            <p class="text-lg font-bold text-gray-800 dark:text-white">
                                @{{ selectedClinic ? "@lang('admin::app.settings.clinics.index.edit.title')" : "@lang('admin::app.settings.clinics.index.create.title')" }}
                            </p>
                        </x-slot>

                        <x-slot:content>
                            <x-admin::form.control-group.control type="hidden" name="id" />

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.settings.clinics.index.create.name')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    id="name"
                                    name="name"
                                    rules="required|min:1|max:100"
                                    :label="trans('admin::app.settings.clinics.index.create.name')"
                                    :placeholder="trans('admin::app.settings.clinics.index.create.name')"
                                />

                                <x-admin::form.control-group.error control-name="name" />
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.settings.clinics.index.create.emails')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="textarea"
                                    id="emails"
                                    name="emails"
                                    :label="trans('admin::app.settings.clinics.index.create.emails')"
                                    :placeholder="trans('admin::app.settings.clinics.index.create.emails')"
                                />
                                <small class="text-gray-500">@lang('admin::app.settings.clinics.index.create.emails-help')</small>
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.settings.clinics.index.create.phones')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="textarea"
                                    id="phones"
                                    name="phones"
                                    :label="trans('admin::app.settings.clinics.index.create.phones')"
                                    :placeholder="trans('admin::app.settings.clinics.index.create.phones')"
                                />
                                <small class="text-gray-500">@lang('admin::app.settings.clinics.index.create.phones-help')</small>
                            </x-admin::form.control-group>
                        </x-slot>

                        <x-slot:footer>
                            <x-admin::button button-type="submit" class="primary-button justify-center" :title="trans('admin::app.settings.clinics.index.create.save-btn')" ::loading="isProcessing" ::disabled="isProcessing" />
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>
        </script>

        <script type="module">
            app.component('v-clinics-settings', {
                template: '#clinics-settings-template',

                data() {
                    return {
                        isProcessing: false,
                        selectedClinic: false,
                    };
                },

                computed: {
                    gridsCount() {
                        let count = this.$refs.datagrid.available.columns.length;
                        if (this.$refs.datagrid.available.actions.length) {
                            ++count;
                        }
                        if (this.$refs.datagrid.available.massActions.length) {
                            ++count;
                        }
                        return count;
                    },
                },

                methods: {
                    openModal() {
                        this.selectedClinic = false;
                        this.$refs.clinicUpdateAndCreateModal.toggle();
                    },

                    updateOrCreate(params, { resetForm, setErrors }) {
                        this.isProcessing = true;
                        const payload = { ...params, _method: params.id ? 'put' : 'post' };

                        // Convert simple textarea JSON strings to arrays if needed
                        if (typeof payload.emails === 'string' && payload.emails.trim()) {
                            try { payload.emails = JSON.parse(payload.emails); } catch (e) {}
                        }
                        if (typeof payload.phones === 'string' && payload.phones.trim()) {
                            try { payload.phones = JSON.parse(payload.phones); } catch (e) {}
                        }

                        this.$axios.post(params.id ? `{{ route('admin.settings.clinics.update', '') }}/${params.id}` : "{{ route('admin.settings.clinics.store') }}", payload, {
                            headers: { 'Content-Type': 'multipart/form-data' }
                        }).then(response => {
                            this.isProcessing = false;
                            this.$refs.clinicUpdateAndCreateModal.toggle();
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            this.$refs.datagrid.get();
                            resetForm();
                        }).catch(error => {
                            this.isProcessing = false;
                            if (error.response && error.response.status === 422) {
                                setErrors(error.response.data.errors);
                            }
                        });
                    },

                    editModal(url) {
                        this.$axios.get(url)
                            .then(response => {
                                const data = response.data.data;
                                // Ensure textareas show JSON strings for arrays
                                if (Array.isArray(data.emails)) data.emails = JSON.stringify(data.emails);
                                if (Array.isArray(data.phones)) data.phones = JSON.stringify(data.phones);
                                this.$refs.modalForm.setValues(data);
                                this.$refs.clinicUpdateAndCreateModal.toggle();
                            })
                            .catch(error => {});
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>

