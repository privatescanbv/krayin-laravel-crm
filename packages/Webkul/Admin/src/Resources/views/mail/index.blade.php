<x-admin::layouts>
    <x-slot:title>
        @if(isset($folder))
            {{ $folder->name ?? 'Unknown' }}
        @else
            @lang('admin::app.mail.index.' . request('route'))
        @endif
    </x-slot>

    <!-- Gmail-like Email Interface -->
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Sidebar -->
        <div class="w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col">
            <!-- Folders List -->
            <div class="flex-1 overflow-y-auto">
                <div class="p-2">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 px-3">
                        Folders
                    </h3>

                    @php
                        $currentRoute = request('route') ?? 'inbox';
                    @endphp

                        <!-- Hierarchical Folders -->
                    <div class="space-y-1">
                        @if(isset($hierarchicalFolders) && is_array($hierarchicalFolders))
                            @foreach ($hierarchicalFolders as $folder)
                                @if(isset($folder['name']))
                                    <!-- Parent Folder -->
                                    <a href="{{ route('admin.mail.index', ['route' => $folder['name']]) }}"
                                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                                              {{ $currentRoute === $folder['name'] ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                        <i class="icon-folder text-lg"></i>
                                        {{ $folder['name'] }}
                                        <span class="ml-auto text-xs bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 px-2 py-1 rounded-full">
                                            {{ $folder['count'] ?? 0 }}
                                        </span>
                                    </a>

                                    <!-- Child Folders -->
                                    @if(isset($folder['children']) && is_array($folder['children']) && count($folder['children']) > 0)
                                        <div class="ml-8 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-4">
                                            @foreach ($folder['children'] as $child)
                                                @if(isset($child['name']))
                                                    <a href="{{ route('admin.mail.index', ['route' => $child['name']]) }}"
                                                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                                                              {{ $currentRoute === $child['name'] ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-600 hover:bg-neutral-bg dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                                        <i class="icon-folder text-sm opacity-75"></i>
                                                        <span class="text-sm">{{ $child['name'] }}</span>
                                                        <span class="ml-auto text-xs bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 px-2 py-1 rounded-full">
                                                            {{ $child['count'] ?? 0 }}
                                                        </span>
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            @endforeach
                        @else
                            <!-- Fallback: Show basic folders if hierarchical data is not available -->
                            <div class="text-sm text-gray-500 dark:text-gray-400 px-3 py-2">
                                No folders available
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>



    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                {!! view_render_event('admin.mail.create.breadcrumbs.before') !!}

                <!-- breadcrumbs -->
                <x-admin::breadcrumbs
                    name="mail.route"
                    :entity="request('route')"
                />

                {!! view_render_event('admin.mail.create.breadcrumbs.after') !!}

                <div class="text-xl font-bold dark:text-white">
                    {{ request('route') }}
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <div class="flex items-center gap-x-2.5">
                    {!! view_render_event('admin.mail.create.compose_mail_btn.before') !!}

                    <!-- Create button for person -->
                    @if (bouncer()->hasPermission('mail.compose'))
                        <button
                            type="button"
                            class="primary-button"
                            @click="$refs.composeMail.toggleModal()"
                        >
                            @lang('admin::app.mail.index.compose-mail-btn')
                        </button>
                    @endif

                    {!! view_render_event('admin.mail.create.compose_mail_btn.after') !!}
                </div>
            </div>
        </div>

        <!-- Compose Mail Vue Component -->
        <v-mail ref="composeMail">
            <!-- Datagrid Shimmer -->
            <x-admin::shimmer.mail.datagrid :is-multi-row="true"/>
        </v-mail>
    </div>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-mail-template"
        >
            {!! view_render_event('admin.mail.'.request('route').'.datagrid.before') !!}

            <!-- DataGrid -->
            <x-admin::datagrid
                ref="datagrid"
                :src="route('admin.mail.index', request('route'))"
            >
                <template #header="{
                    isLoading,
                    available,
                    applied,
                    selectAll,
                    sort,
                    performAction
                }">
                    <template v-if="isLoading">
                        <x-admin::shimmer.mail.datagrid.table.head />
                    </template>

                    <template v-else>
                        <div class="row grid grid-cols-[2fr_2fr_6fr_.8fr] grid-rows-1 items-center border-b px-8 py-4 dark:border-gray-800 max-lg:hidden">
                            <div
                                class="flex items-center gap-6"
                                v-for="(columnGroup, index) in [['name'], ['entity_type'], ['subject', 'reply'], ['created_at']]"
                            >
                                <label
                                    class="flex w-max cursor-pointer select-none items-center gap-2"
                                    for="mass_action_select_all_records"
                                    v-if="! index"
                                >
                                    <input
                                        type="checkbox"
                                        name="mass_action_select_all_records"
                                        id="mass_action_select_all_records"
                                        class="peer hidden"
                                        :checked="['all', 'partial'].includes(applied.massActions.meta.mode)"
                                        @change="selectAll"
                                    >

                                    <span
                                        class="icon-checkbox-outline cursor-pointer rounded-md text-2xl text-gray-600 dark:text-gray-300"
                                        :class="[
                                            applied.massActions.meta.mode === 'all' ? 'peer-checked:icon-checkbox-select peer-checked:text-brandColor' : (
                                                applied.massActions.meta.mode === 'partial' ? 'peer-checked:icon-checkbox-multiple peer-checked:text-brandColor' : ''
                                            ),
                                        ]"
                                    >
                                    </span>
                                </label>

                                <p class="text-gray-600 dark:text-gray-300">
                                    <span class="[&>*]:after:content-['_/_']">
                                        <template v-for="column in columnGroup">
                                            <span
                                                class="after:content-['/'] last:after:content-['']"
                                                :class="{
                                                    'font-medium text-gray-800 dark:text-white': applied.sort.column == column,
                                                    'cursor-pointer hover:text-gray-800 dark:hover:text-white': available.columns.find(columnTemp => columnTemp.index === column)?.sortable,
                                                }"
                                                @click="
                                                    available.columns.find(columnTemp => columnTemp.index === column)?.sortable ? sort(available.columns.find(columnTemp => columnTemp.index === column)): {}
                                                "
                                                v-html="available.columns.find(columnTemp => columnTemp.index === column)?.label"
                                            >
                                            </span>
                                        </template>
                                    </span>

                                    <i
                                        class="align-text-bottom text-base text-gray-800 dark:text-white ltr:ml-1.5 rtl:mr-1.5"
                                        :class="[applied.sort.order === 'asc' ? 'icon-down-stat': 'icon-up-stat']"
                                        v-if="columnGroup.includes(applied.sort.column)"
                                    ></i>
                                </p>
                            </div>
                        </div>

                        <!-- Mobile Sort/Filter Header -->
                        <div class="hidden border-b bg-gray-50 px-4 py-3 text-black dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 max-lg:block">
                            <div class="flex items-center justify-between">
                                <!-- Mass Actions for Mobile -->
                                <div v-if="available.massActions.length">
                                    <label for="mass_action_select_all_records_mobile">
                                        <input
                                            type="checkbox"
                                            name="mass_action_select_all_records_mobile"
                                            id="mass_action_select_all_records_mobile"
                                            class="peer hidden"
                                            :checked="['all', 'partial'].includes(applied.massActions.meta.mode)"
                                            @change="selectAll"
                                        >

                                        <span
                                            class="icon-checkbox-outline cursor-pointer rounded-md text-2xl text-gray-500 peer-checked:text-brandColor"
                                            :class="[
                                                applied.massActions.meta.mode === 'all' ? 'peer-checked:icon-checkbox-select peer-checked:text-brandColor ' : (
                                                    applied.massActions.meta.mode === 'partial' ? 'peer-checked:icon-checkbox-multiple peer-checked:brandColor' : ''
                                                ),
                                            ]"
                                        >
                                        </span>
                                    </label>
                                </div>

                                <!-- Mobile Sort Dropdown -->
                                <div v-if="available.columns.some(column => column.sortable)">
                                    <x-admin::dropdown position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'left' : 'right' }}">
                                        <x-slot:toggle>
                                            <div class="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    class="inline-flex w-full max-w-max cursor-pointer appearance-none items-center justify-between gap-x-2 rounded-md border bg-white px-2.5 py-1.5 text-center leading-6 text-gray-600 transition-all marker:shadow hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                                >
                                                    <span>
                                                        Sort
                                                    </span>

                                                    <span class="icon-down-arrow text-2xl"></span>
                                                </button>
                                            </div>
                                        </x-slot>

                                        <x-slot:menu>
                                            <x-admin::dropdown.menu.item
                                                v-for="column in available.columns.filter(column => column.sortable && column.visibility)"
                                                @click="sort(column)"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <span v-html="column.label"></span>
                                                    <i
                                                        class="align-text-bottom text-base text-gray-600 dark:text-gray-300"
                                                        :class="[applied.sort.order === 'asc' ? 'icon-stats-down': 'icon-stats-up']"
                                                        v-if="column.index == applied.sort.column"
                                                    ></i>
                                                </div>
                                            </x-admin::dropdown.menu.item>
                                        </x-slot>
                                    </x-admin::dropdown>
                                </div>
                            </div>
                        </div>
                    </template>
                </template>

                <template #body="{
                    isLoading,
                    available,
                    applied,
                    selectAll,
                    sort,
                    performAction
                }">

                    <template v-if="isLoading">
                        <x-admin::shimmer.mail.datagrid.table.body />
                    </template>

                    <template v-else>
                        <!-- Desktop Table View -->
                        <div
                            v-for="record in available.records"
                            class="grid grid-cols-[2fr_2fr_6fr_.8fr] items-center border-b px-8 py-4 text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-950 max-lg:hidden cursor-pointer"
                            @click.stop="selectedMail=true; editModal(record.actions.find(action => action.index === 'edit'))"
                        >
                            <!-- Col 1: Name + checkbox + unread dot -->
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="relative flex items-center">
                                    <span class="absolute -right-2 h-1.5 w-1.5 rounded-full bg-sky-600 dark:bg-white" v-if="record && (record.is_read === 0 || record.is_read === false || record.is_read === '0')"></span>
                                    <input
                                        type="checkbox"
                                        :name="`mass_action_select_record_${record.id}`"
                                        :id="`mass_action_select_record_${record.id}`"
                                        :value="record.id"
                                        class="peer hidden"
                                        v-model="applied.massActions.indices"
                                        @click.stop
                                    >
                                    <label
                                        class="icon-checkbox-outline peer-checked:icon-checkbox-select cursor-pointer rounded-md text-2xl !text-gray-500 peer-checked:!text-brandColor dark:!text-gray-300"
                                        :for="`mass_action_select_record_${record.id}`"
                                        @click.stop
                                    ></label>
                                </div>
                                <x-admin::avatar ::name="record.name ?? record.from" />
                                <span class="truncate">@{{ record.name }}</span>
                            </div>

                            <!-- Col 2: Related entity -->
                            <div class="text-sm text-gray-800 dark:text-gray-300">
                                <span v-html="record.entity_type"></span>
                            </div>

                            <!-- Col 3: Subject / preview with tags/attachments -->
                            <div class="min-w-0" :class="{ 'pl-16': record.parent_id }">
                                <div class="flex items-center gap-2">
                                    <p class="line-clamp-1 text-sm text-gray-900 dark:text-gray-100" v-text="record.subject"></p>
                                    <p v-html="record.attachments"></p>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <span
                                        class="flex items-center gap-1 rounded-2xl bg-rose-100 px-2 py-1"
                                        :style="{
                                            'background-color': tag.color,
                                            'color': backgroundColors.find(color => color.background === tag.color)?.text
                                        }"
                                        v-for="(tag, index) in record.tags"
                                        v-html="tag.name"
                                    ></span>
                                </div>
                                <p class="!font-normal" v-html="truncatedReply(record.reply)"></p>
                            </div>

                            <!-- Col 4: Date -->
                            <div class="min-w-[80px] text-right">
                                <p class="leading-none">@{{ record.created_at }}</p>
                            </div>
                        </div>

                        <!-- Mobile Card View -->
                        <div
                            class="hidden border-b px-4 py-4 text-black dark:border-gray-800 dark:text-gray-300 max-lg:block"
                            v-for="record in available.records"
                            @click.stop="selectedMail=true; editModal(record.actions.find(action => action.index === 'edit'))"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <!-- Mass Actions for Mobile Cards -->
                                <div class="flex w-full items-center justify-between gap-2">
                                    <p v-if="available.massActions.length">
                                        <input
                                            type="checkbox"
                                            :name="`mass_action_select_record_${record.id}`"
                                            :id="`mass_action_select_record_${record.id}`"
                                            :value="record.id"
                                            class="peer hidden"
                                            v-model="applied.massActions.indices"
                                            @click.stop
                                        >

                                        <label
                                            class="icon-checkbox-outline peer-checked:icon-checkbox-select cursor-pointer rounded-md text-2xl !text-gray-500 peer-checked:!text-brandColor dark:!text-gray-300"
                                            :for="`mass_action_select_record_${record.id}`"
                                            @click.stop
                                        ></label>
                                    </p>

                                    <!-- Dot Indicator -->
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-sky-600 dark:bg-white"
                                        v-if="record && (record.is_read === 0 || record.is_read === false || record.is_read === '0')"
                                    ></span>
                                </div>
                            </div>

                            <!-- Card Content -->
                            <div class="grid gap-2">
                                <template v-for="column in available.columns">
                                    <div class="flex flex-wrap items-baseline gap-x-2">
                                        <span
                                            :class="{'font-semibold': ! record.is_read}"
                                            class="text-slate-600 dark:text-gray-300"
                                            v-html="column.label + ':'"
                                        ></span>
                                        <span
                                            :class="{
                                                'font-medium': record.is_read,
                                                'font-semibold': ! record.is_read
                                            }"
                                            class="break-words text-slate-900 dark:text-white"
                                            v-html="record[column.index]"
                                        ></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </template>
            </x-admin::datagrid>

            {!! view_render_event('admin.mail.'.request('route').'.datagrid.after') !!}

            {!! view_render_event('admin.mail.create.form.before') !!}

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                enctype="multipart/form-data"
                as="div"
            >
                <form
                    @submit="handleSubmit($event, save)"
                    ref="mailForm"
                >
                    <x-admin::modal
                        ref="toggleComposeModal"
                        position="bottom-right"
                        size="large"
                        @toggle="removeTinyMCE"
                    >
                        <x-slot:header>
                            <div class="flex items-center justify-between gap-2.5 w-full">
                                <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                                    @lang('admin::app.mail.index.mail.title')
                                </h3>
                                <button
                                    type="button"
                                    class="flex items-center justify-center w-8 h-8 cursor-pointer text-gray-600 hover:rounded-md hover:bg-neutral-bg dark:text-gray-300 dark:hover:bg-gray-950 transition-colors"
                                    @click="toggleFullscreen"
                                    :title="isFullscreen ? 'Verkleinen' : 'Volledig scherm'"
                                >
                                    <span v-if="isFullscreen" class="text-xl">⊟</span>
                                    <span v-else class="text-xl">⊞</span>
                                </button>
                            </div>
                        </x-slot>

                        <x-slot:content>
                            <x-admin::form.control-group.control
                                type="hidden"
                                name="id"
                                id="id"
                                v-model="draft.id"
                            />

                            <!-- To -->
                            <x-admin::form.control-group>
                                <div class="relative">
                                    <x-admin::form.control-group.controls.tags
                                        name="reply_to"
                                        class="w-[calc(100%-62px)]"
                                        input-rules="email"
                                        rules="required"
                                        ::data="draft.reply_to"
                                        :label="trans('admin::app.mail.index.mail.to')"
                                        :placeholder="trans('admin::app.mail.index.mail.enter-emails')"
                                        ::allow-duplicates="false"
                                    />

                                    <div class="absolute top-[9px] flex items-center gap-2 ltr:right-2 rtl:left-2">
                                        <span
                                            class="cursor-pointer font-medium hover:underline dark:text-white"
                                            @click="showCC = ! showCC"
                                        >
                                            @lang('admin::app.mail.index.mail.cc')
                                        </span>

                                        <span
                                            class="cursor-pointer font-medium hover:underline dark:text-white"
                                            @click="showBCC = ! showBCC"
                                        >
                                            @lang('admin::app.mail.index.mail.bcc')
                                        </span>
                                    </div>
                                </div>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.mail.index.mail.to')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.error control-name="reply_to" />

                            </x-admin::form.control-group>

                            <template v-if="showCC">
                                <!-- Cc -->
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.controls.tags
                                        name="cc"
                                        class="w-[calc(100%-62px)]"
                                        input-rules="email"
                                        ::data="draft.cc"
                                        :label="trans('admin::app.mail.index.mail.cc')"
                                        :placeholder="trans('admin::app.mail.index.mail.enter-emails')"
                                    />
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.mail.index.mail.cc')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.error control-name="cc" />

                                </x-admin::form.control-group>
                            </template>

                            <template v-if="showBCC">
                                <!-- Cc -->
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.controls.tags
                                        name="bcc"
                                        class="w-[calc(100%-62px)]"
                                        input-rules="email"
                                        ::data="draft.bcc"
                                        :label="trans('admin::app.mail.index.mail.bcc')"
                                        :placeholder="trans('admin::app.mail.index.mail.enter-emails')"
                                    />
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.mail.index.mail.bcc')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.error control-name="bcc" />

                                </x-admin::form.control-group>
                            </template>

                            <!-- Template Selector -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="select"
                                    id="email_template"
                                    name="email_template"
                                    v-model="selectedTemplate"
                                    @change="loadTemplate"
                                    :label="trans('admin::app.mail.index.mail.template')"
                                >
                                    <option value="">Geen template</option>
                                    <option
                                        v-for="template in emailTemplates"
                                        :key="template.name"
                                        :value="template.name"
                                    >
                                        @{{ template.label }}
                                    </option>
                                </x-admin::form.control-group.control>
                                <x-admin::form.control-group.error control-name="email_template" />

                            </x-admin::form.control-group>

                            <!-- Subject -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="text"
                                    id="subject"
                                    name="subject"
                                    rules="required"
                                    v-model="draft.subject"
                                    :label="trans('admin::app.mail.index.mail.subject')"
                                    :placeholder="trans('admin::app.mail.index.mail.subject')"
                                />
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.mail.index.mail.subject')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.label>
                                    Template
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.error control-name="subject" />

                            </x-admin::form.control-group>

                            <!-- Content -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="reply"
                                    id="reply"
                                    rules="required"
                                    rows="12"
                                    ::value="draft.reply"
                                    :tinymce="true"
                                    :label="trans('admin::app.mail.index.mail.message')"
                                />
                                <x-admin::form.control-group.error control-name="reply" />

                            </x-admin::form.control-group>

                            <!-- Attachments -->
                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::attachments
                                    allow-multiple="true"
                                    hide-button="true"
                                />
                            </x-admin::form.control-group>
                        </x-slot>

                        <x-slot:footer>
                            <div class="flex w-full items-center justify-between">
                                <label
                                    class="icon-attachment cursor-pointer rounded-md p-1 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                    for="file-upload"
                                ></label>

                                <div class="flex items-center gap-4">
                                    <button
                                        type="submit"
                                        ref="submitBtn"
                                        class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                                        :disabled="isStoring"
                                        @click="saveAsDraft = 1"
                                    >
                                        @lang('admin::app.mail.index.mail.draft')
                                    </button>

                                    <x-admin::button
                                        class="primary-button"
                                        type="submit"
                                        ref="submitBtn"
                                        :title="trans('admin::app.mail.index.mail.send-btn')"
                                        ::loading="isStoring"
                                        ::disabled="isStoring"
                                        @click="saveAsDraft = 0"
                                    />
                                </div>
                            </div>
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>

            {!! view_render_event('admin.mail.create.form.after') !!}
        </script>

        <script type="module">
            app.component('v-mail', {
                template: '#v-mail-template',

                data() {
                    return {
                        selectedMail: false,

                        showCC: false,

                        showBCC: false,

                        isStoring: false,

                        saveAsDraft: 0,

                        selectedTemplate: '',

                        emailTemplates: [],

                        draft: {
                            id: null,
                            reply_to: [],
                            cc: [],
                            bcc: [],
                            subject: '',
                            reply: '',
                            attachments: [],
                        },

                        backgroundColors: [
                            {
                                label: "@lang('admin::app.components.tags.index.aquarelle-red')",
                                text: '#DC2626',
                                background: '#FEE2E2',
                            }, {
                                label: "@lang('admin::app.components.tags.index.crushed-cashew')",
                                text: '#EA580C',
                                background: '#FFEDD5',
                            }, {
                                label: "@lang('admin::app.components.tags.index.beeswax')",
                                text: '#D97706',
                                background: '#FEF3C7',
                            }, {
                                label: "@lang('admin::app.components.tags.index.lemon-chiffon')",
                                text: '#CA8A04',
                                background: '#FEF9C3',
                            }, {
                                label: "@lang('admin::app.components.tags.index.snow-flurry')",
                                text: '#65A30D',
                                background: '#ECFCCB',
                            }, {
                                label: "@lang('admin::app.components.tags.index.honeydew')",
                                text: '#16A34A',
                                background: '#DCFCE7',
                            },
                        ],
                    };
                },

                mounted() {
                    console.log('mount ');
                    const params = new URLSearchParams(window.location.search);

                    if (params.get('openModal')) {
                        this.$refs.toggleComposeModal.toggle();
                    }

                    this.loadTemplates();
                },

                methods: {
                    loadTemplates() {
                        this.$axios.get('{{ route('admin.mail.templates') }}')
                            .then(response => {
                                this.emailTemplates = response.data.data || [];
                            })
                            .catch(error => {
                                console.error('Error loading templates:', error);
                            });
                    },

                    loadTemplate() {
                        if (!this.selectedTemplate) {
                            return;
                        }

                        // For mail inbox compose, we don't have entity context
                        // Templates will be rendered without entity variables
                        this.$axios.get('{{ route('admin.mail.template_content') }}', {
                                params: {
                                    template: this.selectedTemplate,
                                }
                            })
                            .then(response => {
                                const templateContent = response.data.data.content || '';
                                const signature = @json(auth()->guard('user')->user()->signature ?? '');

                                // Combine template content with signature
                                const fullContent = templateContent + (signature ? '<br><br>' + signature : '');

                                // Set content in TinyMCE or textarea
                                this.$nextTick(() => {
                                    setTimeout(() => {
                                        if (window.tinymce && window.tinymce.get('reply')) {
                                            const editor = window.tinymce.get('reply');
                                            editor.setContent(fullContent);
                                        } else {
                                            this.draft.reply = fullContent;
                                        }
                                    }, 300);
                                });
                            })
                            .catch(error => {
                                console.error('Error loading template content:', error);
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: 'Fout bij het laden van template'
                                });
                            });
                    },

                    removeTinyMCE() {
                        tinymce?.remove?.();
                    },

                    truncatedReply(reply) {
                        const maxLength = 100;

                        if (reply.length > maxLength) {
                            return `${reply.substring(0, maxLength)}...`;
                        }

                        return reply;
                    },

                    toggleModal() {
                        this.draft.reply_to = [];
                        this.draft.subject = '';
                        this.draft.reply = '';
                        this.selectedTemplate = '';

                        // Add user signature to the email body by default
                        @if(auth()->guard('user')->user() && auth()->guard('user')->user()->signature)
                            this.draft.reply = @json(auth()->guard('user')->user()->signature);
                        @endif

                        this.$refs.toggleComposeModal.toggle();

                        // Wait for TinyMCE to initialize, then set the signature
                        this.$nextTick(() => {
                            setTimeout(() => {
                                if (window.tinymce && window.tinymce.get('reply')) {
                                    const editor = window.tinymce.get('reply');
                                    @if(auth()->guard('user')->user() && auth()->guard('user')->user()->signature)
                                        if (!editor.getContent() || editor.getContent().trim() === '') {
                                            editor.setContent(@json(auth()->guard('user')->user()->signature));
                                        }
                                    @endif
                                }
                            }, 500);
                        });
                    },

                    save(params, { resetForm, setErrors  }) {
                        this.isStoring = true;

                        let formData = new FormData(this.$refs.mailForm);

                        formData.append('is_draft', this.saveAsDraft);

                        if (this.draft.id) {
                            formData.append('_method', 'PUT');
                        }

                        this.$axios.post(this.draft.id ? "{{ route('admin.mail.update', ':id') }}".replace(':id', this.draft.id) : '{{ route('admin.mail.store') }}', formData, {
                                headers: {
                                    'Content-Type': 'multipart/form-data',
                                },
                            })
                            .then ((response) => {
                                this.$refs.datagrid.get();

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data?.message });

                                resetForm();
                            })
                            .catch ((error) => {
                                if (error?.response?.status == 422) {
                                    setErrors(error.response.data.errors);
                                } else {
                                    this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                                }
                            }).finally(() => {
                                this.$refs.toggleComposeModal.close();

                                this.isStoring = false;

                                this.resetForm();
                            });
                    },

                    editModal(row) {
                        // Als het geen draft is, ga naar de view-pagina
                        if (!row.is_draft) {
                            window.location.href = row.url;
                            return;
                        }

                        // Alleen voor drafts: open compose-modal
                        this.$axios.get(row.url)
                            .then(response => {
                                this.draft = response.data;
                                this.$refs.toggleComposeModal.toggle();
                                this.showCC = this.draft.cc.length > 0;
                                this.showBCC = this.draft.bcc.length > 0;
                            })
                            .catch(error => {});
                    },

                    resetForm() {
                        this.draft = {
                            id: null,
                            reply_to: [],
                            cc: [],
                            bcc: [],
                            subject: '',
                            reply: '',
                            attachments: [],
                        };
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
