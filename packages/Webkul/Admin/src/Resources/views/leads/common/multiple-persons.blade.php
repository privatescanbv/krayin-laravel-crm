{!! view_render_event('admin.leads.multiple_persons.before') !!}

<v-multiple-persons-component 
    :data="@json($persons ?? [])" 
    :lead-id="{{ $leadId ?? 'null' }}"
></v-multiple-persons-component>

{!! view_render_event('admin.leads.multiple_persons.after') !!}

@pushOnce('scripts')
    <script type="text/x-template" id="v-multiple-persons-component-template">
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold dark:text-white">
                    Contactpersonen (@{{ persons.length }})
                </h3>
                <button
                    @click="addPerson"
                    type="button"
                    class="secondary-button"
                >
                    <i class="icon-plus text-xs"></i>
                    Toevoegen
                </button>
            </div>

            <!-- Persons List -->
            <div v-if="persons.length > 0" class="space-y-2">
                <div
                    v-for="(person, index) in persons"
                    :key="index"
                    class="flex items-center justify-between p-3 rounded-lg border"
                    :class="getPersonCardClass(person)"
                >
                    <div class="flex items-center gap-3 flex-1">
                        <!-- Person Avatar -->
                        <div 
                            class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-semibold"
                            :class="getAvatarClass(person)"
                        >
                            @{{ getPersonInitials(person) }}
                        </div>

                        <!-- Person Info -->
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-sm dark:text-white">
                                    @{{ person.name || 'Nieuwe persoon' }}
                                </span>
                                
                                <!-- Match Percentage -->
                                <div v-if="person.match_percentage" class="flex items-center gap-2">
                                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                        @{{ person.match_percentage }}% match
                                    </span>
                                    <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all duration-300"
                                            :class="getScoreBarClass(person.match_percentage)"
                                            :style="{ width: person.match_percentage + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Organization -->
                            <div v-if="person.organization" class="text-xs text-gray-500 dark:text-gray-400">
                                @{{ person.organization.name }}
                            </div>
                        </div>

                        <!-- Person Lookup (for new persons) -->
                        <div v-if="!person.id" class="flex-1 max-w-xs">
                            <x-admin::lookup
                                ::src="`{{ route('admin.contacts.persons.search') }}`"
                                ::name="`person_ids[${index}]`"
                                :label="'Naam'"
                                placeholder="Zoek persoon..."
                                @on-selected="(selectedPerson) => updatePerson(index, selectedPerson)"
                                :can-add-new="true"
                            />
                        </div>
                    </div>

                                            <!-- Actions -->
                        <div class="flex items-center gap-1">
                            <!-- Edit with Lead (if existing person) -->
                            <a 
                                v-if="person.id && leadId"
                                :href="`/admin/contacts/persons/edit-with-lead/${person.id}/${leadId}`"
                                target="_blank"
                                class="text-green-600 hover:text-green-800 p-1"
                                title="Synchroniseer persoon met lead"
                            >
                                <i class="icon-sync text-sm"></i>
                            </a>

                            <!-- View Person (if existing) -->
                            <a 
                                v-if="person.id"
                                :href="`/admin/contacts/persons/view/${person.id}`"
                                target="_blank"
                                class="text-blue-600 hover:text-blue-800 p-1"
                                title="Bekijk persoon"
                            >
                                <i class="icon-eye text-sm"></i>
                            </a>

                            <!-- Remove Person -->
                            <button
                                @click="removePerson(index)"
                                type="button"
                                class="text-red-600 hover:text-red-800 p-1"
                                title="Verwijder persoon"
                            >
                                <i class="icon-trash text-sm"></i>
                            </button>
                        </div>

                    <!-- Hidden form fields -->
                    <input
                        type="hidden"
                        ::name="`person_ids[${index}]`"
                        ::value="person.id"
                        v-if="person.id"
                    />
                    <input
                        type="hidden"
                        ::name="`persons[${index}][id]`"
                        ::value="person.id"
                        v-if="person.id"
                    />
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="persons.length === 0" class="text-center py-6 text-gray-500 dark:text-gray-400 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                <i class="icon-users text-3xl mb-2"></i>
                <p class="font-medium">Geen contactpersonen gekoppeld</p>
                <p class="text-sm">Klik op "Toevoegen" om contactpersonen te koppelen</p>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-multiple-persons-component', {
            template: '#v-multiple-persons-component-template',

            props: ['data', 'leadId'],

            data() {
                console.log('Multiple-persons component initialized with data:', this.data);
                return {
                    persons: this.data || []
                };
            },

            async mounted() {
                console.log('Multiple-persons component mounted:', {
                    'leadId': this.leadId,
                    'persons.length': this.persons.length,
                    'persons': this.persons
                });
                
                // Calculate match percentages for existing persons
                if (this.leadId) {
                    for (let i = 0; i < this.persons.length; i++) {
                        if (this.persons[i].id && !this.persons[i].match_percentage) {
                            console.log(`Calculating match for person ${i}:`, this.persons[i]);
                            const matchPercentage = await this.calculateMatchPercentage(this.persons[i]);
                            if (matchPercentage !== null) {
                                this.$set(this.persons[i], 'match_percentage', matchPercentage);
                            }
                        }
                    }
                }
            },

            watch: {
                persons: {
                    handler(newValue) {
                        this.$emit('update:data', newValue);
                        
                        // Also update global variable for form access
                        if (window.leadFormPersons !== undefined) {
                            window.leadFormPersons = newValue;
                        }
                    },
                    deep: true
                }
            },

            methods: {
                addPerson() {
                    this.persons.push({
                        id: null,
                        name: '',
                        match_percentage: null,
                        organization: null
                    });
                },

                async removePerson(index) {
                    const person = this.persons[index];
                    
                    // If it's an existing person relationship, confirm deletion
                    if (person.id && this.leadId) {
                        if (!confirm(`Weet je zeker dat je ${person.name} wilt ontkoppelen van deze lead?`)) {
                            return;
                        }
                        
                        try {
                            // Call API to detach person from lead
                            await fetch(`/admin/leads/${this.leadId}/detach-person/${person.id}`, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                    'Content-Type': 'application/json'
                                }
                            });
                            
                            // Show success message
                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: `${person.name} is ontkoppeld van de lead.`
                            });
                        } catch (error) {
                            console.error('Error detaching person:', error);
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'Er is een fout opgetreden bij het ontkoppelen van de persoon.'
                            });
                            return;
                        }
                    }
                    
                    // Remove from local array
                    this.persons.splice(index, 1);
                },

                updatePerson(index, selectedPerson) {
                    this.$set(this.persons, index, {
                        id: selectedPerson.id,
                        name: selectedPerson.name,
                        match_percentage: selectedPerson.match_score_percentage || selectedPerson.match_percentage || null,
                        organization: selectedPerson.organization || null
                    });
                },

                async calculateMatchPercentage(person) {
                    if (!person.id || !this.leadId) return null;
                    
                    try {
                        // Use the searchByLead API that calculates match scores
                        const response = await fetch(`/admin/contacts/persons/searchByLead/${this.leadId}`);
                        const data = await response.json();
                        
                        if (data.data && data.data.length > 0) {
                            const matchedPerson = data.data.find(p => p.id === person.id);
                            return matchedPerson?.match_score_percentage || null;
                        }
                    } catch (error) {
                        console.warn('Could not calculate match percentage:', error);
                    }
                    
                    return null;
                },

                getPersonInitials(person) {
                    if (!person.name) return '?';
                    
                    const names = person.name.split(' ');
                    if (names.length >= 2) {
                        return (names[0][0] + names[names.length - 1][0]).toUpperCase();
                    }
                    return person.name[0].toUpperCase();
                },

                getPersonCardClass(person) {
                    if (!person.id) {
                        return 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900';
                    }
                    
                    if (person.match_percentage >= 90) {
                        return 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900';
                    } else if (person.match_percentage >= 70) {
                        return 'border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900';
                    } else if (person.match_percentage >= 50) {
                        return 'border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-900';
                    } else {
                        return 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800';
                    }
                },

                getAvatarClass(person) {
                    if (!person.id) {
                        return 'bg-blue-600';
                    }
                    
                    if (person.match_percentage >= 90) {
                        return 'bg-green-600';
                    } else if (person.match_percentage >= 70) {
                        return 'bg-yellow-600';
                    } else if (person.match_percentage >= 50) {
                        return 'bg-orange-600';
                    } else {
                        return 'bg-gray-600';
                    }
                },

                getScoreBarClass(percentage) {
                    if (percentage >= 80) {
                        return 'bg-green-500';
                    } else if (percentage >= 60) {
                        return 'bg-yellow-500';
                    } else if (percentage >= 40) {
                        return 'bg-orange-500';
                    } else {
                        return 'bg-red-500';
                    }
                }
            }
        });
    </script>
@endPushOnce