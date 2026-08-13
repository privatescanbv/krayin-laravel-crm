@pushOnce('scripts')
    <script>
        window.emailSuggestionsRoutes = {
            lead: '{{ route('admin.leads.search') }}',
            person: '{{ route('admin.contacts.persons.search') }}',
            salesLead: '{{ route('admin.sales-leads.search') }}',
            order: '{{ route('admin.orders.search') }}',
        };
    </script>
@verbatim
    <!-- Email Suggestions Template -->
    <script type="text/x-template" id="v-email-suggestions-template">
        <div>
            <div v-if="isLoading" class="flex items-center justify-center p-4">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-gray-900 dark:border-gray-100"></div>
            </div>
            <div v-else-if="suggestions.length > 0" class="flex flex-col gap-2 max-h-64 overflow-y-auto">
                <div
                    v-for="suggestion in suggestions"
                    :key="`${suggestion.type}-${suggestion.id}`"
                    @click="selectSuggestion(suggestion)"
                    class="flex cursor-pointer items-center gap-3 rounded-md border p-3 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
                >
                    <div class="flex h-8 w-8 items-center justify-center rounded-full"
                         :class="{
                             'bg-blue-100 text-activity-note-text dark:bg-blue-900 dark:text-blue-300': suggestion.type === 'lead',
                             'bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-300': suggestion.type === 'sales_lead',
                             'bg-green-100 text-status-active-text dark:bg-green-900 dark:text-green-300': suggestion.type === 'person',
                             'bg-orange-100 text-orange-600 dark:bg-orange-900 dark:text-orange-300': suggestion.type === 'order'
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
                            {{ suggestion.type === 'lead' ? 'Lead' : suggestion.type === 'sales_lead' ? 'Sales' : suggestion.type === 'order' ? 'Order' : 'Contact' }}
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
                orderSearchRoute: String,
            },
            emits: ['link-entity', 'loaded'],
            data() {
                return {
                    suggestions: [],
                    isLoading: false,
                    routes: window.emailSuggestionsRoutes || {
                        lead: this.leadSearchRoute || '',
                        person: this.personSearchRoute || '',
                        salesLead: this.salesLeadSearchRoute || '',
                        order: this.orderSearchRoute || '',
                    },
                };
            },
            mounted() {
                this.fetchSuggestions();
            },

            methods: {
                async fetchSuggestions() {
                    // Only use server-computed normalized sender email
                    const senderEmail = this.email?.sender_email || '';
                    console.log('Searching suggestions for email:', senderEmail);
                    if (!senderEmail || typeof senderEmail !== 'string') {
                        this.suggestions = [];
                        this.$emit('loaded', 0);
                        return;
                    }

                    this.isLoading = true;
                    try {
                        // Email search params for leads and persons
                        const emailParams = {
                            search: `email:${senderEmail};`,
                            searchFields: 'emails:like;',
                            searchJoin: 'or',
                            limit: 10, // Limit results to prevent performance issues
                        };

                        // Sales leads only support name search, not email search
                        // So we skip sales leads search when searching by email
                        const [leadsResp, personsResp, salesResp] = await Promise.all([
                            this.$axios.get(this.routes.lead, { params: emailParams }),
                            this.$axios.get(this.routes.person, { params: emailParams }),
                            // Skip sales leads search for email - sales leads don't have email field
                            Promise.resolve({ data: { data: [] } }),
                        ]);

                        const leads = (leadsResp.data?.data || []).map(item => ({
                            id: item.id,
                            name: item.name || [item.first_name, item.last_name].filter(Boolean).join(' '),
                            type: 'lead',
                            stage: item.stage ? { id: item.stage.id, name: item.stage.name, is_won: item.stage.is_won, is_lost: item.stage.is_lost } : null,
                            created_at: item.created_at,
                        }));

                        const persons = (personsResp.data?.data || []).map(item => ({
                            id: item.id,
                            name: item.name || [item.first_name, item.last_name].filter(Boolean).join(' '),
                            type: 'person',
                            stage: null,
                            created_at: item.created_at,
                        }));

                        const salesLeads = (salesResp.data?.data || []).map(item => ({
                            id: item.id,
                            name: item.name,
                            type: 'sales_lead',
                            stage: item.stage ? { id: item.stage.id, name: item.stage.name, is_won: item.stage.is_won, is_lost: item.stage.is_lost } : null,
                            created_at: item.created_at,
                        }));

                        // Open orders for the leads found above (e.g. via a linked sales lead) —
                        // so an existing order isn't missed just because the mail isn't linked yet.
                        const leadIds = leads.map(lead => lead.id);
                        let orders = [];
                        if (leadIds.length && this.routes.order) {
                            try {
                                const ordersResp = await this.$axios.get(this.routes.order, {
                                    params: { lead_id: leadIds, limit: 10 },
                                });
                                orders = (ordersResp.data?.data || []).map(item => ({
                                    id: item.id,
                                    name: item.name,
                                    type: 'order',
                                    stage: item.stage ? { id: item.stage.id, name: item.stage.name } : null,
                                    created_at: item.created_at,
                                }));
                            } catch (error) {
                                console.error('Error fetching order suggestions:', error);
                            }
                        }

                        const merged = [...leads, ...salesLeads, ...orders, ...persons];
                        const uniq = {};
                        merged.forEach(s => { uniq[`${s.type}-${s.id}`] = s; });

                        // Active/ongoing suggestions first, most recently created first —
                        // same ordering as the manual lead/sales lead linking dropdowns.
                        this.suggestions = Object.values(uniq).sort((a, b) => {
                            const aClosed = a.stage?.is_won || a.stage?.is_lost ? 1 : 0;
                            const bClosed = b.stage?.is_won || b.stage?.is_lost ? 1 : 0;

                            if (aClosed !== bClosed) {
                                return aClosed - bClosed;
                            }

                            return new Date(b.created_at) - new Date(a.created_at);
                        });
                    } catch (error) {
                        console.error('Error fetching suggestions:', error);
                        this.suggestions = [];
                    } finally {
                        this.isLoading = false;
                        this.$emit('loaded', this.suggestions.length);
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


