@props([
    'endpoint',
    'emailDetachEndpoint' => null,
    'activeType'          => 'all',
    'types'               => null,
    'extraTypes'          => null,
])

{!! view_render_event('admin.components.activities.before') !!}

<!-- Lead Activities Vue Component -->
<v-activities
    endpoint="{{ $endpoint }}"
    email-detach-endpoint="{{ $emailDetachEndpoint }}"
    active-type="{{ $activeType }}"
    @if($types):types='@json($types)'@endif
    @if($extraTypes):extra-types='@json($extraTypes)'@endif
    ref="activities"
>
    <!-- Shimmer -->
    <x-admin::shimmer.activities />

    @foreach ($extraTypes ?? [] as $type)
        <template v-slot:{{ $type['name'] }}>
            {{ ${$type['name']} ?? '' }}
        </template>
    @endforeach
</v-activities>

{!! view_render_event('admin.components.activities.after') !!}

@pushOnce('scripts')
    <script type="text/x-template" id="v-activities-template">
        <template v-if="isLoading">
            <!-- Shimmer -->
            <x-admin::shimmer.activities />
        </template>

        <template v-else>
            {!! view_render_event('admin.components.activities.content.before') !!}

            <div class="w-full rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex gap-2 overflow-x-auto border-b border-gray-200 dark:border-gray-800">
                    {!! view_render_event('admin.components.activities.content.types.before') !!}

                    <div
                        v-for="type in types"
                        class="cursor-pointer px-3 py-2.5 text-sm font-medium dark:text-white"
                        :class="{'border-brandColor border-b-2 !text-brandColor transition': selectedType == type.name }"
                        @click="selectedType = type.name"
                    >
                        @{{ type.label }}
                    </div>

                    {!! view_render_event('admin.components.activities.content.types.after') !!}
                </div>

                <!-- Show Default Activities if selectedType not in extraTypes -->
                <template v-if="! extraTypes.find(type => type.name == selectedType)">
                    <div class="animate-[on-fade_0.5s_ease-in-out] p-4">
                        {!! view_render_event('admin.components.activities.content.activity.list.before') !!}

                        <!-- Activity List -->
                        <div class="flex flex-col gap-4">
                            {!! view_render_event('admin.components.activities.content.activity.item.before') !!}

                            <!-- Activity Item -->
                            <div
                                class="flex gap-2"
                                v-for="(activity, index) in filteredActivities"
                            >
                                {!! view_render_event('admin.components.activities.content.activity.item.icon.before') !!}

                                <!-- Activity Icon -->
                                <div
                                    class="mt-2 flex h-9 min-h-9 w-9 min-w-9 items-center justify-center rounded-full text-xl"
                                    :class="typeClasses[activity.type] ?? typeClasses['default']"
                                >
                                </div>

                                {!! view_render_event('admin.components.activities.content.activity.item.icon.after') !!}

                                {!! view_render_event('admin.components.activities.content.activity.item.details.before') !!}

                                <!-- Activity Details -->
                                <div
                                    class="flex w-full justify-between gap-4 rounded-md p-4"
                                    :class="{'bg-gray-100 dark:bg-gray-950': index % 2 != 0 }"
                                >
                                    <div class="flex flex-col gap-2">
                                        {!! view_render_event('admin.components.activities.content.activity.item.title.before') !!}

                                        <!-- Activity Title -->
                                        <div
                                            class="flex flex-col gap-1"
                                            v-if="activity.title"
                                        >
                                            <template v-if="activity.type !== 'system'">
                                                <a
                                                    class="flex flex-wrap items-center gap-1 font-medium dark:text-white hover:underline cursor-pointer"
                                                    :class="{
                                                        'text-orange-600 dark:text-orange-400': isToday(activity.schedule_from),
                                                        'text-red-600 dark:text-red-400': !isToday(activity.schedule_from) && isPast(activity.schedule_from)
                                                    }"
                                                    :href="
                                                        activity.type == 'email'
                                                        ? ('{{ route('admin.mail.view', ['route' => 'inbox', 'id' => 'replaceId']) }}'.replace('replaceId', activity.id))
                                                        : ('{{ route('admin.activities.view', 'replaceId') }}'.replace('replaceId', activity.id) + (returnUrl ? ('?return=' + encodeURIComponent(returnUrl)) : ''))
                                                    "
                                                >
                                                    @{{ activity.title }}
                                                    <span v-if="activity.is_done == 1 || activity.is_done === true" class="ml-1 icon-tick text-green-600 text-base" title="Afgerond"></span>
                                                    <span v-if="activity.type === 'email' && activity.linked_entity_type === 'lead'" class="ml-2 inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200" title="E-mail gekoppeld aan lead">
                                                        <span class="icon-activity text-[10px]"></span>
                                                    </span>
                                                    <span v-else-if="activity.type === 'email' && activity.linked_entity_type === 'person'" class="ml-2 inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-2 00" title="E-mail gekoppeld aan persoon">
                                                        <span class="icon-contact text-[10px]"></span>
                                                    </span>
                                                    <span v-else-if="activity.type === 'email' && activity.linked_entity_type === 'activity'" class="icon-activity text-xs text-blue-600 ml-1" title="E-mail gekoppeld aan activiteit"></span>
                                                    <span v-else-if="activity.type === 'email' && activity.linked_entity_type === 'sales'" class="icon-activity text-xs text-blue-600 ml-1" title="E-mail gekoppeld aan sales"></span>
                                                    <span v-else-if="activity.type === 'email'" class="icon-activity text-xs text-blue-600 ml-1" title="E-mail gekoppeld aan onbekend"></span>


                                                    <!-- Status chip hidden per requirement -->
                                                </a>
                                                <div class="text-sm" :class="{
                                                    'text-orange-600 dark:text-orange-400': isToday(activity.schedule_from),
                                                    'text-red-600 dark:text-red-400': !isToday(activity.schedule_from) && isPast(activity.schedule_from),
                                                    'text-gray-600 dark:text-gray-300': !(isToday(activity.schedule_from) || isPast(activity.schedule_from))
                                                }">
                                                    Ingepland vanaf: @{{ $admin.formatDate(activity.schedule_from, 'd MMM yyyy, hh:mm', timezone) }}
                                                </div>
                                            </template>

                                            <template v-else>
                                                <div class="flex flex-wrap items-center gap-1 font-medium dark:text-white">
                                                    @{{ activity.title }}
                                                </div>
                                            </template>

                                            <template v-if="activity.type == 'system' && activity.additional">
                                                <p class="flex items-center gap-1">
                                                    <span class="break-words">
                                                        @{{ truncate(activity.additional?.old?.label ? String(activity.additional.old.label).replaceAll('<br>', ' ') : "@lang('admin::app.components.activities.index.empty')" , 60) }}
                                                    </span>

                                                    <span class="icon-stats-up rotate-90 text-xl"></span>

                                                    <span class="break-words">
                                                        @{{ truncate(activity.additional?.new?.label ? String(activity.additional.new.label).replaceAll('<br>', ' ') : "@lang('admin::app.components.activities.index.empty')" , 60) }}
                                                    </span>
                                                </p>

                                                <p class="mt-1" v-if="activity.additional?.link">
                                                    <a :href="activity.additional.link" class="text-blue-600 hover:underline" target="_blank">
                                                        @{{ activity.additional.link_label || 'Bekijk' }}
                                                    </a>
                                                </p>
                                            </template>
                                        </div>

                                        {!! view_render_event('admin.components.activities.content.activity.item.title.after') !!}

                                        {!! view_render_event('admin.components.activities.content.activity.item.description.before') !!}

                                        <!-- Activity Description -->
                                        <p
                                            class="dark:text-white"
                                            v-if="activity.comment"
                                            v-safe-html="activity.comment"
                                        ></p>

                                        <!-- Call status summary/details -->
                                        <template v-if="activity.type === 'call' && activity.call_statuses?.length">
                                            <div class="mt-1 text-sm">
                                                <div v-if="activity.call_statuses?.length" class="mb-1">
                                                    <span class="font-medium flex items-center gap-1">Laatste: <call-status-icon :status="activity.call_statuses[activity.call_statuses.length - 1].status" size="w-4 h-4"></call-status-icon> @{{ getCallStatusLabel(activity.call_statuses[activity.call_statuses.length - 1].status) }} <span class="text-gray-500">(@{{ $admin.formatDate(activity.call_statuses[activity.call_statuses.length - 1].created_at, 'd MMM yyyy, hh:mm', timezone) }})</span></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800">Niet bereikt: @{{ activity.call_status_summary.not_reachable }}</span>
                                                    <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800">Voicemail: @{{ activity.call_status_summary.voicemail_left }}</span>
                                                    <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800">Gesproken: @{{ activity.call_status_summary.spoken }}</span>
                                                    <button type="button" class="text-blue-600 hover:underline" @click="activity.__showCallDetails = !activity.__showCallDetails">@{{ activity.__showCallDetails ? 'Verberg' : 'Details' }}</button>
                                                </div>
                                                <div v-if="activity.__showCallDetails && activity.call_statuses?.length" class="mt-2 border rounded p-2 dark:border-gray-800">
                                                    <div v-for="cs in activity.call_statuses" :key="cs.created_at" class="text-xs py-1 border-b last:border-b-0 dark:border-gray-800">
                                                        <div class="flex justify-between items-center">
                                                            <div class="flex items-center gap-2">
                                                                <call-status-icon :status="cs.status" size="w-4 h-4"></call-status-icon>
                                                                <span class="font-medium">@{{ getCallStatusLabel(cs.status) }}</span>
                                                            </div>
                                                            <span>@{{ $admin.formatDate(cs.created_at, 'd MMM yyyy, hh:mm', timezone) }}</span>
                                                        </div>
                                                        <div v-if="cs.omschrijving" class="text-gray-600 dark:text-gray-300">@{{ cs.omschrijving }}</div>
                                                        <div v-if="cs.creator" class="text-gray-500">door @{{ cs.creator.name }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Email summary/details -->
                                        <template v-if="activity.emails && activity.emails.length > 0">
                                            <div class="mt-1 text-sm">
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                        <span class="icon-mail text-xs mr-1"></span>
                                                        @{{ activity.emails.length }} @{{ activity.emails.length === 1 ? 'e-mail' : 'e-mails' }}
                                                    </span>
                                                    <button type="button" class="text-blue-600 hover:underline" @click="activity.__showEmailDetails = !activity.__showEmailDetails">@{{ activity.__showEmailDetails ? 'Verberg' : 'Details' }}</button>
                                                </div>
                                                <div v-if="activity.__showEmailDetails && activity.emails?.length" class="mt-2 border rounded p-2 dark:border-gray-800">
                                                    <div v-for="email in activity.emails" :key="email.id" class="text-xs py-1 border-b last:border-b-0 dark:border-gray-800">
                                                        <div class="flex justify-between items-center">
                                                            <div class="flex items-center gap-2">
                                                                <span class="icon-mail text-blue-600 text-xs"></span>
                                                                <template v-if="email.lead_id || (email.additional && email.additional.__source === 'lead')">
                                                                    <span class="ml-0.5 inline-flex items-center gap-1 text-[10px] px-1 py-0.5 rounded bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200" title="Lead">
                                                                        <span class="icon-activity text-[10px]"></span>
                                                                        Lead
                                                                    </span>
                                                                </template>
                                                                <template v-else-if="email.person_id || (email.additional && email.additional.__source === 'person')">
                                                                    <span class="ml-0.5 inline-flex items-center gap-1 text-[10px] px-1 py-0.5 rounded bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200" title="Persoon">
                                                                        <span class="icon-contact text-[10px]"></span>
                                                                        Persoon
                                                                    </span>
                                                                </template>
                                                                <template v-else>
                                                                    <span class="icon-activity text-[10px] text-blue-600" title="Activiteit"></span>
                                                                </template>
                                                                <span class="font-medium truncate max-w-[200px]" :title="email.subject || 'Geen onderwerp'">
                                                                    @{{ email.subject || 'Geen onderwerp' }}
                                                                    <span v-if="email && (email.is_read === 0 || email.is_read === false || email.is_read === '0')" class="inline-block h-1.5 w-1.5 rounded-full bg-sky-600 align-middle ml-1 dark:bg-white"></span>
                                                                </span>
                                                            </div>
                                                            <span>@{{ $admin.formatDate(email.created_at, 'd MMM yyyy, h:mm', timezone) }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <a
                                                                :href="`{{ route('admin.mail.view', ['route' => 'inbox', 'id' => 'replaceID']) }}`.replace('replaceID', email.id)"
                                                                class="text-blue-600 hover:underline text-xs"
                                                                target="_blank"
                                                            >
                                                                E-mail bekijken
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        {!! view_render_event('admin.components.activities.content.activity.item.description.after') !!}

                                        {!! view_render_event('admin.components.activities.content.activity.item.attachments.before') !!}

                                        <!-- Attachments -->
                                        <div
                                            class="flex flex-wrap gap-2"
                                            v-if="activity.files.length"
                                        >
                                            <a
                                                :href="
                                                    (file.is_email_attachment || activity.type === 'email')
                                                    ? `{{ route('admin.mail.attachment_download', 'replaceID') }}`.replace('replaceID', file.id)
                                                    : `{{ route('admin.activities.file_download', 'replaceID') }}`.replace('replaceID', file.id)
                                                "
                                                class="flex cursor-pointer items-center gap-1 rounded-md p-1.5"
                                                target="_blank"
                                                v-for="(file, index) in activity.files"
                                            >
                                                <span class="icon-attached-file text-xl"></span>

                                                <span class="font-medium text-brandColor">
                                                    @{{ file.name }}
                                                </span>
                                            </a>
                                        </div>

                                        <!-- Linked Emails -->
                                        <div
                                            class="flex flex-col gap-1 mt-2"
                                            v-if="activity.emails && activity.emails.length > 0"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                                v-for="email in activity.emails"
                                                :key="email.id"
                                            >
                                                <span class="icon-mail text-green-600"></span>
                                                <a
                                                    :href="`{{ route('admin.mail.view', ['route' => 'inbox', 'id' => 'replaceID']) }}`.replace('replaceID', email.id)"
                                                    class="text-sm text-green-600 hover:underline"
                                                    target="_blank"
                                                >
                                                    @{{ email.subject || 'E-Mail bekijken' }}
                                                </a>
                                            </div>
                                        </div>

                                        {!! view_render_event('admin.components.activities.content.activity.item.attachments.after') !!}

                                        {!! view_render_event('admin.components.activities.content.activity.item.time_and_user.before') !!}

                                        <!-- Activity Time and User -->
                                        <div class="text-gray-500 dark:text-gray-300">
                                            @{{ $admin.formatDate(activity.created_at, 'd MMM yyyy, h:mm A', timezone) }},

                                            @{{ "@lang('admin::app.components.activities.index.by-user', ['user' => 'replace'])".replace('replace', activity.user?.name ?? '@lang('admin::app.components.activities.index.system')') }}
                                        </div>

                                        {!! view_render_event('admin.components.activities.content.activity.item.time_and_user.after') !!}
                                    </div>

                                    {!! view_render_event('admin.components.activities.content.activity.item.more_actions.before') !!}

                                    <!-- Activity More Options -->
                                    <template v-if="activity.type != 'system'">
                                        {!! view_render_event('admin.components.activities.content.activity.item.more_actions.dropdown.after') !!}

                                        <x-admin::dropdown position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'left' : 'right' }}">
                                            <x-slot:toggle>
                                                {!! view_render_event('admin.components.activities.content.activity.item.more_actions.dropdown.toggle.before') !!}

                                                <template v-if="! isUpdating[activity.id]">
                                                    <button
                                                        class="icon-more flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                                    ></button>
                                                </template>

                                                <template v-else>
                                                    <x-admin::spinner />
                                                </template>

                                                {!! view_render_event('admin.components.activities.content.activity.item.more_actions.dropdown.toggle.after') !!}
                                            </x-slot>

                                            <x-slot:menu class="!min-w-40">
                                                {!! view_render_event('admin.components.activities.content.activity.item.more_actions.dropdown.menu_item.before') !!}

                                                <template v-if="activity.type != 'email'">
                                                    @if (bouncer()->hasPermission('activities.edit'))
                                                        <x-admin::dropdown.menu.item
                                                            v-if="! activity.is_done && ['call', 'meeting', 'task'].includes(activity.type)"
                                                            @click="markAsDone(activity)"
                                                        >
                                                            <div class="flex items-center gap-2">
                                                                <span class="icon-tick text-2xl"></span>

                                                                @lang('admin::app.components.activities.index.mark-as-done')
                                                            </div>
                                                        </x-admin::dropdown.menu.item>

                                                        <x-admin::dropdown.menu.item v-if="['call', 'meeting', 'task'].includes(activity.type)">
                                                            <a
                                                                class="flex items-center gap-2"
                                                                :href="'{{ route('admin.activities.edit', 'replaceId') }}'.replace('replaceId', activity.id) + (returnUrl ? ('?return=' + encodeURIComponent(returnUrl)) : '')"
                                                                target="_blank"
                                                            >
                                                                <span class="icon-edit text-2xl"></span>

                                                                @lang('admin::app.components.activities.index.edit')
                                                            </a>
                                                        </x-admin::dropdown.menu.item>
                                                    @endif

                                                    @if (bouncer()->hasPermission('activities.delete'))
                                                        <x-admin::dropdown.menu.item @click="remove(activity)">
                                                            <div class="flex items-center gap-2">
                                                                <span class="icon-delete text-2xl"></span>

                                                                @lang('admin::app.components.activities.index.delete')
                                                            </div>
                                                        </x-admin::dropdown.menu.item>
                                                    @endif
                                                </template>

                                                <template v-else>
                                                    @if (bouncer()->hasPermission('mail.view'))
                                                        <x-admin::dropdown.menu.item>
                                                            <a
                                                                :href="'{{ route('admin.mail.view', ['route' => 'replaceFolder', 'id' => 'replaceMailId']) }}'.replace('replaceFolder', activity.folder_name || 'inbox').replace('replaceMailId', activity.id)"
                                                                class="flex items-center gap-2"
                                                                target="_blank"
                                                            >
                                                                <span class="icon-eye text-2xl"></span>

                                                                @lang('admin::app.components.activities.index.view')
                                                            </a>
                                                        </x-admin::dropdown.menu.item>
                                                    @endif

                                                    <x-admin::dropdown.menu.item @click="unlinkEmail(activity)">
                                                        <div class="flex items-center gap-2">
                                                            <span class="icon-attachment text-2xl"></span>

                                                            @lang('admin::app.components.activities.index.unlink')
                                                        </div>
                                                    </x-admin::dropdown.menu.item>
                                                </template>

                                                {!! view_render_event('admin.components.activities.content.activity.item.more_actions.dropdown.menu_item.after') !!}
                                            </x-slot>
                                        </x-admin::dropdown>

                                        {!! view_render_event('admin.components.activities.content.activity.item.more_actions.dropdown.after') !!}
                                    </template>

                                    {!! view_render_event('admin.components.activities.content.activity.item.more_actions.after') !!}
                                </div>

                                {!! view_render_event('admin.components.activities.content.activity.item.details.after') !!}
                            </div>

                            {!! view_render_event('admin.components.activities.content.activity.item.after') !!}

                            <!-- Empty Placeholder -->
                            <div
                                class="grid justify-center justify-items-center gap-3.5 py-12"
                                v-if="! filteredActivities.length"
                            >
                                <img
                                    class="dark:mix-blend-exclusion dark:invert"
                                    :src="typeIllustrations[selectedType]?.image ?? typeIllustrations['all'].image"
                                >

                                <div class="flex flex-col items-center gap-2">
                                    <p class="text-xl font-semibold dark:text-white">
                                        @{{ typeIllustrations[selectedType]?.title ?? typeIllustrations['all'].title }}
                                    </p>

                                    <p class="text-gray-400 dark:text-gray-400">
                                        @{{ typeIllustrations[selectedType]?.description ?? typeIllustrations['all'].description }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {!! view_render_event('admin.components.activities.content.activity.list.after') !!}
                    </div>
                </template>

                <template v-else>
                    <template v-for="type in extraTypes">
                        {!! view_render_event('admin.components.activities.content.activity.extra_types.before') !!}

                        <div v-show="selectedType == type.name">
                            <slot :name="type.name"></slot>
                        </div>

                        {!! view_render_event('admin.components.activities.content.activity.extra_types.after') !!}
                    </template>
                </template>
            </div>

            {!! view_render_event('admin.components.activities.content.after') !!}
        </template>
    </script>

    <script type="module">
        app.component('v-activities', {
            template: '#v-activities-template',

            props: {
                endpoint: {
                    type: String,
                    default: '',
                },

                emailDetachEndpoint: {
                    type: String,
                    default: '',
                },

                activeType: {
                    type: String,
                    default: 'all',
                },

                types: {
                    type: Array,
                    default: [
                        {
                            name: 'planned',
                            label: "{{ trans('admin::app.components.activities.index.planned') }}",
                        }, {
                            name: 'note',
                            label: "{{ trans('admin::app.components.activities.index.notes') }}",
                        }, {
                            name: 'email',
                            label: "{{ trans('admin::app.components.activities.index.emails') }}",
                        }, {
                            name: 'call',
                            label: "{{ trans('admin::app.components.activities.index.calls') }}",
                        }, {
                            name: 'meeting',
                            label: "{{ trans('admin::app.components.activities.index.meetings') }}",
                        }, {
                            name: 'task',
                            label: "{{ trans('admin::app.components.activities.index.internal-task') }}",
                        }, {
                            name: 'file',
                            label: "{{ trans('admin::app.components.activities.index.files') }}",
                        }, {
                            name: 'system',
                            label: "{{ trans('admin::app.components.activities.index.change-log') }}",
                        }, {
                            name: 'all',
                            label: "{{ trans('admin::app.components.activities.index.all') }}",
                        }
                    ],
                },

                extraTypes: {
                    type: Array,
                    default: [],
                },

            },

            data() {
                return {
                    isLoading: false,

                    isUpdating: {},

                    activities: [],

                    selectedType: this.activeType,

                    typeClasses: {
                        email: 'icon-mail bg-green-200 text-green-900 dark:!text-green-900',
                        note: 'icon-note bg-orange-200 text-orange-800 dark:!text-orange-800',
                        call: 'icon-call bg-cyan-200 text-cyan-800 dark:!text-cyan-800',
                        meeting: 'icon-activity bg-blue-200 text-blue-800 dark:!text-blue-800',
                        task: 'icon-activity bg-blue-200 text-blue-800 dark:!text-blue-800',
                        file: 'icon-file bg-green-200 text-green-900 dark:!text-green-900',
                        system: 'icon-system-generate bg-yellow-200 text-yellow-900 dark:!text-yellow-900',
                        default: 'icon-activity bg-blue-200 text-blue-800 dark:!text-blue-800',
                    },

                    typeIllustrations: {
                        all: {
                            image: "{{ vite()->asset('images/empty-placeholders/activities.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.all.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.all.description') }}",
                        },

                        planned: {
                            image: "{{ vite()->asset('images/empty-placeholders/plans.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.planned.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.planned.description') }}",
                        },

                        note: {
                            image: "{{ vite()->asset('images/empty-placeholders/notes.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.notes.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.notes.description') }}",
                        },

                        call: {
                            image: "{{ vite()->asset('images/empty-placeholders/calls.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.calls.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.calls.description') }}",
                        },

                        meeting: {
                            image: "{{ vite()->asset('images/empty-placeholders/meetings.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.meetings.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.meetings.description') }}",
                        },

                        task: {
                            image: "{{ vite()->asset('images/empty-placeholders/lunches.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.tasks.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.tasks.description') }}",
                        },

                        file: {
                            image: "{{ vite()->asset('images/empty-placeholders/files.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.files.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.files.description') }}",
                        },

                        email: {
                            image: "{{ vite()->asset('images/empty-placeholders/emails.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.emails.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.emails.description') }}",
                        },

                        system: {
                            image: "{{ vite()->asset('images/empty-placeholders/activities.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.system.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.system.description') }}",
                        }
                    },

                    timezone: "{{ config('app.timezone') }}",

                    // Current page URL for return navigation after editing activities
                    returnUrl: (typeof window !== 'undefined' ? (window.location.pathname + window.location.search) : ''),
                }
            },

            computed: {
                filteredActivities() {
                    if (this.selectedType == 'all') {
                        return this.activities;
                    } else if (this.selectedType == 'planned') {
                        return this.activities
                            .filter(activity => ! activity.is_done)
                            .slice()
                            .sort((a, b) => {
                                const aTime = a && a.schedule_from ? new Date(a.schedule_from).getTime() : Infinity;
                                const bTime = b && b.schedule_from ? new Date(b.schedule_from).getTime() : Infinity;
                                return aTime - bTime; // earliest first
                            });
                    }

                    return this.activities.filter(activity => activity.type == this.selectedType);
                }
            },

            mounted() {
                this.get();

                if (this.extraTypes?.length) {
                    this.extraTypes.forEach(type => {
                        this.types.push(type);
                    });
                }

                this.$emitter.on('on-activity-added', (activity) => this.activities.unshift(activity));
            },

            methods: {
                get() {
                    this.isLoading = true;

                    this.$axios.get(this.endpoint)
                        .then(response => {
                            this.activities = response.data.data;
                            this.isLoading = false;
                        })
                        .catch(error => {
                            console.error(error);
                        });
                },

                markAsDone(activity) {
                    this.$emitter.emit('open-confirm-modal', {
                        agree: () => {
                            this.isUpdating[activity.id] = true;

                            this.$axios.put("{{ route('admin.activities.update', 'replaceId') }}".replace('replaceId', activity.id), {
                                    'is_done': 1
                                })
                                .then((response) => {
                                    this.isUpdating[activity.id] = false;

                                    activity.is_done = 1;

                                    this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                })
                                .catch((error) => {
                                    this.isUpdating[activity.id] = false;

                                    this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                                });
                        },
                    });
                },

                remove(activity) {
                    this.$emitter.emit('open-confirm-modal', {
                        agree: () => {
                            this.isUpdating[activity.id] = true;

                            this.$axios.delete("{{ route('admin.activities.delete', 'replaceId') }}".replace('replaceId', activity.id))
                                .then((response) => {
                                    this.isUpdating[activity.id] = false;

                                    this.activities.splice(this.activities.indexOf(activity), 1);

                                    this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                })
                                .catch((error) => {
                                    this.isUpdating[activity.id] = false;

                                    this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                                });
                        },
                    });
                },

                unlinkEmail(activity) {
                    this.$emitter.emit('open-confirm-modal', {
                        agree: () => {
                            let emailId = activity.parent_id ?? activity.id;

                            this.$axios.delete(this.emailDetachEndpoint, {
                                    data: {
                                        email_id: emailId,
                                    }
                                })
                                .then((response) => {
                                    let relatedActivities = this.activities.filter(activity => activity.parent_id == emailId || activity.id == emailId);

                                    relatedActivities.forEach(activity => {
                                        const index = this.activities.findIndex(a => a === activity);

                                        if (index !== -1) {
                                            this.activities.splice(index, 1);
                                        }
                                    });

                                    this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                })
                                .catch((error) => {
                                    this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                                });
                        }
                    });
                },

                getCallStatusLabel(status) {
                    const labels = {
                        'not_reachable': 'Niet kunnen bereiken',
                        'voicemail_left': 'Voicemail ingesproken',
                        'spoken': 'Gesproken'
                    };
                    return labels[status] || status;
                },

                truncate(value, maxLength = 60) {
                    if (!value) {
                        return '';
                    }
                    const text = String(value);
                    if (text.length <= maxLength) {
                        return text;
                    }
                    return text.slice(0, maxLength - 1) + '…';
                },

                isToday(dateStr) {
                    if (!dateStr) return false;
                    const d = new Date(dateStr);
                    const now = new Date();
                    return d.getFullYear() === now.getFullYear()
                        && d.getMonth() === now.getMonth()
                        && d.getDate() === now.getDate();
                },

                isPast(dateStr) {
                    if (!dateStr) return false;
                    return new Date(dateStr).getTime() < Date.now();
                },
            },
        });
    </script>
@endPushOnce
