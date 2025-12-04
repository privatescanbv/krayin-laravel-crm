@include('adminc.components.entity-selector')

<x-admin::layouts>
    <x-slot:title>Entity Selector Demo</x-slot>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">Entity Selector Demo</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Test alle functionaliteiten van de entity selector component
            </div>
        </div>

        <!-- Entity Selector Tests -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Partner Products Selector -->
            <div class="box-shadow rounded-lg border bg-white p-4">
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Partner Products</h3>

                <v-entity-selector
                    name="partner_products"
                    label="Partner Products"
                    placeholder="Selecteer partner producten..."
                    search-route="/admin/partner-products/search"
                    :multiple="true"
                    :items="[]"
                />
            </div>

            <!-- Contacts/Persons Selector -->
            <div class="box-shadow rounded-lg border bg-white p-4">
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Contacts/Persons</h3>

                @include('adminc.components.contact-person-selector')

                <v-contact-person-selector
                    name="contact_person_id_demo"
                    label="Contactpersoon"
                    placeholder="Selecteer contactpersoon..."
                    :current-value="null"
                    :current-label="null"
                    :can-add-new="true"
                />
            </div>

            <!-- Products Selector -->
            <div class="box-shadow rounded-lg border bg-white p-4">
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Products</h3>

                <v-entity-selector
                    name="products"
                    label="Products"
                    placeholder="Selecteer producten..."
                    search-route="/admin/products/search"
                    :multiple="true"
                    :items="[]"
                />
            </div>

            <!-- Leads Selector -->
            <div class="box-shadow rounded-lg border bg-white p-4">
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Leads</h3>

                <v-entity-selector
                    name="leads"
                    label="Leads"
                    placeholder="Selecteer leads..."
                    search-route="/admin/leads/search"
                    :multiple="true"
                    :items="[]"
                />
            </div>
        </div>

        <!-- Single Selection Test -->
        <div class="box-shadow rounded-lg border bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Single Selection Test</h3>

            <v-entity-selector
                name="single_test"
                label="Single Selection"
                placeholder="Selecteer één item..."
                search-route="/admin/partner-products/search"
                :multiple="false"
                :items="[]"
            />
        </div>

        <!-- Form Components Demo -->
        <div class="box-shadow rounded-lg border bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Form Components Demo</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Text Input -->
                <x-adminc::components.field
                    type="text"
                    name="text_demo"
                    label="Text Input"
                    placeholder="Enter text..."
                />

                <!-- Email Input -->
                <x-adminc::components.field
                    type="email"
                    name="email_demo"
                    label="Email Input"
                    placeholder="Enter email..."
                />

                <!-- Date Picker -->
                <x-adminc::components.field
                    type="date"
                    name="date_demo"
                    label="Date Picker"
                    placeholder="dd-mm-yyyy"
                />

                <!-- Select Dropdown -->
                <x-adminc::components.field
                    type="select"
                    name="select_demo"
                    label="Select Dropdown"
                >
                    <option value="">Choose an option...</option>
                    <option value="option1">Option 1</option>
                    <option value="option2">Option 2</option>
                    <option value="option3">Option 3</option>
                </x-adminc::components.field>

                <!-- Textarea -->
                <x-adminc::components.field
                    class="md:col-span-2"
                    type="textarea"
                    name="textarea_demo"
                    label="Textarea"
                    placeholder="Enter description..."
                />
                <!-- Number Input -->
                <x-adminc::components.field
                    type="number"
                    name="number_demo"
                    label="Number Input"
                    placeholder="Enter number..."
                />

                <!-- Password Input -->
                <x-adminc::components.field
                    type="password"
                    name="password_demo"
                    label="Password Input"
                    placeholder="Enter password..."
                />
            </div>
        </div>

        <!-- Advanced Components Demo -->
        <div class="box-shadow rounded-lg border bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Advanced Components Demo</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Multi-select -->
                <x-adminc::components.field
                    type="multiselect"
                    name="multiselect_demo"
                    label="Multi-select"
                >
                    <option value="option1">Option 1</option>
                    <option value="option2">Option 2</option>
                    <option value="option3">Option 3</option>
                    <option value="option4">Option 4</option>
                </x-adminc::components.field>

                <!-- URL Input -->
                <x-adminc::components.field
                    type="url"
                    name="url_demo"
                    label="URL Input"
                    placeholder="https://example.com"
                />

                <!-- File Upload -->
                <x-adminc::components.field
                    type="file"
                    name="file_demo"
                    label="File Upload"
                />

                <!-- Search Input -->
                <x-adminc::components.field
                    type="search"
                    name="search_demo"
                    label="Search Input"
                    placeholder="Search..."
                />
            </div>
        </div>

        <!-- Component Status -->
        <div class="box-shadow rounded-lg border bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Component Status</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-succes rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Entity Selectors</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-succes rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Form Components</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-succes rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Date Picker</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-succes rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Advanced Inputs</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-brand-herniapoli-main rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Interactive Elements</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-succes rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">All Components Ready</span>
                </div>
            </div>
        </div>
    </div>
</x-admin::layouts>
