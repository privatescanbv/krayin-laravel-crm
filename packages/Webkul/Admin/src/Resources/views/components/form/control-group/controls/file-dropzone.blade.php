<v-file-dropzone
    {{ $attributes }}
    v-bind="$attrs"
></v-file-dropzone>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-file-dropzone-template"
    >
        <div
            class="relative flex w-full cursor-pointer items-center gap-2 rounded border border-dashed border-gray-300 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-500"
            :class="[isDragging ? '!border-brandColor bg-brandColor/5 dark:bg-brandColor/10' : '']"
            @click="browse"
            @dragenter.prevent.stop="onDragEnter"
            @dragover.prevent.stop="onDragEnter"
            @dragleave.prevent.stop="onDragLeave"
            @drop.prevent.stop="onDrop"
        >
            <i class="icon-attachment text-lg font-medium"></i>

            <span
                class="truncate"
                v-if="fileNames.length"
            >
                @{{ fileNames.join(', ') }}
            </span>

            <span
                class="truncate text-gray-500 dark:text-gray-400"
                v-else
            >
                @{{ placeholder }}
            </span>

            <input
                type="file"
                class="hidden"
                ref="input"
                v-bind="$attrs"
                @change="onChange"
                @blur="$emit('blur', $event)"
            />
        </div>
    </script>

    <script type="module">
        app.component('v-file-dropzone', {
            template: '#v-file-dropzone-template',

            inheritAttrs: false,

            emits: ['change', 'blur'],

            data() {
                return {
                    isDragging: false,

                    dragCounter: 0,

                    fileNames: [],
                };
            },

            computed: {
                isMultiple() {
                    return this.$attrs.multiple !== undefined && this.$attrs.multiple !== false;
                },

                placeholder() {
                    return this.isMultiple
                        ? "@lang('admin::app.components.form.control-group.controls.file-dropzone.placeholder-multiple')"
                        : "@lang('admin::app.components.form.control-group.controls.file-dropzone.placeholder')";
                },
            },

            methods: {
                browse() {
                    if (this.$refs.input.disabled) {
                        return;
                    }

                    this.$refs.input.click();
                },

                onChange(event) {
                    this.fileNames = event.target.files
                        ? Array.from(event.target.files).map((file) => file.name)
                        : [];

                    this.$emit('change', event);
                },

                onDragEnter() {
                    if (this.$refs.input.disabled) {
                        return;
                    }

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

                    const input = this.$refs.input;

                    if (input.disabled) {
                        return;
                    }

                    const droppedFiles = event.dataTransfer?.files;

                    if (! droppedFiles?.length) {
                        return;
                    }

                    const dataTransfer = new DataTransfer();

                    const filesToAssign = input.multiple
                        ? Array.from(droppedFiles)
                        : [droppedFiles[0]];

                    filesToAssign.forEach((file) => dataTransfer.items.add(file));

                    input.files = dataTransfer.files;

                    input.dispatchEvent(new Event('change', { bubbles: true }));
                },
            },
        });
    </script>
@endPushOnce
