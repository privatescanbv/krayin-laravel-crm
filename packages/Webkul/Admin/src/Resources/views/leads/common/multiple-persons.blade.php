{!! view_render_event('admin.leads.multiple_persons.before') !!}

<v-multiple-persons-component :data="persons"></v-multiple-persons-component>

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
                                <span 
                                    v-if="person.match_percentage" 
                                    class="px-2 py-1 text-xs rounded-full font-medium"
                                    :class="getMatchBadgeClass(person.match_percentage)"
                                >
                                    @{{ person.match_percentage }}% match
                                </span>
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
                    <div class="flex items-center gap-2">
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

            props: ['data'],

            data() {
                return {
                    persons: this.data || []
                };
            },

            watch: {
                persons: {
                    handler(newValue) {
                        this.$emit('update:data', newValue);
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

                removePerson(index) {
                    this.persons.splice(index, 1);
                },

                updatePerson(index, selectedPerson) {
                    this.$set(this.persons, index, {
                        id: selectedPerson.id,
                        name: selectedPerson.name,
                        match_percentage: selectedPerson.match_percentage || null,
                        organization: selectedPerson.organization || null
                    });
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

                getMatchBadgeClass(percentage) {
                    if (percentage >= 90) {
                        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                    } else if (percentage >= 70) {
                        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                    } else if (percentage >= 50) {
                        return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
                    } else {
                        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                    }
                }
            }
        });
    </script>
@endPushOnce