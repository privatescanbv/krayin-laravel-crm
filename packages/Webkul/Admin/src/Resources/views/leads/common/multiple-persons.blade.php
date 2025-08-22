{!! view_render_event('admin.leads.multiple_persons.before') !!}

<v-multiple-persons-component :data="persons"></v-multiple-persons-component>

{!! view_render_event('admin.leads.multiple_persons.after') !!}

@pushOnce('scripts')
    <script type="text/x-template" id="v-multiple-persons-component-template">
        <div class="flex flex-col gap-4">
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
                    Persoon toevoegen
                </button>
            </div>

            <!-- Persons List -->
            <div v-if="persons.length > 0" class="space-y-3">
                <div
                    v-for="(person, index) in persons"
                    :key="index"
                    class="border border-gray-200 rounded-lg p-4 bg-gray-50 dark:bg-gray-800 dark:border-gray-700"
                >
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium dark:text-white">
                            Persoon @{{ index + 1 }}
                        </h4>
                        <button
                            @click="removePerson(index)"
                            type="button"
                            class="text-red-600 hover:text-red-800"
                            v-if="persons.length > 1 || person.id"
                        >
                            <i class="icon-trash text-sm"></i>
                        </button>
                    </div>

                    <!-- Person Lookup -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            Zoek contactpersoon
                        </x-admin::form.control-group.label>

                        <x-admin::lookup
                            ::src="`{{ route('admin.contacts.persons.search') }}`"
                            ::name="`persons[${index}][id]`"
                            :label="'Naam'"
                            ::value="person"
                            placeholder="Zoek op naam, email of telefoon..."
                            @on-selected="(selectedPerson) => updatePerson(index, selectedPerson)"
                            :can-add-new="true"
                        />

                        <input
                            type="hidden"
                            ::name="`persons[${index}][id]`"
                            ::value="person.id"
                            v-if="person.id"
                        />
                    </x-admin::form.control-group>

                    <!-- Person Details (when selected or creating new) -->
                    <div v-if="person.id || person.name" class="mt-4 space-y-3">
                        <!-- Name -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>
                                Naam
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                ::name="`persons[${index}][name]`"
                                v-model="person.name"
                                placeholder="Volledige naam"
                            />
                        </x-admin::form.control-group>

                        <!-- Email -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>
                                Email
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="email"
                                ::name="`persons[${index}][email]`"
                                v-model="person.email"
                                placeholder="email@example.com"
                            />
                        </x-admin::form.control-group>

                        <!-- Phone -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>
                                Telefoon
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                ::name="`persons[${index}][phone]`"
                                v-model="person.phone"
                                placeholder="+31 6 12345678"
                            />
                        </x-admin::form.control-group>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="persons.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                <i class="icon-users text-4xl mb-2"></i>
                <p>Nog geen contactpersonen gekoppeld</p>
                <p class="text-sm">Klik op "Persoon toevoegen" om te beginnen</p>
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
                        email: '',
                        phone: ''
                    });
                },

                removePerson(index) {
                    this.persons.splice(index, 1);
                },

                updatePerson(index, selectedPerson) {
                    this.$set(this.persons, index, {
                        id: selectedPerson.id,
                        name: selectedPerson.name,
                        email: selectedPerson.email || '',
                        phone: selectedPerson.phone || ''
                    });
                }
            }
        });
    </script>
@endPushOnce