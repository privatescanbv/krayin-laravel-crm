@php
    // Prepare email data with accessors for Vue component
    $emailData = $email->getAttributes();
    $emailData['sender_email'] = $email->sender_email;
    $emailData['has_relationships'] = $email->has_relationships;
@endphp

@pushOnce('scripts')
@include('adminc.emails.email-suggestions')
@include('adminc.emails.entity-linker')
<script
    type="text/x-template"
    id="v-action-email-template"
>
    {!! view_render_event('admin.mail.view.action_mail.before', ['email' => $email]) !!}

    <div class="flex flex-col gap-6">
        <!-- When a relationship exists: Panel 2 only (current link + reset) -->
        <template v-if="hasRelationships">
            <div class="flex flex-col gap-2">
                <label class="font-semibold text-gray-800 dark:text-gray-300">
                    Gekoppeld aan
                </label>
                <div class="flex items-center justify-between rounded-md border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                    <a
                        v-if="currentLink?.url"
                        :href="currentLink.url"
                        class="flex items-center gap-3 flex-1 hover:opacity-80 transition-opacity"
                    >
                        <span class="icon-link text-gray-600 dark:text-gray-300"></span>
                        <div class="text-sm">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                @{{ currentLink?.label }}
                            </div>
                            <div class="text-gray-500 dark:text-gray-400">
                                @{{ currentLink?.subtitle }}
                            </div>
                        </div>
                    </a>
                    <button
                        type="button"
                        @click="resetLink"
                        class="flex items-center gap-2 rounded-md border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300 dark:hover:bg-red-900/50"
                    >
                        <span class="icon-trash"></span>
                        Verwijder koppeling
                    </button>
                </div>
            </div>
        </template>

        <!-- When no relationship exists: show Panel 1 (suggestions) and Panel 3 (manual select) -->
        <template v-else>
            <!-- Panel 1: Suggestions -->
            <div class="flex flex-col gap-2">
                <label class="font-semibold text-gray-800 dark:text-gray-300 flex items-center gap-2">
                    <span>Suggesties op basis van afzender</span>
                    <i
                        class="icon-info text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 cursor-help text-sm"
                        title="Klik op een suggestie om deze te koppelen aan de mail"
                        aria-label="Uitleg: Klik op een suggestie om deze te koppelen aan de mail"
                    ></i>
                </label>
                <v-email-suggestions
                    :email="email"
                    @link-entity="linkEntity"
                ></v-email-suggestions>
                @if (bouncer()->hasPermission('leads.create'))
                    <button
                        type="button"
                        @click="createLeadFromEmail"
                        class="primary-button mt-1"
                    >
                        <span class="icon-plus text-lg"></span>
                        Maak Lead
                    </button>
                @endif
            </div>

            <!-- Panel 3: Manual entity selector -->
            <div class="flex flex-col gap-2">
                <label class="font-semibold text-gray-800 dark:text-gray-300">
                    Koppel handmatig
                </label>
                <v-entity-linker
                    :email="email"
                    :lead-search-route="'{{ route('admin.leads.search') }}'"
                    :sales-lead-search-route="'{{ route('admin.sales-leads.search') }}'"
                    @link-entity="linkEntity"
                    @unlink-entity="unlinkEntity"
                    :unlinking="unlinking"
                ></v-entity-linker>
            </div>
        </template>
    </div>

    <!-- Create Contact Modal -->
    <v-create-contact ref="createContact"></v-create-contact>

    <!-- Create Lead Modal -->
    <v-create-lead ref="createLead"></v-create-lead>

    {!! view_render_event('admin.mail.view.action_mail.after', ['email' => $email]) !!}
</script>

