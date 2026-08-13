@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-sales-lead-suggestion-template">
        <div class="flex items-center justify-between" :title="createdAtTooltip">
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="font-medium">{{ salesLead.name || 'Sales Lead #' + salesLead.id }}</div>
                    <span
                        v-if="salesLead.stage && salesLead.stage.name"
                        class="ml-2 shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="salesLead.stage.is_won
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                            : salesLead.stage.is_lost
                                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                : 'bg-slate-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200'"
                    >{{ salesLead.stage.name }}</span>
                    <span class="ml-2 text-status-active-text text-xs">+ Toevoegen</span>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        if (!app._context.components['v-sales-lead-suggestion']) {
            app.component('v-sales-lead-suggestion', {
                template: '#v-sales-lead-suggestion-template',
                props: ['salesLead'],
                computed: {
                    createdAtTooltip() {
                        if (! this.salesLead.created_at) {
                            return undefined;
                        }

                        return 'Aangemaakt op ' + this.$admin.formatDate(this.salesLead.created_at, 'd MMM yyyy, HH:mm');
                    }
                },
            });
        }
    </script>
@endverbatim
@endPushOnce

