{!! view_render_event('admin.dashboard.index.patient_portal_sessions.before') !!}

<v-dashboard-patient-portal-sessions>
    <div class="light-shimmer-bg dark:shimmer h-[104px] w-full rounded-lg"></div>
</v-dashboard-patient-portal-sessions>

{!! view_render_event('admin.dashboard.index.patient_portal_sessions.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-dashboard-patient-portal-sessions-template"
    >
        <div class="grid gap-3 rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-base font-semibold dark:text-gray-300">
                Actieve patiëntportaal sessies
            </p>

            <template v-if="isLoading">
                <div class="light-shimmer-bg dark:shimmer h-[52px] w-full rounded-lg"></div>
            </template>

            <template v-else>
                <p
                    class="text-3xl font-bold dark:text-white"
                    v-if="available"
                >
                    @{{ count }}
                </p>

                <p
                    class="text-lg font-semibold text-gray-400 dark:text-gray-500"
                    v-else
                >
                    Niet beschikbaar
                </p>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <template v-if="fetchedAtLabel">
                        Laatst bijgewerkt: @{{ fetchedAtLabel }}
                    </template>
                    <template v-else>
                        Nog geen gegevens opgehaald
                    </template>
                </p>
            </template>
        </div>
    </script>

    <script type="module">
        app.component('v-dashboard-patient-portal-sessions', {
            template: '#v-dashboard-patient-portal-sessions-template',

            data() {
                return {
                    isLoading: true,
                    available: false,
                    count: null,
                    fetchedAt: null,
                    refreshInterval: null,
                }
            },

            computed: {
                fetchedAtLabel() {
                    if (! this.fetchedAt) {
                        return null;
                    }

                    return new Date(this.fetchedAt).toLocaleTimeString('nl-NL', {
                        hour: '2-digit',
                        minute: '2-digit',
                    });
                },
            },

            mounted() {
                this.getSessionCount();

                this.refreshInterval = setInterval(() => this.getSessionCount(), 60000);
            },

            beforeUnmount() {
                clearInterval(this.refreshInterval);
            },

            methods: {
                getSessionCount() {
                    this.$axios.get("{{ route('admin.dashboard.patient-portal-sessions') }}")
                        .then(response => {
                            this.available = response.data.available;
                            this.count = response.data.count;
                            this.fetchedAt = response.data.fetched_at;
                            this.isLoading = false;
                        })
                        .catch(() => {
                            this.available = false;
                            this.isLoading = false;
                        });
                },
            },
        });
    </script>
@endPushOnce
