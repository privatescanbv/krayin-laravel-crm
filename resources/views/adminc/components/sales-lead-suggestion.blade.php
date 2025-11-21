@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-sales-lead-suggestion-template">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="font-medium">{{ salesLead.name || 'Sales Lead #' + salesLead.id }}</div>
                    <span class="ml-2 text-status-active-text text-xs">+ Toevoegen</span>
                </div>
                <div class="text-sm text-gray-600">
                    <span v-if="salesLead.pipeline_stage">{{ salesLead.pipeline_stage.name }}</span>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        if (!app._context.components['v-sales-lead-suggestion']) {
            app.component('v-sales-lead-suggestion', {
                template: '#v-sales-lead-suggestion-template',
                props: ['salesLead'],
            });
        }
    </script>
@endverbatim
@endPushOnce

