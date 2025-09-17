@props([
    'endpoint' => '',
    'title' => 'E-mails',
])

<div class="flex flex-col gap-2">
    <p class="text-base font-semibold dark:text-white">{{ $title }}</p>

    <v-email-feed endpoint="{{ $endpoint }}">
        <div class="flex items-center justify-center py-6">
            <x-admin::spinner />
        </div>
    </v-email-feed>
</div>

@pushOnce('scripts')
    <script type="text/x-template" id="v-email-feed-template">
        <div class="flex flex-col gap-2">
            <template v-if="isLoading">
                <slot />
            </template>

            <template v-else>
                <div v-if="emails.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                    Geen e-mails gevonden.
                </div>

                <div v-else class="flex flex-col divide-y divide-gray-100 dark:divide-gray-800 rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                    <div v-for="email in emails" :key="email.__key" class="flex items-start justify-between gap-2 px-3 py-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="icon-mail text-blue-600 text-xs"></span>
                                <a :href="mailViewUrl(email)" target="_blank" class="font-medium text-sm text-gray-900 dark:text-gray-100 hover:underline truncate">
                                    @{{ email.subject || 'Geen onderwerp' }}
                                </a>
                                <span v-if="email && (email.is_read === 0 || email.is_read === false || email.is_read === '0')" class="inline-block h-1.5 w-1.5 rounded-full bg-sky-600 ml-1 dark:bg-white"></span>
                            </div>
                            <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                <span>@{{ formatDate(email.created_at) }}</span>
                                <span class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-[10px] text-gray-700 dark:text-gray-300">
                                    @{{ email.__source_label }}
                                </span>
                            </div>
                        </div>

                        <a :href="mailViewUrl(email)" target="_blank" class="flex-shrink-0 ml-2 flex h-6 w-6 items-center justify-center rounded-md text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300" title="E-mail bekijken">
                            <span class="icon-right-arrow text-xs"></span>
                        </a>
                    </div>
                </div>
            </template>
        </div>
    </script>

    <script type="module">
        app.component('v-email-feed', {
            template: '#v-email-feed-template',
            props: {
                endpoint: { type: String, required: true },
            },
            data() {
                return {
                    isLoading: true,
                    emails: [],
                };
            },
            mounted() {
                this.fetch();
            },
            methods: {
                fetch() {
                    this.isLoading = true;
                    this.$axios.get(this.endpoint)
                        .then((response) => {
                            const activities = response?.data?.data || [];
                            const aggregated = [];

                            activities.forEach((activity) => {
                                // If activity itself is an email-type, include it
                                if (activity.type === 'email') {
                                    aggregated.push({
                                        __key: `act-${activity.id}`,
                                        id: activity.id,
                                        subject: activity.title || activity.subject,
                                        created_at: activity.created_at,
                                        is_read: activity.is_read ?? 0,
                                        folders: activity.additional?.folders || ['inbox'],
                                        __source_label: 'Activiteit',
                                    });
                                }

                                // Include any emails attached to the activity
                                if (Array.isArray(activity.emails)) {
                                    activity.emails.forEach((em) => {
                                        aggregated.push({
                                            __key: `em-${em.id}`,
                                            id: em.id,
                                            subject: em.subject,
                                            created_at: em.created_at,
                                            is_read: em.is_read,
                                            folders: em.folders || ['inbox'],
                                            __source_label: 'Activiteit',
                                        });
                                    });
                                }
                            });

                            // Sort by created_at desc
                            aggregated.sort((a, b) => (new Date(b.created_at) - new Date(a.created_at)));

                            this.emails = aggregated;
                        })
                        .finally(() => this.isLoading = false);
                },

                mailViewUrl(email) {
                    const folder = Array.isArray(email.folders) && email.folders.length ? email.folders[0] : 'inbox';
                    return `{{ route('admin.mail.view', ['route' => 'INBOX', 'id' => 'EMAIL_ID']) }}`
                        .replace('INBOX', folder)
                        .replace('EMAIL_ID', email.id);
                },

                formatDate(value) {
                    try {
                        return this.$admin.formatDate(value, 'd MMM yyyy, h:mm', '{{ config('app.timezone') }}');
                    } catch (e) {
                        return value;
                    }
                },
            },
        });
    </script>
@endPushOnce

