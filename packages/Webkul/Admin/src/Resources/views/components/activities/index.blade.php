@props([
    'endpoint',
    'emailDetachEndpoint' => null,
    'activeType'          => 'action_needed',
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

            <div class="w-full rounded-md border bg-white dark:border-gray-800 dark:bg-gray-900">
                <!-- Main Tabs -->
                <div class="flex gap-4 overflow-x-auto border-b border-gray-200 px-4 dark:border-gray-800">
                    {!! view_render_event('admin.components.activities.content.types.before') !!}

                    <!-- Action Needed Tab -->
                    <div
                        class="flex cursor-pointer items-center gap-2 border-b-2 px-1 py-4 text-sm font-medium transition-all hover:text-gray-800 dark:text-white dark:hover:text-white"
                        :class="selectedType == 'action_needed' ? 'border-brandColor text-brandColor' : 'border-transparent text-gray-600'"
                        @click="selectedType = 'action_needed'"
                    >
                        Actie nodig
                        <span class="flex items-center justify-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-800 dark:bg-gray-950 dark:text-white">
                            @{{ countActionNeeded }}
                        </span>
                    </div>

                    <!-- Inbox Tab -->
                    <div
                        class="flex cursor-pointer items-center gap-2 border-b-2 px-1 py-4 text-sm font-medium transition-all hover:text-gray-800 dark:text-white dark:hover:text-white"
                        :class="selectedType == 'inbox' ? 'border-brandColor text-brandColor' : 'border-transparent text-gray-600'"
                        @click="selectedType = 'inbox'"
                    >
                        Inbox
                        <span class="flex items-center justify-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-800 dark:bg-gray-950 dark:text-white">
                            @{{ countInbox }}
                        </span>
                    </div>

                    <!-- Other Tabs -->
                    <div
                        v-for="type in types"
                        class="flex cursor-pointer items-center gap-2 border-b-2 px-1 py-4 text-sm font-medium transition-all hover:text-gray-800 dark:text-white dark:hover:text-white"
                        :class="selectedType == type.name ? 'border-brandColor text-brandColor' : 'border-transparent text-gray-600'"
                        @click="selectedType = type.name"
                    >
                        @{{ type.label }}
                    </div>

                    {!! view_render_event('admin.components.activities.content.types.after') !!}
                </div>

                <div class="p-4">
                    <!-- Sub Tabs for All -->
                    <div v-if="selectedType == 'all'" class="mb-4 flex gap-2 overflow-x-auto pb-2">
                        <div
                            class="cursor-pointer rounded-full border px-3 py-1.5 text-xs font-medium transition-all hover:bg-gray-100 dark:text-white dark:hover:bg-gray-900"
                            :class="activityTypeFilter == 'all' ? 'bg-gray-100 border-gray-400 dark:bg-gray-800 dark:border-gray-600' : 'bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-800'"
                            @click="activityTypeFilter = 'all'"
                        >
                            @lang('admin::app.components.activities.index.all')
                        </div>

                        <div
                            v-for="type in filterTypes.filter(t => t.name !== 'patient_message')"
                            class="cursor-pointer whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-medium transition-all hover:bg-gray-100 dark:text-white dark:hover:bg-gray-900"
                            :class="activityTypeFilter == type.name ? 'bg-gray-100 border-gray-400 dark:bg-gray-800 dark:border-gray-600' : 'bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-800'"
                            @click="activityTypeFilter = type.name"
                        >
                            @{{ type.label }}
                        </div>
                    </div>

                    <div v-if="selectedType == 'inbox'" class="mb-4 flex gap-2 overflow-x-auto pb-2">
                        <div
                            class="cursor-pointer rounded-full border px-3 py-1.5 text-xs font-medium transition-all hover:bg-gray-100 dark:text-white dark:hover:bg-gray-900"
                            :class="activityTypeFilter == 'all' ? 'bg-gray-100 border-gray-400 dark:bg-gray-800 dark:border-gray-600' : 'bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-800'"
                            @click="activityTypeFilter = 'all'"
                        >
                            @lang('admin::app.components.activities.index.all')
                        </div>

                        <div class="flex gap-2 overflow-x-auto">
                            <div
                                class="cursor-pointer whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-medium transition-all hover:bg-gray-100 dark:text-white dark:hover:bg-gray-900"
                                :class="activityTypeFilter === 'email'
            ? 'bg-gray-100 border-gray-400 dark:bg-gray-800 dark:border-gray-600'
            : 'bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-800'"
                                @click="activityTypeFilter = 'email'"
                            >
                                E-mail
                            </div>

                            <div
                                class="cursor-pointer whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-medium transition-all hover:bg-gray-100 dark:text-white dark:hover:bg-gray-900"
                                :class="activityTypeFilter === 'patient_message'
            ? 'bg-gray-100 border-gray-400 dark:bg-gray-800 dark:border-gray-600'
            : 'bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-800'"
                                @click="activityTypeFilter = 'patient_message'"
                            >
                                Patientberichten
                            </div>
                        </div>

                    </div>

                    <!-- Show Default Activities -->
                    <template v-if="['action_needed', 'inbox', 'planned', 'all', 'system'].includes(selectedType)">
                        <div class="animate-[on-fade_0.5s_ease-in-out]">
                            {!! view_render_event('admin.components.activities.content.activity.list.before') !!}

                            <!-- Activity List -->
                            <div class="flex flex-col gap-4">
                                {!! view_render_event('admin.components.activities.content.activity.item.before') !!}

                                <!-- Activity Item -->
                                @include('admin::components.activities.activity-item')

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
                    default: 'action_needed',
                },

                types: {
                    type: Array,
                    default: [
                        {
                            name: 'planned',
                            label: "{{ trans('admin::app.components.activities.index.planned') }}",
                        },
                        {
                            name: 'all',
                            label: "{{ trans('admin::app.components.activities.index.all') }}",
                        },
                        {
                            name: 'system',
                            label: "{{ trans('admin::app.components.activities.index.change-log') }}",
                        }
                    ],
                },

                filterTypes: {
                    type: Array,
                    default: [
                        @foreach (App\Enums\ActivityType::cases() as $type)
                            @if ($type !== App\Enums\ActivityType::SYSTEM)
                                {
                                    name: '{{ $type->value }}',
                                    label: '{{ $type->label() }}',
                                },
                            @endif
                        @endforeach
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

                    activityTypeFilter: 'all',

                    typeClasses: {
                        email: 'icon-mail bg-activity-email-bg text-activity-email-text dark:!text-activity-email-text',
                        email_unread: 'icon-mail bg-activity-email-bg text-activity-email-text dark:!text-activity-email-text',
                        patient_message: 'icon-patient-message bg-activity-email-bg text-activity-email-text dark:!text-activity-email-text',
                        patient_message_unread: 'icon-patient-message bg-activity-email-bg text-activity-email-text dark:!text-activity-email-text',
                        note: 'icon-note bg-activity-note-bg text-activity-note-text dark:!text-activity-note-text',
                        call: 'icon-call bg-activity-call-bg text-activity-call-text dark:!text-cyan-800',
                        meeting: 'icon-activity bg-activity-task-bg text-activity-task-text dark:!text-activity-task-text',
                        task: 'icon-activity bg-activity-task-bg text-activity-task-text dark:!text-activity-task-text',
                        file: 'icon-file bg-activity-file-bg text-activity-file-text dark:!text-activity-email-text',
                        system: 'icon-system-generate bg-yellow-200 text-yellow-900 dark:!text-yellow-900',
                        default: 'icon-activity bg-activity-task-bg text-activity-task-text dark:!text-activity-task-text',
                    },

                    typeIllustrations: {
                        all: {
                            image: "{{ vite()->asset('images/empty-placeholders/activities.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.all.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.all.description') }}",
                        },
                        // ... other illustrations preserved by Vue logic if unused, but good to have
                        action_needed: {
                            image: "{{ vite()->asset('images/empty-placeholders/activities.svg') }}",
                            title: "Geen acties nodig",
                            description: "Er zijn geen activiteiten die actie vereisen.",
                        },
                        inbox: {
                            image: "{{ vite()->asset('images/empty-placeholders/emails.svg') }}",
                            title: "Inbox is leeg",
                            description: "Er zijn geen nieuwe berichten.",
                        },
                        planned: {
                            image: "{{ vite()->asset('images/empty-placeholders/plans.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.planned.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.planned.description') }}",
                        },
                        system: {
                            image: "{{ vite()->asset('images/empty-placeholders/activities.svg') }}",
                            title: "{{ trans('admin::app.components.activities.index.empty-placeholders.system.title') }}",
                            description: "{{ trans('admin::app.components.activities.index.empty-placeholders.system.description') }}",
                        }
                    },

                    timezone: "{{ config('app.timezone') }}",

                    returnUrl: (typeof window !== 'undefined' ? (window.location.pathname + window.location.search) : ''),
                }
            },

            computed: {
                countActionNeeded() {
                    return this.activities.filter(activity =>
                        ! activity.is_done &&
                        this.isPast(activity.schedule_from) &&
                        ['call', 'meeting', 'task'].includes(activity.type)
                    ).length;
                },

                countInbox() {
                    return this.activities.filter(activity => ['email', 'patient_message'].includes(activity.type)).length;
                },

                filteredActivities() {
                    console.log('selectedType = '+this.selectedType + ', activityTypeFilter = ' + this.activityTypeFilter);
                    // console.log(JSON.parse(JSON.stringify(this.activities)));
                    if (this.selectedType == 'action_needed') {
                         return this.activities
                             .filter(activity => ! activity.is_done && this.isPast(activity.schedule_from))
                            .sort((a, b) => {
                                const aTime = a && a.schedule_from ? new Date(a.schedule_from).getTime() : Infinity;
                                const bTime = b && b.schedule_from ? new Date(b.schedule_from).getTime() : Infinity;
                                return aTime - bTime;
                            });
                    }

                    if (this.selectedType === 'inbox' && this.activityTypeFilter === 'all') {
                        return this.activities
                            .filter(activity => ['email', 'patient_message'].includes(activity.type))
                            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    } else if (this.selectedType === 'inbox') {
                        return this.activities
                            .filter(activity => activity.type === this.activityTypeFilter)
                            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    }

                    if (this.selectedType == 'planned') {
                        return this.activities
                            .filter(activity => ! activity.is_done && !['email', 'patient_message'].includes(activity.type))
                            .slice()
                            .sort((a, b) => {
                                const aTime = a && a.schedule_from ? new Date(a.schedule_from).getTime() : Infinity;
                                const bTime = b && b.schedule_from ? new Date(b.schedule_from).getTime() : Infinity;
                                return aTime - bTime;
                            });
                    }

                    if (this.selectedType == 'system') {
                        return this.activities.filter(activity => activity.type == 'system');
                    }

                    // Default case: 'all'
                    let filtered = this.activities.filter(activity => activity.type !== 'system');

                    // If 'all' tab, respect filter
                    if (this.selectedType == 'all' && this.activityTypeFilter !== 'all') {
                        console.log('filter all and filter on all = ' + this.activityTypeFilter);
                        filtered = filtered.filter(activity => activity.type !== 'system' && activity.type === this.activityTypeFilter);
                    } else if (this.selectedType != 'all' && !['action_needed', 'inbox', 'planned', 'system'].includes(this.selectedType)) {
                         // Fallback for extraTypes
                         console.log('filter fallback = ' + this.activityTypeFilter);
                         filtered = filtered.filter(activity => activity.type == this.selectedType);
                    }

                    return filtered;
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
                    console.log('retrieve acti ' + this.endpoint);
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

                // truncateHtml(html, maxLength = 150) {
                //     if (!html) return '';
                //
                //     const tempDiv = document.createElement('div');
                //     tempDiv.innerHTML = html;
                //     const textContent = tempDiv.textContent || tempDiv.innerText || '';
                //
                //     if (textContent.length <= maxLength) {
                //         return textContent;
                //     }
                //
                //     return textContent.substring(0, maxLength) + '...';
                // },

                truncateHtmlASSummary(html, maxLength = 150) {
                    if (!html) return '';

                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;

                    tempDiv.querySelectorAll('style, script, link').forEach(el => el.remove());

                    const textContent = (tempDiv.textContent || tempDiv.innerText || '').replace(/\s+/g, ' ').trim();

                    if (textContent.length <= maxLength) {
                        return textContent;
                    }

                    return textContent.substring(0, maxLength) + '...';
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

                getTypeClass(activity) {
                    let type = activity.type;

                    if (['email', 'patient_message'].includes(type) && ! activity.is_read) {
                        type += '_unread';
                    }

                    return this.typeClasses[type] ?? this.typeClasses['default'];
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