<!-- Email Action Vue Component -->
<script type="module">
    app.component('v-action-email', {
        template: '#v-action-email-template',

        data() {
            return {
                link: 'contact',

                email: @json($emailData),

                unlinking: {
                    lead: false,
                    contact: false,
                    sales_lead: false,
                },

                tagTextColor: {
                    '#FEE2E2': '#DC2626',
                    '#FFEDD5': '#EA580C',
                    '#FEF3C7': '#D97706',
                    '#FEF9C3': '#CA8A04',
                    '#ECFCCB': '#65A30D',
                    '#DCFCE7': '#16A34A',
                },
            };
        },

        computed: {
            hasRelationships() {
                // Prefer backend accessor; fallback to checking individual foreign keys
                if (this.email && Object.prototype.hasOwnProperty.call(this.email, 'has_relationships')) {
                    return !!this.email.has_relationships;
                }
                return !!(this.email?.person_id || this.email?.lead_id || this.email?.sales_lead_id || this.email?.activity_id);
            },

            senderEmail() {
                // Prefer backend accessor; fallback to checking from field
                if (this.email && Object.prototype.hasOwnProperty.call(this.email, 'sender_email')) {
                    return this.email.sender_email;
                }
                return this.email?.from || '';
            },

            currentLink() {
                if (this.email?.lead_id && this.email?.lead) {
                    return {
                        type: 'lead',
                        id: this.email.lead_id,
                        label: this.email.lead.name || [this.email.lead.first_name, this.email.lead.last_name].filter(Boolean).join(' '),
                        subtitle: 'Lead',
                        url: '{{ route('admin.leads.view', ':id') }}'.replace(':id', this.email.lead_id),
                    };
                }
                if (this.email?.sales_lead_id && this.email?.sales_lead) {
                    return {
                        type: 'sales_lead',
                        id: this.email.sales_lead_id,
                        label: this.email.sales_lead.name,
                        subtitle: 'Sales Lead',
                        url: '{{ route('admin.sales-leads.view', ':id') }}'.replace(':id', this.email.sales_lead_id),
                    };
                }
                if (this.email?.person_id && this.email?.person) {
                    return {
                        type: 'person',
                        id: this.email.person_id,
                        label: this.email.person.name || [this.email.person.first_name, this.email.person.last_name].filter(Boolean).join(' '),
                        subtitle: 'Contact',
                        url: '{{ route('admin.contacts.persons.view', ':id') }}'.replace(':id', this.email.person_id),
                    };
                }
                if (this.email?.activity_id && this.email?.activity) {
                    return {
                        type: 'activity',
                        id: this.email.activity_id,
                        label: this.email.activity.title || this.email.activity.name || ('Activiteit #' + this.email.activity_id),
                        subtitle: 'Activiteit',
                        url: '{{ route('admin.activities.view', ':id') }}'.replace(':id', this.email.activity_id),
                    };
                }
                return null;
            },
        },

        created() {
            @if ($email->person)
                this.email.person = @json($email->person);
            @endif

            @if ($email->lead)
                this.email.lead = @json($email->lead);
            @endif

            @if ($email->salesLead)
                this.email.sales_lead = @json($email->salesLead);
                this.email.sales_lead_id = {{ $email->sales_lead_id ?? 'null' }};
            @endif
        },

        methods: {
            resetLink() {
                this.$emitter.emit('open-confirm-modal', {
                    agree: () => {
                        this.$axios.post('{{ route('admin.mail.update', $email->id) }}', {
                            _method: 'PUT',
                            person_id: null,
                            lead_id: null,
                            sales_lead_id: null,
                            activity_id: null,
                        }).then(response => {
                            this.email.person = null;
                            this.email.person_id = null;
                            this.email.lead = null;
                            this.email.lead_id = null;
                            this.email.sales_lead = null;
                            this.email.sales_lead_id = null;
                            this.email.activity = null;
                            this.email.activity_id = null;
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            window.location.reload();
                        });
                    },
                });
            },

            openDrawer() {
                this.$refs.emailLinkDrawer.open();
            },

            linkContact(person) {
                this.$axios.post('{{ route('admin.mail.update', $email->id) }}', {
                    _method: 'PUT',
                    person_id: person.id,
                    lead_id: null,
                    sales_lead_id: null,
                })
                    .then (response => {
                        this.email.lead = this.email.lead_id = null;
                        this.email.sales_lead = this.email.sales_lead_id = null;
                        this.email.person = person;
                        this.email.person_id = person.id;
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        window.location.reload();
                    })
                    .catch (error => {});
            },

            unlinkContact() {
                this.$emitter.emit('open-confirm-modal', {
                    agree: () => {
                        this.unlinking.contact = true;

                        this.$axios.post('{{ route('admin.mail.update', $email->id) }}', {
                            _method: 'PUT',
                            person_id: null,
                        })
                            .then (response => {
                                this.email['person'] = this.email['person_id'] = null;

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                window.location.reload();
                            })
                            .catch (error => {})
                            .finally(() => this.unlinking.contact = false);
                    },
                });
            },

            linkLead(lead) {
                this.$axios.post('{{ route('admin.mail.update', $email->id) }}', {
                    _method: 'PUT',
                    lead_id: lead.id,
                    person_id: null,
                    sales_lead_id: null,
                })
                    .then (response => {
                        this.email.person = this.email.person_id = null;
                        this.email.sales_lead = this.email.sales_lead_id = null;
                        this.email.lead = lead;
                        this.email.lead_id = lead.id;
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        window.location.reload();
                    })
                    .catch (error => {});
            },

            linkSalesLead(salesLead) {
                this.$axios.post('{{ route('admin.mail.update', $email->id) }}', {
                    _method: 'PUT',
                    sales_lead_id: salesLead.id,
                    person_id: null,
                    lead_id: null,
                    activity_id: null,
                })
                    .then (response => {
                        this.email.person = this.email.person_id = null;
                        this.email.lead = this.email.lead_id = null;
                        this.email.sales_lead = salesLead;
                        this.email.sales_lead_id = salesLead.id;
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        window.location.reload();
                    })
                    .catch (error => {});
            },

            unlinkLead() {
                this.$emitter.emit('open-confirm-modal', {
                    agree: () => {
                        this.unlinking.lead = true;

                        this.$axios.post('{{ route('admin.mail.update', $email->id) }}', {
                            _method: 'PUT',
                            lead_id: null,
                        })
                            .then (response => {
                                this.email['lead'] = this.email['lead_id'] = null;

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                window.location.reload();
                            })
                            .catch (error => {})
                            .finally(() => this.unlinking.lead = false);
                    },
                });
            },

            linkActivity(activity) {
                this.email['activity'] = activity;
                this.email['activity_id'] = activity.id;
                this.$axios.post('{{ route('admin.mail.update', $email->id) }}', {
                    _method: 'PUT',
                    activity_id: activity.id,
                })
                    .then (response => {
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        window.location.reload();
                    })
                    .catch (error => {});
            },

            unlinkActivity() {
                this.$emitter.emit('open-confirm-modal', {
                    agree: () => {
                        this.$axios.post('{{ route('admin.mail.update', $email->id) }}', {
                            _method: 'PUT',
                            activity_id: null,
                        })
                            .then (response => {
                                this.email['activity_id'] = null;
                                this.email['activity'] = null;
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                window.location.reload();
                            })
                            .catch (error => {});
                    },
                });
            },

            unlinkSalesLead() {
                this.$emitter.emit('open-confirm-modal', {
                    agree: () => {
                        this.$axios.post('{{ route('admin.mail.update', $email->id) }}', {
                            _method: 'PUT',
                            sales_lead_id: null,
                        })
                            .then (response => {
                                this.email['sales_lead'] = this.email['sales_lead_id'] = null;
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                window.location.reload();
                            })
                            .catch (error => {});
                    },
                });
            },

            openContactModal() {
                this.$refs.createContact.$refs.contactModal.open();
            },

            openLeadModal() {
                this.$refs.createLead.$refs.leadModal.open();
            },

            linkEntity(entity) {
                if (entity.type === 'lead') {
                    this.linkLead(entity);
                } else if (entity.type === 'sales_lead') {
                    this.linkSalesLead(entity);
                } else if (entity.type === 'person') {
                    this.linkContact(entity);
                } else if (entity.type === 'activity') {
                    this.linkActivity(entity);
                }
            },

            unlinkEntity(entityType) {
                if (entityType === 'lead') {
                    this.unlinkLead();
                } else if (entityType === 'sales_lead') {
                    this.unlinkSalesLead();
                } else if (entityType === 'person') {
                    this.unlinkContact();
                } else if (entityType === 'activity') {
                    this.resetLink();
                }
            },

            createLeadFromEmail() {
                const params = new URLSearchParams();
                if (this.senderEmail) {
                    // Extract email from string (not JSON array)
                    params.append('email', this.senderEmail);
                }
                if (this.email?.name) {
                    const nameParts = this.email.name.split(' ');
                    if (nameParts.length > 0) {
                        params.append('first_name', nameParts[0]);
                    }
                    if (nameParts.length > 1) {
                        params.append('last_name', nameParts.slice(1).join(' '));
                    }
                }
                window.location.href = '{{ route('admin.leads.create') }}?' + params.toString();
            },
        },
    });
</script>
@endPushOnce

