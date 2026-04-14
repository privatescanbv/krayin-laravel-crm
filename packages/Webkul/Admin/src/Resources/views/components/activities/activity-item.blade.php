@php
    use App\Enums\ActivityType;

    $canBeMarkedAsDoneTypes = array_map(
        fn (ActivityType $type) => $type->value,
        ActivityType::canBeMarkedAsDone()
    );

    // Vue template sits inside an HTML attribute; we must avoid injecting double quotes.
    // Build a JS array literal with single-quoted strings: ['call','task',...]
    $canBeMarkedAsDoneTypesJs = '[' . implode(',', array_map(
        fn (string $value) => "'" . str_replace("'", "\\'", $value) . "'",
        $canBeMarkedAsDoneTypes
    )) . ']';
@endphp

<div class="flex gap-2" v-for="activity in filteredActivities" :key="activity.id">
    {!! view_render_event('admin.components.activities.content.activity.item.icon.before') !!}

    {!! view_render_event('admin.components.activities.content.activity.item.details.before') !!}

    <!-- Activity Details -->
    <div class="flex w-full items-start gap-4 rounded-xl border p-4 transition-all"
        :class="{
            'bg-red-50/50 border-red-200 dark:bg-red-950/20 dark:border-red-900': !isToday(activity.schedule_from) &&
                isPast(activity.schedule_from),
            'bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-800': isToday(activity.schedule_from) || !
                isPast(activity.schedule_from)
        }">

        <!-- Activity Icon -->
        <div class="relative mt-2 flex h-9 min-h-9 w-9 min-w-9 items-center justify-center rounded-full text-xl"
            :class="getTypeClass(activity)">
            <span v-if="['email', 'patient_message'].includes(activity.type) && ! activity.is_read"
                class="absolute -right-1 -top-1 flex h-3 w-3">
                <span
                    class="relative inline-flex h-3 w-3 rounded-full border-2 border-white bg-blue-600 dark:border-gray-900"></span>
            </span>
            <span v-if="activity.type === 'file' && !activity.is_done"
                class="absolute -right-1 -top-1 flex h-3 w-3"
                title="Nog te verwerken">
                <span
                    class="relative inline-flex h-3 w-3 rounded-full border-2 border-white bg-amber-500 dark:border-gray-900"></span>
            </span>
        </div>

        {!! view_render_event('admin.components.activities.content.activity.item.icon.after') !!}

        <div class="flex flex-col grow gap-2">
            {!! view_render_event('admin.components.activities.content.activity.item.title.before') !!}

            <!-- Activity Title -->
            <div class="flex flex-1 flex-row items-center gap-4 border-b py-1" v-if="activity.title">
                <template v-if="activity.type !== 'system'">
                    <a class="flex cursor-pointer flex-wrap grow items-center gap-1 font-medium hover:underline dark:text-white"
                        :class="{
                            'text-orange-600 dark:text-orange-400': isToday(activity.schedule_from),
                            'text-status-expired-text dark:text-red-400': !isToday(activity.schedule_from) && isPast(
                                activity.schedule_from)
                        }"
                        :href="activity.type == 'email' ?
                            ('{{ route('admin.mail.view', ['route' => 'inbox', 'id' => 'replaceId']) }}'.replace(
                                'replaceId', activity.id)) :
                            ('{{ route('admin.activities.view', 'replaceId') }}'.replace('replaceId', activity.id) + (
                                returnUrl ? ('?return_url=' + encodeURIComponent(returnUrl)) : ''))">
                        @{{ activity.title || 'geen' }}

                        <!-- Status chip hidden per requirement -->
                    </a>
                    <span v-if="!isToday(activity.schedule_from) && isPast(activity.schedule_from)"
                        class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-error dark:bg-red-900/30 dark:text-red-400">
                        Te laat
                    </span>
                    <span v-else-if="activity.schedule_from"
                        class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                        Gepland
                    </span>
                    <div v-if="activity.schedule_from" class="text-xs"
                        :class="{
                            'text-orange-600 dark:text-orange-400': isToday(activity.schedule_from),
                            'text-status-expired-text dark:text-red-400': !isToday(activity.schedule_from) && isPast(
                                activity.schedule_from),
                            'text-gray-600 dark:text-gray-300': !(isToday(activity.schedule_from) || isPast(activity
                                .schedule_from))
                        }">
                        Vanaf: @{{ $admin.formatDate(activity.schedule_from, 'd MMM yyyy, hh:mm', timezone) }}
                    </div>
                    <!-- Entity source label (shows where the activity originates from in the hierarchy) -->
                    <span v-if="activity.entity_source"
                          class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                          :class="{
                              'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400':   activity.entity_source.type === 'person',
                              'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400': activity.entity_source.type === 'lead',
                              'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400':  activity.entity_source.type === 'sales',
                              'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-400':   activity.entity_source.type === 'order',
                          }"
                          :title="'Activiteit van: ' + activity.entity_source.label">
                        @{{ activity.entity_source.label }}
                    </span>

                    <div class="flex flex-row gap-1">
                    <span v-if="activity.is_published_to_portal"
                          class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400"
                          title="Gepubliceerd in patiëntportaal">Portaal<template v-if="activity.portal_persons && activity.portal_persons.length">: @{{ activity.portal_persons.map(p => p.name).join(', ') }}</template></span>
                    <span v-if="activity.is_done == 1"
                          class="icon-tick ml-1 text-base text-status-active-text" title="Afgerond"></span>
                        <span v-if="activity.type === 'email' && activity.linked_entity_type === 'lead'"
                              class="ml-2 inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-800 dark:bg-slate-800 dark:text-slate-200"
                              title="E-mail gekoppeld aan lead">
                            <span class="icon-activity text-[10px]"></span>
                        </span>
                        <span v-else-if="activity.type === 'email' && activity.linked_entity_type === 'person'"
                              class="ml-2 inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-800 dark:bg-slate-800 dark:text-slate-200"
                              title="E-mail gekoppeld aan persoon">
                            <span class="icon-contact text-[10px]"></span>
                        </span>
                        <span v-else-if="activity.type === 'email' && activity.linked_entity_type === 'activity'"
                              class="ml-2 inline-flex items-center gap-1 rounded bg-violet-100 px-1.5 py-0.5 text-[10px] font-medium text-violet-800 dark:bg-violet-900/30 dark:text-violet-300"
                              :title="'Activiteit: ' + (activity.activity_label || '')">
                            <span class="icon-activity text-[10px]"></span>
                            <span v-if="activity.activity_label">@{{ activity.activity_label }}</span>
                        </span>
                        <span v-else-if="activity.type === 'email' && activity.linked_entity_type === 'clinic'"
                              class="ml-2 inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-800 dark:bg-slate-800 dark:text-slate-200"
                              title="E-mail gekoppeld aan kliniek">
                            <span class="icon-activity text-[10px]"></span>
                        </span>
                        <span v-else-if="activity.type === 'email' && activity.linked_entity_type === 'sales'"
                              class="ml-2 inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-800 dark:bg-slate-800 dark:text-slate-200"
                              title="E-mail gekoppeld aan sales">
                            <span class="icon-activity text-[10px]"></span>
                        </span>
                        <span v-else-if="activity.type === 'email' && activity.linked_entity_type === 'order'"
                              class="ml-2 inline-flex items-center gap-1 rounded bg-blue-100 px-1.5 py-0.5 text-[10px] text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
                              title="E-mail gekoppeld aan order">
                            <span class="icon-activity text-[10px]"></span>
                        </span>
                        <span v-else-if="activity.type === 'email'"
                              class="icon-activity ml-1 text-xs text-activity-note-text"
                              title="E-mail gekoppeld aan onbekend"></span>
                    </div>
                </template>

                <template v-else>
                    <a class="flex cursor-pointer flex-wrap grow items-center gap-1 font-medium hover:underline dark:text-white"
                        :href="'{{ route('admin.activities.view', 'replaceId') }}'.replace('replaceId', activity.id) +
                            (returnUrl ? ('?return_url=' + encodeURIComponent(returnUrl)) : '')">
                        @{{ activity.title || 'geen' }}
                    </a>
                </template>

                <template v-if="activity.type == 'system' && activity.additional">
                    <p class="flex items-center gap-1">
                        <span class="break-words">
                            @{{ truncate(activity.additional?.old?.label ? String(activity.additional.old.label).replaceAll('<br>', ' ') : "@lang('admin::app.components.activities.index.empty')", 60) }}
                        </span>

                        <span class="icon-stats-up rotate-90 text-xl"></span>

                        <span class="break-words">
                            @{{ truncate(activity.additional?.new?.label ? String(activity.additional.new.label).replaceAll('<br>', ' ') : "@lang('admin::app.components.activities.index.empty')", 60) }}
                        </span>
                    </p>

                    <p class="mt-1" v-if="activity.additional?.link">
                        <a :href="activity.additional.link" class="text-activity-note-text hover:underline"
                            target="_blank">
                            @{{ activity.additional.link_label || 'Bekijk' }}
                        </a>
                    </p>
                </template>
            </div>

            {!! view_render_event('admin.components.activities.content.activity.item.title.after') !!}

