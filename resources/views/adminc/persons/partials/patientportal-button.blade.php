@php use App\Enums\PortalRevocationReason; @endphp
@props([
    'person',
    'presentLarge' => false,
    'returnUrl' => null,
])

@if ($person->is_active)
    @if (empty($person->keycloak_user_id))
        @if (bouncer()->hasPermission('contacts.persons.portal-create'))
            <form
                method="POST"
                action="{{ route('admin.contacts.persons.portal.create', $person->id) }}"
                onsubmit="return confirm('Portal account aanmaken voor {{ $person->name }}?')"
                style="display: inline;">
                @csrf
                @if ($returnUrl)
                    <input type="hidden" name="return_url" value="{{ $returnUrl }}">
                @endif
                @if ($presentLarge)
                    <button
                        type="submit"
                        title="Patiëntportaal account aanmaken"
                        class="group flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-activity-note-bg font-medium text-activity-note-text transition-all hover:border-activity-note-border hover:text-blue-700"
                    >
                        <span class="icon-user text-2xl text-activity-note-text transition-all group-hover:text-blue-700 dark:!text-activity-note-text"></span>
                        Portaal
                    </button>
                @else
                    <button type="submit" class="icon-user rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 text-activity-note-text hover:text-blue-700" title="Patiëntportaal account aanmaken"></button>
                @endif
            </form>
        @endif
    @else
        @if (bouncer()->hasPermission('contacts.persons.portal-delete'))
            <v-portal-revoke-button
                person-id="{{ $person->id }}"
                person-name="{{ $person->name }}"
                action-url="{{ route('admin.contacts.persons.portal.delete', $person->id) }}"
                return-url="{{ $returnUrl }}"
                present-large="{{ $presentLarge ? 'true' : 'false' }}"
                csrf-token="{{ csrf_token() }}"
            ></v-portal-revoke-button>
        @endif
    @endif
@endif

@pushOnce('scripts')
    <script type="text/x-template" id="v-portal-revoke-button-template">
        <div style="display: inline;">
            <template v-if="presentLarge === 'true'">
                <button
                    type="button"
                    @click="openModal"
                    title="Patiëntportaal account intrekken"
                    class="group flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-red-100 bg-red-50 font-medium text-status-expired-text transition-all hover:border-error hover:bg-red-100 hover:text-red-700 dark:border-red-700 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-950"
                >
                    <span class="icon-cross-large text-2xl text-status-expired-text transition-all group-hover:text-red-700 dark:!text-red-300"></span>
                    Portaal
                </button>
            </template>
            <template v-else>
                <button
                    type="button"
                    @click="openModal"
                    class="icon-cross-large rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 text-status-expired-text hover:text-red-700"
                    title="Patiëntportaal account intrekken"
                ></button>
            </template>

            <Teleport to="body">
                <x-admin::modal ref="portalRevokeModal">
                <x-slot:header>
                    <h3 class="text-base font-semibold dark:text-white">
                        Patiëntportaal account intrekken
                    </h3>
                </x-slot>

                <x-slot:content>
                    <form :action="actionUrl" method="POST" ref="revokeForm">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <input type="hidden" name="_method" value="DELETE">
                        <input v-if="returnUrl" type="hidden" name="return_url" :value="returnUrl">

                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                            Portaal account verwijderen voor <strong>@{{ personName }}</strong>
                        </p>

                        <x-admin::form.control-group class="mb-4">
                            <x-admin::form.control-group.label class="required">
                                Reden van intrekking
                            </x-admin::form.control-group.label>

                            <select
                                name="revocation_reason"
                                v-model="revocationReason"
                                class="!w-full min-h-[38px] border border-gray-300 dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900 text-sm"
                                required
                            >
                                <option value="">Selecteer reden...</option>
                                @foreach (PortalRevocationReason::cases() as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </select>
                        </x-admin::form.control-group>

                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>
                                Toelichting
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="textarea"
                                name="revocation_comment"
                                v-model="revocationComment"
                                rows="3"
                                placeholder="Optionele toelichting..."
                            />
                        </x-admin::form.control-group>
                    </form>
                </x-slot>

                <x-slot:footer>
                    <button
                        type="button"
                        class="secondary-button mr-2"
                        @click="closeModal"
                    >
                        Annuleren
                    </button>

                    <button
                        type="button"
                        class="primary-button bg-red-600 hover:bg-red-700"
                        @click="submitForm"
                    >
                        Intrekken
                    </button>
                </x-slot>
            </x-admin::modal>
            </Teleport>
        </div>
    </script>

    <script type="module">
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof app !== 'undefined' && !app._context.components['v-portal-revoke-button']) {
                app.component('v-portal-revoke-button', {
                    template: '#v-portal-revoke-button-template',

                    props: {
                        personId: { type: String, required: true },
                        personName: { type: String, required: true },
                        actionUrl: { type: String, required: true },
                        returnUrl: { type: String, default: '' },
                        presentLarge: { type: String, default: 'false' },
                        csrfToken: { type: String, required: true },
                    },

                    data() {
                        return {
                            revocationReason: '',
                            revocationComment: '',
                        };
                    },

                    methods: {
                        openModal() {
                            this.revocationReason = '';
                            this.revocationComment = '';
                            this.$refs.portalRevokeModal.open();
                        },

                        closeModal() {
                            this.$refs.portalRevokeModal.close();
                        },

                        submitForm() {
                            if (!this.revocationReason) {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: 'Selecteer een reden voor intrekking'
                                });
                                return;
                            }

                            this.$refs.revokeForm.submit();
                        },
                    },
                });
            }
        });
    </script>
@endPushOnce
