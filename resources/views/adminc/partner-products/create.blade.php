@php use App\Models\PartnerProduct; @endphp
<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.partner_products.index.create.title')
    </x-slot>

    <x-admin::form :action="route('admin.partner_products.store')" method="POST">
        @include('adminc.components.validation-errors')
        <!-- Hidden field for return_to -->
        @if (isset($returnTo))
            <input type="hidden" name="return_to" value="{{ $returnTo }}"/>
        @endif

        <div class="flex flex-col gap-4">
            <div
                class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="partner_products.create"/>

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.partner_products.index.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.partner_products.index.create.save-btn')
                    </button>
                </div>
            </div>

            <div
                class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @php
                    $oldRelatedProducts = collect(old('related_products', []))->map(function($id) {
                        $product = PartnerProduct::whereNull('deleted_at')->find($id);
                        return $product ? ['id' => $product->id, 'name' => $product->name] : null;
                    })->filter()->values()->toArray();

                    // Pre-select clinic if provided
                    $selectedClinics = old('clinics', []);
                    if (empty($selectedClinics) && isset($preSelectedClinicId)) {
                        $selectedClinics = [$preSelectedClinicId];
                    }

                    // Calculate template product ID for Vue component
                    $templateProductId = old('product_id', $preSelectedProductId ?? null);

                    // Calculate selected resources for Vue component
                    $selectedResources = old('resources', []);
                @endphp

                    <!-- Product Template Selector -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg dark:bg-gray-800">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        @lang('admin::app.partner_products.index.create.template_product')
                    </h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                @lang('admin::app.partner_products.index.create.select_template_product')
                            </label>

                            @include('adminc.components.product-selector')

                            @if ($templateProductId)
                            <v-product-selector
                                id="template-product-selector"
                                name="product_id"
                                placeholder="{{ trans('admin::app.partner_products.index.create.select_template_product') }}"
                                search-route="{{ route('admin.partner_products.template_products') }}"
                                :can-add-new="false"
                                :multiple="false"
                                :current-value="{{ $templateProductId }}"
                            />
                            @else
                            <v-product-selector
                                id="template-product-selector"
                                name="product_id"
                                placeholder="{{ trans('admin::app.partner_products.index.create.select_template_product') }}"
                                search-route="{{ route('admin.partner_products.template_products') }}"
                                :can-add-new="false"
                                :multiple="false"
                            />
                            @endif
                        </div>

                        <div id="template-product-info" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                @lang('admin::app.partner_products.index.create.template_info')
                            </label>
                            <div id="template-product-details" class="text-sm text-gray-600 dark:text-gray-400">
                                <!-- Template product details will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>

                <x-adminc::partner-products.partner-product-form-fields
                    :selected-clinics="$selectedClinics"
                    :selected-resources="$selectedResources"
                    :related-products="$oldRelatedProducts"
                    :template-product-id="$templateProductId"
                />
            </div>

            <x-adminc::partner-products.partner-product-purchase-prices/>

            <!-- Related Purchase Prices -->
            <x-adminc::partner-products.partner-product-related-purchase-prices/>

        </div>
    </x-admin::form>
</x-admin::layouts>