{!! view_render_event('admin.components.activities.content.activity.item.time_and_user.before') !!}

            <!-- Activity Time and User -->

        <div class="flex flex-wrap items-center mb-2 gap-x-4 gap-y-1 text-xs text-gray-500">
              <div class="flex items-center">
                <span class="icon-clock text-base"></span>
                @{{ $admin.formatDate(activity.created_at, 'd MMM yyyy, h:mm A', timezone) }}
            </div>

            <div class="flex items-center gap-1">
                <span class="icon-user text-base"></span>
                @{{ activity.user?.name }}
            </div>
        </div>
            {!! view_render_event('admin.components.activities.content.activity.item.time_and_user.after') !!}

            {!! view_render_event('admin.components.activities.content.activity.item.description.before') !!}

            <!-- Activity Description -->
            <p class="dark:text-white" v-if="activity.comment">
                <span v-if="activity.type === 'email'">
                    @{{ truncateHtmlASSummary(activity.comment, 350) }}
                </span>
                <span v-else v-safe-html="activity.comment"></span>
            </p>

            <!-- Call status summary/details -->
            <template v-if="activity.type === 'call' && activity.call_statuses?.length">
                <div class="mt-1 text-sm">
                    <div v-if="activity.call_statuses?.length" class="mb-1">
                        <span class="flex items-center gap-1 font-medium">Laatste: <call-status-icon
                                :status="activity.call_statuses[activity.call_statuses.length - 1].status"
                                size="w-4 h-4"></call-status-icon> @{{ getCallStatusLabel(activity.call_statuses[activity.call_statuses.length - 1].status) }} <span
                                class="text-gray-500">(@{{ $admin.formatDate(activity.call_statuses[activity.call_statuses.length - 1].created_at, 'd MMM yyyy, hh:mm', timezone) }})</span></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-neutral-bg px-2 py-0.5 dark:bg-gray-800">Niet bereikt:
                            @{{ activity.call_status_summary.not_reachable }}</span>
                        <span class="rounded bg-neutral-bg px-2 py-0.5 dark:bg-gray-800">Voicemail:
                            @{{ activity.call_status_summary.voicemail_left }}</span>
                        <span class="rounded bg-neutral-bg px-2 py-0.5 dark:bg-gray-800">Gesproken:
                            @{{ activity.call_status_summary.spoken }}</span>
                        <button type="button" class="text-activity-note-text hover:underline"
                            @click="activity.__showCallDetails = !activity.__showCallDetails">@{{ activity.__showCallDetails ? 'Verberg' : 'Details' }}</button>
                    </div>
                    <div v-if="activity.__showCallDetails && activity.call_statuses?.length"
                        class="mt-2 rounded border p-2 dark:border-gray-800">
                        <div v-for="cs in activity.call_statuses" :key="cs.id || cs.created_at"
                            class="border-b py-1 text-xs last:border-b-0 dark:border-gray-800">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <call-status-icon :status="cs.status" size="w-4 h-4"></call-status-icon>
                                    <span class="font-medium">@{{ getCallStatusLabel(cs.status) }}</span>
                                </div>
                                <span>@{{ $admin.formatDate(cs.created_at, 'd MMM yyyy, hh:mm', timezone) }}</span>
                            </div>
                            <div v-if="cs.omschrijving" class="text-gray-600 dark:text-gray-300">@{{ cs.omschrijving }}
                            </div>
                            <div v-if="cs.creator" class="text-gray-500">door @{{ cs.creator.name }}</div>
                        </div>
                    </div>
                </div>
            </template>
            {!! view_render_event('admin.components.activities.content.activity.item.attachments.after') !!}


        </div>

        {!! view_render_event('admin.components.activities.content.activity.item.more_actions.before') !!}

        <!-- Activity More Options -->
        <template v-if="activity.type != 'system'">
            {!! view_render_event('admin.components.activities.content.activity.item.more_actions.dropdown.before') !!}

            <x-admin::dropdown position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'left' : 'right' }}">
                <x-slot:toggle>
                    {!! view_render_event('admin.components.activities.content.activity.item.more_actions.dropdown.toggle.before') !!}

                    <template v-if="! isUpdating[activity.id]">
                        <button
                            class="icon-more flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"></button>
                    </template>

                    <template v-else>
                        <x-admin::spinner />
                    </template>

                    {!! view_render_event('admin.components.activities.content.activity.item.more_actions.dropdown.toggle.after') !!}
                </x-slot>

                <x-slot:menu class="!min-w-40">
                    {!! view_render_event(
                        'admin.components.activities.content.activity.item.more_actions.dropdown.menu_item.before'
                    ) !!}

                    <template v-if="activity.type != 'email'">
                        @if (bouncer()->hasPermission('activities.edit'))
                            <x-admin::dropdown.menu.item
                                v-if="! activity.is_done && {!! $canBeMarkedAsDoneTypesJs !!}.includes(activity.type)"
                                @click="markAsDone(activity)">
                                <div class="flex items-center gap-2">
                                    <span class="icon-tick text-2xl"></span>

                                    @lang('admin::app.components.activities.index.mark-as-done')
                                </div>
                            </x-admin::dropdown.menu.item>

                            <x-admin::dropdown.menu.item>
                                <a class="flex items-center gap-2"
                                    :href="'{{ route('admin.activities.edit', 'replaceId') }}'.replace('replaceId', activity
                                        .id) + (returnUrl ? ('?return_url=' + encodeURIComponent(returnUrl)) : '')">
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
                                <a :href="'{{ route('admin.mail.view', ['route' => 'replaceFolder', 'id' => 'replaceMailId']) }}'
                                .replace('replaceFolder', activity.folder_name || 'inbox').replace('replaceMailId',
                                    activity.id)"
                                    class="flex items-center gap-2" target="_blank">
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
