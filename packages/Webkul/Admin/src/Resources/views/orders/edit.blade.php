@php use App\Models\SalesLead; @endphp
<x-admin::layouts>
    <x-slot:title>
        Order bewerken
    </x-slot>

    <x-admin::form :action="route('admin.orders.update', ['id' => $orders->id])" method="POST">
        <input type="hidden" name="_method" value="put">

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
                            <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full {{ $orders->status->getStatusClass() }}">
                                {{ $orders->status->label() }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <a href="{{ route('admin.planning.monitor.order', ['orderId' => $orders->id]) }}" class="secondary-button">
                        Resource Planner
                    </a>
                    <button type="submit" class="primary-button">
                        Opslaan
                    </button>
                </div>
            </div>

            <!-- Apart panel met velden en tabs -->
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <v-order-tabs>
                    <template #details>
                        <div class="space-y-6">

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">Titel</x-admin::form.control-group.label>
                                <x-admin::form.control-group.control type="text" name="title" :value="$orders->title" rules="required"/>
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">Sales Lead</x-admin::form.control-group.label>
                                <x-admin::form.control-group.control
                                    type="select"
                                    name="sales_lead_id"
                                    value="{{ $orders->sales_lead_id ?? '' }}"
                                    rules="required"
                                    readonly
                                    disabled
                                >
                                    <option value="">Selecteer een Sales Lead</option>
                                    @if(isset($salesLeads))
                                        @foreach($salesLeads as $id => $name)
                                            <option value="{{ $id }}" {{ $orders->sales_lead_id == $id ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </x-admin::form.control-group.control>
                                <input type="hidden" name="sales_lead_id" value="{{ $orders->sales_lead_id }}">
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">Status</x-admin::form.control-group.label>
                                <x-admin::form.control-group.control
                                    type="select"
                                    name="status"
                                    value="{{ $orders->status->value ?? '' }}"
                                    rules="required"
                                >
                                    @foreach(\App\Enums\OrderStatus::cases() as $status)
                                        <option value="{{ $status->value }}" {{ $orders->status === $status ? 'selected' : '' }}>
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

                            @include('admin::orders.partials.items', ['order' => $orders])
                        </div>
                    </template>

                    <template #checks>
                        <div class="space-y-6">
                            <div class="flex flex-col gap-1">
                                <p class="text-base font-semibold text-gray-800 dark:text-white">Order Checks</p>
                                <p class="text-sm text-gray-600 dark:text-white">Beheer de checklist voor deze order.</p>
                            </div>

                            <v-order-checks :order-id="{{ $orders->id }}" :checks='@json($orders->orderChecks ?? [])'></v-order-checks>
                        </div>
                    </template>
                </v-order-tabs>
            </div>
        </div>
    </x-admin::form>

    

    @pushOnce('scripts')
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
                            @click="activeTab = 'checks'">
                        Checks
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
            // Register v-order-tabs
            app.component('v-order-tabs', {
                template: '#v-order-tabs-template',
                data() {
                    return {
                        activeTab: 'details',
                    };
                },
            });

            // Sales Lead change functionality (kept)
            const initOrderEditSalesLead = () => {
                try {
                    const salesLeadSelect = document.querySelector('select[name="sales_lead_id"]');
                    if (salesLeadSelect && !salesLeadSelect.dataset.bound) {
                        salesLeadSelect.addEventListener('change', function() {
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

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initOrderEditSalesLead, { once: true });
            } else {
                initOrderEditSalesLead();
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
                                class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200"
                                title="Check verwijderen">
                            <i class="icon-delete text-lg"></i>
                        </button>
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
                                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
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
                        if (check.id) {
                            try {
                                const csrfToken = (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'))
                                    || (document.querySelector('input[name="_token"]')?.value)
                                    || '';
                                const response = await fetch(`/admin/orders/${this.orderId}/checks/${check.id}`, {
                                    method: 'DELETE',
                                    headers: {
                                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                                    }
                                });

                                if (!response.ok) {
                                    console.error('Error deleting check');
                                    return;
                                }
                            } catch (error) {
                                console.error('Error deleting check:', error);
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

