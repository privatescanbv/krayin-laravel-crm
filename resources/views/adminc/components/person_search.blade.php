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
                    class="px-3 py-2 cursor-pointer hover:bg-gray-100 border-b last:border-b-0"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center">
                                <div class="font-medium">{{ person.name }}</div>
                                <span class="ml-2 text-green-600 text-xs">+ Toevoegen</span>
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
                </li>
            </ul>

            <!-- Geen resultaten - optie om nieuwe persoon aan te maken -->
            <div v-if="search.length >= 2 && !isSearching && suggestions.length === 0" class="p-3 border rounded bg-blue-50 border-blue-200">
                <div class="text-center">
                    <div class="text-sm text-blue-700 mb-2">Geen bestaande personen gevonden voor "{{ search }}"</div>
                    <button
                        @click="$emit('create-new')"
                        class="text-blue-600 hover:text-blue-800 bg-blue-100 hover:bg-blue-200 px-3 py-1 rounded text-sm"
                    >
                        Nieuwe persoon aanmaken: "{{ search }}"
                    </button>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-person-search', {
            template: '#v-person-search-template',
            props: ['search', 'suggestions', 'isSearching'],
            emits: ['update:search', 'select', 'create-new'],
            methods: {
                getScoreColorClass(score) {
                    if (score >= 80) {
                        return 'bg-green-500';
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
    </script>
@endverbatim
@endPushOnce


