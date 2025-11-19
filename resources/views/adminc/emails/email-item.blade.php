@pushOnce('scripts')
    <script>
        window.emailItemRoutes = {
            attachmentDownload: '{{ route('admin.mail.attachment_download') }}',
            delete: '{{ route('admin.mail.delete', ':id') }}',
            move: '{{ route('admin.mail.move', ':id') }}',
            index: '{{ route('admin.mail.index', ['route' => 'FOLDER_NAME']) }}',
        };
    </script>
    <!-- Email Item Template -->
    <script
        type="text/x-template"
        id="v-email-item-template"
    >
        <div class="box-shadow flex gap-2.5 rounded bg-white p-4 dark:bg-gray-900 max-xl:flex-wrap">
            <div class="flex w-full flex-col gap-4">
                <div class="flex w-full items-center justify-between gap-4">
                    <div class="flex gap-4">
                        {!! view_render_event('admin.mail.view.avatar.before', ['email' => $email]) !!}

                        <!-- Mailer Sort name -->
                        <x-admin::avatar ::name="email.name ?? email.from" />

                        {!! view_render_event('admin.mail.view.avatar.after', ['email' => $email]) !!}

                        {!! view_render_event('admin.mail.view.mail_receivers.before', ['email' => $email]) !!}

                        <!-- Mailer receivers -->
                        <div class="flex flex-col gap-1">
                            <!-- Mailer Name -->
                            <span class="dark:text-gray-300">@{{ email.name ?? email.from }}</span>

                            <div class="flex flex-col gap-1 dark:text-gray-300">
                                <div class="flex items-center gap-1">
                                    <!-- Mail To -->
                                    <span>@lang('admin::app.mail.view.to') @{{ (email.reply_to || []).join(', ') }}</span>

                                    <!-- Show More Button -->
                                    <i
                                        v-if="email?.cc?.length || email?.bcc?.length"
                                        class="cursor-pointer text-2xl"
                                        :class="email.showMore ? 'icon-up-arrow' : 'icon-down-arrow'"
                                        @click="email.showMore = ! email.showMore"
                                    ></i>
                                </div>

                                <!-- Show more emails -->
                                <div
                                    class="flex flex-col"
                                    v-if="email.showMore"
                                >
                                    <span v-if="email?.cc">
                                        @lang('admin::app.mail.view.cc'):

                                        @{{ (email.cc || []).join(', ') }}
                                    </span>

                                    <span v-if="email.bcc">
                                        @lang('admin::app.mail.view.bcc'):

                                        @{{ (email.bcc || []).join(', ') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {!! view_render_event('admin.mail.view.mail_receivers.after', ['email' => $email]) !!}
                    </div>

                    {!! view_render_event('admin.mail.view.time_actions.before', ['email' => $email]) !!}

                    <!-- Time and Actions -->
                    <div class="flex items-center justify-center gap-2 dark:text-gray-300">
                        @{{ email.time_ago }}

                        <div class="flex select-none items-center">
                            <x-admin::dropdown position="bottom-right">
                                <x-slot:toggle>
                                    <button class="icon-more flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-2xl transition-all hover:rounded-md hover:bg-neutral-bg dark:hover:bg-gray-950"></button>
                                </x-slot>

                                <!-- Admin Dropdown -->
                                <x-slot:menu class="!min-w-40">
                                    <x-admin::dropdown.menu.item>
                                        <div
                                            class="flex cursor-pointer items-center gap-2"
                                            @click="emailAction('reply')"
                                        >
                                            <i class="icon-reply text-2xl"></i>

                                            @lang('admin::app.mail.view.reply')
                                        </div>
                                    </x-admin::dropdown.menu.item>

                                    <x-admin::dropdown.menu.item>
                                        <div
                                            class="flex cursor-pointer items-center gap-2"
                                            @click="emailAction('reply')"
                                        >
                                            <i class="icon-reply text-2xl"></i>

                                            @lang('admin::app.mail.view.reply')
                                        </div>
                                    </x-admin::dropdown.menu.item>

                                    <x-admin::dropdown.menu.item>
                                        <div
                                            class="flex cursor-pointer items-center gap-2"
                                            @click="emailAction('forward')"
                                        >
                                            <i class="icon-forward text-2xl"></i>

                                            @lang('admin::app.mail.view.forward')
                                        </div>
                                    </x-admin::dropdown.menu.item>

                                    <x-admin::dropdown.menu.item>
                                        <div
                                            class="flex cursor-pointer items-center gap-2"
                                            @click="emailAction('delete')"
                                        >
                                            <i class="icon-delete text-2xl"></i>

                                            @lang('admin::app.mail.view.delete')
                                        </div>
                                    </x-admin::dropdown.menu.item>
                                </x-slot>
                            </x-admin::dropdown>
                        </div>
                    </div>

                    {!! view_render_event('admin.mail.view.time_actions.before', ['email' => $email]) !!}
                </div>

                {!! view_render_event('admin.mail.view.mail_body.before', ['email' => $email]) !!}

                <!-- Mail Body -->
                <div
                    class="dark:text-gray-300"
                    v-safe-html="email.reply"
                ></div>

                {!! view_render_event('admin.mail.view.mail_body.after', ['email' => $email]) !!}

                {!! view_render_event('admin.mail.view.attach.before', ['email' => $email]) !!}

                <div
                    class="flex flex-wrap gap-2"
                    v-if="email.attachments.length"
                >
                    <div
                        class="group relative flex items-center gap-2 rounded-md border border-gray-300 bg-neutral-bg px-2 py-1.5 dark:border-gray-800 dark:bg-gray-900"
                        target="_blank"
                        v-for="attachment in email.attachments"
                    >
                        <!-- Thumbnail or Icon -->
                        <div class="flex items-center gap-2">
                            <template v-if="isImage(attachment.path)">
                                <span class="icon-image text-2xl"></span>
                            </template>

                            <template v-else-if="isVideo(attachment.path)">
                                <span class="icon-video text-2xl"></span>
                            </template>

                            <template v-else-if="isDocument(attachment.path)">
                                <span class="icon-file text-2xl"></span>
                            </template>

                            <template v-else>
                                <span class="icon-attachment text-2xl"></span>
                            </template>
                        </div>

                        <span class="max-w-[400px] truncate dark:text-white">
                            @{{ attachment.name || attachment.path }}
                        </span>

                        <a
                            class="icon-download absolute right-0 rounded-md bg-gradient-to-r from-transparent via-gray-50 to-gray-100 p-2 pl-8 text-xl opacity-0 transition-all group-hover:opacity-100 dark:via-gray-900 dark:to-gray-900"
                            :href="routes.attachmentDownload + '/' + attachment.id"
                        ></a>
                    </div>
                </div>

                {!! view_render_event('admin.mail.view.attach.after', ['email' => $email]) !!}

                {!! view_render_event('admin.mail.view.replay_reply_all_forward_email.before', ['email' => $email]) !!}

                <!-- Reply, Reply All and Forward email -->
                <template v-if="! action[email.id]">
                    <div class="flex gap-6 border-t-2 py-4 font-medium dark:border-gray-800">
                        <label
                            class="flex cursor-pointer items-center gap-2 text-brandColor"
                            @click="emailAction('reply')"
                        >
                            @lang('admin::app.mail.view.reply')

                            <i class="icon-reply text-2xl"></i>
                        </label>

                        <label
                            class="flex cursor-pointer items-center gap-2 text-brandColor"
                            @click="emailAction('reply-all')"
                        >
                            @lang('admin::app.mail.view.reply-all')

                            <i class="icon-reply-all text-2xl"></i>
                        </label>

                        <label
                            class="flex cursor-pointer items-center gap-2 text-brandColor"
                            @click="emailAction('forward')"
                        >
                            @lang('admin::app.mail.view.forward')

                            <i class="icon-forward text-2xl"></i>
                        </label>

                        <label
                            class="flex cursor-pointer items-center gap-2 text-brandColor"
                            @click="emailAction('move')"
                        >
                            @lang('admin::app.mail.view.move')

                            <i class="icon-folder text-2xl"></i>
                        </label>
                    </div>
                </template>

                {!! view_render_event('admin.mail.view.replay_reply_all_forward_email.after', ['email' => $email]) !!}

                <template v-else>
                    <!-- Email Form Vue Component -->
                    <v-email-form
                        :action="action"
                        :email="email"
                        @on-discard="$emit('onDiscard')"
                    ></v-email-form>
                </template>
            </div>
        </div>
    </script>

    <!-- Email Item Vue Component -->
    <script type="module">
        app.component('v-email-item', {
            template: '#v-email-item-template',

            props: ['index', 'email', 'action'],

            emits: ['on-discard', 'on-email-action'],

            data() {
                return {
                    routes: window.emailItemRoutes || {
                        attachmentDownload: '',
                        delete: '',
                        move: '',
                        index: '',
                    },
                };
            },

            methods: {
                isImage(path) {
                    return /\.(jpg|jpeg|png|gif|webp)$/i.test(path);
                },

                isVideo(path) {
                    return /\.(mp4|avi|mov|wmv|mkv)$/i.test(path);
                },

                isDocument(path) {
                    return /\.(pdf|docx?|xlsx?|pptx?)$/i.test(path);
                },

                emailAction(type) {
                    if (type == 'move') {
                        this.showMoveModal();
                    } else if (type != 'delete') {
                        this.$emit('on-email-action', {type, email: this.email});
                    } else {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                this.$axios.post(this.routes.delete.replace(':id', this.email.id), {
                                    _method: 'DELETE',
                                    type: 'trash'
                                })
                                .then ((response) => {
                                    if (response.status == 200) {
                                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                        this.$emit('on-discard');
                                    }
                                });
                            }
                        });
                    }
                },

                showMoveModal() {
                    const hierarchicalFolders = this.$parent?.hierarchicalFolders || [];
                    this.createFolderSelectModal(hierarchicalFolders);
                },

                createFolderSelectModal(folders) {
                    // Clean up any existing modal
                    this.closeExistingModal();

                    const modalHtml = this.buildModalHtml(folders);
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                    document.body.style.overflow = 'hidden';

                    this.setupModalEventHandlers();
                },

                buildModalHtml(folders) {
                    return `
                        <div id="folder-select-modal" class="fixed inset-0 z-[10003] bg-gray-500 bg-opacity-50 transition-opacity">
                            <div class="fixed inset-0 z-[10004] transform overflow-y-auto transition">
                                <div class="flex min-h-full items-center justify-center p-5">
                                    <div class="box-shadow w-full max-w-[500px] rounded-lg bg-white dark:bg-gray-900">
                                        <div class="flex items-center justify-between gap-2.5 border-b px-4 py-3 text-lg font-bold text-gray-800 dark:border-gray-800 dark:text-white">
                                            Verplaats e-mail
                                            <span class="icon-cross-large cursor-pointer text-3xl hover:rounded-md hover:bg-neutral-bg dark:hover:bg-gray-950" onclick="closeFolderModal()"></span>
                                        </div>

                                        <div class="px-4 py-4">
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                    Selecteer folder
                                                </label>
                                                <select id="folder-select" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white">
                                                    <option value="">..</option>
                                                    ${this.buildFolderOptions(folders)}
                                                </select>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                                Selecteer de folder om naar toe te verplaatsen
                                            </div>

                                            <div class="flex justify-end gap-2.5">
                                                <button type="button" class="transparent-button" onclick="closeFolderModal()">
                                                    Annuleren
                                                </button>
                                                <button type="button" class="primary-button" onclick="moveEmailToFolder()">
                                                    Verplaats e-mail
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                },

                setupModalEventHandlers() {
                    const self = this;

                    window.closeFolderModal = () => {
                        const modal = document.getElementById('folder-select-modal');
                        if (modal) {
                            modal.remove();
                            document.body.style.overflow = 'auto';
                        }
                    };

                    window.moveEmailToFolder = () => {
                        self.moveEmailToFolder();
                    };
                },

                closeExistingModal() {
                    const existingModal = document.getElementById('folder-select-modal');
                    if (existingModal) {
                        existingModal.remove();
                    }
                },

                buildFolderOptions(folders, level = 0) {
                    if (!folders || !Array.isArray(folders)) {
                        return '';
                    }

                    const indent = '&nbsp;'.repeat(level * 4);

                    return folders.map(folder => {
                        let options = `<option value="${folder.id}">${indent}${folder.name}</option>`;

                        if (folder.children && Array.isArray(folder.children) && folder.children.length > 0) {
                            options += this.buildFolderOptions(folder.children, level + 1);
                        }

                        return options;
                    }).join('');
                },

                moveEmailToFolder() {
                    const folderSelect = document.getElementById('folder-select');

                    if (!folderSelect) {
                        this.$emitter.emit('add-flash', { type: 'error', message: 'Folder selection not found.' });
                        return;
                    }

                    const folderId = folderSelect.value;

                    if (!folderId) {
                        this.$emitter.emit('add-flash', { type: 'error', message: 'Please select a folder.' });
                        return;
                    }

                    // Show loading state
                    this.$emitter.emit('add-flash', { type: 'info', message: 'Moving email...' });

                    this.$axios.post(this.routes.move.replace(':id', this.email.id), {
                        folder_id: folderId
                    })
                    .then((response) => {
                        if (response.status === 200) {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            // Close the modal
                            window.closeFolderModal();

                            // Redirect to the new folder after a short delay
                            setTimeout(() => {
                                const folderName = response.data.data?.folder_name?.toLowerCase();
                                if (folderName) {
                                    window.location.href = this.routes.index.replace('FOLDER_NAME', folderName);
                                }
                            }, 1000);
                        }
                    })
                    .catch((error) => {
                        const errorMessage = error.response?.data?.message ||
                                           error.response?.data?.error ||
                                           'Verplaatsen van e-mail is niet gelukt.';
                        this.$emitter.emit('add-flash', { type: 'error', message: errorMessage });
                    });
                },
            },
        });
    </script>
@endPushOnce

