@php
    use App\Enums\EmailTemplateType;
@endphp

@props([
    'entity'            => null,
    'entityControlName' => null,
    'emails'            => [],
    'storeUrl'          => null,
    'showButton'        => true,
    'activityId'        => null,
    'defaultTemplate'   => null,
    'orderId'           => null,
])

<!-- Mail Button -->
<div>
    {!! view_render_event('admin.components.activities.actions.mail.create_btn.before') !!}

    @if ($showButton)
        <button
            type="button"
            class="flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent  bg-activity-email-bg font-medium text-activity-email-text transition-all hover:border-activity-email-border"
            @click="$refs.mailActionComponent.openModal('mail')"
        >
            <span class="icon-mail text-2xl dark:!text-activity-email-text"></span>

            @lang('admin::app.components.activities.actions.mail.btn')
        </button>
    @endif

    {!! view_render_event('admin.components.activities.actions.mail.create_btn.after') !!}

    {!! view_render_event('admin.components.activities.actions.mail.before') !!}

    <!-- Mail Activity Action Vue Component -->
    <v-mail-activity
        ref="mailActionComponent"
        :entity="{{ json_encode($entity) }}"
        entity-control-name="{{ $entityControlName }}"
        :activity-id="{{ $activityId ? (int) $activityId : 'null' }}"
        :emails="{{ json_encode($emails) }}"
        :default-template="'{{ $defaultTemplate ?? '' }}'"
        @if($orderId) :order-id="{{ $orderId }}" @endif
        @if ($storeUrl) :store-url="'{{ $storeUrl }}'" @endif
    ></v-mail-activity>

    {!! view_render_event('admin.components.activities.actions.mail.after') !!}
</div>

