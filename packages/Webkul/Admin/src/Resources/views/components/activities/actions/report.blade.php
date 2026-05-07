@props([
    'entity' => null,
])

<!-- Report Upload Button -->
<div>
    <button
        class="flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-green-50 font-medium text-green-700 transition-all hover:border-green-300 dark:bg-green-900/20 dark:text-green-300 dark:hover:border-green-700"
        @click="$refs.reportUploadComponent.openModal()"
    >
        <span class="icon-attachment text-2xl dark:!text-green-300"></span>

        Rapportage
    </button>

    <!-- Report Upload Vue Component -->
    <v-report-upload
        ref="reportUploadComponent"
        :entity="{{ json_encode($entity) }}"
    ></v-report-upload>
</div>

@pushOnce('scripts')
    <script type="text/x-template" id="v-report-upload-template">
        <Teleport to="body">
            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="modalForm"
            >
                <form @submit="handleSubmit($event, save)">
                    <x-admin::modal
                        ref="reportUploadModal"
                        position="center"
                    >
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                Rapportage uploaden
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <!-- Order ID (hidden) -->
                            <x-admin::form.control-group.control
                                type="hidden"
                                name="order_id"
                                ::value="entity.id"
                            />

                            <!-- Title -->
                            <x-adminc::components.field
                                type="text"
                                name="title"
                                label="Titel (optioneel)"
                            />

                            <!-- Clinic selector -->
                            <div class="mb-4">
                                <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                    Kliniek <span class="text-red-500">*</span>
                                </label>
                                <div v-if="loadingData" class="text-sm text-gray-500">
                                    Laden...
                                </div>
                                <div v-else-if="clinics.length === 0" class="text-sm text-gray-500">
                                    Geen klinieken gevonden voor deze order.
                                </div>
                                <select
                                    v-else
                                    v-model="selectedClinicId"
                                    name="clinic_id"
                                    class="w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                    required
                                >
                                    <option value="" disabled>Selecteer een kliniek</option>
                                    <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
                                        @{{ clinic.name }}
                                    </option>
                                </select>
                                <input type="hidden" name="clinic_id" :value="selectedClinicId" />
                            </div>

                            <!-- Checks selector (multi-select) -->
                            <div class="mb-4">
                                <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                    Checks afvinken <span class="text-red-500">*</span>
                                </label>
                                <div v-if="loadingData" class="text-sm text-gray-500">
                                    Laden...
                                </div>
                                <div v-else-if="checks.length === 0" class="text-sm text-gray-500">
                                    Geen openstaande checks gevonden.
                                </div>
                                <div v-else class="max-h-48 space-y-2 overflow-y-auto rounded border border-gray-200 p-2 dark:border-gray-700">
                                    <label
                                        v-for="check in checks"
                                        :key="check.id"
                                        class="flex cursor-pointer items-center gap-2 rounded p-1 hover:bg-gray-50 dark:hover:bg-gray-800"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="check.id"
                                            v-model="selectedCheckIds"
                                            class="h-4 w-4 shrink-0 rounded border-gray-300"
                                        />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            @{{ check.name }}
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <!-- File input -->
                            <x-adminc::components.field
                                type="file"
                                id="report_file"
                                name="file"
                                label="Bestand"
                                rules="required"
                                class="!mb-0"
                            />

                            <!-- Comment -->
                            <x-adminc::components.field
                                type="textarea"
                                name="comment"
                                label="Opmerking (optioneel)"
                            />
                        </x-slot>

                        <x-slot:footer>
                            <x-admin::button
                                class="primary-button"
                                title="Uploaden"
                                ::loading="isStoring"
                                ::disabled="isStoring || selectedCheckIds.length === 0 || !selectedClinicId"
                            />
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>
        </Teleport>
    </script>

    <script type="module">
        app.component('v-report-upload', {
            template: '#v-report-upload-template',

            props: {
                entity: {
                    type: Object,
                    required: true,
                    default: () => ({})
                },
            },

            data() {
                return {
                    isStoring: false,
                    loadingData: false,
                    clinics: [],
                    checks: [],
                    selectedClinicId: '',
                    selectedCheckIds: [],
                };
            },

            methods: {
                openModal() {
                    this.selectedClinicId = '';
                    this.selectedCheckIds = [];
                    this.clinics = [];
                    this.checks = [];
                    this.$refs.reportUploadModal.open();
                    this.fetchData();
                },

                fetchData() {
                    this.loadingData = true;

                    const url = '{{ route("admin.orders.report-upload-data", ":id") }}'.replace(':id', this.entity.id);

                    this.$axios.get(url)
                        .then(response => {
                            this.clinics = response.data.clinics ?? [];
                            this.checks = response.data.checks ?? [];

                            if (this.clinics.length === 1) {
                                this.selectedClinicId = this.clinics[0].id;
                            }
                        })
                        .catch(() => {
                            this.clinics = [];
                            this.checks = [];
                        })
                        .finally(() => {
                            this.loadingData = false;
                        });
                },

                save(params, { setErrors }) {
                    if (!this.selectedClinicId || this.selectedCheckIds.length === 0) {
                        return;
                    }

                    this.isStoring = true;

                    const formData = new FormData();

                    Object.entries(params).forEach(([key, value]) => {
                        if (value === null || value === undefined) return;
                        if (key === 'clinic_id') return;
                        if (value instanceof FileList) {
                            Array.from(value).forEach(f => formData.append(key, f));
                        } else if (value instanceof File || value instanceof Blob) {
                            formData.append(key, value);
                        } else {
                            formData.append(key, String(value));
                        }
                    });

                    formData.set('clinic_id', String(this.selectedClinicId));

                    this.selectedCheckIds.forEach(id => {
                        formData.append('check_ids[]', String(id));
                    });

                    const url = '{{ route("admin.orders.report-upload.store", ":id") }}'.replace(':id', this.entity.id);

                    this.$axios.post(url, formData)
                        .then(response => {
                            this.isStoring = false;

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            this.$emitter.emit('on-activity-added', response.data.data);

                            this.$refs.reportUploadModal.close();
                        })
                        .catch(error => {
                            this.isStoring = false;

                            if (error.response && error.response.status === 422) {
                                setErrors(error.response.data.errors);
                            } else {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error.response?.data?.message || 'Er is een fout opgetreden.',
                                });
                            }
                        });
                },
            },
        });
    </script>
@endPushOnce
