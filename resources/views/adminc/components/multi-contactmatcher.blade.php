{!! view_render_event('admin.leads.multi_contactmatcher.before') !!}

<div class="panel bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
    <div class="panel-header mb-3">
        <div class="mt-2 text-xs text-blue-500">
            <div class="flex items-center gap-1">
                <strong>Matching criteria:</strong>
                <div class="relative inline-block group">
                    <span class="cursor-help underline decoration-dotted hover:text-blue-700">ℹ️</span>
                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-50">
                        <div class="space-y-1">
                            <div><strong>Naamvelden (85%):</strong></div>
                            <div class="text-gray-300 text-xs pl-2">• voornaam, achternaam, achternaam voorvoegsel</div>
                            <div class="text-gray-300 text-xs pl-2">• getrouwde naam, getrouwde naam voorvoegsel</div>
                            <div class="text-gray-300 text-xs pl-2">• initialen, geboortedatum</div>
                            <div class="mt-1"><strong>E-mailadressen (5%)</strong></div>
                            <div class="mt-1"><strong>Telefoonnummers (5%)</strong></div>
                            <div class="mt-1"><strong>Adresgegevens (5%):</strong></div>
                            <div class="text-gray-300 text-xs pl-2">• straat, huisnummer(+toevoeging), postcode, plaats, land</div>
                        </div>
                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <v-multi-contact-matcher
        :lead='@json($lead ?? new stdClass())'
        :existing-persons='@json($persons ?? [])'
    ></v-multi-contact-matcher>
</div>

{!! view_render_event('admin.leads.multi_contactmatcher.after') !!}

@include('adminc.components.person_search')
@include('adminc.components.person-search-helpers')

