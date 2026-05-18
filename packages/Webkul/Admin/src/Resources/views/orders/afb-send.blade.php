<x-admin::layouts>
    <x-slot:title>
        AFB handmatig verzenden
    </x-slot>

    <div class="flex flex-col gap-4">
        {{-- TinyMCE scripts + hidden boot editor must live inside #app so <v-tinymce> mounts --}}
        <div class="pointer-events-none fixed left-[-9999px] top-0 h-px w-px overflow-hidden opacity-0"
             aria-hidden="true">
            <textarea id="__afb-send-tinymce-boot" tabindex="-1"></textarea>
            <x-admin::tinymce selector="textarea#__afb-send-tinymce-boot"/>
        </div>

        {{-- Header --}}
        <div
            class="flex items-center justify-between rounded-lg border bg-white px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="orders.afb-send" :entity="$order"/>
                <div class="flex items-center gap-3">
                    <div class="text-xl font-bold dark:text-gray-300">AFB handmatig verzenden</div>
                    <span
                        class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                        #{{ $order->order_number ?: $order->name }}
                    </span>
                </div>
            </div>
            <a href="{{ route('admin.orders.view', $order->id) }}" class="secondary-button">
                Terug naar order
            </a>
        </div>

        {{-- Main content --}}
        <div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
            <v-afb-send-wizard
                :order-id="{{ $order->id }}"
                :initial-rows='@json($afbStatusRows->map(fn ($row) => [
                    "department_id" => $row["department"]->id,
                    "department_name" => $row["department"]->name,
                    "clinic_id" => $row["department"]->clinic_id,
                    "clinic_name" => $row["department"]->clinic?->name,
                    "person_id" => $row["person"]?->id,
                    "person_name" => $row["person"]?->name,
                    "dispatch_id" => $row["dispatch"]?->id,
                    "dispatch_sent_at" => $row["dispatch"]?->sent_at?->format("d-m-Y H:i"),
                    "dispatch_pdf_url" => $row["dispatch"] ? route("admin.clinic-guide.afb-pdf.view", ["personDocumentId" => $row["dispatch"]->id]) : null,
                    "delete_url" => $row["dispatch"] ? route("admin.orders.afb.delete", ["orderId" => $order->id, "personDocumentId" => $row["dispatch"]->id]) : null,
                ])->values())'
                view-url="{{ route('admin.orders.view', $order->id) }}"
            ></v-afb-send-wizard>
        </div>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-afb-send-wizard-template">
            <div>
                {{-- Document overview --}}
                <div v-if="!composing">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">AFB documenten overzicht</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Overzicht van alle AFB documenten per afdeling. Klik op "Email samenstellen" om handmatig een AFB te verzenden.
                        </p>
                    </div>

                    <div class="p-6">
                        <div v-if="rows.length === 0"
                             class="text-center py-12 text-gray-500 dark:text-gray-400">
                            <p class="text-lg font-medium">Geen AFB documenten gevonden</p>
                            <p class="text-sm mt-1">Er zijn geen kliniekafdelingen gekoppeld aan de order items.</p>
                        </div>

                        <div v-else class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Kliniek / Afdeling
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Persoon
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Actie
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                    <tr v-for="row in rows" :key="row.department_id + '_' + (row.person_id || '')"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900 dark:text-white">
                                            <div class="font-medium">@{{ row.clinic_name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">@{{ row.department_name }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            @{{ row.person_name || '-' }}
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <span v-if="row.dispatch_sent_at"
                                                  class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                ✓ Verzonden @{{ row.dispatch_sent_at }}
                                            </span>
                                            <span v-else
                                                  class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                                Nog niet verzonden
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                            <div class="flex items-center justify-end gap-2">
                                                <a v-if="row.dispatch_pdf_url"
                                                   :href="row.dispatch_pdf_url"
                                                   target="_blank" rel="noopener noreferrer"
                                                   class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">
                                                    Bekijk formulier
                                                </a>
                                                <button v-if="row.dispatch_id"
                                                        type="button"
                                                        class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"
                                                        :disabled="deletingRow === row.dispatch_id"
                                                        @click="deleteDispatch(row)">
                                                    @{{ deletingRow === row.dispatch_id ? 'Bezig...' : 'Verwijder' }}
                                                </button>
                                                <button type="button"
                                                        class="primary-button text-xs !px-3 !py-1.5"
                                                        @click="startCompose(row)">
                                                    Email samenstellen
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Email composer --}}
                <div v-if="composing">
                    {{-- Back header --}}
                    <div class="flex items-center gap-3 border-b border-gray-200 px-6 py-3 dark:border-gray-800 bg-blue-50 dark:bg-blue-900/20">
                        <button type="button" @click="exitCompose"
                                class="text-sm font-medium text-brandColor hover:underline">&larr; Terug naar overzicht
                        </button>
                        <span class="text-sm text-gray-500 dark:text-gray-400">|</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                            @{{ composeTarget.clinic_name }} › @{{ composeTarget.department_name }}
                            <span v-if="composeTarget.person_name"> › @{{ composeTarget.person_name }}</span>
                        </span>
                    </div>

                    {{-- Loading state --}}
                    <div v-if="loadingCompose" class="p-6 text-center text-gray-500 dark:text-gray-400">
                        <p class="text-lg font-medium">Email voorbereiden...</p>
                    </div>

                    {{-- Compose form --}}
                    <div v-else class="p-6">
                        <div class="space-y-6">
                            <div class="flex flex-col gap-1">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Email samenstellen</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Stel de AFB email op en verstuur deze naar de kliniek afdeling.
                                    De AFB documenten en GVL formulieren worden automatisch bijgevoegd.
                                </p>
                            </div>

                            <div class="space-y-4">
                                {{-- To --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Aan
                                        <span class="text-red-500">*</span></label>
                                    <input type="email" v-model="emailTo"
                                           placeholder="E-mailadres afdeling"
                                           class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"/>
                                </div>

                                {{-- Subject --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Onderwerp
                                        <span class="text-red-500">*</span></label>
                                    <input type="text" v-model="emailSubject" placeholder="Onderwerp"
                                           class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"/>
                                </div>

                                {{-- Body --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bericht
                                        <span class="text-red-500">*</span></label>
                                    <textarea
                                        id="afb-email-editor"
                                        v-model="emailBody"
                                        class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                        rows="12"
                                    ></textarea>
                                    <v-tinymce selector="textarea#afb-email-editor"
                                               :field="emailBodyField"></v-tinymce>
                                </div>

                                {{-- Auto-generated attachments --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Automatische bijlagen</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                        Deze bijlagen worden automatisch gegenereerd en bijgevoegd bij het versturen.
                                    </p>
                                    <div v-if="autoAttachments.length" class="space-y-1">
                                        <div v-for="(att, i) in autoAttachments" :key="i"
                                             class="flex items-center gap-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 p-2 text-sm text-gray-600 dark:text-gray-400">
                                            <span class="icon-attachment text-lg text-blue-600"></span>
                                            <span>@{{ att.name }}</span>
                                            <span class="ml-auto text-xs text-blue-600 font-medium">Automatisch</span>
                                        </div>
                                    </div>
                                    <div v-else class="text-sm text-gray-400">Geen automatische bijlagen beschikbaar.</div>
                                </div>

                                {{-- Extra attachments --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Extra bijlagen</label>
                                    <input
                                        type="file"
                                        ref="extraAttachmentInput"
                                        multiple
                                        @change="onExtraAttachmentChange"
                                        class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-gray-700 dark:file:text-gray-200"
                                    />
                                    <div v-if="extraAttachments.length" class="mt-2 space-y-1">
                                        <div v-for="(att, i) in extraAttachments" :key="i"
                                             class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                            <span class="icon-attachment text-lg"></span>
                                            <span>@{{ att.name }}</span>
                                            <button type="button" @click="removeExtraAttachment(i)"
                                                    class="text-red-500 hover:text-red-700 text-xs ml-auto">
                                                Verwijderen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Navigation --}}
                    <div v-if="!loadingCompose"
                         class="flex items-center justify-between border-t border-gray-200 dark:border-gray-800 px-6 py-4">
                        <button type="button" class="secondary-button" @click="exitCompose">
                            Annuleren
                        </button>
                        <button type="button"
                                class="primary-button"
                                :disabled="isSending"
                                @click="sendEmail">
                            <span v-if="isSending">Versturen...</span>
                            <span v-else>Verstuur AFB email</span>
                        </button>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-afb-send-wizard', {
                template: '#v-afb-send-wizard-template',

                props: {
                    orderId: { type: Number, required: true },
                    initialRows: { type: Array, default: () => [] },
                    viewUrl: { type: String, required: true },
                },

                data() {
                    return {
                        rows: JSON.parse(JSON.stringify(this.initialRows || [])),
                        deletingRow: null,

                        composing: false,
                        composeTarget: null,
                        loadingCompose: false,

                        emailTo: '',
                        emailSubject: '',
                        emailBody: '',
                        autoAttachments: [],
                        extraAttachments: [],
                        extraAttachmentFiles: [],
                        isSending: false,
                    };
                },

                computed: {
                    emailBodyField() {
                        return {
                            onInput: (content) => {
                                this.emailBody = content;
                            },
                        };
                    },
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

                    async startCompose(row) {
                        this.composeTarget = row;
                        this.composing = true;
                        this.loadingCompose = true;
                        this.emailTo = '';
                        this.emailSubject = '';
                        this.emailBody = '';
                        this.autoAttachments = [];
                        this.extraAttachments = [];
                        this.extraAttachmentFiles = [];

                        try {
                            let url = `/admin/orders/${this.orderId}/afb-send/${row.department_id}/prepare`;
                            if (row.person_id) {
                                url += `?person_id=${row.person_id}`;
                            }

                            const response = await fetch(url, {
                                headers: { 'Accept': 'application/json' },
                            });

                            if (!response.ok) {
                                const err = await response.json().catch(() => ({}));
                                throw new Error(err.message || 'Fout bij voorbereiden email');
                            }

                            const data = await response.json();

                            this.emailTo = data.recipient_email || '';
                            this.emailSubject = data.subject || '';
                            this.emailBody = data.body || '';
                            this.autoAttachments = data.attachments || [];

                            this.$nextTick(() => {
                                this.initTinyMCE();
                            });
                        } catch (e) {
                            this.emitFlash('error', e.message || 'Fout bij voorbereiden email');
                            this.composing = false;
                            this.composeTarget = null;
                        } finally {
                            this.loadingCompose = false;
                        }
                    },

                    exitCompose() {
                        this.composing = false;
                        this.composeTarget = null;

                        if (window.tinymce) {
                            try {
                                const editor = window.tinymce.get('afb-email-editor');
                                if (editor) editor.remove();
                            } catch (e) { /* ok */ }
                        }
                    },

                    async deleteDispatch(row) {
                        if (!row.delete_url || this.deletingRow) return;

                        if (!confirm('Weet u zeker dat u deze AFB wilt verwijderen?')) return;

                        this.deletingRow = row.dispatch_id;
                        try {
                            const response = await fetch(row.delete_url, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.getCsrfToken(),
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok) throw new Error(data.message || 'Verwijderen mislukt');

                            this.emitFlash('success', data.message || 'AFB verwijderd.');

                            row.dispatch_id = null;
                            row.dispatch_sent_at = null;
                            row.dispatch_pdf_url = null;
                            row.delete_url = null;
                        } catch (e) {
                            this.emitFlash('error', e.message || 'Verwijderen mislukt');
                        } finally {
                            this.deletingRow = null;
                        }
                    },

                    onExtraAttachmentChange(event) {
                        const files = Array.from(event.target.files || []);
                        files.forEach(f => {
                            this.extraAttachmentFiles.push(f);
                            this.extraAttachments.push({ name: f.name });
                        });
                        event.target.value = '';
                    },

                    removeExtraAttachment(index) {
                        this.extraAttachments.splice(index, 1);
                        this.extraAttachmentFiles.splice(index, 1);
                    },

                    async sendEmail() {
                        window.tinymce?.triggerSave?.();

                        const body = this.getTinyMCEContent('afb-email-editor');
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

                        this.isSending = true;

                        const formData = new FormData();
                        formData.append('subject', this.emailSubject);
                        formData.append('reply', body);
                        formData.append('reply_to', this.emailTo);

                        if (this.composeTarget.person_id) {
                            formData.append('person_id', this.composeTarget.person_id);
                        }

                        this.extraAttachmentFiles.forEach((file, i) => {
                            formData.append(`attachments[${i}]`, file);
                        });

                        try {
                            const url = `/admin/orders/${this.orderId}/afb-send/${this.composeTarget.department_id}/send`;
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.getCsrfToken(),
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: formData,
                            });

                            const data = await response.json().catch(() => ({}));

                            if (!response.ok) {
                                throw new Error(data.message || 'Versturen mislukt');
                            }

                            this.emitFlash('success', data.message || 'AFB verstuurd');

                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } catch (e) {
                            this.emitFlash('error', e.message || 'Versturen mislukt');
                        } finally {
                            this.isSending = false;
                        }
                    },

                    initTinyMCE() {
                        this.setTinyMCEContent('afb-email-editor', this.emailBody, 30);
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
                        return this.emailBody || '';
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
