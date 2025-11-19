@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-lead-suggestion-template">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="font-medium">{{ lead.name || (lead.first_name && lead.last_name ? `${lead.first_name} ${lead.last_name}` : 'Lead #' + lead.id) }}</div>
                    <span class="ml-2 text-succes text-xs">+ Toevoegen</span>
                </div>
                <div class="text-sm text-gray-600">
                    <span v-if="lead.emails && lead.emails.length">{{ lead.emails[0].value }}</span>
                    <span v-if="lead.phones && lead.phones.length"> • {{ lead.phones[0].value }}</span>
                </div>
            </div>
            <div v-if="lead.match_score_percentage" class="ml-3 flex-shrink-0">
                <div class="flex items-center">
                    <div class="text-xs font-medium text-gray-700 mr-2">
                        {{ Math.round(lead.match_score_percentage || 0) }}% match
                    </div>
                    <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="getScoreColorClass(lead.match_score_percentage)"
                            :style="{ width: (lead.match_score_percentage || 0) + '%' }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        if (!app._context.components['v-lead-suggestion']) {
            app.component('v-lead-suggestion', {
                template: '#v-lead-suggestion-template',
                props: ['lead'],
                methods: {
                    getScoreColorClass(score) {
                        if (score >= 80) {
                            return 'bg-succes';
                        } else if (score >= 60) {
                            return 'bg-yellow-500';
                        } else if (score >= 40) {
                            return 'bg-orange-500';
                        } else {
                            return 'bg-red-500';
                        }
                    }
                }
            });
        }
    </script>
@endverbatim
@endPushOnce