@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-multi-contact-matcher-template">
        <div>
            <div class="flex gap-4 max-lg:flex-wrap">
                <!-- Left: selected persons and actions -->
                <div class="flex-1 min-w-[320px]">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="font-semibold text-sm">Gekoppelde personen ({{ selectedPersons.length }})</div>
                        <button
                            @click="clearAllPersons"
                            v-if="selectedPersons.length > 0"
                            class="text-red-600 hover:text-red-800 text-xs"
                        >
                            Alles verwijderen
                        </button>
                    </div>

                    <div v-if="selectedPersons.length > 0" class="space-y-2">
                        <div
                            v-for="(person, index) in selectedPersons"
                            :key="person.id"
                            class="p-2 border rounded bg-green-50 border-green-200"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <a :href="'/admin/contacts/persons/view/' + person.id"
                                           class="text-blue-600 hover:text-blue-800 underline font-medium"
                                           target="_blank">
                                            {{ person.name }}
                                        </a>

                                        <a :href="'/admin/leads/sync-lead-to-person/' + lead.id + '/' + person.id"
                                           class="text-green-600 hover:text-green-800"
                                           target="_blank"
                                           title="Gegevens overnemen (lead → person)">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                        </a>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1 text-sm text-gray-600">
                                        <span v-if="person.emails && person.emails.length">{{ person.emails[0].value }}</span>
                                        <span v-if="person.phones && person.phones.length">{{ person.phones[0].value }}</span>

                                        <div v-if="person.match_score_percentage !== null" class="flex items-center gap-1">
                                            <span class="text-xs text-gray-500">Match:</span>
                                            <div class="flex items-center gap-1">
                                                <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                                    <div
                                                        class="h-full rounded-full transition-all duration-300"
                                                        :class="{
                                                            'bg-red-500': person.match_score_percentage < 50,
                                                            'bg-yellow-500': person.match_score_percentage >= 50 && person.match_score_percentage < 80,
                                                            'bg-green-500': person.match_score_percentage >= 80
                                                        }"
                                                        :style="{ width: (person.match_score_percentage || 0) + '%' }"
                                                    ></div>
                                                </div>
                                                <span class="text-xs font-medium"
                                                      :class="{
                                                          'text-red-600': person.match_score_percentage < 50,
                                                          'text-yellow-600': person.match_score_percentage >= 50 && person.match_score_percentage < 80,
                                                          'text-green-600': person.match_score_percentage >= 80
                                                      }">
                                                    {{ Math.round(person.match_score_percentage || 0) }}%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    @click="removePerson(index)"
                                    class="text-red-600 hover:text-red-800 p-1"
                                    title="Verwijder persoon"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedPersons.length === 0" class="p-3 border rounded bg-gray-50 border-gray-200 text-center text-gray-500">
                        Geen personen gekoppeld
                    </div>

                    <div v-if="lead && lead.id && (lead.first_name || lead.last_name || lead.emails || lead.phones) && selectedPersons.length === 0" class="mt-4 p-3 border rounded bg-yellow-50 border-yellow-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-semibold text-sm text-yellow-800">Contact aanmaken van lead gegevens</div>
                                <div class="text-sm text-yellow-700">
                                    <span v-if="lead.first_name || lead.last_name">
                                        {{ (lead.first_name || '') + ' ' + (lead.last_name || '') }}
                                    </span>
                                    <span v-if="lead.emails && lead.emails.length"> ({{ lead.emails[0].value }})</span>
                                    <span v-if="lead.phones && lead.phones.length"> - {{ lead.phones[0].value }}</span>
                                </div>
                            </div>
                            <button
                                @click="createPersonFromLead"
                                :disabled="isCreatingPerson"
                                class="text-yellow-600 hover:text-yellow-800 bg-yellow-100 hover:bg-yellow-200 px-3 py-1 rounded text-sm"
                                :class="{ 'opacity-50 cursor-not-allowed': isCreatingPerson }"
                            >
                                {{ isCreatingPerson ? 'Aanmaken...' : 'Contact aanmaken' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right: search and suggestions -->
                <div class="flex-1 min-w-[320px]">
                    <v-person-search
                        :search="search"
                        :suggestions="suggestions"
                        :is-searching="isSearching"
                        @update:search="(val) => { search = val; onSearch(); }"
                        @select="addPerson"
                        @create-new="createNewPerson"
                    ></v-person-search>
                </div>
            </div>

            <!-- Hidden form fields -->
            <input
                v-for="(person, index) in selectedPersons"
                :key="person.id"
                type="hidden"
                :name="'person_ids[' + index + ']'"
                :value="person.id"
            />
            <!-- Ensure key exists when list is empty so backend can detach all -->
            <input
                v-if="selectedPersons.length === 0"
                type="hidden"
                name="person_ids[]"
                value=""
            />
        </div>
    </script>

    <script type="module">
        app.component('v-multi-contact-matcher', {
            template: '#v-multi-contact-matcher-template',

            props: ['lead', 'existingPersons'],

                         data() {
                 return {
                     search: '',
                     suggestions: [],
                     selectedPersons: [...(this.existingPersons || [])],
                     searchTimeout: null,
                     isSearching: false,
                     isCreatingPerson: false,
                 };
             },

            mounted() {
                // Expose for external integrations (e.g., edit lead auto-suggest linking)
                window.multiContactMatcher = this;

                // Calculate match scores for existing persons
                this.calculateExistingMatchScores();
            },

            methods: {
                onSearch() {
                    clearTimeout(this.searchTimeout);

                    if (this.search.length < 2) {
                        this.suggestions = [];
                        return;
                    }

                    this.searchTimeout = setTimeout(() => {
                        this.fetchSuggestions(this.search);
                    }, 300);
                },

                async fetchSuggestions(query) {
                    this.isSearching = true;
                    try {
                        const results = await (window.adminc && window.adminc.fetchPersons
                            ? window.adminc.fetchPersons(query, { leadId: this.lead && this.lead.id })
                            : []);

                        this.suggestions = (results || []).filter(person => !this.isPersonSelected(person.id));
                    } catch (e) {
                        console.error('Zoekopdracht mislukt:', e);
                        this.suggestions = [];
                    } finally {
                        this.isSearching = false;
                    }
                },

                addPerson(person) {
                    if (!this.isPersonSelected(person.id)) {
                        const enriched = Object.assign({}, person);
                        if ((!enriched.first_name || !enriched.last_name) && enriched.name) {
                            const parts = String(enriched.name).trim().split(/\s+/);
                            enriched.first_name = enriched.first_name || (parts[0] || '');
                            enriched.last_name = enriched.last_name || (parts.slice(1).join(' ') || '');
                        }
                        if (!Array.isArray(enriched.emails)) { enriched.emails = []; }
                        if (!Array.isArray(enriched.phones)) { enriched.phones = []; }
                        this.selectedPersons.push(enriched);

                        // Clear search and suggestions after adding
                        this.search = '';
                        this.suggestions = [];

                        // Notify parent safely
                        if (typeof window.handlePersonsUpdated === 'function') {
                            window.handlePersonsUpdated(this.selectedPersons);
                        } else if (window.leadFormComponent && typeof window.leadFormComponent.updateFormDataFromPersons === 'function') {
                            window.leadFormComponent.persons = this.selectedPersons;
                            window.leadFormComponent.updateFormDataFromPersons();
                        }
                    }
                },

                removePerson(index) {
                    const person = this.selectedPersons[index];

                    if (confirm(`Weet je zeker dat je ${person.name} wilt ontkoppelen?`)) {
                        // If this is an existing person on an existing lead, call detach API
                        if (this.lead && this.lead.id && person.id) {
                            this.detachPersonFromLead(person.id);
                        }

                        this.selectedPersons.splice(index, 1);

                        // Emit event for parent components to listen to
                        this.$emit('person-removed', person);
                        this.$emit('persons-updated', this.selectedPersons);

                        // Also call the lead form component directly if available
                        if (window.leadFormComponent && typeof window.leadFormComponent.updateFormDataFromPersons === 'function') {
                            window.leadFormComponent.persons = this.selectedPersons;
                            window.leadFormComponent.updateFormDataFromPersons();
                        }
                    }
                },

                clearAllPersons() {
                    if (confirm('Weet je zeker dat je alle personen wilt ontkoppelen?')) {
                        this.selectedPersons = [];

                        // Emit event for parent components to listen to
                        this.$emit('persons-updated', this.selectedPersons);

                        // Also call the lead form component directly if available
                        if (window.leadFormComponent && typeof window.leadFormComponent.updateFormDataFromPersons === 'function') {
                            window.leadFormComponent.persons = this.selectedPersons;
                            window.leadFormComponent.updateFormDataFromPersons();
                        }
                    }
                },

                isPersonSelected(personId) {
                    return this.selectedPersons.some(p => p.id === personId);
                },

                async calculateExistingMatchScores() {
                    if (!this.lead || !this.lead.id || this.selectedPersons.length === 0) return;

                    try {
                        const response = await axios.get(`/admin/contacts/persons/searchByLead/${this.lead.id}`);
                        const personsWithScores = response.data.data || [];

                        // Update match scores for existing persons
                        this.selectedPersons.forEach((person, index) => {
                            const personWithScore = personsWithScores.find(p => p.id === person.id);
                            if (personWithScore && personWithScore.match_score_percentage) {
                                this.selectedPersons[index].match_score_percentage = personWithScore.match_score_percentage;
                            }
                        });
                    } catch (error) {
                        console.warn('Kon match scores niet berekenen:', error);
                    }
                },

                async createPersonFromLead() {
                     if (!this.lead || this.isCreatingPerson) {
                         return;
                     }

                     // Check if we have at least a name or email
                     const hasName = (this.lead.first_name || this.lead.last_name);
                     const hasEmail = this.lead.emails && this.lead.emails.length > 0;

                     if (!hasName && !hasEmail) {
                         alert('Kan geen contact aanmaken: naam of e-mail is vereist.');
                         return;
                     }

                     this.isCreatingPerson = true;

                     try {
                         const personData = {
                             entity_type: 'persons',
                             first_name: this.lead.first_name || '',
                             last_name: this.lead.last_name || '',
                             lastname_prefix: this.lead.lastname_prefix || '',
                             married_name: this.lead.married_name || '',
                             married_name_prefix: this.lead.married_name_prefix || '',
                             initials: this.lead.initials || '',
                             date_of_birth: this.formatDateToDutch(this.lead.date_of_birth),
                             gender: this.lead.gender || '',
                             salutation: this.lead.salutation || '',
                             emails: this.lead.emails || [],
                             phones: this.lead.phones || []
                         };

                         const response = await axios.post('/admin/contacts/persons/create', personData);

                                                 if (response.data && response.data.data) {
                            const newPerson = response.data.data;

                            // Add to selected persons
                            this.selectedPersons.push(newPerson);

                            // Emit event for parent components to listen to
                            this.$emit('person-added', newPerson);
                            this.$emit('persons-updated', this.selectedPersons);

                            // Also call the lead form component directly if available
                            if (window.leadFormComponent && typeof window.leadFormComponent.updateFormDataFromPersons === 'function') {
                                window.leadFormComponent.persons = this.selectedPersons;
                                window.leadFormComponent.updateFormDataFromPersons();
                            }

                            alert('Persoon succesvol aangemaakt en gekoppeld aan deze lead.');
                        }
                     } catch (error) {
                         console.error('Fout bij aanmaken Persoon:', error);

                         let errorMessage = 'Er is een fout opgetreden bij het aanmaken van de Persoon.';
                         if (error.response?.data?.message) {
                             errorMessage = error.response.data.message;
                         } else if (error.response?.data?.errors) {
                             const errors = Object.values(error.response.data.errors).flat();
                             errorMessage = errors.join(', ');
                         }

                         alert(errorMessage);
                     } finally {
                         this.isCreatingPerson = false;
                     }
                 },

                 async createNewPerson() {
                     if (!this.search || this.search.length < 2) {
                         return;
                     }

                     this.isCreatingPerson = true;

                     try {
                         // Parse the search term for name parts
                         const nameParts = this.search.trim().split(' ');
                         const firstName = nameParts[0] || '';
                         const lastName = nameParts.slice(1).join(' ') || '';

                         const personData = {
                             entity_type: 'persons',
                             first_name: firstName,
                             last_name: lastName,
                             emails: [],
                             phones: []
                         };

                         const response = await axios.post('/admin/contacts/persons/create', personData);

                                                 if (response.data && response.data.data) {
                            const newPerson = response.data.data;

                            // Add to selected persons
                            this.selectedPersons.push(newPerson);

                            // Clear search
                            this.search = '';
                            this.suggestions = [];

                            // Emit event for parent components to listen to
                            this.$emit('person-added', newPerson);
                            this.$emit('persons-updated', this.selectedPersons);

                            // Also call the lead form component directly if available
                            if (window.leadFormComponent && typeof window.leadFormComponent.updateFormDataFromPersons === 'function') {
                                window.leadFormComponent.persons = this.selectedPersons;
                                window.leadFormComponent.updateFormDataFromPersons();
                            }

                            alert(`Nieuwe persoon "${newPerson.name}" succesvol aangemaakt en gekoppeld.`);
                        }
                     } catch (error) {
                         console.error('Fout bij aanmaken nieuwe persoon:', error);

                         let errorMessage = 'Er is een fout opgetreden bij het aanmaken van de nieuwe Persoon.';
                         if (error.response?.data?.message) {
                             errorMessage = error.response.data.message;
                         } else if (error.response?.data?.errors) {
                             const errors = Object.values(error.response.data.errors).flat();
                             errorMessage = errors.join(', ');
                         }

                         alert(errorMessage);
                     } finally {
                         this.isCreatingPerson = false;
                     }
                 },

                 async detachPersonFromLead(personId) {
                     try {
                         await fetch(`/admin/leads/${this.lead.id}/detach-person/${personId}`, {
                             method: 'DELETE',
                             headers: {
                                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                 'Content-Type': 'application/json'
                             }
                         });
                     } catch (error) {
                         console.error('Error detaching person:', error);
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

                formatDateToDutch(value) {
                    if (!value) {
                        return '';
                    }
                    // Already in dd-mm-yyyy
                    if (/^\d{2}-\d{2}-\d{4}$/.test(value)) {
                        return value;
                    }
                    // ISO 8601 -> take date part
                    const isoDate = (String(value).match(/^(\d{4}-\d{2}-\d{2})/) || [])[1];
                    const datePart = isoDate || String(value);
                    const m = datePart.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                    if (m) {
                        return `${m[3]}-${m[2]}-${m[1]}`;
                    }
                    // Fallback parse
                    try {
                        const d = new Date(value);
                        if (!isNaN(d.getTime())) {
                            const dd = String(d.getDate()).padStart(2, '0');
                            const mm = String(d.getMonth() + 1).padStart(2, '0');
                            const yyyy = d.getFullYear();
                            return `${dd}-${mm}-${yyyy}`;
                        }
                    } catch (e) {}
                    return '';
                }
            }
        });
    </script>
@endverbatim
@endPushOnce
