@pushOnce('scripts')
    <script>
        window.emailSuggestionsRoutes = {
            lead: '{{ route('admin.leads.search') }}',
            person: '{{ route('admin.contacts.persons.search') }}',
            salesLead: '{{ route('admin.sales-leads.search') }}',
        };
    </script>
@verbatim
    <!-- Email Suggestions Template -->
    <script type="text/x-template" id="v-email-suggestions-template">
        <div>
            <div v-if="isLoading" class="flex items-center justify-center p-4">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-gray-900 dark:border-gray-100"></div>
            </div>
            <div v-else-if="suggestions.length === 0 && !isLoading" class="text-sm text-gray-500 dark:text-gray-400 p-2">
                Geen suggesties gevonden
            </div>
            <div v-else class="flex flex-col gap-2 max-h-64 overflow-y-auto">
                <div
                    v-for="suggestion in suggestions"
                    :key="`${suggestion.type}-${suggestion.id}`"
                    @click="selectSuggestion(suggestion)"
                    class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 bg-white p-3 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
                >
                    <div class="flex h-8 w-8 items-center justify-center rounded-full"
                         :class="{
                             'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-300': suggestion.type === 'lead',
                             'bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-300': suggestion.type === 'sales_lead',
                             'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-300': suggestion.type === 'person'
                         }"
                    >
                        <span class="text-xs font-semibold">
                            {{ (suggestion.name || '').charAt(0).toUpperCase() }}
                        </span>
                    </div>
                    <div class="flex-1">
                        <div class="font-medium text-gray-900 dark:text-gray-100">
                            {{ suggestion.name }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ suggestion.type === 'lead' ? 'Lead' : suggestion.type === 'sales_lead' ? 'Sales Lead' : 'Contact' }}
                            <span v-if="suggestion.stage"> - {{ suggestion.stage.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </script>

    <!-- Email Suggestions Component -->
    <script type="module">
        app.component('v-email-suggestions', {
            template: '#v-email-suggestions-template',
            props: {
                email: Object,
                leadSearchRoute: String,
                personSearchRoute: String,
                salesLeadSearchRoute: String,
            },
            emits: ['link-entity'],
            data() {
                return {
                    suggestions: [],
                    isLoading: false,
                    routes: window.emailSuggestionsRoutes || {
                        lead: this.leadSearchRoute || '',
                        person: this.personSearchRoute || '',
                        salesLead: this.salesLeadSearchRoute || '',
                    },
                };
            },
            mounted() {
                console.log('v-email-suggestions mounted, email:', this.email);
                this.fetchSuggestions();
            },
            
            created() {
                console.log('v-email-suggestions created, email:', this.email);
            },
            methods: {
                async fetchSuggestions() {
                    // Only use server-computed normalized sender email
                    const senderEmail = this.email?.sender_email || '';
                    console.log('Searching suggestions for email:', senderEmail);
                    if (!senderEmail || typeof senderEmail !== 'string') {
                        this.suggestions = [];
                        return;
                    }

                    this.isLoading = true;
                    try {
                        const params = {
                            search: `email:${senderEmail};`,
                            searchFields: 'emails:like;',
                            searchJoin: 'or',
                            limit: 10, // Limit results to prevent performance issues
                        };

                        const [leadsResp, personsResp, salesResp] = await Promise.all([
                            this.$axios.get(this.routes.lead, { params }),
                            this.$axios.get(this.routes.person, { params }),
                            this.$axios.get(this.routes.salesLead, { params }).catch(() => ({ data: { data: [] } })),
                        ]);

                        const leads = (leadsResp.data?.data || []).map(item => ({
                            id: item.id,
                            name: item.name || [item.first_name, item.last_name].filter(Boolean).join(' '),
                            type: 'lead',
                            stage: item.stage ? { id: item.stage.id, name: item.stage.name } : null,
                        }));

                        const persons = (personsResp.data?.data || []).map(item => ({
                            id: item.id,
                            name: item.name || [item.first_name, item.last_name].filter(Boolean).join(' '),
                            type: 'person',
                            stage: null,
                        }));

                        const salesLeads = (salesResp.data?.data || []).map(item => ({
                            id: item.id,
                            name: item.name,
                            type: 'sales_lead',
                            stage: item.pipeline_stage ? { id: item.pipeline_stage.id, name: item.pipeline_stage.name } : null,
                        }));

                        const merged = [...leads, ...salesLeads, ...persons];
                        const uniq = {};
                        merged.forEach(s => { uniq[`${s.type}-${s.id}`] = s; });
                        this.suggestions = Object.values(uniq);
                    } catch (error) {
                        console.error('Error fetching suggestions:', error);
                        this.suggestions = [];
                    } finally {
                        this.isLoading = false;
                    }
                },

                selectSuggestion(suggestion) {
                    this.$emit('link-entity', suggestion);
                },
            },
        });
    </script>
@endverbatim
@endPushOnce


