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
                <!-- Tab navigatie -->
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex space-x-8 px-6" aria-label="Tabs">
                        <button type="button" 
                                class="tab-button active py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                                data-tab="details">
                            Details
                        </button>
                        <button type="button" 
                                class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-colors duration-200"
                                data-tab="checks">
                            Checks
                        </button>
                    </nav>
                </div>

                <!-- Tab content -->
                <div class="p-6">
                    <!-- Details tab -->
                    <div id="details-tab" class="tab-content">
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
                    </div>

                    <!-- Checks tab -->
                    <div id="checks-tab" class="tab-content hidden">
                        <div class="space-y-6">
                            <div class="flex flex-col gap-1">
                                <p class="text-base font-semibold text-gray-800 dark:text-white">Order Checks</p>
                                <p class="text-sm text-gray-600 dark:text-white">Beheer de checklist voor deze order.</p>
                            </div>

                            <v-order-checks :order-id="{{ $orders->id }}" :checks='@json($orders->orderChecks ?? [])'></v-order-checks>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-admin::form>

    @pushOnce('styles')
        <style>
            .tab-button.active {
                border-color: #3b82f6;
                color: #2563eb;
            }
            .tab-button.active:hover {
                color: #2563eb;
                border-color: #3b82f6;
            }
        </style>
    @endPushOnce

    @pushOnce('scripts')
        <script type="module">
            document.addEventListener('DOMContentLoaded', function() {
                // Tab functionality
                const tabButtons = document.querySelectorAll('.tab-button');
                const tabContents = document.querySelectorAll('.tab-content');

                tabButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const targetTab = this.getAttribute('data-tab');
                        
                        // Remove active class from all buttons
                        tabButtons.forEach(btn => {
                            btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                            btn.classList.add('border-transparent', 'text-gray-500');
                        });
                        
                        // Add active class to clicked button
                        this.classList.add('active', 'border-blue-500', 'text-blue-600');
                        this.classList.remove('border-transparent', 'text-gray-500');
                        
                        // Hide all tab contents
                        tabContents.forEach(content => {
                            content.classList.add('hidden');
                        });
                        
                        // Show target tab content
                        const targetContent = document.getElementById(targetTab + '-tab');
                        if (targetContent) {
                            targetContent.classList.remove('hidden');
                        }
                    });
                });

                // Sales Lead change functionality
                const salesLeadSelect = document.querySelector('select[name="sales_lead_id"]');
                const orderItemsComponent = document.querySelector('v-order-item-list');
                
                if (salesLeadSelect) {
                    salesLeadSelect.addEventListener('change', function() {
                        const salesLeadId = this.value;
                        if (salesLeadId) {
                            // Load persons for the selected sales lead
                            fetch(`/admin/orders/persons/${salesLeadId}`)
                                .then(response => response.json())
                                .then(data => {
                                    // Update the persons data in the order items component
                                    if (window.app && window.app.config && window.app.config.globalProperties) {
                                        const app = window.app;
                                        // Find the order items component and update its persons data
                                        const orderItemsVue = app._instance?.proxy?.$refs?.orderItemsList;
                                        if (orderItemsVue) {
                                            orderItemsVue.persons = data.persons || {};
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('Error loading persons:', error);
                                });
                        }
                    });
                }
            });
        </script>

        <!-- Order Checks Component -->
        <script type="text/x-template" id="v-order-checks-template">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Checklist</h3>
                    <button type="button" @click="addCheck" class="primary-button">
                        Check toevoegen
                    </button>
                </div>

                <div class="space-y-3">
                    <div v-for="(check, index) in checks" :key="check.id || index" 
                         class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <input type="checkbox" 
                               v-model="check.done" 
                               @change="updateCheck(check)"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <input type="text" 
                               v-model="check.name" 
                               @blur="updateCheck(check)"
                               class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Check naam">
                        <button type="button" 
                                @click="removeCheck(check, index)"
                                class="text-red-600 hover:text-red-800">
                            <i class="icon-delete"></i>
                        </button>
                    </div>
                </div>

                <div v-if="checks.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                    Nog geen checks toegevoegd. Klik op "Check toevoegen" om te beginnen.
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-order-checks', {
                template: '#v-order-checks-template',
                props: ['orderId', 'checks'],
                data() {
                    return {
                        checks: this.checks && this.checks.length ? this.checks.map(c => ({
                            id: c.id || null,
                            name: c.name || '',
                            done: c.done || false,
                            order_id: this.orderId
                        })) : []
                    };
                },
                methods: {
                    addCheck() {
                        this.checks.push({
                            id: null,
                            name: '',
                            done: false,
                            order_id: this.orderId
                        });
                    },
                    async updateCheck(check) {
                        if (!check.name.trim()) return;

                        try {
                            const url = check.id 
                                ? `/admin/orders/${this.orderId}/checks/${check.id}`
                                : `/admin/orders/${this.orderId}/checks`;
                            
                            const method = check.id ? 'PUT' : 'POST';
                            
                            const response = await fetch(url, {
                                method: method,
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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
                            } else {
                                console.error('Error updating check');
                            }
                        } catch (error) {
                            console.error('Error updating check:', error);
                        }
                    },
                    async removeCheck(check, index) {
                        if (check.id) {
                            try {
                                const response = await fetch(`/admin/orders/${this.orderId}/checks/${check.id}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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
                        
                        this.checks.splice(index, 1);
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>

