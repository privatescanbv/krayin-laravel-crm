@php
    use App\Enums\EmailTemplateType;
@endphp
<x-admin::layouts>
    <x-slot:title>
        Afspraak bevestigen
    </x-slot>

    <div class="flex flex-col gap-4">
        {{-- TinyMCE scripts + hidden boot editor must live inside #app so <v-tinymce> mounts --}}
        <div class="pointer-events-none fixed left-[-9999px] top-0 h-px w-px overflow-hidden opacity-0" aria-hidden="true">
            <textarea id="__order-confirm-tinymce-boot" tabindex="-1"></textarea>
            <x-admin::tinymce selector="textarea#__order-confirm-tinymce-boot" />
        </div>

        {{-- Header --}}
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="orders.confirm" :entity="$orders"/>
                <div class="flex items-center gap-3">
                    <div class="text-xl font-bold dark:text-gray-300">Afspraak bevestigen</div>
                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                        #{{ $orders->name }}
                    </span>
                </div>
            </div>
            <a href="{{ route('admin.orders.edit', $orders->id) }}" class="secondary-button">
                Terug naar order
            </a>
        </div>

        {{-- Wizard --}}
        <div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
            <v-appointment-wizard
                :order-id="{{ $orders->id }}"
                :sales-lead-id="{{ $orders->sales_lead_id ?? 'null' }}"
                :initial-content='@json($orders->confirmation_letter_content ?? null)'
                :emails='@json($orderEmailOptions ?? [])'
                view-url="{{ route('admin.orders.view', $orders->id) }}"
            ></v-appointment-wizard>
        </div>
    </div>

    @pushOnce('scripts')
        {{-- Step 1: Orderbevestiging template --}}
        <script type="text/x-template" id="v-appointment-wizard-template">
            <div>
                {{-- Step indicator --}}
                <div class="flex items-center border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                    <template v-for="(step, index) in steps" :key="index">
                        <button
                            type="button"
                            class="flex items-center gap-2 text-sm font-medium transition-colors"
                            :class="[
                                currentStep === index
                                    ? 'text-brandColor'
                                    : currentStep > index
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-gray-400 dark:text-gray-500'
                            ]"
                            @click="goToStep(index)"
                            :disabled="index > currentStep"
                        >
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-full border-2 text-xs font-bold transition-colors"
                                :class="[
                                    currentStep === index
                                        ? 'border-brandColor bg-brandColor text-white'
                                        : currentStep > index
                                            ? 'border-green-500 bg-green-500 text-white'
                                            : 'border-gray-300 dark:border-gray-600'
                                ]"
                            >
                                <span v-if="currentStep > index">&#10003;</span>
                                <span v-else>@{{ index + 1 }}</span>
                            </span>
                            <span>@{{ step }}</span>
                        </button>
                        <div v-if="index < steps.length - 1" class="mx-3 h-px w-8 bg-gray-300 dark:bg-gray-600"></div>
                    </template>
                </div>

                {{-- Step content --}}
                <div class="p-6">
                    {{-- STEP 1: Orderbevestiging --}}
                    <div v-show="currentStep === 0">
                        <div class="space-y-6">
                            <div class="flex flex-col gap-1">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Orderbevestiging</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Genereer de orderbevestiging(en). Gebruik Preview om het resultaat als PDF te bekijken. Deze zal als pdf beschikbaar worden gesteld in het patiëntportaal.
                                </p>
                            </div>

                            <div class="space-y-4">
                                <div class="flex items-center gap-4">
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template</label>
                                        <select
                                            v-model="confirmationTemplate"
                                            class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                        >
                                            <option value="">Selecteer een template</option>
                                            <option v-for="t in confirmationTemplates" :key="t.code || t.name" :value="t.code || t.name">
                                                @{{ t.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <button type="button" @click="generateLetter" :disabled="!confirmationTemplate || isGenerating" class="primary-button">
                                            <span v-if="isGenerating">Bezig...</span>
                                            <span v-else>Genereer brief</span>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="letterContent" class="space-y-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Brief inhoud</label>
                                        <textarea
                                            id="confirmation-letter-editor"
                                            v-model="letterContent"
                                            class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                            rows="12"
                                        ></textarea>
                                        <v-tinymce selector="textarea#confirmation-letter-editor" :field="confirmationLetterField"></v-tinymce>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button" @click="previewConfirmationPdf" :disabled="!letterContent || previewPdfLoading" class="secondary-button">
                                            <span v-if="previewPdfLoading">Preview laden...</span>
                                            <span v-else>Preview</span>
                                        </button>
                                    </div>

                                    <div v-if="previewPdfBlobUrl" class="mt-2 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                        <iframe
                                            :src="previewPdfBlobUrl"
                                            class="h-[min(70vh,640px)] w-full border-0 bg-gray-100 dark:bg-gray-800"
                                            title="PDF preview orderbevestiging"
                                        ></iframe>
                                    </div>
                                </div>

                                <div v-else class="text-center py-12 text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center gap-3">
                                        <i class="icon-document text-4xl text-gray-300 dark:text-gray-600"></i>
                                        <p class="text-lg font-medium">Nog geen brief gegenereerd</p>
                                        <p class="text-sm">Selecteer een template en klik op "Genereer brief" om te beginnen</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- STEP 2: Bestanden uploaden --}}
                    <div v-show="currentStep === 1">
                        <div class="space-y-6">
                            <div class="flex flex-col gap-1">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Bestanden uploaden</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Upload bestanden als activiteit. Standaard worden ze gedeeld via het patiëntportaal; je kunt dat per upload uitzetten.
                                </p>
                            </div>

                            {{-- Uploaded files list --}}
                            <div v-if="uploadedFiles.length" class="space-y-2">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Geüploade bestanden</h4>
                                <div v-for="(file, i) in uploadedFiles" :key="i" class="flex items-center gap-3 rounded-lg bg-green-50 dark:bg-green-900/20 p-3">
                                    <span class="icon-file text-lg text-green-600"></span>
                                    <span class="text-sm text-gray-800 dark:text-gray-200">@{{ file.title || file.filename }}</span>
                                    <span class="ml-auto text-xs text-green-600 font-medium">Geüpload</span>
                                </div>
                            </div>

                            {{-- Upload form --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                                <x-adminc::components.field type="text" name="file_title" label="Titel" />
                                <x-adminc::components.field type="textarea" name="file_comment" label="Omschrijving" />

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bestand(en)</label>
                                    <input
                                        type="file"
                                        ref="fileInput"
                                        multiple
                                        class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-gray-700 dark:file:text-gray-200"
                                    />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Je kunt meerdere bestanden tegelijk selecteren.</p>
                                </div>

                                <div class="mt-4">
                                    <label class="flex cursor-pointer items-center gap-2">
                                        <input type="checkbox" v-model="publishToPortal" @change="onPublishChange" class="h-4 w-4 shrink-0 rounded border-gray-300" />
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Publiceren in patiëntportaal</span>
                                    </label>
                                    <div v-if="publishToPortal" class="mt-3">
                                        <div v-if="loadingPersons" class="text-sm text-gray-500">Personen laden...</div>
                                        <div v-else-if="persons.length > 1">
                                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Deel met persoon</label>
                                            <select v-model="selectedPersonId" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                                <option value="" disabled>Selecteer een persoon</option>
                                                <option v-for="person in persons" :key="person.id" :value="person.id">@{{ person.name }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <button type="button" @click="uploadFile" :disabled="isUploadingFile" class="primary-button">
                                    <span v-if="isUploadingFile">Uploaden...</span>
                                    <span v-else>Upload geselecteerde bestanden</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- STEP 3: Email versturen --}}
                    <div v-show="currentStep === 2">
                        <div class="space-y-6">
                            <div class="flex flex-col gap-1">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Email versturen</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Stel de bevestigingsmail op en verstuur deze naar de patiënt.
                                </p>
                            </div>

                            <div class="space-y-4">
                                {{-- To --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Aan <span class="text-red-500">*</span></label>
                                    <div class="flex items-center gap-2">
                                        <select v-if="entityEmails.length" v-model="emailTo" class="flex-1 rounded border border-gray-200 px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                            <option value="" disabled>Selecteer een e-mailadres</option>
                                            <option v-for="mail in entityEmails" :key="mail.value" :value="mail.value">
                                                @{{ mail.value }} <span v-if="mail.is_default">(standaard)</span>
                                            </option>
                                        </select>
                                        <input v-else type="email" v-model="emailTo" placeholder="E-mailadres invoeren" class="flex-1 rounded border border-gray-200 px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                                    </div>
                                </div>

                                {{-- CC toggle --}}
                                <div class="flex gap-4 text-sm">
                                    <button type="button" @click="showCC = !showCC" class="font-medium text-brandColor hover:underline">CC</button>
                                    <button type="button" @click="showBCC = !showBCC" class="font-medium text-brandColor hover:underline">BCC</button>
                                </div>

                                <div v-if="showCC">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CC</label>
                                    <input type="text" v-model="emailCC" placeholder="E-mailadressen (komma-gescheiden)" class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                                </div>

                                <div v-if="showBCC">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">BCC</label>
                                    <input type="text" v-model="emailBCC" placeholder="E-mailadressen (komma-gescheiden)" class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                                </div>

                                {{-- Template --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template</label>
                                    <select v-model="emailSelectedTemplate" @change="loadEmailTemplate" class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                        <option value="">Geen template</option>
                                        <option v-for="t in emailTemplates" :key="t.code || t.name" :value="t.code || t.name">
                                            @{{ t.label }}
                                        </option>
                                    </select>
                                </div>

                                {{-- Subject --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Onderwerp <span class="text-red-500">*</span></label>
                                    <input type="text" v-model="emailSubject" placeholder="Onderwerp" class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                                </div>

                                {{-- Body --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bericht <span class="text-red-500">*</span></label>
                                    <textarea
                                        id="wizard-email-editor"
                                        v-model="emailBody"
                                        class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                        rows="12"
                                    ></textarea>
                                    <v-tinymce selector="textarea#wizard-email-editor" :field="emailBodyField"></v-tinymce>
                                </div>

                                {{-- Attachments --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bijlagen</label>
                                    <input
                                        type="file"
                                        ref="emailAttachmentInput"
                                        multiple
                                        @change="onEmailAttachmentChange"
                                        class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-gray-700 dark:file:text-gray-200"
                                    />
                                    <div v-if="emailAttachments.length" class="mt-2 space-y-1">
                                        <div v-for="(att, i) in emailAttachments" :key="i" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                            <span class="icon-attachment text-lg"></span>
                                            <span>@{{ att.name }}</span>
                                            <button type="button" @click="removeEmailAttachment(i)" class="text-red-500 hover:text-red-700 text-xs ml-auto">Verwijderen</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Navigation --}}
                <div class="flex items-center justify-between border-t border-gray-200 dark:border-gray-800 px-6 py-4">
                    <button
                        v-if="currentStep > 0"
                        type="button"
                        class="secondary-button"
                        @click="currentStep--"
                    >
                        Vorige
                    </button>
                    <div v-else></div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="currentStep === 1"
                            type="button"
                            class="secondary-button"
                            @click="skipStep"
                        >
                            Overslaan
                        </button>

                        <button
                            v-if="currentStep < steps.length - 1"
                            type="button"
                            class="primary-button"
                            :disabled="isSavingLetter || isUploadingFile"
                            @click="nextStep"
                        >
                            <span v-if="isSavingLetter">Opslaan...</span>
                            <span v-else-if="isUploadingFile">Uploaden...</span>
                            <span v-else>Volgende</span>
                        </button>

                        <button
                            v-if="currentStep === steps.length - 1"
                            type="button"
                            class="primary-button"
                            :disabled="isSendingEmail"
                            @click="sendEmail"
                        >
                            <span v-if="isSendingEmail">Versturen...</span>
                            <span v-else>Verstuur email</span>
                        </button>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-appointment-wizard', {
                template: '#v-appointment-wizard-template',

                props: {
                    orderId: { type: Number, required: true },
                    salesLeadId: { type: Number, default: null },
                    initialContent: { type: String, default: '' },
                    emails: { type: Array, default: () => [] },
                    viewUrl: { type: String, required: true },
                },

                data() {
                    return {
                        currentStep: 0,
                        steps: ['Orderbevestiging', 'Bestanden uploaden', 'Email versturen'],

                        // Step 1
                        confirmationTemplates: [],
                        confirmationTemplate: '',
                        letterContent: this.initialContent || '',
                        isGenerating: false,
                        isSavingLetter: false,
                        previewPdfBlobUrl: null,
                        previewPdfLoading: false,

                        // Step 2
                        publishToPortal: true,
                        persons: [],
                        selectedPersonId: '',
                        loadingPersons: false,
                        isUploadingFile: false,
                        uploadedFiles: [],

                        // Step 3
                        emailTemplates: [],
                        emailSelectedTemplate: '',
                        emailTo: '',
                        emailCC: '',
                        emailBCC: '',
                        emailSubject: '',
                        emailBody: '',
                        emailAttachments: [],
                        emailAttachmentFiles: [],
                        showCC: false,
                        showBCC: false,
                        isSendingEmail: false,
                        entityEmails: this.emails || [],
                    };
                },

                watch: {
                    currentStep(newVal) {
                        if (newVal === 1 && this.publishToPortal) {
                            this.loadPortalPersons();
                        }
                    },
                },

                computed: {
                    confirmationLetterField() {
                        return {
                            onInput: (content) => {
                                this.letterContent = content;
                            },
                        };
                    },
                    emailBodyField() {
                        return {
                            onInput: (content) => {
                                this.emailBody = content;
                            },
                        };
                    },
                },

                mounted() {
                    this.loadConfirmationTemplates();
                },

                beforeUnmount() {
                    if (this.previewPdfBlobUrl) {
                        URL.revokeObjectURL(this.previewPdfBlobUrl);
                    }
                },

                methods: {
                    getCsrfToken() {
                        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                            || document.querySelector('input[name="_token"]')?.value
                            || '';
                    },

                    emitFlash(type, message) {
                        try {
                            const emitter = window.app?.config?.globalProperties?.$emitter || window.app?.$emitter;
                            if (emitter) emitter.emit('add-flash', { type, message });
                        } catch (e) {
                            console[type === 'error' ? 'error' : 'log'](message);
                        }
                    },

                    goToStep(index) {
                        if (index <= this.currentStep) {
                            this.currentStep = index;
                        }
                    },

                    hasPendingFiles() {
                        const fileInput = this.$refs.fileInput;
                        return fileInput && fileInput.files && fileInput.files.length > 0;
                    },

                    async nextStep() {
                        if (this.currentStep === 0) {
                            const content = this.getTinyMCEContent('confirmation-letter-editor');
                            if (!content || !String(content).trim()) {
                                this.emitFlash('error', 'Genereer eerst de orderbevestiging en vul de inhoud in.');
                                return;
                            }
                            const saved = await this.persistConfirmationLetter();
                            if (!saved) {
                                return;
                            }
                        }

                        if (this.currentStep === 1 && this.hasPendingFiles()) {
                            await this.uploadFile();
                            if (this.hasPendingFiles()) {
                                return;
                            }
                        }

                        if (this.currentStep < this.steps.length - 1) {
                            this.currentStep++;
                            if (this.currentStep === 2) {
                                await this.prepareEmailStep();
                            }
                        }
                    },

                    skipStep() {
                        if (this.currentStep === 1 && this.hasPendingFiles()) {
                            if (!confirm('Er zijn geselecteerde bestanden die nog niet geüpload zijn. Weet je zeker dat je deze stap wilt overslaan?')) {
                                return;
                            }
                            this.$refs.fileInput.value = '';
                        }
                        this.currentStep++;
                        if (this.currentStep === 2) {
                            this.prepareEmailStep();
                        }
                    },

                    // ---- Step 1: Orderbevestiging ----

                    async loadConfirmationTemplates() {
                        try {
                            const response = await fetch('{{ route("admin.mail.templates") }}?entity_type=order', {
                                headers: { 'Accept': 'application/json' },
                            });
                            const data = await response.json();
                            this.confirmationTemplates = data.data || [];
                        } catch (e) {
                            this.emitFlash('error', 'Fout bij laden templates');
                        }
                    },

                    async generateLetter() {
                        if (!this.confirmationTemplate) return;
                        this.isGenerating = true;
                        try {
                            const response = await fetch(`/admin/orders/${this.orderId}/confirmation/template-content?template=${this.confirmationTemplate}`, {
                                headers: { 'Accept': 'application/json' },
                            });
                            if (!response.ok) {
                                const err = await response.json();
                                throw new Error(err.message || 'Fout bij genereren brief');
                            }
                            const data = await response.json();
                            this.letterContent = data.data.content || '';
                            this.$nextTick(() => this.setTinyMCEContent('confirmation-letter-editor', this.letterContent));
                            this.emitFlash('success', 'Brief gegenereerd');
                        } catch (e) {
                            this.emitFlash('error', e.message || 'Fout bij genereren brief');
                        } finally {
                            this.isGenerating = false;
                        }
                    },

                    async persistConfirmationLetter() {
                        window.tinymce?.triggerSave?.();
                        const content = this.getTinyMCEContent('confirmation-letter-editor');
                        if (!content || !String(content).trim()) {
                            this.emitFlash('error', 'Geen inhoud om op te slaan');
                            return false;
                        }
                        this.isSavingLetter = true;
                        try {
                            const response = await fetch(`/admin/orders/${this.orderId}/confirmation/save`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.getCsrfToken() },
                                body: JSON.stringify({ content }),
                            });
                            if (!response.ok) {
                                const err = await response.json();
                                throw new Error(err.message || 'Fout bij opslaan');
                            }
                            this.letterContent = content;
                            return true;
                        } catch (e) {
                            this.emitFlash('error', e.message || 'Fout bij opslaan brief');
                            return false;
                        } finally {
                            this.isSavingLetter = false;
                        }
                    },

                    async previewConfirmationPdf() {
                        window.tinymce?.triggerSave?.();
                        const content = this.getTinyMCEContent('confirmation-letter-editor');
                        if (!content || !String(content).trim()) {
                            this.emitFlash('error', 'Geen inhoud voor preview');
                            return;
                        }
                        this.previewPdfLoading = true;
                        try {
                            const response = await fetch(`/admin/orders/${this.orderId}/confirmation/preview-pdf`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/pdf',
                                    'X-CSRF-TOKEN': this.getCsrfToken(),
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({ content }),
                            });
                            if (!response.ok) {
                                const err = await response.json().catch(() => ({}));
                                throw new Error(err.message || 'Preview mislukt');
                            }
                            const blob = await response.blob();
                            if (this.previewPdfBlobUrl) {
                                URL.revokeObjectURL(this.previewPdfBlobUrl);
                            }
                            this.previewPdfBlobUrl = URL.createObjectURL(blob);
                        } catch (e) {
                            this.emitFlash('error', e.message || 'Preview mislukt');
                        } finally {
                            this.previewPdfLoading = false;
                        }
                    },

                    // ---- Step 2: Bestanden uploaden ----

                    onPublishChange() {
                        this.persons = [];
                        this.selectedPersonId = '';
                        if (!this.publishToPortal) {
                            return;
                        }
                        this.loadPortalPersons();
                    },

                    loadPortalPersons() {
                        if (!this.publishToPortal) {
                            return;
                        }

                        this.loadingPersons = true;
                        this.$axios.get("{{ route('admin.activities.persons-for-entity') }}", {
                            params: { entity_type: 'order', entity_id: this.orderId }
                        }).then(response => {
                            this.persons = response.data.data ?? [];
                            if (this.persons.length === 1) {
                                this.selectedPersonId = this.persons[0].id;
                            }
                        }).catch(() => {
                            this.persons = [];
                        }).finally(() => {
                            this.loadingPersons = false;
                        });
                    },

                    async uploadFile() {
                        const fileInput = this.$refs.fileInput;
                        const files = Array.from(fileInput?.files || []);
                        if (!files.length) {
                            this.emitFlash('error', 'Selecteer minstens één bestand');
                            return;
                        }

                        const titleField = document.querySelector('[name="file_title"]');
                        const commentField = document.querySelector('[name="file_comment"]');
                        const baseTitle = (titleField?.value || '').trim();
                        const comment = commentField?.value || '';

                        this.isUploadingFile = true;
                        let uploadedCount = 0;

                        try {
                            for (const file of files) {
                                let title;
                                if (files.length > 1) {
                                    title = baseTitle ? `${baseTitle} – ${file.name}` : file.name;
                                } else {
                                    title = baseTitle || file.name;
                                }

                                const formData = new FormData();
                                formData.append('type', 'file');
                                if (this.orderId == null || this.orderId === '') {
                                    this.emitFlash('error', 'Order-ID ontbreekt; vernieuw de pagina en probeer opnieuw.');
                                    break;
                                }
                                formData.append('order_id', String(this.orderId));
                                const sl = this.salesLeadId;
                                if (sl != null && sl !== '' && sl !== 'null' && Number.isFinite(Number(sl))) {
                                    formData.append('sales_lead_id', String(Number(sl)));
                                }
                                formData.append('title', title);
                                formData.append('comment', comment);
                                formData.append('file', file);
                                formData.append('publish_to_portal', this.publishToPortal ? '1' : '0');
                                if (this.publishToPortal && this.selectedPersonId) {
                                    formData.append('person_ids[]', String(this.selectedPersonId));
                                }

                                try {
                                    const response = await this.$axios.post("{{ route('admin.activities.store') }}", formData);
                                    this.uploadedFiles.push({
                                        title,
                                        filename: file.name,
                                    });
                                    uploadedCount++;
                                    if (files.length === 1) {
                                        this.emitFlash('success', response.data.message || 'Bestand geüpload');
                                    }
                                } catch (error) {
                                    const msg = error.response?.data?.message || 'Fout bij uploaden';
                                    this.emitFlash('error', `${msg} (${file.name})`);
                                    break;
                                }
                            }

                            if (uploadedCount === files.length) {
                                if (files.length > 1) {
                                    this.emitFlash('success', `${uploadedCount} bestanden geüpload`);
                                }
                                if (titleField) {
                                    titleField.value = '';
                                }
                                if (commentField) {
                                    commentField.value = '';
                                }
                                if (fileInput) {
                                    fileInput.value = '';
                                }
                            }
                        } finally {
                            this.isUploadingFile = false;
                        }
                    },

                    // ---- Step 3: Email versturen ----

                    async prepareEmailStep() {
                        await this.loadEmailTemplates();
                        await this.loadMailPreview();
                        this.$nextTick(() => {
                            this.initEmailTinyMCE();
                        });
                    },

                    async loadEmailTemplates() {
                        try {
                            const response = await this.$axios.get('{{ route("admin.mail.templates") }}', {
                                params: { entity_type: @json(EmailTemplateType::ORDER->value) }
                            });
                            const templates = response.data?.data || response.data || [];
                            this.emailTemplates = Array.isArray(templates) ? templates : [];
                        } catch (e) {
                            this.emailTemplates = [];
                        }
                    },

                    async loadMailPreview() {
                        try {
                            const response = await fetch(`/admin/orders/${this.orderId}/mail/preview`, {
                                headers: { 'Accept': 'application/json' },
                            });
                            const payload = await response.json();
                            if (!response.ok) {
                                this.emitFlash('info', payload?.message || 'Kon mail preview niet laden');
                                return;
                            }

                            if (payload.default_email) {
                                this.emailTo = typeof payload.default_email === 'object'
                                    ? (payload.default_email.email || payload.default_email.value || '')
                                    : payload.default_email;
                            } else if (!this.emailTo && this.entityEmails.length) {
                                const def = this.entityEmails.find(e => e.is_default);
                                this.emailTo = def ? def.value : (this.entityEmails[0]?.value || '');
                            }

                            if (payload.emails?.length) {
                                this.entityEmails = payload.emails;
                            }

                            this.emailSubject = payload.subject || '';

                            // Set default template for email
                            const defaultTpl = 'appointment-confirmation';
                            if (this.emailTemplates.some(t => (t.code || t.name) === defaultTpl)) {
                                this.emailSelectedTemplate = defaultTpl;
                                this.$nextTick(() => this.loadEmailTemplate());
                            } else if (payload.body) {
                                this.emailBody = payload.body;
                                this.$nextTick(() => this.setTinyMCEContent('wizard-email-editor', payload.body));
                            }

                            // Auto-add PDF attachment if available
                            if (payload.attachments?.length) {
                                for (const att of payload.attachments) {
                                    if (att.url && att.filename) {
                                        await this.fetchAndAddAttachment(att.url, att.filename);
                                    }
                                }
                            }
                        } catch (e) {
                            this.emitFlash('info', 'Kon mail niet voorbereiden. Vul de gegevens handmatig in.');
                        }
                    },

                    async fetchAndAddAttachment(url, filename) {
                        try {
                            const response = await fetch(url);
                            if (!response.ok) return;
                            const blob = await response.blob();
                            const file = new File([blob], filename, { type: blob.type || 'application/pdf' });
                            this.emailAttachmentFiles.push(file);
                            this.emailAttachments.push({ name: filename });
                        } catch (e) {
                            // Non-critical
                        }
                    },

                    loadEmailTemplate() {
                        if (!this.emailSelectedTemplate) return;

                        const entities = {};
                        if (this.salesLeadId) entities.sales_lead = this.salesLeadId;
                        if (this.orderId) entities.order = this.orderId;

                        if (Object.keys(entities).length === 0) {
                            this.emitFlash('error', 'Geen entities gevonden voor template');
                            return;
                        }

                        Promise.all([
                            this.$axios.post('{{ route("admin.mail.template_content_body") }}', {
                                email_template_identifier: this.emailSelectedTemplate,
                                entities,
                            }),
                            this.$axios.post('{{ route("admin.mail.template_content_subject") }}', {
                                email_template_identifier: this.emailSelectedTemplate,
                                entities,
                            }),
                        ]).then(([bodyRes, subjectRes]) => {
                            const content = bodyRes.data.data.content || '';
                            const subject = subjectRes.data.data.subject || '';
                            const signature = @json(auth()->guard('user')->user()->signature ?? '');
                            const fullContent = content + (signature ? '<br><br>' + signature : '');

                            if (subject) this.emailSubject = subject;
                            this.emailBody = fullContent;
                            this.setTinyMCEContent('wizard-email-editor', fullContent);
                        }).catch(error => {
                            this.emitFlash('error', 'Fout bij laden template: ' + (error.response?.data?.message || error.message));
                        });
                    },

                    onEmailAttachmentChange(event) {
                        const files = Array.from(event.target.files || []);
                        files.forEach(f => {
                            this.emailAttachmentFiles.push(f);
                            this.emailAttachments.push({ name: f.name });
                        });
                        event.target.value = '';
                    },

                    removeEmailAttachment(index) {
                        this.emailAttachments.splice(index, 1);
                        this.emailAttachmentFiles.splice(index, 1);
                    },

                    async sendEmail() {
                        window.tinymce?.triggerSave?.();

                        const body = this.getTinyMCEContent('wizard-email-editor');
                        if (!this.emailTo) {
                            this.emitFlash('error', 'Vul een e-mailadres in');
                            return;
                        }
                        if (!this.emailSubject) {
                            this.emitFlash('error', 'Vul een onderwerp in');
                            return;
                        }
                        if (!body) {
                            this.emitFlash('error', 'Vul een bericht in');
                            return;
                        }

                        this.isSendingEmail = true;

                        const formData = new FormData();
                        formData.append('type', 'email');
                        formData.append('order_id', this.orderId);
                        formData.append('reply_to[]', this.emailTo);
                        formData.append('subject', this.emailSubject);
                        formData.append('reply', body);

                        if (this.emailCC) {
                            this.emailCC.split(',').map(s => s.trim()).filter(Boolean).forEach(cc => {
                                formData.append('cc[]', cc);
                            });
                        }
                        if (this.emailBCC) {
                            this.emailBCC.split(',').map(s => s.trim()).filter(Boolean).forEach(bcc => {
                                formData.append('bcc[]', bcc);
                            });
                        }

                        this.emailAttachmentFiles.forEach((file, i) => {
                            formData.append(`attachments[${i}]`, file);
                        });

                        try {
                            const response = await this.$axios.post("{{ route('admin.mail.store') }}", formData, {
                                headers: { 'Content-Type': 'multipart/form-data' },
                            });

                            this.emitFlash('success', response.data.message || 'Email verstuurd');

                            // Mark order as sent
                            try {
                                await fetch(`/admin/orders/${this.orderId}/status/sent`, {
                                    method: 'POST',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': this.getCsrfToken(),
                                    },
                                });
                            } catch (e) { /* non-critical */ }

                            // Redirect to order view
                            setTimeout(() => {
                                window.location.href = this.viewUrl;
                            }, 500);
                        } catch (error) {
                            this.isSendingEmail = false;
                            const errors = error.response?.data?.errors;
                            if (errors) {
                                Object.values(errors).flat().forEach(msg => this.emitFlash('error', msg));
                            } else {
                                this.emitFlash('error', error.response?.data?.message || 'Versturen mislukt');
                            }
                        }
                    },

                    // ---- TinyMCE helpers ----

                    initEmailTinyMCE() {
                        this.setTinyMCEContent('wizard-email-editor', this.emailBody, 30);
                    },

                    setTinyMCEContent(editorId, content, retries = 25) {
                        if (!content || !content.trim()) return;
                        if (window.tinymce) {
                            try {
                                const editor = window.tinymce.get(editorId);
                                if (editor && !editor.removed && editor.initialized) {
                                    editor.setContent(content);
                                    return;
                                }
                            } catch (e) { /* not ready */ }
                        }
                        if (retries > 0) {
                            setTimeout(() => this.setTinyMCEContent(editorId, content, retries - 1), 200);
                        }
                    },

                    getTinyMCEContent(editorId) {
                        if (window.tinymce) {
                            try {
                                const editor = window.tinymce.get(editorId);
                                if (editor && !editor.removed && editor.initialized) {
                                    return editor.getContent();
                                }
                            } catch (e) { /* fallback */ }
                        }
                        if (editorId === 'confirmation-letter-editor') return this.letterContent || '';
                        if (editorId === 'wizard-email-editor') return this.emailBody || '';
                        return '';
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
