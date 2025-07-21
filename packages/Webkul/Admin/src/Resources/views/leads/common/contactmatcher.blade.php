{!! view_render_event('admin.leads.create.contactmatcher.before') !!}

<div class="panel bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
    <div class="panel-header mb-3">
        <h3 class="text-lg font-semibold text-blue-800">Contactpersoon Koppelen</h3>
        <p class="text-sm text-blue-600">Zoek en koppel een bestaande contactpersoon of maak een nieuwe aan</p>
        <div class="mt-2 text-xs text-blue-500">
            <div class="flex items-center gap-1">
                <strong>Matching criteria:</strong>
                <div class="relative inline-block group">
                    <span class="cursor-help underline decoration-dotted hover:text-blue-700">
                        ℹ️
                    </span>
                    <!-- Custom tooltip -->
                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-50">
                        <div class="space-y-1">
                            <div><strong>Naamvelden (90%):</strong></div>
                            <div class="text-gray-300 text-xs pl-2">• voornaam, achternaam, achternaam voorvoegsel</div>
                            <div class="text-gray-300 text-xs pl-2">• getrouwde naam, getrouwde naam voorvoegsel</div>
                            <div class="text-gray-300 text-xs pl-2">• initialen</div>
                            <div class="mt-1"><strong>E-mailadressen (5%)</strong></div>
                            <div class="mt-1"><strong>Telefoonnummers (5%)</strong></div>
                        </div>
                        <!-- Tooltip arrow -->
                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <v-contact-matcher
        :lead='@json($lead ?? new stdClass())'
        :person='@json($lead->person ?? new stdClass())'
    ></v-contact-matcher>
</div>

{!! view_render_event('admin.leads.create.contactmatcher.after') !!}

