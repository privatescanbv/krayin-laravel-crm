@include('adminc.components.person-suggestion')
@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-person-search-template">
        <div class="mb-4">
            <label class="block font-semibold mb-1">Persoon zoeken</label>

            <!-- Zoekveld -->
            <div class="relative">
                <input
                    :value="search"
                    @input="$emit('update:search', $event.target.value)"
                    placeholder="Zoek op naam, e-mail, telefoon..."
                    class="input w-full mb-2"
                    autocomplete="off"
                />
                <!-- Loading spinner -->
                <div v-if="isSearching" class="absolute right-3 top-1/2 transform -translate-y-1/2 -mb-1">
                    <svg class="h-4 w-4 animate-spin text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>

            <!-- Suggesties -->
            <ul v-if="suggestions.length" class="border rounded bg-white shadow mb-2 max-h-60 overflow-y-auto">
                <li
                    v-for="person in suggestions"
                    :key="person.id"
                    @click="$emit('select', person)"
                    class="px-3 py-2 cursor-pointer hover:bg-neutral-bg border-b last:border-b-0"
                >
                    <v-person-suggestion :person="person" />
                </li>
            </ul>

            <!-- Geen resultaten - optie om nieuwe persoon aan te maken -->
            <div v-if="search.length >= 2 && !isSearching && suggestions.length === 0" class="p-3 border rounded bg-activity-note-bg border-activity-note-border">
                <div class="text-center">
                    <div class="text-sm text-blue-700 mb-2">Geen bestaande personen gevonden voor "{{ search }}"</div>
                    <button
                        @click="$emit('create-new')"
                        class="text-activity-note-text hover:text-activity-task-text bg-blue-100 hover:bg-activity-task-bg px-3 py-1 rounded text-sm"
                    >
                        Nieuwe persoon aanmaken: "{{ search }}"
                    </button>
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
        if (!app._context.components['v-person-search']) {
            app.component('v-person-search', {
                template: '#v-person-search-template',
                props: ['search', 'suggestions', 'isSearching'],
                emits: ['update:search', 'select', 'create-new'],
                methods: {}
            });
        }
    </script>
@endverbatim
@endPushOnce


