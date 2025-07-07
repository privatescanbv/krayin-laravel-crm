{!! view_render_event('admin.leads.create.contact_person.form_controls.before') !!}

<v-contact-organisation-component :data="person"></v-contact-organisation-component>

{!! view_render_event('admin.leads.create.contact_person.form_controls.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-contact-organisation-component-template"
    >
        <!-- Person Organization -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.common.contact.organization')
            </x-admin::form.control-group.label>

            @php
                $organizationAttribute = app('Webkul\Attribute\Repositories\AttributeRepository')->findOneWhere([
                    'entity_type' => 'persons',
                    'code'        => 'organization_id'
                ]);

                if ($organizationAttribute) {
                    $organizationAttribute->code = 'person[' . $organizationAttribute->code . ']';
                }
            @endphp

            <x-admin::attributes.edit.lookup />

            @if($organizationAttribute)
                <v-lookup-component
                    :key="person.organization?.id"
                    :attribute='@json($organizationAttribute)'
                    :value="person.organization"
                    :is-disabled="person?.id ? true : false"
                    can-add-new="true"
                ></v-lookup-component>
            @endif
        </x-admin::form.control-group>
    </script>

    <script type="module">
        app.component('v-contact-organisation-component', {
            template: '#v-contact-organisation-component-template',

            props: ['data'],

            data () {
                return {
                    is_searching: false,

                    person: this.data ? this.data : {
                        'name': ''
                    },

                    persons: [],
                }
            },

            computed: {
                src() {
                    return "{{ route('admin.contacts.persons.search') }}";
                },

                params() {
                    return {
                        params: {
                            query: this.person['name']
                        }
                    }
                },

                nameValidationRule() {
                    return this.person.name ? '' : 'required';
                }
            },

            methods: {
                addPerson (person) {
                    this.person = person;
                },
            }
        });
    </script>
@endPushOnce
