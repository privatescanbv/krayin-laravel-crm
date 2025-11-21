@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-person-suggestion-template">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="font-medium">{{ person.name }}</div>
                    <span class="ml-2 text-status-active-text text-xs">+ Toevoegen</span>
                </div>
                <div class="text-sm text-gray-600">
                    <span v-if="person.emails && person.emails.length">{{ person.emails[0].value }}</span>
                    <span v-if="person.phones && person.phones.length"> • {{ person.phones[0].value }}</span>
                </div>
            </div>
            <div v-if="person.match_score_percentage" class="ml-3 flex-shrink-0">
                <div class="flex items-center">
                    <div class="text-xs font-medium text-gray-700 mr-2">
                        {{ Math.round(person.match_score_percentage || 0) }}% match
                    </div>
                    <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="getScoreColorClass(person.match_score_percentage)"
                            :style="{ width: (person.match_score_percentage || 0) + '%' }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        if (!app._context.components['v-person-suggestion']) {
            app.component('v-person-suggestion', {
                template: '#v-person-suggestion-template',
                props: ['person'],
                methods: {
                    getScoreColorClass(score) {
                        if (score >= 80) {
                            return 'bg-succes';
                        } else if (score >= 60) {
                            return 'bg-status-on_hold-text';
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