@pushOnce('scripts')
    <script type="text/x-template" id="v-mail-activity-template">
        <Teleport to="body">
            {!! view_render_event('admin.components.activities.actions.mail.form_controls.before') !!}

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                enctype="multipart/form-data"
                as="div"
            >
                <form
                    @submit="handleSubmit($event, save)"
                    ref="mailActionForm"
                >
                    {!! view_render_event('admin.components.activities.actions.mail.form_controls.modal.before') !!}

                    <x-admin::modal
                        ref="mailActivityModal"
                        position="bottom-right"
                        @toggle="removeTinyMCE"
                    >
                        <x-slot:header>
                            {!! view_render_event('admin.components.activities.actions.mail.form_controls.modal.header.before') !!}

                            <div class="flex items-center justify-between gap-2.5 w-full">
                                <h3 class="text-base font-semibold dark:text-white">
                                    @lang('admin::app.components.activities.actions.mail.title')
                                </h3>

                                <button
                                    type="button"
                                    class="flex items-center justify-center w-8 h-8 cursor-pointer text-gray-600 hover:rounded-md hover:bg-neutral-bg dark:text-gray-300 dark:hover:bg-gray-950 transition-colors"
                                    @click="toggleFullscreen"
                                    :title="isFullscreen ? 'Verkleinen' : 'Volledig scherm'"
                                >
                                    <span v-if="isFullscreen" class="text-xl">⊟</span>
                                    <span v-else class="text-xl">⊞</span>
                                </button>
                            </div>

                            {!! view_render_event('admin.components.activities.actions.mail.form_controls.modal.header.before') !!}
                        </x-slot>

                        <x-slot:content>
                            {!! view_render_event('admin.components.activities.actions.mail.form_controls.modal.content.controls.before') !!}

                            <!-- Activity Type -->
                            <x-admin::form.control-group.control
                                type="hidden"
                                name="type"
                                value="email"
                            />

                            <!-- Id -->
                            <x-admin::form.control-group.control
                                type="hidden"
                                ::name="entityControlName"
                                ::value="entity.id"
                            />

                            <!-- To -->
                            <x-admin::form.control-group>
                                <div class="relative">
                                    <x-admin::form.control-group.control
                                        type="tags"
                                        name="reply_to"
                                        rules="required"
                                        input-rules="email"
                                        :label="trans('admin::app.components.activities.actions.mail.to')"
                                        :placeholder="trans('admin::app.components.activities.actions.mail.enter-emails')"
                                    />

                                    <div class="absolute top-[9px] flex items-center gap-2 ltr:right-2 rtl:left-2">
                                        <template v-if="entityEmails.length">
                                            <x-admin::dropdown position="bottom-right" ::close-on-click="true">
                                                <x-slot:toggle>
                                                    <button type="button" class="rounded-md px-2 py-1 text-sm transition-all hover:bg-gray-200 dark:hover:bg-gray-950">
                                                        @{{ selectedEmailLabel || (entityEmails[0]?.value || 'Kies') }}
                                                    </button>
                                                </x-slot:toggle>

                                                <x-slot:menu class="!p-0 !top-8 min-w-[220px]">
                                                    <x-admin::dropdown.menu.item
                                                        class="flex items-center justify-between gap-2"
                                                        v-for="mail in entityEmails"
                                                        @click="setReplyTo(mail.value)"
                                                    >
                                                        <span class="truncate max-w-[160px]">@{{ mail.value }}</span>
                                                        <span v-if="mail.is_default" class="text-xs text-gray-500">default</span>
                                                    </x-admin::dropdown.menu.item>
                                                    <x-admin::dropdown.menu.item @click="focusReplyToInput()">
                                                        Anders
                                                    </x-admin::dropdown.menu.item>
                                                </x-slot:menu>
                                            </x-admin::dropdown>
                                        </template>

                                        <span
                                            class="cursor-pointer font-medium hover:underline dark:text-white"
                                            @click="showCC = ! showCC"
                                        >
                                            @lang('admin::app.components.activities.actions.mail.cc')
                                        </span>

                                        <span
                                            class="cursor-pointer font-medium hover:underline dark:text-white"
                                            @click="showBCC = ! showBCC"
                                        >
                                            @lang('admin::app.components.activities.actions.mail.bcc')
                                        </span>
                                    </div>
                                </div>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.components.activities.actions.mail.to')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.error control-name="reply_to" />

                            </x-admin::form.control-group>

                            <template v-if="showCC">
                                <!-- Cc -->
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.control
                                        type="tags"
                                        name="cc"
                                        input-rules="email"
                                        :label="trans('admin::app.components.activities.actions.mail.cc')"
                                        :placeholder="trans('admin::app.components.activities.actions.mail.enter-emails')"
                                    />
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.components.activities.actions.mail.cc')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.error control-name="cc" />

                                </x-admin::form.control-group>
                            </template>

                            <template v-if="showBCC">
                                <!-- Cc -->
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.control
                                        type="tags"
                                        name="bcc"
                                        input-rules="email"
                                        :label="trans('admin::app.components.activities.actions.mail.bcc')"
                                        :placeholder="trans('admin::app.components.activities.actions.mail.enter-emails')"
                                    />
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.components.activities.actions.mail.bcc')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.error control-name="bcc" />

                                </x-admin::form.control-group>
                            </template>

                            <!-- Template Selector -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="select"
                                    id="email_template"
                                    name="email_template"
                                    v-model="selectedTemplate"
                                    @change="loadTemplate"
                                    :label="trans('admin::app.components.activities.actions.mail.template')"
                                >
                                    <option value="">Geen template</option>
                                    <option
                                        v-for="template in emailTemplates"
                                        :key="template.code || template.name"
                                        :value="template.code || template.name"
                                    >
                                        @{{ template.label }}
                                    </option>
                                </x-admin::form.control-group.control>
                                <x-admin::form.control-group.error control-name="email_template" />

                            </x-admin::form.control-group>

                            <!-- Subject -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="text"
                                    id="subject"
                                    name="subject"
                                    rules="required"
                                    :label="trans('admin::app.components.activities.actions.mail.subject')"
                                    :placeholder="trans('admin::app.components.activities.actions.mail.subject')"
                                />
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.components.activities.actions.mail.subject')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.label>
                                    Template
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.error control-name="subject" />

                            </x-admin::form.control-group>

                            <!-- Content -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="reply"
                                    id="reply"
                                    rules="required"
                                    :tinymce="true"
                                    :label="trans('admin::app.components.activities.actions.mail.message')"
                                />
                                <x-admin::form.control-group.error control-name="reply" />

                            </x-admin::form.control-group>

                            <!-- Attachments -->
                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::attachments
                                    ref="attachmentsComponent"
                                    allow-multiple="true"
                                    hide-button="true"
                                />
                            </x-admin::form.control-group>

                            {!! view_render_event('admin.components.activities.actions.mail.form_controls.modal.content.controls.after') !!}
                        </x-slot>

                        <x-slot:footer>
                            {!! view_render_event('admin.components.activities.actions.mail.form_controls.modal.footer.save_button.before') !!}

                            <div class="flex w-full items-center justify-between">
                                <label
                                    class="icon-attachment cursor-pointer p-1 text-2xl hover:rounded-md hover:bg-neutral-bg dark:hover:bg-gray-950"
                                    for="file-upload"
                                ></label>

                                <x-admin::button
                                    class="primary-button"
                                    :title="trans('admin::app.components.activities.actions.mail.send-btn')"
                                    ::loading="isStoring"
                                    ::disabled="isStoring"
                                />
                            </div>

                            {!! view_render_event('admin.components.activities.actions.mail.form_controls.modal.footer.save_button.after') !!}
                        </x-slot>
                    </x-admin::modal>

                    {!! view_render_event('admin.components.activities.actions.mail.form_controls.modal.after') !!}
                </form>
            </x-admin::form>

            {!! view_render_event('admin.components.activities.actions.mail.form_controls.after') !!}
        </Teleport>
    </script>

    <script type="module">
            app.component('v-mail-activity', {
                template: '#v-mail-activity-template',

                props: {
                    entity: {
                        type: Object,
                        required: true,
                        default: () => {}
                    },

                    entityControlName: {
                        type: String,
                        required: true,
                        default: ''
                    },

                    activityId: {
                        type: [Number, null],
                        required: false,
                        default: null,
                    },

                    emails: {
                        type: Array,
                        required: false,
                        default: () => []
                    },

                    storeUrl: {
                        type: String,
                        required: false,
                        default: ''
                    },

                    defaultTemplate: {
                        type: String,
                        required: false,
                        default: ''
                    },

                    orderId: {
                        type: [Number, String],
                        required: false,
                        default: null
                    }
                },

                data() {
                    return {
                        // Local copy of defaultTemplate prop that can be mutated
                        currentDefaultTemplate: this.defaultTemplate || '',
                        showCC: false,

                        showBCC: false,

                        isStoring: false,

                        entityEmails: [],

                        selectedEmailLabel: '',

                        selectedTemplate: '',

                        emailTemplates: [],

                        // Store person_id and lead_id for info mail context
                        infoMailPersonId: null,
                        infoMailLeadId: null,

                        // Store entity type override (e.g., for order mails)
                        entityTypeOverride: null,

                        // Email template type enum values
                        templateTypes: {
                            LEAD: @json(EmailTemplateType::LEAD->value),
                            ALGEMEEN: @json(EmailTemplateType::ALGEMEEN->value),
                            ORDER: @json(EmailTemplateType::ORDER->value),
                            GVL: @json(EmailTemplateType::GVL->value),
                        },
                    }
                },

              created() {
                  this.loadTemplates();
              },

              mounted() {
                  this.__mailDialogListener = async (event) => {
                      const detail = event?.detail || {};

                      // Store person_id and lead_id from payload for info mail context
                      if (detail.person_id) {
                          this.infoMailPersonId = detail.person_id;
                      }
                      if (detail.lead_id) {
                          this.infoMailLeadId = detail.lead_id;
                      }

                      // Store entity_type if provided (e.g., for orders, gvl)
                      if (detail.entity_type) {
                          this.entityTypeOverride = detail.entity_type;
                      }

                      // Store default_template if provided (e.g., for order confirmation, gvl info mail)
                      if (detail.default_template) {
                          this.currentDefaultTemplate = detail.default_template;
                      }

                      // Reload templates if entity_type changed (to get correct filtered templates)
                      if (detail.entity_type) {
                          await this.loadTemplates();
                      }

                      await this.openModalWithPayload(detail);
                  };

                  window.addEventListener('open-email-dialog', this.__mailDialogListener);

                  this.__mailDialogHandler = (payload) => {
                      this.openModalWithPayload(payload || {});
                  };

                  if (! Array.isArray(window.__mailDialogHandlers)) {
                      window.__mailDialogHandlers = [];
                  }

                  window.__mailDialogHandlers.push(this.__mailDialogHandler);

                  // Use emails from server-side if provided, otherwise fallback to client-side logic
                  if (this.emails && this.emails.length > 0) {
                      this.entityEmails = this.emails;
                  } else {
                      this.entityEmails = this.collectEntityEmails();
                  }

                  // Ensure templates are loaded
                  if (!this.emailTemplates || this.emailTemplates.length === 0) {
                      this.loadTemplates();
                  }
              },

              beforeUnmount() {
                  if (this.__mailDialogListener) {
                      window.removeEventListener('open-email-dialog', this.__mailDialogListener);
                  }

                  if (this.__mailDialogHandler && Array.isArray(window.__mailDialogHandlers)) {
                      window.__mailDialogHandlers = window.__mailDialogHandlers.filter((handler) => handler !== this.__mailDialogHandler);
                  }
              },

              methods: {
                  async loadTemplates() {
                      // Use entity type override if provided (e.g., from order mail, gvl)
                      if (this.entityTypeOverride) {
                          const entityType = this.entityTypeOverride;
                          try {
                              const response = await this.$axios.get('{{ route('admin.mail.templates') }}', {
                                  params: {
                                      entity_type: entityType
                                  }
                              });
                              const templates = response.data?.data || response.data || [];
                              this.emailTemplates = Array.isArray(templates) ? templates : [];
                          } catch (error) {
                              this.emailTemplates = [];
                          }
                          return;
                      }

                      // Determine entity type based on entityControlName or entity type
                      const controlName = (this.entityControlName || '').toString().toLowerCase();
                      const entityTypeStr = (this.entity?.entity_type || this.entity?.type || '').toString().toLowerCase();

                      let entityType = this.templateTypes.LEAD; // Default to LEAD for leads (which includes ALGEMEEN)

                      // Check for orders (sales_lead_id from order context)
                      if (controlName === 'sales_lead_id' && entityTypeStr === 'order') {
                          entityType = this.templateTypes.ORDER;
                      }
                      // Check for leads - LEAD type includes ALGEMEEN templates
                      else if (controlName === 'lead_id' || entityTypeStr === 'lead' || entityTypeStr === 'leads') {
                          entityType = this.templateTypes.LEAD; // LEAD type returns both LEAD and ALGEMEEN templates
                      }
                      // Check for orders (when sales_lead_id is used in order context)
                      else if (controlName === 'sales_lead_id') {
                          // Default to order for sales_lead_id when opened from order edit page
                          entityType = this.templateTypes.ORDER;
                      }
                      // Default fallback: use LEAD (which includes ALGEMEEN)
                      else {
                          entityType = this.templateTypes.LEAD;
                      }

                      try {
                          const response = await this.$axios.get('{{ route('admin.mail.templates') }}', {
                              params: {
                                  entity_type: entityType
                              }
                          });
                          // Handle different response structures
                          const templates = response.data?.data || response.data || [];
                          this.emailTemplates = Array.isArray(templates) ? templates : [];
                      } catch (error) {
                          this.emailTemplates = [];
                      }
                  },

                  setContentWithRetry(html, retries = 25) {
                      if (!html || !html.trim()) return;

                      // Check if TinyMCE is available and editor is ready
                      if (window.tinymce) {
                          try {
                              const editor = window.tinymce.get('reply');
                              if (editor && !editor.removed) {
                                  // Editor exists and is not removed, set content
                                  editor.setContent(html);
                                  return;
                              }
                          } catch (e) {
                              // Editor not ready yet, continue to retry
                          }
                      }

                      // Retry if TinyMCE not ready yet
                      if (retries > 0) {
                          setTimeout(() => this.setContentWithRetry(html, retries - 1), 200);
                      } else {
                          // Final fallback: set textarea when all retries exhausted
                          const messageField = this.$refs.mailActionForm?.querySelector('[name="reply"]');
                          if (messageField) {
                              messageField.value = html;
                              messageField.dispatchEvent(new Event('input', { bubbles: true }));
                          }
                      }
                  },

                  /**
                   * Build entities array from available entity information
                   */
                  buildEntitiesArray() {

                      const entities = {};

                      // Helper to add entity if ID exists
                      const addEntity = (key, id) => {
                          if (id) {
                              entities[key] = parseInt(id);
                          }
                      };

                      // PRIORITY 1: Check Vue data properties (set by info mail button)
                      if (this.infoMailLeadId) {
                          addEntity('lead', this.infoMailLeadId);
                      }
                      if (this.infoMailPersonId) {
                          addEntity('person', this.infoMailPersonId);
                      }

                      // PRIORITY 2: Add entity IDs from entity object (most reliable for lead view)
                      if (this.entity?.id) {
                          const controlName = (this.entityControlName || '').toString().toLowerCase();
                          const entityType = (this.entity.entity_type || this.entity.type || '').toString().toLowerCase();


                          // Prioritize entityControlName over entity type
                          if (controlName === 'person_id') {
                              addEntity('person', this.entity.id);
                          } else if (controlName === 'sales_lead_id') {
                              addEntity('sales_lead', this.entity.id);
                          } else if (controlName === 'lead_id') {
                              addEntity('lead', this.entity.id);
                          } else if (entityType === 'leads' || entityType === 'lead') {
                              addEntity('lead', this.entity.id);
                          } else if (entityType === 'sales_leads' || entityType === 'sales_lead') {
                              addEntity('sales_lead', this.entity.id);
                          }
                      }

                      // PRIORITY 3: Fallbacks - if nested lead object exists
                      if (!entities.lead && this.entity?.lead?.id) {
                          console.log('Using nested lead', this.entity.lead.id);
                          addEntity('lead', this.entity.lead.id);
                      }

                      // PRIORITY 4: Check form for lead_id, person_id, and order_id
                      const form = this.$refs.mailActionForm;
                      if (form) {
                          const formLeadId = form.querySelector('[name="lead_id"]')?.value || form.dataset.leadId;
                          const formPersonId = form.querySelector('[name="person_id"]')?.value || form.dataset.personId;
                          const formOrderId = form.querySelector('[name="order_id"]')?.value || form.dataset.orderId;

                          if (formLeadId && !entities.lead) {
                              addEntity('lead', formLeadId);
                          }
                          if (formPersonId && !entities.person) {
                              addEntity('person', formPersonId);
                          }
                          if (formOrderId && !entities.order) {
                              addEntity('order', formOrderId);
                          }
                      }

                      // PRIORITY 4.5: Check orderId prop (from order edit page)
                      if (this.orderId && !entities.order) {
                          addEntity('order', this.orderId);
                      }

                      // PRIORITY 5: Ensure at least one id is present; if still missing and entityControlName indicates sales lead
                      if (!entities.lead && !entities.sales_lead) {
                          if ((this.entityControlName || '').toString().toLowerCase() === 'sales_lead_id' && this.entity?.id) {
                              addEntity('sales_lead', this.entity.id);
                          }
                      }

                      return entities;
                  },

                  loadTemplate() {
                      if (!this.selectedTemplate) {
                          return;
                      }

                      const entities = this.buildEntitiesArray();

                      if (Object.keys(entities).length === 0) {
                          this.$emitter.emit('add-flash', {
                              type: 'error',
                              message: 'Geen entities gevonden om template variabelen op te lossen'
                          });
                          return;
                      }

                      // Load body and subject separately using new API
                      Promise.all([
                          this.$axios.post('{{ route('admin.mail.template_content_body') }}', {
                              email_template_identifier: this.selectedTemplate,
                              entities: entities
                          }),
                          this.$axios.post('{{ route('admin.mail.template_content_subject') }}', {
                              email_template_identifier: this.selectedTemplate,
                              entities: entities
                          })
                      ])
                          .then(([bodyResponse, subjectResponse]) => {
                              const templateContent = bodyResponse.data.data.content || '';
                              const templateSubject = subjectResponse.data.data.subject || '';
                              const signature = @json(auth()->guard('user')->user()->signature ?? '');

                              // Set subject field if template has a subject
                              const subjectField = this.$refs.mailActionForm.querySelector('[name="subject"]');
                              if (subjectField && templateSubject) {
                                  subjectField.value = templateSubject;
                                  subjectField.dispatchEvent(new Event('input', { bubbles: true }));
                              }

                              // Combine template content with signature
                              const fullContent = templateContent + (signature ? '<br><br>' + signature : '');

                              // Set content in TinyMCE or textarea
                              this.setContentWithRetry(fullContent);
                          })
                          .catch(error => {
                              console.error('Error loading template', error);
                              this.$emitter.emit('add-flash', {
                                  type: 'error',
                                  message: 'Fout bij het laden van template: ' + (error.response?.data?.message || error.message)
                              });
                          });
                  },

                  removeTinyMCE() {
                      tinymce?.remove?.();
                  },
                  openModal(type) {
                      this.openModalWithPayload({});
                  },

                  async openModalWithPayload(payload = {}) {
                      const {
                          defaultEmail = null,
                          activityId = null,
                          subject = '',
                          body = '',
                          emails = null,
                          attachments = [],
                      } = payload || {};

                      // Ensure templates are loaded before opening modal
                      if (!this.emailTemplates || this.emailTemplates.length === 0) {
                          await this.loadTemplates();
                      }

                      // Set default template if available (before opening modal)
                      const defaultTemplateValue = this.currentDefaultTemplate || this.defaultTemplate;
                      if (defaultTemplateValue && this.emailTemplates.some(t => (t.code || t.name) === defaultTemplateValue)) {
                          this.selectedTemplate = defaultTemplateValue;
                      } else {
                          this.selectedTemplate = ''; // Reset template selection
                      }

                      this.$refs.mailActivityModal.open();

                      setTimeout(async () => {
                          if (Array.isArray(emails) && emails.length) {
                              this.entityEmails = emails;
                          } else if (! this.entityEmails.length) {
                              this.entityEmails = this.emails && this.emails.length ? this.emails : this.collectEntityEmails();
                          }

                          const emailField = this.$refs.mailActionForm.querySelector('[name="reply_to"]');
                          const hasExistingEmail = emailField && emailField.value && emailField.value.trim().length;

                          // Extract email from defaultEmail if it's an object
                          let resolvedEmail = defaultEmail;
                          if (defaultEmail && typeof defaultEmail === 'object' && !Array.isArray(defaultEmail)) {
                              resolvedEmail = defaultEmail.email || defaultEmail.value || null;
                          }

                          if (!resolvedEmail && !hasExistingEmail) {
                              resolvedEmail = this.getDefaultEmail();
                          }

                          if (resolvedEmail && (!hasExistingEmail || (this.selectedEmailLabel || '').toLowerCase() !== String(resolvedEmail).toLowerCase())) {
                              this.setReplyTo(resolvedEmail);
                          }

                          const subjectField = this.$refs.mailActionForm.querySelector('[name="subject"]');
                          if (subjectField && subject) {
                              subjectField.value = subject;
                              subjectField.dispatchEvent(new Event('input', { bubbles: true }));
                          }

                          const messageField = this.$refs.mailActionForm.querySelector('[name="reply"]');

                          // If default template was set, load it (even if body exists, template takes precedence)
                          if (defaultTemplateValue && this.selectedTemplate === defaultTemplateValue) {
                              setTimeout(() => this.loadTemplate(), 250);
                          } else if (body && body.trim()) {
                              this.setContentWithRetry(body);
                          } else if (messageField && !messageField.value.trim()) {
                              // Fallback to reply template or signature
                              const fallbackTemplate = 'reply';
                              if (this.emailTemplates.some(t => (t.code || t.name) === fallbackTemplate)) {
                                  this.selectedTemplate = fallbackTemplate;
                                  setTimeout(() => this.loadTemplate(), 250);
                              } else {
                                  // Fallback to signature only
                                  @if(auth()->guard('user')->user() && auth()->guard('user')->user()->signature)
                                      this.setContentWithRetry(@json(auth()->guard('user')->user()->signature));
                                  @endif
                              }
                          }

                          // Inject activity_id hidden input if provided
                          const formEl = this.$refs.mailActionForm;
                          if (formEl && (activityId || this.activityId)) {
                              let hidden = formEl.querySelector('input[name="activity_id"]');
                              if (!hidden) {
                                  hidden = document.createElement('input');
                                  hidden.type = 'hidden';
                                  hidden.name = 'activity_id';
                                  formEl.appendChild(hidden);
                              }
                              hidden.value = activityId || this.activityId;
                          }

                          // Add attachments if provided (sequentially to avoid race conditions)
                          if (attachments && Array.isArray(attachments) && attachments.length > 0) {
                              for (let i = 0; i < attachments.length; i++) {
                                  const attachment = attachments[i];
                                  if (attachment.url && attachment.filename) {
                                      // Add delay between attachments to ensure they're added sequentially
                                      await new Promise(resolve => setTimeout(resolve, i * 200));
                                      await this.addPdfAttachment(attachment.url, attachment.filename);
                                  }
                              }
                          }
                      }, 100);
                  },

                  async addPdfAttachment(pdfUrl, filename) {
                      try {
                          // Wait for the modal and attachments component to be fully rendered
                          // Try multiple times to find the component
                          let fileInput = null;

                          for (let attempt = 0; attempt < 10; attempt++) {
                              await this.$nextTick();
                              await new Promise(resolve => setTimeout(resolve, 200));

                              // Try to find via ref first
                              const attachmentsComponent = this.$refs.attachmentsComponent;

                              if (attachmentsComponent) {
                                  // Found component via ref, now get file input
                                  const componentUid = attachmentsComponent.$.uid;
                                  fileInput = attachmentsComponent.$refs[componentUid + '_attachmentInput'];
                                  if (fileInput) {
                                      break;
                                  }
                              }

                              // If not found via ref, try to find via DOM
                              if (!fileInput) {
                                  const formEl = this.$refs.mailActionForm;
                                  if (formEl) {
                                      fileInput = formEl.querySelector('input[type="file"][accept="attachment/*"]');
                                      if (fileInput) {
                                          break;
                                      }
                                  }
                              }
                          }

                          if (!fileInput) {
                              return;
                          }

                          // Download PDF as blob
                          let response;
                          try {
                              response = await fetch(pdfUrl);
                              if (!response.ok) {
                                  console.warn('[Mail] Failed to download PDF attachment:', pdfUrl, response.status);
                                  return;
                              }
                          } catch (error) {
                              console.warn('[Mail] Error downloading PDF attachment (CORS/network):', pdfUrl, error.message);
                              return;
                          }

                          const blob = await response.blob();

                          // Convert blob to File object
                          const file = new File([blob], filename, { type: 'application/pdf' });

                          // Create DataTransfer object
                          const dataTransfer = new DataTransfer();

                          // Add existing files if any
                          if (fileInput.files && fileInput.files.length > 0) {
                              for (let i = 0; i < fileInput.files.length; i++) {
                                  dataTransfer.items.add(fileInput.files[i]);
                              }
                          }

                          // Add the new PDF file
                          dataTransfer.items.add(file);

                          // Set the files on the input
                          fileInput.files = dataTransfer.files;

                          // Trigger change event to add the attachment
                          // This will trigger the add() method in the attachments component
                          fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                      } catch (error) {
                          // Silently fail - attachment addition is not critical
                      }
                  },

                  collectEntityEmails() {
                      // If server-provided emails exist, use them
                      if (this.emails && this.emails.length > 0) {
                        return this.emails;
                    }

                    // Fallback to client-side logic for backwards compatibility
                    const results = [];
                    const pushEmail = (value, isDefault = false) => {
                        if (value && typeof value === 'string') {
                            results.push({ value, is_default: !!isDefault });
                        }
                    };

                    // Lead or Person with emails array [{value, is_default}]
                    const tryExtract = (obj) => {
                        if (!obj) return;
                        if (Array.isArray(obj.emails)) {
                            obj.emails.forEach(e => {
                                if (e && e.value) pushEmail(e.value, e.is_default === true || e.is_default === 'on' || e.is_default === '1');
                            });
                        }
                        if (obj.email) pushEmail(obj.email, true);
                    };

                    tryExtract(this.entity);

                    // Some entities may have nested person
                    if (this.entity && this.entity.contactPerson)
                    {
                        tryExtract(this.entity.person);
                    }
                    else if (this.entity && this.entity.person)
                    {
                        tryExtract(this.entity.person);
                    }

                    // De-duplicate, preserve first/default
                    const seen = new Set();
                    const deduped = [];
                    results.forEach(r => {
                        const key = r.value.toLowerCase();
                        if (!seen.has(key)) {
                            seen.add(key);
                            deduped.push(r);
                        }
                    });

                    // Ensure one default
                    if (!deduped.some(e => e.is_default) && deduped.length) {
                        deduped[0].is_default = true;
                    }

                    return deduped;
                },

                getDefaultEmail() {
                    const def = this.entityEmails.find(e => e.is_default);
                    return def ? def.value : (this.entityEmails[0]?.value || '');
                },

                setReplyTo(email) {
                    // v-control-tags uses an inner input named 'temp-<name>' and adds tag on blur
                    const tempInput = this.$refs.mailActionForm.querySelector('input[name="temp-reply_to"]');
                    if (tempInput) {
                        tempInput.value = email;
                        tempInput.dispatchEvent(new Event('input', { bubbles: true }));
                        tempInput.blur();
                        this.selectedEmailLabel = email;
                    }
                },

                focusReplyToInput() {
                    const emailField = this.$refs.mailActionForm.querySelector('[name="reply_to"]');
                    if (emailField) {
                        emailField.focus();
                    }
                },

                  save(params, { resetForm, setErrors  }) {
                      this.isStoring = true;

                      const entityId = this.entity?.id ?? null;
                      let fallbackUrlTemplate = null;

                      // Determine fallback URL based on entity control name
                      if (this.entityControlName === 'clinic_id') {
                          fallbackUrlTemplate = "{{ route('admin.clinics.emails.store', 'replaceEntityId') }}";
                      } else if (this.entityControlName === 'lead_id') {
                          fallbackUrlTemplate = "{{ route('admin.leads.emails.store', 'replaceEntityId') }}";
                      } else if (this.entityControlName === 'sales_lead_id') {
                          fallbackUrlTemplate = "{{ route('admin.sales-leads.emails.store', 'replaceEntityId') }}";
                      } else if (this.entityControlName === 'person_id') {
                          // Persons use the general mail store route, person_id is sent via form data
                          fallbackUrlTemplate = "{{ route('admin.mail.store') }}";
                      } else {
                          // Default to leads for backward compatibility
                          fallbackUrlTemplate = "{{ route('admin.leads.emails.store', 'replaceEntityId') }}";
                      }

                      let resolvedStoreUrl = null;
                      if (this.storeUrl && this.storeUrl.length) {
                          resolvedStoreUrl = this.storeUrl;
                      } else if (fallbackUrlTemplate) {
                          // For person_id, use the route as-is (no entity ID in URL)
                          if (this.entityControlName === 'person_id') {
                              resolvedStoreUrl = fallbackUrlTemplate;
                          } else if (entityId) {
                              resolvedStoreUrl = fallbackUrlTemplate.replace('replaceEntityId', entityId);
                          }
                      }

                      if (!resolvedStoreUrl || resolvedStoreUrl.includes('replaceEntityId')) {
                          this.isStoring = false;
                          this.$emitter.emit('add-flash', {
                              type: 'error',
                              message: 'Kan geen e-mailroute bepalen voor deze entiteit.',
                          });
                          return;
                      }

                      const formData = new FormData(this.$refs.mailActionForm);

                      this.$axios.post(resolvedStoreUrl, formData, {
                              headers: {
                                  'Content-Type': 'multipart/form-data'
                              }
                          })
                          .then (response => {
                              this.isStoring = false;

                              this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                              this.$emitter.emit('on-activity-added', response.data.data);

                              this.$refs.mailActivityModal.close();
                          })
                          .catch (error => {
                              this.isStoring = false;

                              if (error?.response?.status == 422) {
                                  setErrors(error.response.data.errors);
                              } else {
                                  this.$emitter.emit('add-flash', { type: 'error', message: error?.response?.data?.message || 'Opslaan van e-mail mislukt.' });

                                  this.$refs.mailActivityModal.close();
                              }
                          });
                  },
            },
        });
    </script>
@endPushOnce
