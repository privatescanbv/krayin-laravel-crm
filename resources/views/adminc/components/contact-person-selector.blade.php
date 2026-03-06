@include('adminc.components.entity-selector')
@include('adminc.components.person-suggestion')
@include('adminc.components.person-search-helpers')

@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-contact-person-selector-template">
        <v-entity-selector
            :name="name || 'contact_person_id'"
            :label="label !== undefined ? label : 'Contactpersoon'"
            :placeholder="placeholder || 'Selecteer contactpersoon...'"
            :search-route="resolvedSearchRoute"
            :items="computedItems"
            :can-add-new="canAddNew !== false"
            :multiple="false"
            :fetcher="fetchPersons"
            @update:items="onItemsUpdated"
        >
            <template #suggestion="{ item }">
                <v-person-suggestion :person="item" />
            </template>
        </v-entity-selector>
    </script>

    <script type="module">
        app.component('v-contact-person-selector', {
            template: '#v-contact-person-selector-template',
            props: ['name','label','placeholder','currentValue','currentLabel','canAddNew','searchRoute'],
            emits: ['select','remove','create-new','change','update:value'],
            computed: {
                resolvedSearchRoute() {
                    return this.searchRoute || '/admin/contacts/persons/search';
                },
                computedItems() {
                    if (this.currentValue) {
                        return [{ id: this.currentValue, name: this.currentLabel || '' }];
                    }
                    return [];
                }
            },
            methods: {
                async fetchPersons(query) {
                    if (window.adminc && typeof window.adminc.fetchPersons === 'function') {
                        return await window.adminc.fetchPersons(query);
                    } else {
                        console.error('Could not find global fetchPersons function');
                    }
                    return [];
                },
                onItemsUpdated(items) {
                    const first = Array.isArray(items) && items.length ? items[0] : null;
                    this.$emit('change', first);
                    this.$emit('update:value', first ? first.id : null);
                }
            }
        });
    </script>
@endverbatim
@endPushOnce
