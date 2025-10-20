<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.partner_products.index.create.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.partner_products.store')" method="POST">
        <!-- Hidden field for return_to -->
        @if(isset($returnTo))
            <input type="hidden" name="return_to" value="{{ $returnTo }}" />
        @endif

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.partner_products.create" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.settings.partner_products.index.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.partner_products.index.create.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @php
                    $oldRelatedProducts = collect(old('related_products', []))->map(function($id) {
                        $product = \App\Models\PartnerProduct::whereNull('deleted_at')->find($id);
                        return $product ? ['id' => $product->id, 'name' => $product->name] : null;
                    })->filter()->values()->toArray();

                    // Pre-select clinic if provided
                    $selectedClinics = old('clinics', []);
                    if (empty($selectedClinics) && isset($preSelectedClinicId)) {
                        $selectedClinics = [$preSelectedClinicId];
                    }
                @endphp

                <!-- Product Template Selector -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg dark:bg-gray-800">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        @lang('admin::app.settings.partner_products.index.create.template_product')
                    </h3>
                    
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                @lang('admin::app.settings.partner_products.index.create.select_template_product')
                            </label>
                            <select 
                                id="template-product-selector" 
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                onchange="loadTemplateProduct(this.value)"
                            >
                                <option value="">@lang('admin::app.settings.partner_products.index.create.no_template')</option>
                            </select>
                        </div>
                        
                        <div id="template-product-info" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                @lang('admin::app.settings.partner_products.index.create.template_info')
                            </label>
                            <div id="template-product-details" class="text-sm text-gray-600 dark:text-gray-400">
                                <!-- Template product details will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>

                <x-admin::partner-product-form-fields
                    :selected-clinics="$selectedClinics"
                    :selected-resources="old('resources', [])"
                    :related-products="$oldRelatedProducts"
                    :template-product-id="old('product_id', null)"
                />
            </div>

            <x-admin::partner-product-purchase-prices />
        </div>
    </x-admin::form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadTemplateProducts();
        });

        function loadTemplateProducts() {
            fetch('{{ route("admin.settings.partner_products.template_products") }}')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('template-product-selector');
                    data.data.forEach(product => {
                        const option = document.createElement('option');
                        option.value = product.id;
                        option.textContent = product.name_with_path;
                        select.appendChild(option);
                    });
                })
                .catch(error => console.error('Error loading template products:', error));
        }

        function loadTemplateProduct(productId) {
            if (!productId) {
                document.getElementById('template-product-info').classList.add('hidden');
                
                // Clear the product_id field
                let productIdField = document.querySelector('input[name="product_id"]');
                if (productIdField) {
                    productIdField.remove();
                }
                return;
            }

            fetch(`{{ route("admin.settings.partner_products.template_product", ":id") }}`.replace(':id', productId))
                .then(response => response.json())
                .then(data => {
                    const product = data.data;
                    
                    // Update form fields with template data
                    if (product.name) {
                        document.querySelector('input[name="name"]').value = product.name;
                    }
                    
                    if (product.description) {
                        document.querySelector('textarea[name="description"]').value = product.description;
                    }
                    
                    if (product.currency) {
                        document.querySelector('select[name="currency"]').value = product.currency;
                    }
                    
                    if (product.price) {
                        document.querySelector('input[name="sales_price"]').value = product.price;
                    }
                    
                    if (product.resource_type_id) {
                        document.querySelector('select[name="resource_type_id"]').value = product.resource_type_id;
                    }

                    // Set the product_id hidden field
                    let productIdField = document.querySelector('input[name="product_id"]');
                    if (!productIdField) {
                        productIdField = document.createElement('input');
                        productIdField.type = 'hidden';
                        productIdField.name = 'product_id';
                        document.querySelector('form').appendChild(productIdField);
                    }
                    productIdField.value = product.id;

                    // Show template info
                    const infoDiv = document.getElementById('template-product-details');
                    infoDiv.innerHTML = `
                        <div class="bg-white dark:bg-gray-700 p-3 rounded border">
                            <div class="font-medium">${product.name_with_path}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                ${product.currency} ${product.price || '0.00'} | 
                                @lang('admin::app.settings.partner_products.index.create.template_loaded')
                            </div>
                        </div>
                    `;
                    document.getElementById('template-product-info').classList.remove('hidden');
                })
                .catch(error => console.error('Error loading template product:', error));
        }
    </script>
</x-admin::layouts>

