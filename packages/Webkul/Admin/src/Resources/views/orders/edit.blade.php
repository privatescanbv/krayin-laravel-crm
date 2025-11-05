@php use App\Enums\OrderStatus;use App\Models\SalesLead; @endphp
<x-admin::layouts>
    <x-slot:title>
        Order bewerken
    </x-slot>

    <x-admin::form id="order-edit-form" :action="route('admin.orders.update', ['id' => $orders->id])" method="POST">
        <input type="hidden" name="_method" value="put">
        <x-admin::form.control-group>
            <x-admin::form.control-group.control type="hidden" name="redirect_to"
                                                 value="{{ route('admin.orders.edit', ['id' => $orders->id]) }}"/>
        </x-admin::form.control-group>

        <div class="flex flex-col gap-4">
            <!-- Titel panel met order status -->
            <div
                class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="orders.edit" :entity="$orders"/>

                    <div class="flex items-center gap-3">
                        <div class="text-xl font-bold dark:text-gray-300">
                            Order bewerken
                        </div>
                        @if($orders->status)
                            <span
                                class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full {{ $orders->status->getStatusClass() }}">
                                {{ $orders->status->label() }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    @php $mailDisabled = $orders->status !== OrderStatus::INGEPLAND; @endphp
                    @if ($orders->sales_lead_id)
                        <button
                            type="button"
                            class="secondary-button {{ $mailDisabled ? 'pointer-events-none opacity-60' : '' }}"
                            id="order-compose-mail"
                            {{ $mailDisabled ? 'disabled' : '' }}
                            title="{{ $mailDisabled ? 'Eerst order op Ingepland zetten' : '' }}"
                        >
                            Maak order mail
                        </button>
                    @endif

                    <button
                        type="button"
                        class="secondary-button"
                        id="order-edit-planner"
                        data-redirect-to="{{ route('admin.planning.monitor.order', ['orderId' => $orders->id]) }}"
                    >
                        Resource Planner
                    </button>

                    <button
                        type="button"
                        class="secondary-button"
                        id="order-edit-apply"
                        data-redirect-to="{{ route('admin.orders.edit', ['id' => $orders->id]) }}"
                    >
                        Toepassen
                    </button>

                    <button
                        type="button"
                        class="primary-button"
                        id="order-edit-save"
                        data-redirect-to="{{ route('admin.orders.index') }}"
                    >
                        Opslaan
                    </button>
                </div>
            </div>


            <!-- Apart panel met velden en tabs -->
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <v-order-tabs>
                    <template #details>
                        <div class="space-y-6">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">Titel
                                </x-admin::form.control-group.label>
                                <x-admin::form.control-group.control type="text" name="title" :value="$orders->title"
                                                                     rules="required"/>
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">Sales
                                </x-admin::form.control-group.label>
                                <x-admin::form.control-group.control
                                    type="select"
                                    name="sales_lead_id"
                                    value="{{ $orders->sales_lead_id ?? '' }}"
                                    rules="required"
                                    readonly
                                    disabled
                                >
                                    <option value="">Selecteer een sales</option>
                                    @if(isset($salesLeads))
                                        @foreach($salesLeads as $id => $name)
                                            <option
                                                value="{{ $id }}" {{ $orders->sales_lead_id == $id ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </x-admin::form.control-group.control>
                                <input type="hidden" name="sales_lead_id" value="{{ $orders->sales_lead_id }}">
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">Status
                                </x-admin::form.control-group.label>
                                <x-admin::form.control-group.control
                                    type="select"
                                    name="status"
                                    value="{{ $orders->status->value ?? '' }}"
                                    rules="required"
                                >
                                    @foreach(OrderStatus::cases() as $status)
                                        <option
                                            value="{{ $status->value }}" {{ $orders->status === $status ? 'selected' : '' }}>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </x-admin::form.control-group.control>
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>Orders combineren</x-admin::form.control-group.label>
                                <x-admin::form.control-group.control
                                    type="select"
                                    name="combine_order"
                                    value="{{ $orders->combine_order ? '1' : '0' }}"
                                >
                                    <option value="1" {{ $orders->combine_order ? 'selected' : '' }}>Ja</option>
                                    <option value="0" {{ !$orders->combine_order ? 'selected' : '' }}>Nee</option>
                                </x-admin::form.control-group.control>
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>GVL Formulier Link</x-admin::form.control-group.label>
                                <div class="flex items-center gap-2">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="gvl_form_link"
                                        value="{{ $orders->salesLead?->gvl_form_link ?? '' }}"
                                        class="flex-1"
                                    />
                                    @if($orders->salesLead->gvl_form_link)
                                        <button
                                            type="button"
                                            id="reset-gvl-form-link"
                                            class="secondary-button"
                                            title="Reset GVL formulier link"
                                        >
                                            Reset
                                        </button>
                                    @endif
                                </div>
                                <x-admin::form.control-group.error control-name="gvl_form_link" />
                                @if($orders->salesLead && $orders->salesLead->gvl_form_link)
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                        <a href="{{ $orders->salesLead->gvl_form_link }}" target="_blank" class="text-blue-600 hover:underline">
                                            Open formulier
                                        </a>
                                    </p>
                                @endif
                            </x-admin::form.control-group>
                            </div>

                            @include('admin::orders.partials.items', ['order' => $orders])
                        </div>
                    </template>

                    <template #checks>
                        <div class="space-y-6">
                            <div class="flex flex-col gap-1">
                                <p class="text-base font-semibold text-gray-800 dark:text-white">Order Checks</p>
                                <p class="text-sm text-gray-600 dark:text-white">Beheer de checklist voor deze
                                    order.</p>
                            </div>

                            <v-order-checks :order-id="{{ $orders->id }}"
                                            :checks='@json($orders->orderChecks ?? [])'></v-order-checks>
                        </div>
                    </template>
                </v-order-tabs>
            </div>
        </div>
    </x-admin::form>

      @if ($orders->sales_lead_id)
          @php
              $orderMailEntity = [
                  'id'   => $orders->sales_lead_id,
                  'name' => $orders->salesLead?->name,
              ];
          @endphp

          <x-admin::activities.actions.mail
              :entity="$orderMailEntity"
              entity-control-name="sales_lead_id"
              :emails="$orderEmailOptions ?? []"
              store-url="{{ route('admin.sales-leads.emails.store', ['id' => $orders->sales_lead_id]) }}"
              :show-button="false"
          />
      @endif


    @pushOnce('scripts')
        @php
            $completedChecks = ($orders->orderChecks ?? collect())->where('done', true)->count();
            $totalChecks = ($orders->orderChecks ?? collect())->count();
        @endphp
            <!-- v-order-tabs component template -->
        <script type="text/x-template" id="v-order-tabs-template">
            <div class="flex flex-col gap-0">
                <div class="flex gap-6 border-b border-gray-200 dark:border-gray-800 px-6">
                    <button type="button"
                            :class="[
                                'py-4 px-1 border-b-2 text-sm font-medium transition-colors duration-200',
                                activeTab === 'details'
                                  ? 'text-brandColor border-brandColor'
                                  : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300'
                            ]"
                            @click="activeTab = 'details'">
                        Details
                    </button>

                    <button type="button"
                            :class="[
                                'py-4 px-1 border-b-2 text-sm font-medium transition-colors duration-200',
                                activeTab === 'checks'
                                  ? 'text-brandColor border-brandColor'
                                  : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300'
                            ]"
                            @click="activeTab = 'checks'"
                            title="{{ $completedChecks }} checks gedaan van de {{ $totalChecks }}">
                        Checks ({{ $completedChecks }}/{{ $totalChecks }})
                    </button>
                </div>

                <div class="p-6">
                    <div v-show="activeTab === 'details'">
                        <slot name="details"></slot>
                    </div>

                    <div v-show="activeTab === 'checks'">
                        <slot name="checks"></slot>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">

            // Use event delegation to handle Vue.js DOM changes
            document.addEventListener('click', function (e) {
                // Check if clicked element is one of our buttons
                if (e.target.id === 'order-edit-planner' ||
                    e.target.id === 'order-edit-apply' ||
                    e.target.id === 'order-edit-save') {

                    e.preventDefault();

                    var target = e.target.getAttribute('data-redirect-to');
                    if (!target) return;

                    var form = document.getElementById('order-edit-form') || document.querySelector('form');
                    if (!form) return;

                    var hidden = form.querySelector('input[name="redirect_to"]');
                    if (!hidden) return;

                    hidden.value = target;
                    form.submit();
                }

                // Reset GVL form link button via delegation
                if (e.target && e.target.id === 'reset-gvl-form-link') {
                    e.preventDefault();
                    const input = document.querySelector('input[name="gvl_form_link"]');
                    if (input) {
                        input.value = '';
                    }
                    return;
                }

                // Compose order mail button via delegation (robust to re-renders)
                if (e.target && e.target.id === 'order-compose-mail') {
                    e.preventDefault();

                    const button = e.target;
                    if (button.dataset.loading === 'true') {
                        return;
                    }

                    const endpoint = "{{ route('admin.orders.mail.preview', ['orderId' => $orders->id]) }}";

                    const emitFlash = (type, message) => {
                        try {
                            const emitter = window.app?.config?.globalProperties?.$emitter || window.app?.$emitter;
                            if (emitter) {
                                emitter.emit('add-flash', { type, message });
                            } else {
                                console[type === 'error' ? 'error' : 'log'](message);
                            }
                        } catch (err) {
                            console.error('[OrderEdit] Flash emit failed', err, message);
                        }
                    };

                    (async () => {
                        try {
                            button.dataset.loading = 'true';
                            const originalLabel = button.dataset.originalLabel || button.textContent.trim();
                            button.dataset.originalLabel = originalLabel;
                            button.textContent = 'Bezig...';
                            button.classList.add('pointer-events-none', 'opacity-60');

                            const response = await fetch(endpoint, { headers: { 'Accept': 'application/json' } });
                            const payload = await response.json().catch(() => ({}));

                            if (!response.ok) {
                                throw new Error(payload?.message || 'Kon order mail niet voorbereiden.');
                            }

                            window.dispatchEvent(new CustomEvent('open-email-dialog', {
                                detail: {
                                    defaultEmail: payload.default_email || null,
                                    subject: payload.subject || '',
                                    body: payload.body || '',
                                    emails: payload.emails || [],
                                },
                            }));

                            if (!payload.default_email) {
                                emitFlash('info', 'Geen standaard e-mailadres gevonden. Vul het adres handmatig in.');
                            }
                        } catch (error) {
                            emitFlash('error', error?.message || 'Voorbereiden van de ordermail is mislukt.');
                        } finally {
                            button.dataset.loading = 'false';
                            button.textContent = button.dataset.originalLabel || 'Maak order mail';
                            button.classList.remove('pointer-events-none', 'opacity-60');
                        }
                    })();
                }
            });

            // Register v-order-tabs
            app.component('v-order-tabs', {
                template: '#v-order-tabs-template',
                data() {
                    return {
                        activeTab: 'details',
                    };
                },
            });

            // sales change functionality (kept)
            const initOrderEditSalesLead = () => {
                try {
                    const salesLeadSelect = document.querySelector('select[name="sales_lead_id"]');
                    if (salesLeadSelect && !salesLeadSelect.dataset.bound) {
                        salesLeadSelect.addEventListener('change', function () {
                            const salesLeadId = this.value;
                            if (salesLeadId) {
                                fetch(`/admin/orders/persons/${salesLeadId}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (window.app && window.app.config && window.app.config.globalProperties) {
                                            const app = window.app;
                                            const orderItemsVue = app._instance?.proxy?.$refs?.orderItemsList;
                                            if (orderItemsVue) {
                                                orderItemsVue.persons = data.persons || {};
                                            }
                                        }
                                    })
                                    .catch(error => {
                                        console.error('[OrderEdit] Error loading persons:', error);
                                    });
                            }
                        });
                        salesLeadSelect.dataset.bound = 'true';
                    }
                } catch (err) {
                    console.error('[OrderEdit] SalesLead init error', err);
                }
            };

              const initOrderMailCompose = () => {
                  try {
                      const button = document.getElementById('order-compose-mail');
                      if (!button || button.dataset.bound === 'true') {
                          return;
                      }

                      const endpoint = "{{ route('admin.orders.mail.preview', ['orderId' => $orders->id]) }}";

                      const emitFlash = (type, message) => {
                          try {
                              const emitter = window.app?.config?.globalProperties?.$emitter || window.app?.$emitter;
                              if (emitter) {
                                  emitter.emit('add-flash', {type, message});
                              } else {
                                  console[type === 'error' ? 'error' : 'log'](message);
                              }
                          } catch (err) {
                              console.error('[OrderEdit] Flash emit failed', err, message);
                          }
                      };

                      button.addEventListener('click', async () => {
                          if (button.dataset.loading === 'true') {
                              return;
                          }

                          button.dataset.loading = 'true';
                          const originalLabel = button.dataset.originalLabel || button.textContent.trim();
                          button.dataset.originalLabel = originalLabel;
                          button.textContent = 'Bezig...';
                          button.classList.add('pointer-events-none', 'opacity-60');

                          try {
                              const response = await fetch(endpoint, {headers: {'Accept': 'application/json'}});
                              const payload = await response.json();

                              if (!response.ok) {
                                  throw new Error(payload?.message || 'Kon order mail niet voorbereiden.');
                              }

                              const detail = {
                                  defaultEmail: payload.default_email || null,
                                  subject: payload.subject || '',
                                  body: payload.body || '',
                                  emails: payload.emails || [],
                              };

                              let handled = false;
                              const handlers = window.__mailDialogHandlers;
                              if (Array.isArray(handlers) && handlers.length) {
                                  handlers.forEach((handler) => {
                                      try {
                                          handler(detail);
                                          handled = true;
                                      } catch (handlerError) {
                                          console.error('[OrderEdit] Mail handler error', handlerError);
                                      }
                                  });
                              }

                              if (!handled) {
                                  window.dispatchEvent(new CustomEvent('open-email-dialog', {detail}));
                              }

                              if (!payload.default_email) {
                                  emitFlash('info', 'Geen standaard e-mailadres gevonden. Vul het adres handmatig in.');
                              } else {
                                  emitFlash('success', 'Ordermail is voor je klaargezet.');
                              }
                          } catch (error) {
                              emitFlash('error', error?.message || 'Voorbereiden van de ordermail is mislukt.');
                              if (!window.app && typeof alert === 'function') {
                                  alert(error?.message || 'Voorbereiden van de ordermail is mislukt.');
                              }
                          } finally {
                              button.dataset.loading = 'false';
                              button.textContent = button.dataset.originalLabel || 'Maak order mail';
                              button.classList.remove('pointer-events-none', 'opacity-60');
                          }
                      });

                      button.dataset.bound = 'true';
                  } catch (err) {
                      console.error('[OrderEdit] Mail compose init error', err);
                  }
              };

            // Debug logging for redirect buttons and submission flow
            const initOrderEditDebug = () => {
                try {
                    const form = document.getElementById('order-edit-form') || document.querySelector('form');
                    const btnPlanner = document.getElementById('order-edit-planner');
                    const btnApply = document.getElementById('order-edit-apply');

                    if (btnPlanner && !btnPlanner.dataset.logBound) {
                        btnPlanner.addEventListener('click', (e) => {
                            const form = document.getElementById('order-edit-form') || document.querySelector('form');
                            const hidden = form ? form.querySelector('input[name="redirect_to"]') : null;
                            console.log('[OrderEdit][click] Planner clicked', {
                                buttonId: 'order-edit-planner',
                                dataRedirectTo: btnPlanner.getAttribute('data-redirect-to'),
                                formaction: btnPlanner.getAttribute('formaction'),
                                hiddenRedirectToBefore: hidden ? hidden.value : null,
                            });
                        });
                        btnPlanner.dataset.logBound = 'true';
                    }

                    if (btnApply && !btnApply.dataset.logBound) {
                        btnApply.addEventListener('click', (e) => {
                            const form = document.getElementById('order-edit-form') || document.querySelector('form');
                            const hidden = form ? form.querySelector('input[name="redirect_to"]') : null;
                            console.log('[OrderEdit][click] Apply clicked', {
                                buttonId: 'order-edit-apply',
                                dataRedirectTo: btnApply.getAttribute('data-redirect-to'),
                                formaction: btnApply.getAttribute('formaction'),
                                hiddenRedirectToBefore: hidden ? hidden.value : null,
                            });
                        });
                        btnApply.dataset.logBound = 'true';
                    }

                    if (form && !form.dataset.logBound) {
                        form.addEventListener('submit', (e) => {
                            const active = document.activeElement;
                            const hidden = form.querySelector('input[name="redirect_to"]');
                            const activeFormaction = active && active.getAttribute ? active.getAttribute('formaction') : null;
                            console.log('[OrderEdit][submit]', {
                                action: form.getAttribute('action'),
                                method: (form.getAttribute('method') || 'GET').toUpperCase(),
                                activeElement: active ? (active.id || active.name || active.tagName) : null,
                                activeFormaction,
                                hiddenRedirectTo: hidden ? hidden.value : null,
                            });
                        }, true);
                        form.dataset.logBound = 'true';
                    }
                } catch (err) {
                    console.log('[OrderEdit] Debug init error', err);
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    initOrderEditSalesLead();
                    initOrderEditDebug();
                      initOrderMailCompose();

                    // After email stored, mark order as sent (min JS: one small call)
                    try {
                        const emitter = window.app?.config?.globalProperties?.$emitter || window.app?.$emitter;
                        if (emitter && !window.__orderMailStatusBound) {
                            emitter.on('on-activity-added', async () => {
                                try {
                                    await fetch("{{ route('admin.orders.status.sent', ['orderId' => $orders->id]) }}", {
                                        method: 'POST',
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')) || (document.querySelector('input[name="_token"]')?.value) || ''
                                        }
                                    });
                                } catch (e) {
                                    // ignore quietly; user can still continue
                                }
                            });
                            window.__orderMailStatusBound = true;
                        }
                    } catch (e) {
                        // ignore
                    }
                }, {once: true});
            } else {
                initOrderEditSalesLead();
                initOrderEditDebug();
                  initOrderMailCompose();

                // After email stored, mark order as sent
                try {
                    const emitter = window.app?.config?.globalProperties?.$emitter || window.app?.$emitter;
                    if (emitter && !window.__orderMailStatusBound) {
                        emitter.on('on-activity-added', async () => {
                            try {
                                await fetch("{{ route('admin.orders.status.sent', ['orderId' => $orders->id]) }}", {
                                    method: 'POST',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')) || (document.querySelector('input[name="_token"]')?.value) || ''
                                    }
                                });
                            } catch (e) {}
                        });
                        window.__orderMailStatusBound = true;
                    }
                } catch (e) {}
            }
        </script>

        <!-- Order Checks Component -->
        <script type="text/x-template" id="v-order-checks-template">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Checklist</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            @{{ completedChecks }}/@{{ localChecks.length }} voltooid
                        </p>
                    </div>
                    <button type="button" @click="addCheck" class="primary-button flex items-center gap-2">
                        <i class="icon-plus text-sm"></i>
                        Check toevoegen
                    </button>
                </div>

                <div class="space-y-3">
                    <div v-for="(check, index) in localChecks" :key="check.id || index"
                         class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg transition-all duration-200"
                         :class="{ 'opacity-60': check.done }">
                        <input type="checkbox"
                               v-model="check.done"
                               @change="updateCheck(check)"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer">
                        <input type="text"
                               v-model="check.name"
                               @blur="updateCheck(check)"
                               @keyup.enter="updateCheck(check)"
                               class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white transition-all duration-200"
                               :class="{ 'line-through text-gray-500': check.done }"
                               placeholder="Check naam invoeren...">
                        <button type="button"
                                @click="removeCheck(check, index)"
                                v-if="check.removable !== false"
                                class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200"
                                title="Check verwijderen">
                            <i class="icon-delete text-lg"></i>
                        </button>
                        <span v-else class="text-gray-400 text-sm px-2" title="Deze check kan niet worden verwijderd">
                            <i class="icon-lock text-sm"></i>
                        </span>
                    </div>
                </div>

                <div v-if="localChecks.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                    <div class="flex flex-col items-center gap-3">
                        <i class="icon-check text-4xl text-gray-300 dark:text-gray-600"></i>
                        <p class="text-lg font-medium">Nog geen checks toegevoegd</p>
                        <p class="text-sm">Klik op "Check toevoegen" om te beginnen met je checklist</p>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-order-checks', {
                template: '#v-order-checks-template',
                props: ['orderId', 'checks'],
                data() {
                    return {
                        localChecks: this.checks && this.checks.length ? this.checks.map(c => ({
                            id: c.id || null,
                            name: c.name || '',
                            done: c.done || false,
                            removable: c.removable !== undefined ? c.removable : true,
                            order_id: this.orderId
                        })) : []
                    };
                },
                computed: {
                    completedChecks() {
                        return this.localChecks.filter(check => check.done).length;
                    }
                },
                methods: {
                    addCheck() {
                        this.localChecks.push({
                            id: null,
                            name: '',
                            done: false,
                            removable: true,
                            order_id: this.orderId
                        });

                        // Focus on the new input after it's rendered
                        this.$nextTick(() => {
                            try {
                                const inputs = this.$el.querySelectorAll('input[type="text"]');
                                if (inputs && inputs.length > 0) {
                                    inputs[inputs.length - 1].focus();
                                }
                            } catch (e) {
                                // no-op
                            }
                        });
                    },
                    async updateCheck(check) {
                        if (!check.name.trim()) {
                            // If name is empty and it's a new check, remove it
                            if (!check.id) {
                                const index = this.localChecks.indexOf(check);
                                if (index > -1) {
                                    this.localChecks.splice(index, 1);
                                }
                            }
                            return;
                        }

                        try {
                            const url = check.id
                                ? `/admin/orders/${this.orderId}/checks/${check.id}`
                                : `/admin/orders/${this.orderId}/checks`;

                            const method = check.id ? 'PUT' : 'POST';

                            const csrfToken = (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'))
                                || (document.querySelector('input[name="_token"]')?.value)
                                || '';

                            const response = await fetch(url, {
                                method: method,
                                headers: {
                                    'Content-Type': 'application/json',
                                    ...(csrfToken ? {'X-CSRF-TOKEN': csrfToken} : {})
                                },
                                body: JSON.stringify({
                                    name: check.name,
                                    done: check.done
                                })
                            });

                            if (response.ok) {
                                const data = await response.json();
                                if (!check.id) {
                                    check.id = data.id;
                                }

                                // Show success feedback
                                this.showNotification('Check opgeslagen', 'success');
                            } else {
                                console.error('Error updating check');
                                this.showNotification('Fout bij opslaan check', 'error');
                            }
                        } catch (error) {
                            console.error('Error updating check:', error);
                            this.showNotification('Fout bij opslaan check', 'error');
                        }
                    },
                    async removeCheck(check, index) {
                        // Prevent deletion if removable is false
                        if (check.removable === false) {
                            this.showNotification('Deze check kan niet worden verwijderd', 'error');
                            return;
                        }

                        if (check.id) {
                            try {
                                const csrfToken = (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'))
                                    || (document.querySelector('input[name="_token"]')?.value)
                                    || '';
                                const response = await fetch(`/admin/orders/${this.orderId}/checks/${check.id}`, {
                                    method: 'DELETE',
                                    headers: {
                                        ...(csrfToken ? {'X-CSRF-TOKEN': csrfToken} : {})
                                    }
                                });

                                if (!response.ok) {
                                    const data = await response.json();
                                    this.showNotification(data.message || 'Fout bij verwijderen check', 'error');
                                    return;
                                }
                            } catch (error) {
                                console.error('Error deleting check:', error);
                                this.showNotification('Fout bij verwijderen check', 'error');
                                return;
                            }
                        }

                        this.localChecks.splice(index, 1);
                        this.showNotification('Check verwijderd', 'success');
                    },
                    showNotification(message, type = 'info') {
                        // Simple notification - you can replace this with your preferred notification system
                        const notification = document.createElement('div');
                        notification.className = `fixed top-4 right-4 px-4 py-2 rounded-md text-white z-50 ${
                            type === 'success' ? 'bg-green-500' :
                                type === 'error' ? 'bg-red-500' : 'bg-blue-500'
                        }`;
                        notification.textContent = message;
                        document.body.appendChild(notification);

                        setTimeout(() => {
                            notification.remove();
                        }, 3000);
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>

