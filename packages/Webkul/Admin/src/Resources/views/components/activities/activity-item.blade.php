@php
    use App\Enums\ActivityType;
    use App\Http\Controllers\Concerns\ReturnUrl;

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

    $toJsString = fn (string $value): string => "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";

    $currentReturnUrl = ReturnUrl::currentWithHash('activiteiten');
    $activityUrlWithReturn = fn (string $routeName): string => $toJsString(
        ReturnUrl::appendQuery(route($routeName, '__ID__'), $currentReturnUrl)
    );

    $activityViewPatternJs = $activityUrlWithReturn('admin.activities.view');
    $activityEditPatternJs = $activityUrlWithReturn('admin.activities.edit');
    $mailViewPatternJs = $toJsString(
        ReturnUrl::appendQuery(
            route('admin.mail.view', ['route' => '__FOLDER__', 'id' => '__ID__']),
            $currentReturnUrl
        )
    );
@endphp

<div class="flex gap-2" v-for="activity in filteredActivities" :key="activity.id">
    {!! view_render_event('admin.components.activities.content.activity.item.icon.before') !!}

    {!! view_render_event('admin.components.activities.content.activity.item.details.before') !!}

    <!-- Activity Details -->
    <div class="flex w-full items-start gap-3 rounded-xl border p-3 transition-all"
        :class="{
            'border-l-4 border-l-red-500 bg-red-50/30 border-red-200 dark:bg-red-950/20 dark:border-red-900': !activity.is_done && isPastDay(activity.schedule_to),
            'border-l-4 border-l-green-500 bg-green-50/20 border-gray-200 dark:bg-green-950/10 dark:border-gray-800': activity.is_done,
            'border-l-4 border-l-gray-300 bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-800': !activity.is_done && !isPastDay(activity.schedule_to)
        }">

        <!-- Check / Reopen button -->
        <template v-if="{!! $canBeMarkedAsDoneTypesJs !!}.includes(activity.type)">
            <button
                class="group relative mt-2 flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center border-none bg-transparent p-0"
                :title="activity.is_done ? 'Heropenen' : 'Markeer als afgerond'"
                :disabled="isUpdating[activity.id]"
                @click.stop="activity.is_done ? reopen(activity) : markAsDone(activity)"
            >
                <!-- Open state: grijze ring, hover → groene rand + vinkje -->
                <span v-if="!activity.is_done"
                    class="relative flex h-5 w-5 items-center justify-center rounded-full border-2 border-gray-300 transition-colors group-hover:border-green-500 dark:border-gray-600 dark:group-hover:border-green-500">
                    <svg class="opacity-0 transition-opacity group-hover:opacity-100" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                </span>
                <!-- Done state: gevulde groene cirkel met wit vinkje -->
                <span v-else
                    class="flex h-5 w-5 items-center justify-center rounded-full bg-green-500 shadow-sm">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                </span>
            </button>
        </template>

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

        <div class="flex min-w-0 flex-1 flex-col gap-1">
            {!! view_render_event('admin.components.activities.content.activity.item.title.before') !!}

            <!-- Activity header: title, entity label, deadline pill, and actions on one full-width row -->
            <div class="flex w-full flex-wrap items-center gap-x-2 gap-y-0.5 border-b py-0.5" v-if="activity.title || activity.type != 'system'">
                <template v-if="activity.type !== 'system'">
                    <a class="min-w-0 font-medium hover:underline dark:text-white"
                        :class="{
                            'text-orange-600 dark:text-orange-400': !activity.is_done && isToday(activity.schedule_to) && !isPastDay(activity.schedule_to),
                            'text-status-expired-text dark:text-red-400': !activity.is_done && isPastDay(activity.schedule_to)
                        }"
                        :href="(activity.type === 'email'
                            ? {!! $mailViewPatternJs !!}.replace('__FOLDER__', activity.folder_name || 'inbox').replace('__ID__', String(activity.id))
                            : {!! $activityViewPatternJs !!}.replace('__ID__', String(activity.id)))">
                        @{{ activity.title || 'geen' }}
                    </a>
                </template>

                <template v-else>
                    <span class="min-w-0 font-medium dark:text-white">
                        @{{ activity.title || 'geen' }}
                    </span>

                    <template v-if="activity.additional">
                        <p class="flex items-center gap-1">
                            <span class="break-words">
                                @{{ truncate(activity.additional?.old?.label ? String(activity.additional.old.label).replaceAll('<br>', ' ') : "@lang('admin::app.components.activities.index.empty')", 60) }}
                            </span>

                            <span class="icon-stats-up rotate-90 text-xl"></span>

                            <span class="break-words">
                                @{{ truncate(activity.additional?.new?.label ? String(activity.additional.new.label).replaceAll('<br>', ' ') : "@lang('admin::app.components.activities.index.empty')", 60) }}
                            </span>
                        </p>

                        <p v-if="activity.additional?.link">
                            <a :href="activity.additional.link" class="text-activity-note-text hover:underline"
                                target="_blank">
                                @{{ activity.additional.link_label || 'Bekijk' }}
                            </a>
                        </p>
                    </template>
                </template>

                <!-- Entity source label (shows where the activity originates from in the hierarchy) -->
                <template v-if="activity.entity_source">
                    <a v-if="activity.entity_source.url"
                       :href="activity.entity_source.url"
                       class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-medium transition-opacity hover:opacity-75"
                       :class="getEntitySourceBadgeClass(activity.entity_source.type)"
                       :title="'Ga naar: ' + activity.entity_source.label">
                        @{{ activity.entity_source.label }}
                    </a>
                    <span v-else
                          class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                          :class="getEntitySourceBadgeClass(activity.entity_source.type)"
                          :title="'Activiteit van: ' + activity.entity_source.label">
                        @{{ activity.entity_source.label }}
                    </span>
                </template>

                <span v-if="activity.type !== 'system' && activity.is_published_to_portal"
                      class="inline-flex shrink-0 items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400"
                      title="Gepubliceerd in patiëntportaal">Portaal<template v-if="activity.portal_persons && activity.portal_persons.length">: @{{ activity.portal_persons.map(p => p.name).join(', ') }}</template></span>

                <template v-if="activity.type != 'system'">
                    {!! view_render_event('admin.components.activities.content.activity.item.more_actions.before') !!}

                    <div class="ml-auto flex shrink-0 items-center gap-2">
                        <!-- Deadline pill -->
                        <template v-if="!activity.is_done && activity.schedule_to">
                            <span
                                class="inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-xs font-medium whitespace-nowrap"
                                :class="isPastDay(activity.schedule_to)
                                    ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'"
                            >
                                <span v-if="isPastDay(activity.schedule_to)" class="icon-clock text-sm"></span>
                                <span v-else class="icon-calendar text-sm"></span>
                                <span>
                                    @{{ $admin.formatDate(activity.schedule_to, 'd MMM yyyy', timezone) }}
                                </span>
                            </span>
                        </template>

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
                                                :href="{!! $activityEditPatternJs !!}.replace('__ID__', String(activity.id))">
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
                    </div>

                    {!! view_render_event('admin.components.activities.content.activity.item.more_actions.after') !!}
                </template>
            </div>

            {!! view_render_event('admin.components.activities.content.activity.item.title.after') !!}

            {!! view_render_event('admin.components.activities.content.activity.item.time_and_user.before') !!}

            <!-- Activity User + created date -->
            <div class="flex items-center gap-x-3 text-xs text-gray-500">
                <div v-if="activity.user?.name" class="flex min-w-0 items-center gap-1">
                    <span class="icon-user text-sm"></span>
                    @{{ activity.user.name }}
                </div>
                <span class="ml-auto shrink-0 text-[10.5px] text-gray-400 dark:text-gray-500">
                    Aangemaakt @{{ $admin.formatDate(activity.created_at, 'd MMM', timezone) }}
                </span>
            </div>

            {!! view_render_event('admin.components.activities.content.activity.item.time_and_user.after') !!}

            {!! view_render_event('admin.components.activities.content.activity.item.description.before') !!}

            <!-- Activity Description -->
            <p class="dark:text-white" v-if="activity.comment">
                <span v-if="activity.type === 'email'">
                    @{{ truncateHtmlASSummary(activity.comment, 350) }}
                </span>
{{--                <span v-else v-safe-html="activity.comment"></span>--}}
            </p>

            <!-- Actions timeline (task + call) -->
            <template v-if="['task', 'call'].includes(activity.type) && activity.actions?.length">
                <div class="flex flex-col gap-0.5">
                    <div v-for="(a, i) in activity.actions" :key="i"
                         class="flex items-start gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                        <span class="shrink-0 mt-px text-sm"
                              :class="getActionIconClass(a)"></span>
                        <span class="truncate min-w-0">
                            @{{ $admin.formatDate(a.date_full, 'd MMM HH:mm', timezone) }} ·
                            <strong class="font-semibold text-gray-600 dark:text-gray-300">@{{ a.creator }}</strong>:
                            @{{ a.label }}
                        </span>
                    </div>
                </div>
            </template>
            {!! view_render_event('admin.components.activities.content.activity.item.attachments.after') !!}

        </div>
    </div>

    {!! view_render_event('admin.components.activities.content.activity.item.details.after') !!}
</div>