<script type="module">
        let isUserEditing = false;
        let templateLoaded = false;

        function setFieldValue(name, value) {
            const el = document.querySelector(`[name="${name}"]`);
            if (!el) return;

            const stringValue = value !== null && value !== undefined ? String(value) : '';
            el.value = stringValue;

            if (el.tagName === 'SELECT') {
                const option = el.querySelector(`option[value="${stringValue}"]`);
                if (option || stringValue === '') {
                    el.value = stringValue;
                }
            }

            el.dispatchEvent(new Event('input', {bubbles: true}));
            el.dispatchEvent(new Event('change', {bubbles: true}));

            if (el.__vnode) {
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        // Helper function to set field value when element is available
        function setFieldValueWhenReady(name, value, maxAttempts = 50) {
            const el = document.querySelector(`[name="${name}"]`);
            if (el) {
                setFieldValue(name, value);
                return;
            }

            // If element not found, use MutationObserver to wait for it
            let attempts = 0;
            const observer = new MutationObserver(function(mutations, obs) {
                attempts++;
                const element = document.querySelector(`[name="${name}"]`);
                if (element) {
                    setFieldValue(name, value);
                    obs.disconnect();
                } else if (attempts >= maxAttempts) {
                    obs.disconnect();
                }
            });

            observer.observe(document.body, { childList: true, subtree: true });

            // Also try immediately in case element was just added
            requestAnimationFrame(() => {
                const element = document.querySelector(`[name="${name}"]`);
                if (element) {
                    setFieldValue(name, value);
                    observer.disconnect();
                }
            });
        }

        // Define loadTemplateProduct function
        // Wrap in IIFE to prevent duplicate declaration errors if script is loaded multiple times
        const loadTemplateProduct = (function() {
            if (window._loadTemplateProduct) {
                return window._loadTemplateProduct;
            }
            return function(productId) {
                if (!productId) {
                    document.getElementById('template-product-info').classList.add('hidden');
                    const productIdField = document.querySelector('input[name="product_id"]');
                    if (productIdField) {
                        productIdField.remove();
                    }
                    templateLoaded = false;
                    return;
                }

                // Only load template if user hasn't started editing or if explicitly requested
                if (isUserEditing && templateLoaded) {
                    if (!confirm('Je hebt al wijzigingen gemaakt. Wil je deze overschrijven met de template gegevens?')) {
                        return;
                    }
                }

                fetch(`{{ route("admin.partner_products.template_product", ":id") }}`.replace(':id', productId))
                    .then(response => response.json())
                    .then(data => {
                        const product = data.data;

                        // Update form fields with template data
                        if (product.name !== undefined) {
                            setFieldValue('name', product.name);
                        }

                        if (product.description !== undefined) {
                            setFieldValue('description', product.description);
                        }

                        if (product.currency !== undefined) {
                            setFieldValue('currency', product.currency);
                        }

                        if (product.price !== undefined) {
                            setFieldValue('sales_price', product.price);
                        }

                        if (product.resource_type_id !== undefined && product.resource_type_id !== null) {
                            setFieldValueWhenReady('resource_type_id', product.resource_type_id);
                        }

                        // Set the product_id hidden field
                        const productIdField = document.querySelector('input[name="product_id"]');
                        if (productIdField) {
                            productIdField.value = product.id;
                            productIdField.dataset.templateLoaded = product.id.toString();
                        }

                        // Show template info
                        const infoDiv = document.getElementById('template-product-details');
                        infoDiv.innerHTML = `
                            <div class="bg-white dark:bg-gray-700 p-3 rounded border">
                                <div class="font-medium">${product.name_with_path}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    ${product.currency} ${product.price || '0.00'} |
                                    @lang('admin::app.partner_products.index.create.template_loaded')
                                </div>
                            </div>
                        `;
                        document.getElementById('template-product-info').classList.remove('hidden');

                        templateLoaded = true;
                        isUserEditing = false;
                    })
                    .catch(error => {
                        console.error('Error loading template product:', error);
                    });
            };
        })();

        window._loadTemplateProduct = loadTemplateProduct;

        document.addEventListener('DOMContentLoaded', function () {
            // Track user input to prevent template overwrites
            const formFields = document.querySelectorAll('input, textarea, select');
            formFields.forEach(field => {
                field.addEventListener('input', () => { isUserEditing = true; });
                field.addEventListener('change', () => { isUserEditing = true; });
            });

            // Find the form
            let form = document.querySelector('form[action*="partner_products"]') ||
                       document.querySelector('form:not(.phpdebugbar-settings)') ||
                       document.querySelector('form');

            if (form) {
                // Use MutationObserver to detect when the Vue component adds/updates the hidden input
                let lastProductId = null;
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        // Check for added nodes (when hidden input is created)
                        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1 && node.tagName === 'INPUT' && node.name === 'product_id') {
                                    if (node.value) {
                                        const productId = parseInt(node.value);
                                        if (productId && !isNaN(productId) && productId !== lastProductId) {
                                            lastProductId = productId;
                                            node.dataset.templateLoaded = productId.toString();
                                            loadTemplateProduct(productId);
                                        }
                                    }
                                }
                            });
                        }

                        // Check for attribute changes (when hidden input value is updated)
                        if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                            const target = mutation.target;
                            if (target.tagName === 'INPUT' && target.name === 'product_id') {
                                const productId = parseInt(target.value);
                                if (productId && !isNaN(productId) && productId !== lastProductId) {
                                    lastProductId = productId;
                                    target.dataset.templateLoaded = productId.toString();
                                    loadTemplateProduct(productId);
                                }
                            }
                        }
                    });
                });

                // Observe both form and document.body to catch Vue component changes
                observer.observe(form, { childList: true, subtree: true, attributes: true, attributeFilter: ['value'] });
                observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['value'] });

                // Also listen for input/change events directly
                form.addEventListener('change', function(e) {
                    if (e.target.name === 'product_id' && e.target.value) {
                        const productId = parseInt(e.target.value);
                        if (productId && !isNaN(productId)) {
                            const currentLoadedId = e.target.dataset.templateLoaded;
                            if (currentLoadedId !== productId.toString()) {
                                e.target.dataset.templateLoaded = productId.toString();
                                loadTemplateProduct(productId);
                            }
                        }
                    }
                });

                form.addEventListener('input', function(e) {
                    if (e.target.name === 'product_id' && e.target.value) {
                        const productId = parseInt(e.target.value);
                        if (productId && !isNaN(productId)) {
                            const currentLoadedId = e.target.dataset.templateLoaded;
                            if (currentLoadedId !== productId.toString()) {
                                e.target.dataset.templateLoaded = productId.toString();
                                loadTemplateProduct(productId);
                            }
                        }
                    }
                });
            }

            // Auto-select product template if templateProductId is provided
            @if ($templateProductId)
                // Wait for Vue component to mount, then trigger template load
                const templateProductId = {{ $templateProductId }};
                let vueComponentReady = false;
                const vueObserver = new MutationObserver(function(mutations, obs) {
                    const templateSelector = document.getElementById('template-product-selector');
                    // Check if Vue component has rendered (has child elements or specific Vue markers)
                    if (templateSelector && (templateSelector.children.length > 0 || templateSelector.__vue__)) {
                        if (!vueComponentReady) {
                            vueComponentReady = true;
                            loadTemplateProduct(templateProductId);
                            obs.disconnect();
                        }
                    }
                });

                vueObserver.observe(document.body, { childList: true, subtree: true });

                // Also try immediately in case component is already mounted
                requestAnimationFrame(() => {
                    const templateSelector = document.getElementById('template-product-selector');
                    if (templateSelector && (templateSelector.children.length > 0 || templateSelector.__vue__)) {
                        if (!vueComponentReady) {
                            vueComponentReady = true;
                            loadTemplateProduct(templateProductId);
                            vueObserver.disconnect();
                        }
                    }
                });
            @endif
        });
    </script>


