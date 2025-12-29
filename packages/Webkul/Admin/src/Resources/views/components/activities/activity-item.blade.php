<div
    class="flex gap-2"
    v-for="(activity, index) in filteredActivities"
    :key="activity.id"
>
    {!! view_render_event('admin.components.activities.content.activity.item.icon.before') !!}

    <!-- Activity Icon -->
    <div
        class="mt-2 flex h-9 min-h-9 w-9 min-w-9 items-center justify-center rounded-full text-xl relative"
        :class="getTypeClass(activity)"
    >
        <span
            v-if="['email', 'patient_message'].includes(activity.type) && ! activity.is_read"
            class="absolute -top-1 -right-1 flex h-3 w-3"
        >
            <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-600 border-2 border-white dark:border-gray-900"></span>
        </span>
    </div>

    {!! view_render_event('admin.components.activities.content.activity.item.icon.after') !!}

    {!! view_render_event('admin.components.activities.content.activity.item.details.before') !!}

    <!-- Activity Details -->
    <div
        class="flex w-full justify-between gap-4 rounded-md p-4"
        :class="{'bg-neutral-bg dark:bg-gray-950': index % 2 != 0 }"
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
                            'text-status-expired-text dark:text-red-400': !isToday(activity.schedule_from) && isPast(activity.schedule_from)
                        }"
                        :href="
                            activity.type == 'email'
                            ? ('{{ route('admin.mail.view', ['route' => 'inbox', 'id' => 'replaceId']) }}'.replace('replaceId', activity.id))
                            : ('{{ route('admin.activities.view', 'replaceId') }}'.replace('replaceId', activity.id) + (returnUrl ? ('?return=' + encodeURIComponent(returnUrl)) : ''))
                        "
                    >
                        @{{ activity.title || 'geen' }}

                        <span v-if="activity.is_done == 1 || activity.is_done === true" class="ml-1 icon-tick text-status-active-text text-base" title="Afgerond"></span>
                        <span v-if="activity.type === 'email' && activity.linked_entity_type === 'lead'" class="ml-2 inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200" title="E-mail gekoppeld aan lead">
                            <span class="icon-activity text-[10px]"></span>
                        </span>
                        <span v-else-if="activity.type === 'email' && activity.linked_entity_type === 'person'" class="ml-2 inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-2 00" title="E-mail gekoppeld aan persoon">
                            <span class="icon-contact text-[10px]"></span>
                        </span>
                        <span v-else-if="activity.type === 'email' && activity.linked_entity_type === 'activity'" class="icon-activity text-xs text-activity-note-text ml-1" title="E-mail gekoppeld aan activiteit"></span>
                        <span v-else-if="activity.type === 'email' && activity.linked_entity_type === 'sales'" class="icon-activity text-xs text-activity-note-text ml-1" title="E-mail gekoppeld aan sales"></span>
                        <span v-else-if="activity.type === 'email'" class="icon-activity text-xs text-activity-note-text ml-1" title="E-mail gekoppeld aan onbekend"></span>

                        <!-- Status chip hidden per requirement -->
                    </a>
                    <div v-if="activity.schedule_from" class="text-sm" :class="{
                        'text-orange-600 dark:text-orange-400': isToday(activity.schedule_from),
                        'text-status-expired-text dark:text-red-400': !isToday(activity.schedule_from) && isPast(activity.schedule_from ),
                        'text-gray-600 dark:text-gray-300': !(isToday(activity.schedule_from) || isPast(activity.schedule_from))
                    }">
                        Ingepland vanaf: @{{ $admin.formatDate(activity.schedule_from, 'd MMM yyyy, hh:mm', timezone) }}
                    </div>
                </template>

                <template v-else>
                    <div class="flex flex-wrap items-center gap-1 font-medium dark:text-white">
                        @{{ activity.title || 'geen' }}
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
                        <a :href="activity.additional.link" class="text-activity-note-text hover:underline" target="_blank">
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
            >
                <span v-if="activity.type === 'email'">
                    @{{ truncateHtml(activity.comment, 150) }}
                </span>
                <span v-else v-safe-html="activity.comment"></span>
            </p>

            <!-- Call status summary/details -->
            <template v-if="activity.type === 'call' && activity.call_statuses?.length">
                <div class="mt-1 text-sm">
                    <div v-if="activity.call_statuses?.length" class="mb-1">
                        <span class="font-medium flex items-center gap-1">Laatste: <call-status-icon :status="activity.call_statuses[activity.call_statuses.length - 1].status" size="w-4 h-4"></call-status-icon> @{{ getCallStatusLabel(activity.call_statuses[activity.call_statuses.length - 1].status) }} <span class="text-gray-500">(@{{ $admin.formatDate(activity.call_statuses[activity.call_statuses.length - 1].created_at, 'd MMM yyyy, hh:mm', timezone) }})</span></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded bg-neutral-bg dark:bg-gray-800">Niet bereikt: @{{ activity.call_status_summary.not_reachable }}</span>
                        <span class="px-2 py-0.5 rounded bg-neutral-bg dark:bg-gray-800">Voicemail: @{{ activity.call_status_summary.voicemail_left }}</span>
                        <span class="px-2 py-0.5 rounded bg-neutral-bg dark:bg-gray-800">Gesproken: @{{ activity.call_status_summary.spoken }}</span>
                        <button type="button" class="text-activity-note-text hover:underline" @click="activity.__showCallDetails = !activity.__showCallDetails">@{{ activity.__showCallDetails ? 'Verberg' : 'Details' }}</button>
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
            {!! view_render_event('admin.components.activities.content.activity.item.attachments.after') !!}

            {!! view_render_event('admin.components.activities.content.activity.item.time_and_user.before') !!}

            <!-- Activity Time and User -->
            <div class="text-gray-500 dark:text-gray-300">
                @{{ $admin.formatDate(activity.created_at, 'd MMM yyyy, h:mm A', timezone) }},

                Toegewezen aan: @{{ activity.user?.name ?? '-' }}
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

