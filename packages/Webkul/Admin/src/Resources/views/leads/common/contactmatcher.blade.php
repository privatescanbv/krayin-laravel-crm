{!! view_render_event('admin.leads.create.contactmatcher.before') !!}

<v-contact-matcher
    :lead='@json($lead ?? new stdClass())'
    :person='@json($lead->person ?? new stdClass())'
></v-contact-matcher>


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
                            <a :href="'/admin/contacts/persons/view/' + currentPerson.id"
                               class="text-blue-600 hover:text-blue-800 underline"
                               target="_blank">
                                {{ currentPerson.name }}
                            </a>
                            <span v-if="currentPerson.email"> ({{ currentPerson.email }})</span>
                            <span v-if="currentPerson.phone"> - {{ currentPerson.phone }}</span>
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
                    class="px-3 py-2 cursor-pointer hover:bg-gray-100"
                >
                    {{ person.name }}
                    <span v-if="person.email"> ({{ person.email }})</span>
                    <span v-if="person.phone"> {{ person.phone }}</span>
                </li>
            </ul>

            <!-- Geselecteerde persoon tonen -->
            <div v-if="selectedPerson && selectedPerson.id !== currentPerson?.id" class="mt-2 p-2 border rounded bg-blue-50 border-blue-200">
                <strong>Nieuwe selectie:</strong> {{ selectedPerson.name }}<br>
                <span v-if="selectedPerson.email">Email: {{ selectedPerson.email }}</span><br>
                <span v-if="selectedPerson.phone">Telefoon: {{ selectedPerson.phone }}</span>
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
                    searchTimeout: null,
                    isCreatingPerson: false,
                };
            },

            mounted() {
                if (!this.currentPerson) {
                    this.autoSearchFromLead();
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
            }
        });
    </script>
@endverbatim
@endPushOnce

