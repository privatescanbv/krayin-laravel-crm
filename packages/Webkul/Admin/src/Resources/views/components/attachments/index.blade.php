@props([
    'name'                => 'attachments',
    'validations'         => null,
    'uploadedAttachments' => [],
    'allowMultiple'       => false,
    'hideButton'          => false,
])

<v-attachments
    name="{{ $name }}"
    validations="{{ $validations }}"
    :uploaded-attachments='{{ json_encode($uploadedAttachments) }}'
    :allow-multiple="{{ $allowMultiple }}"
    :hide-button="{{ $hideButton }}"
>
</v-attachments>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-attachments-template"
    >
        <!-- File Attachment Input -->
        <div
            class="relative items-center rounded-md transition-all"
            :class="[isDragging ? 'outline-dashed outline-2 outline-offset-4 outline-brandColor' : '']"
            v-show="! hideButton"
            @dragenter.prevent.stop="onDragEnter"
            @dragover.prevent.stop="onDragEnter"
            @dragleave.prevent.stop="onDragLeave"
            @drop.prevent.stop="onDrop"
        >
            <input
                type="file"
                class="hidden"
                id="file-upload"
                accept="attachment/*"
                :multiple="allowMultiple"
                :ref="$.uid + '_attachmentInput'"
                @change="add"
            />

            <label
                class="flex cursor-pointer items-center gap-1"
                for="file-upload"
            >
                <i class="icon-attachment text-xl font-medium"></i>

                <span class="font-semibold">
                    @lang('Add Attachments')
                </span>
            </label>
        </div>

        <!-- Uploaded attachments -->
        <div
            v-if="attachments?.length"
            class="flex flex-wrap gap-2"
        >
            <template v-for="(attachment, index) in attachments">
                <v-attachment-item
                    :name="name"
                    :index="index"
                    :attachment="attachment"
                    @onRemove="remove($event)"
                >
                </v-attachment-item>
            </template>
        </div>
    </script>

    <script type="text/x-template" id="v-attachment-item-template">
        <div class="flex items-center gap-2 rounded-md bg-neutral-bg px-2.5 py-1 dark:bg-gray-950">
            <span class="max-w-xs truncate dark:text-white">
                @{{ attachment.name }}
            </span>

            <x-admin::form.control-group.control
                type="file"
                ::name="name + '[]'"
                class="hidden" 
                ::ref="$.uid + '_attachmentInput_' + index"
            />

            <i 
                class="icon-cross-large cursor-pointer rounded-md p-0.5 text-xl hover:bg-gray-200 dark:hover:bg-gray-800"
                @click="remove"
            ></i>
        </div>
    </script>

    <script type="module">
        app.component('v-attachments', {
            template: '#v-attachments-template',

            props: {
                name: {
                    type: String, 
                    default: 'attachments',
                },

                validations: {
                    type: String,
                    default: '',
                },

                uploadedAttachments: {
                    type: Array,
                    default: () => []
                },

                allowMultiple: {
                    type: Boolean,
                    default: false,
                },

                hideButton: {
                    type: Boolean,
                    default: false,
                },

                errors: {
                    type: Object,
                    default: () => {}
                }
            },

            data() {
                return {
                    attachments: [],

                    isDragging: false,

                    dragCounter: 0,
                }
            },

            mounted() {
                this.attachments = this.uploadedAttachments;
            },

            methods: {
                add() {
                    let attachmentInput = this.$refs[this.$.uid + '_attachmentInput'];

                    if (attachmentInput.files == undefined) {
                        return;
                    }

                    this.addFiles(attachmentInput.files);
                },

                addFiles(files) {
                    Array.from(files).forEach((file) => {
                        this.attachments.push({
                            id: 'attachment_' + this.attachments.length,
                            name: file.name,
                            file: file
                        });
                    });
                },

                onDragEnter() {
                    this.dragCounter++;

                    this.isDragging = true;
                },

                onDragLeave() {
                    this.dragCounter--;

                    if (this.dragCounter <= 0) {
                        this.dragCounter = 0;

                        this.isDragging = false;
                    }
                },

                onDrop(event) {
                    this.dragCounter = 0;

                    this.isDragging = false;

                    const droppedFiles = event.dataTransfer?.files;

                    if (! droppedFiles?.length) {
                        return;
                    }

                    this.addFiles(this.allowMultiple ? droppedFiles : [droppedFiles[0]]);
                },

                remove(attachment) {
                    let index = this.attachments.indexOf(attachment);

                    this.attachments.splice(index, 1);
                },
            }
        });

        app.component('v-attachment-item', {
            template: '#v-attachment-item-template',

            props: ['index', 'attachment', 'name'],

            mounted() {
                if (this.attachment.file instanceof File) {
                    this.setFile(this.attachment.file);
                }
            },

            methods: {
                remove() {
                    this.$emit('onRemove', this.attachment)
                },

                setFile(file) {
                    const dataTransfer = new DataTransfer();

                    dataTransfer.items.add(file);

                    this.$refs[this.$.uid + '_attachmentInput_' + this.index].files = dataTransfer.files;
                },
            }
        });
    </script>
@endPushOnce