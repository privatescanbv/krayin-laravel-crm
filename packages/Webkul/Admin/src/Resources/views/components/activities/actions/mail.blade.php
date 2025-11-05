@props([
    'entity'            => null,
    'entityControlName' => null,
    'emails'            => [],
    'storeUrl'          => null,
    'showButton'        => true,
])

<!-- Mail Button -->
<div>
    {!! view_render_event('admin.components.activities.actions.mail.create_btn.before') !!}

    @if ($showButton)
        <button
            type="button"
            class="flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-green-200 font-medium text-green-900 transition-all hover:border-green-400"
            @click="$refs.mailActionComponent.openModal('mail')"
        >
            <span class="icon-mail text-2xl dark:!text-green-900"></span>

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
        :activity-id="{{ isset($activity) ? (int) $activity->id : 'null' }}"
        :emails="{{ json_encode($emails) }}"
        @if ($storeUrl) store-url="{{ $storeUrl }}" @endif
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
                    >
                        <x-slot:header>
                            {!! view_render_event('admin.components.activities.actions.mail.form_controls.modal.header.before') !!}

                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.components.activities.actions.mail.title')
                            </h3>

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
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.components.activities.actions.mail.to')
                                </x-admin::form.control-group.label>

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

                                <x-admin::form.control-group.error control-name="reply_to" />
                            </x-admin::form.control-group>

                            <template v-if="showCC">
                                <!-- Cc -->
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.components.activities.actions.mail.cc')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="tags"
                                        name="cc"
                                        input-rules="email"
                                        :label="trans('admin::app.components.activities.actions.mail.cc')"
                                        :placeholder="trans('admin::app.components.activities.actions.mail.enter-emails')"
                                    />

                                    <x-admin::form.control-group.error control-name="cc" />
                                </x-admin::form.control-group>
                            </template>

                            <template v-if="showBCC">
                                <!-- Cc -->
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.components.activities.actions.mail.bcc')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="tags"
                                        name="bcc"
                                        input-rules="email"
                                        :label="trans('admin::app.components.activities.actions.mail.bcc')"
                                        :placeholder="trans('admin::app.components.activities.actions.mail.enter-emails')"
                                    />

                                    <x-admin::form.control-group.error control-name="bcc" />
                                </x-admin::form.control-group>
                            </template>

                            <!-- Subject -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.components.activities.actions.mail.subject')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    id="subject"
                                    name="subject"
                                    rules="required"
                                    :label="trans('admin::app.components.activities.actions.mail.subject')"
                                    :placeholder="trans('admin::app.components.activities.actions.mail.subject')"
                                />

                                <x-admin::form.control-group.error control-name="subject" />
                            </x-admin::form.control-group>

                            <!-- Content -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="reply"
                                    id="reply"
                                    rules="required"
                                    {{-- tinymce="true" --}}
                                    :label="trans('admin::app.components.activities.actions.mail.message')"
                                />

                                <x-admin::form.control-group.error control-name="reply" />
                            </x-admin::form.control-group>

                            <!-- Attachments -->
                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::attachments
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
                                    class="icon-attachment cursor-pointer p-1 text-2xl hover:rounded-md hover:bg-gray-100 dark:hover:bg-gray-950"
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
                }
            },

            data() {
                return {
                    showCC: false,

                    showBCC: false,

                    isStoring: false,

                    entityEmails: [],

                    selectedEmailLabel: '',
                }
            },

              mounted() {
                  // Listen for email dialog events from other components
                  window.addEventListener('open-email-dialog', (event) => {
                      const detail = event?.detail || {};
                      this.openModalWithPayload(detail);
                  });

                  // Use emails from server-side if provided, otherwise fallback to client-side logic
                  if (this.emails && this.emails.length > 0) {
                      this.entityEmails = this.emails;
                  } else {
                      this.entityEmails = this.collectEntityEmails();
                  }
              },

              methods: {
                  openModal(type) {
                      this.openModalWithPayload({});
                  },

                  openModalWithPayload(payload = {}) {
                      const {
                          defaultEmail = null,
                          activityId = null,
                          subject = '',
                          body = '',
                          emails = null,
                      } = payload || {};

                      this.$refs.mailActivityModal.open();

                      setTimeout(() => {
                          if (Array.isArray(emails) && emails.length) {
                              this.entityEmails = emails;
                          } else if (! this.entityEmails.length) {
                              this.entityEmails = this.emails && this.emails.length ? this.emails : this.collectEntityEmails();
                          }

                          const emailField = this.$refs.mailActionForm.querySelector('[name="reply_to"]');
                          const hasExistingEmail = emailField && emailField.value && emailField.value.trim().length;
                          const resolvedEmail = defaultEmail || (!hasExistingEmail ? this.getDefaultEmail() : null);

                          if (resolvedEmail && (!hasExistingEmail || (this.selectedEmailLabel || '').toLowerCase() !== resolvedEmail.toLowerCase())) {
                              this.setReplyTo(resolvedEmail);
                          }

                          const subjectField = this.$refs.mailActionForm.querySelector('[name="subject"]');
                          if (subjectField && subject) {
                              subjectField.value = subject;
                              subjectField.dispatchEvent(new Event('input', { bubbles: true }));
                          }

                          const messageField = this.$refs.mailActionForm.querySelector('[name="reply"]');
                          if (messageField) {
                              if (body && body.trim()) {
                                  messageField.value = body;
                                  messageField.dispatchEvent(new Event('input', { bubbles: true }));
                              } else if (!messageField.value.trim()) {
                                  @if(auth()->guard('user')->user() && auth()->guard('user')->user()->signature)
                                      messageField.value = `{{ auth()->guard('user')->user()->signature }}`;
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
                      }, 100);
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

                      const fallbackUrlTemplate = "{{ route('admin.leads.emails.store', 'replaceEntityId') }}";
                      const entityId = this.entity?.id ?? null;
                      const resolvedStoreUrl = this.storeUrl && this.storeUrl.length
                          ? this.storeUrl
                          : (entityId ? fallbackUrlTemplate.replace('replaceEntityId', entityId) : '');

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