@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-contact-matcher-template">
        <div>
            <!-- Huidige contactpersoon status -->
            <div class="mb-3 p-2 border rounded" :class="currentPerson ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-semibold text-sm">
                            {{ currentPerson ? 'Huidige contactpersoon:' : 'Geen contactpersoon gekoppeld' }}
                        </span>
                        <div v-if="currentPerson" class="text-sm text-gray-600">
                            <div class="flex items-center gap-2">
                                <a :href="'/admin/contacts/persons/view/' + currentPerson.id"
                                   class="text-blue-600 hover:text-blue-800 underline"
                                   target="_blank">
                                    {{ currentPerson.name }}
                                </a>
                                <!-- View icon -->
                                <a :href="'/admin/contacts/persons/view/' + currentPerson.id"
                                   class="text-blue-600 hover:text-blue-800"
                                   target="_blank"
                                   title="Bekijk contactpersoon"
                                   @click.stop>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                                <!-- Sync icon -->
                                <a :href="'/admin/contacts/persons/edit-with-lead/' + currentPerson.id + '/' + lead.id"
                                   class="text-green-600 hover:text-green-800"
                                   target="_blank"
                                   title="Synchroniseer gegevens"
                                   @click.stop>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </a>
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                <span v-if="currentPerson.email">{{ currentPerson.email }}</span>
                                <span v-if="currentPerson.phone">{{ currentPerson.phone }}</span>
                                <!-- Match score indicator -->
                                <div v-if="currentPersonMatchScore !== null" class="flex items-center gap-1">
                                    <span class="text-xs text-gray-500">Match:</span>
                                    <div class="flex items-center gap-1">
                                        <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all duration-300"
                                                :class="{
                                                    'bg-red-500': currentPersonMatchScore < 50,
                                                    'bg-yellow-500': currentPersonMatchScore >= 50 && currentPersonMatchScore < 80,
                                                    'bg-green-500': currentPersonMatchScore >= 80
                                                }"
                                                :style="{ width: currentPersonMatchScore + '%' }"
                                            ></div>
                                        </div>
                                        <span class="text-xs font-medium"
                                              :class="{
                                                  'text-red-600': currentPersonMatchScore < 50,
                                                  'text-yellow-600': currentPersonMatchScore >= 50 && currentPersonMatchScore < 80,
                                                  'text-green-600': currentPersonMatchScore >= 80
                                              }">
                                            {{ Math.round(currentPersonMatchScore) }}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!currentPerson && lead" class="text-sm text-gray-600 mt-1">
                            <span v-if="lead.first_name || lead.last_name">
                                Lead: {{ (lead.first_name || '') + ' ' + (lead.last_name || '') }}
                            </span>
                            <span v-if="lead.email"> ({{ lead.email }})</span>
                            <span v-if="lead.phone"> - {{ lead.phone }}</span>
                        </div>
                    </div>
                    <div v-if="currentPerson" class="text-xs text-gray-500">
                        <a :href="'/admin/contacts/persons/view/' + currentPerson.id"
                           class="text-blue-600 hover:text-blue-800"
                           target="_blank">
                            Bekijk details →
                        </a>
                    </div>
                    <div v-if="!currentPerson && lead && (lead.first_name || lead.last_name || lead.email || lead.phone)" class="text-xs">
                        <button
                            @click="createPersonFromLead"
                            :disabled="isCreatingPerson"
                            class="text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-2 py-1 rounded text-xs"
                            :class="{ 'opacity-50 cursor-not-allowed': isCreatingPerson }"
                        >
                            {{ isCreatingPerson ? 'Aanmaken...' : 'Contact aanmaken' }}
                        </button>
                    </div>
                </div>
            </div>

            <label class="block font-semibold mb-1">Contactpersoon zoeken</label>

            <!-- Zoekveld -->
            <input
                v-model="search"
                @input="onSearch"
                placeholder="Zoek op naam, e-mail, telefoon..."
                class="input w-full mb-2"
                autocomplete="off"
            />

            <!-- Suggesties -->
            <ul v-if="suggestions.length" class="border rounded bg-white shadow mb-2">
                <li
                    v-for="person in suggestions"
                    :key="person.id"
                    @click="selectPerson(person)"
                    class="px-3 py-2 cursor-pointer hover:bg-gray-100 border-b last:border-b-0"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center">
                                <div class="font-medium">{{ person.name }}</div>
                                <a :href="'/admin/contacts/persons/view/' + person.id"
                                   target="_blank"
                                   class="ml-2 text-blue-600 hover:text-blue-800 text-xs"
                                   title="Bekijk contactpersoon details"
                                   @click.stop>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                </a>
                                <a :href="'/admin/contacts/persons/edit-with-lead/' + person.id + '/' + lead.id"
                                   target="_blank"
                                   class="ml-1 text-green-600 hover:text-green-800 text-xs"
                                   title="Synchroniseer met lead gegevens"
                                   @click.stop>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </a>
                            </div>
                            <div class="text-sm text-gray-600">
                                <span v-if="person.email">{{ person.email }}</span>
                                <span v-if="person.phone && person.email"> • </span>
                                <span v-if="person.phone">{{ person.phone }}</span>
                            </div>
                        </div>
                        <div v-if="person.match_score_percentage" class="ml-3 flex-shrink-0">
                            <div class="flex items-center">
                                <div class="text-xs font-medium text-gray-700 mr-2">
                                    {{ person.match_score_percentage }}% match
                                </div>
                                <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="getScoreColorClass(person.match_score_percentage)"
                                        :style="{ width: person.match_score_percentage + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>

            <!-- Geselecteerde persoon tonen -->
            <div v-if="selectedPerson && selectedPerson.id !== currentPerson?.id" class="mt-2 p-3 border rounded bg-blue-50 border-blue-200">
                <div class="flex items-center justify-between mb-2">
                    <strong class="text-blue-800">Nieuwe selectie:</strong>
                    <div v-if="selectedPerson.match_score_percentage" class="flex items-center">
                        <span class="text-xs font-medium text-blue-700 mr-2">
                            {{ selectedPerson.match_score_percentage }}% match
                        </span>
                        <div class="w-12 h-1.5 bg-blue-200 rounded-full overflow-hidden">
                            <div
                                class="h-full rounded-full"
                                :class="getScoreColorClass(selectedPerson.match_score_percentage)"
                                :style="{ width: selectedPerson.match_score_percentage + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
                <div class="text-sm">
                    <div class="flex items-center">
                        <div class="font-medium text-blue-900">{{ selectedPerson.name }}</div>
                        <a :href="'/admin/contacts/persons/view/' + selectedPerson.id"
                           target="_blank"
                           class="ml-2 text-blue-600 hover:text-blue-800 text-xs"
                           title="Bekijk contactpersoon details">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                        </a>
                        <a :href="'/admin/contacts/persons/edit-with-lead/' + selectedPerson.id + '/' + lead.id"
                           target="_blank"
                           class="ml-1 text-green-600 hover:text-green-800 text-xs"
                           title="Synchroniseer met lead gegevens">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </a>
                    </div>
                    <div class="text-blue-700 mt-1">
                        <span v-if="selectedPerson.email">Email: {{ selectedPerson.email }}</span>
                        <span v-if="selectedPerson.phone && selectedPerson.email"><br></span>
                        <span v-if="selectedPerson.phone">Telefoon: {{ selectedPerson.phone }}</span>
                    </div>
                </div>
            </div>

            <!-- Hidden veld voor formulier -->
            <input type="hidden" name="person_id" :value="selectedPerson?.id || currentPerson?.id || ''" />
        </div>
    </script>

    <script type="module">
        app.component('v-contact-matcher', {
            template: '#v-contact-matcher-template',

            props: ['lead', 'person'],

            data() {
                return {
                    search: '',
                    suggestions: [],
                    selectedPerson: null,
                    currentPerson: this.person && this.person.id ? this.person : null,
                    currentPersonMatchScore: null,
                    searchTimeout: null,
                    isCreatingPerson: false,
                };
            },

            mounted() {
                if (!this.currentPerson) {
                    this.autoSearchFromLead();
                } else {
                    this.calculateCurrentPersonMatchScore();
                }
            },

            methods: {
                autoSearchFromLead() {
                    const terms = [
                        this.lead?.first_name,
                        this.lead?.last_name,
                        this.lead?.email,
                        this.lead?.phone,
                    ]
                        .filter(Boolean)
                        .join(' ');

                    if (!terms) return;

                    this.fetchSuggestionsByLead( this.lead.id);
                },

                onSearch() {
                    clearTimeout(this.searchTimeout);

                    if (!this.search) {
                        this.suggestions = [];
                        return;
                    }

                    this.searchTimeout = setTimeout(() => {
                        this.fetchSuggestions(this.search);
                    }, 300);
                },

                async fetchSuggestionsByLead(leadId) {
                    try {
                        const response = await axios.get('/admin/contacts/persons/searchByLead/' + leadId);
                        this.suggestions = response.data.data || [];
                    } catch (e) {
                        console.warn('Zoekopdracht mislukt:', e);
                        this.suggestions = [];
                    }
                },

                async fetchSuggestions(query) {
                    console.log(query);
                    try {
                        const response = await axios.get('/admin/contacts/persons/search', {
                            params: { query }
                        });

                        // const response = await axios.get('/admin/contacts/persons/search', {
                        //     params: { 'search':query,'searchFields' : 'first_name:like;last_name:like;email:like', 'limit': 10 }
                        // });
                        this.suggestions = response.data.data || [];
                    } catch (e) {
                        console.warn('Zoekopdracht mislukt:', e);
                        this.suggestions = [];
                    }
                },

                selectPerson(person) {
                    this.selectedPerson = person;
                    this.search = '';
                    this.suggestions = [];
                },

                async createPersonFromLead() {
                    if (!this.lead || this.isCreatingPerson) {
                        return;
                    }

                    // Check if we have at least a name or email
                    const hasName = (this.lead.first_name || this.lead.last_name);
                    const hasEmail = this.lead.email;

                    if (!hasName && !hasEmail) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: 'Kan geen contact aanmaken: naam of e-mail is vereist.'
                        });
                        return;
                    }

                    this.isCreatingPerson = true;

                    try {
                        // Prepare person data from lead
                        const personData = {
                            entity_type: 'persons',
                            first_name: this.lead.first_name || '',
                            last_name: this.lead.last_name || '',
                            emails: hasEmail ? [{ value: this.lead.email, label: 'Work' }] : [{ value: `${this.lead.first_name || ''}`.trim() + '-reply@example.com', label: 'Work' }],
                            contact_numbers: this.lead.phone ? [{ value: this.lead.phone, label: 'Work' }] : [{ value: '067433444', label: 'Work' }],
                        };

                        // Ensure contact_numbers array has proper structure
                        if (!personData.contact_numbers || personData.contact_numbers.length === 0) {
                            personData.contact_numbers = [];
                        }

                        // Debug log
                        console.log('Creating person with data:', personData);

                        // Create person via API
                        const response = await axios.post('/admin/contacts/persons/create', personData);

                        if (response.data.data) {
                            const newPerson = response.data.data;

                            // Set the newly created person as current and selected
                            this.currentPerson = newPerson;
                            this.selectedPerson = newPerson;

                            // Update the lead object in the component with the new person_id
                            this.lead.person_id = newPerson.id;
                            this.lead.person = newPerson;

                            // Show success message
                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: 'Contactpersoon succesvol aangemaakt en gekoppeld aan deze lead.'
                            });
                        }
                    } catch (error) {
                        console.error('Fout bij aanmaken contactpersoon:', error);

                        let errorMessage = 'Er is een fout opgetreden bij het aanmaken van de contactpersoon.';

                        if (error.response?.data?.message) {
                            errorMessage = error.response.data.message;
                        } else if (error.response?.data?.errors) {
                            const errors = Object.values(error.response.data.errors).flat();
                            errorMessage = errors.join(', ');
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: errorMessage
                        });
                    } finally {
                        this.isCreatingPerson = false;
                    }
                },

                async calculateCurrentPersonMatchScore() {
                    if (!this.currentPerson || !this.lead) {
                        return;
                    }

                    try {
                        const response = await axios.get('/admin/contacts/persons/searchByLead/' + this.lead.id);
                        const suggestions = response.data.data || [];

                        // Find the current person in the suggestions to get their match score
                        const currentPersonWithScore = suggestions.find(person => person.id === this.currentPerson.id);

                        if (currentPersonWithScore && currentPersonWithScore.match_score_percentage) {
                            this.currentPersonMatchScore = currentPersonWithScore.match_score_percentage;
                        }
                    } catch (error) {
                        console.warn('Kon match score niet berekenen:', error);
                    }
                },

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
                },
            }
        });
    </script>
@endverbatim
@endPushOnce

