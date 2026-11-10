@props([
    'deleteUrl',
    'redirectUrl',
    'name' => '',
    'label' => 'Gebruiker',
])
{{-- Generic "Verwijderen" button with confirm modal; same look and flow as v-person-delete / v-clinic-delete. --}}
<v-entity-delete
    delete-url="{{ $deleteUrl }}"
    redirect-url="{{ $redirectUrl }}"
    entity-name="{{ $name }}"
    entity-label="{{ $label }}"
/>

@pushOnce('scripts', 'entity-delete-button')
    <script type="text/x-template" id="v-entity-delete-template">
        <button
            type="button"
            class="secondary-button border border-red-100 text-status-expired-text hover:border-error hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950 flex items-center gap-1"
            :class="{ 'opacity-50 pointer-events-none': isDeleting }"
            :disabled="isDeleting"
            @click="confirmDelete"
        >
            <span class="icon-delete text-base"></span>

            <span>Verwijderen</span>
        </button>
    </script>

    <script type="module">
        app.component('v-entity-delete', {
            template: '#v-entity-delete-template',

            props: {
                deleteUrl: { type: String, required: true },
                redirectUrl: { type: String, required: true },
                entityName: { type: String, default: '' },
                entityLabel: { type: String, default: 'Item' },
            },

            data() {
                return { isDeleting: false };
            },

            methods: {
                confirmDelete() {
                    if (this.isDeleting) {
                        return;
                    }

                    this.$emitter.emit('open-confirm-modal', {
                        title: this.entityLabel + ' verwijderen',
                        message: `Weet je zeker dat je "${this.entityName}" wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.`,
                        options: { btnDisagree: 'Annuleren', btnAgree: 'Verwijderen' },
                        agree: () => {
                            this.isDeleting = true;

                            this.$axios.delete(this.deleteUrl)
                                .then((response) => {
                                    this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                    window.location.href = this.redirectUrl;
                                })
                                .catch((error) => {
                                    this.$emitter.emit('add-flash', {
                                        type: 'error',
                                        message: error?.response?.data?.message ?? this.entityLabel + ' kan niet worden verwijderd.',
                                    });
                                })
                                .finally(() => {
                                    this.isDeleting = false;
                                });
                        },
                        disagree: () => {
                            this.isDeleting = false;
                        },
                    });
                },
            },
        });
    </script>
@endPushOnce
