<x-admin::layouts>
    <x-slot:title>Entity Selector Demo</x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">Entity Selector Demo</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Test alle functionaliteiten van de entity selector component
            </div>
        </div>

        <!-- Entity Selector Tests -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Partner Products Selector -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Partner Products</h3>
                
                <x-adminc::components.entity-selector
                    name="partner_products"
                    label="Partner Products"
                    placeholder="Selecteer partner producten..."
                    searchRoute="/admin/partner-products/search"
                    :multiple="true"
                    :items="[]"
                />
            </div>

            <!-- Contacts/Persons Selector -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Contacts/Persons</h3>
                
                <x-adminc::components.entity-selector
                    name="contacts"
                    label="Contacts"
                    placeholder="Selecteer contacten..."
                    searchRoute="/admin/contacts/persons/search"
                    :multiple="true"
                    :items="[]"
                />
            </div>

            <!-- Products Selector -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Products</h3>
                
                <x-adminc::components.entity-selector
                    name="products"
                    label="Products"
                    placeholder="Selecteer producten..."
                    searchRoute="/admin/products/search"
                    :multiple="true"
                    :items="[]"
                />
            </div>

            <!-- Leads Selector -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Leads</h3>
                
                <x-adminc::components.entity-selector
                    name="leads"
                    label="Leads"
                    placeholder="Selecteer leads..."
                    searchRoute="/admin/leads/search"
                    :multiple="true"
                    :items="[]"
                />
            </div>
        </div>

        <!-- Single Selection Test -->
        <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Single Selection Test</h3>
            
            <x-adminc::components.entity-selector
                name="single_test"
                label="Single Selection"
                placeholder="Selecteer één item..."
                searchRoute="/admin/partner-products/search"
                :multiple="false"
                :items="[]"
            />
        </div>

        <!-- Form Components Demo -->
        <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Form Components Demo</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Text Input -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Text Input
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="text"
                        name="text_demo"
                        placeholder="Enter text..."
                    />
                </x-admin::form.control-group>

                <!-- Email Input -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Email Input
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="email"
                        name="email_demo"
                        placeholder="Enter email..."
                    />
                </x-admin::form.control-group>

                <!-- Date Picker -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Date Picker
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="date"
                        name="date_demo"
                        placeholder="dd-mm-yyyy"
                    />
                </x-admin::form.control-group>

                <!-- Select Dropdown -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Select Dropdown
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="select"
                        name="select_demo"
                    >
                        <option value="">Choose an option...</option>
                        <option value="option1">Option 1</option>
                        <option value="option2">Option 2</option>
                        <option value="option3">Option 3</option>
                    </x-admin::form.control-group.control>
                </x-admin::form.control-group>

                <!-- Textarea -->
                <x-admin::form.control-group class="md:col-span-2">
                    <x-admin::form.control-group.label>
                        Textarea
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="textarea"
                        name="textarea_demo"
                        placeholder="Enter description..."
                    />
                </x-admin::form.control-group>

                <!-- Number Input -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Number Input
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="number"
                        name="number_demo"
                        placeholder="Enter number..."
                    />
                </x-admin::form.control-group>

                <!-- Password Input -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Password Input
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="password"
                        name="password_demo"
                        placeholder="Enter password..."
                    />
                </x-admin::form.control-group>
            </div>
        </div>

        <!-- Advanced Components Demo -->
        <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Advanced Components Demo</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Multi-select -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Multi-select
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="multiselect"
                        name="multiselect_demo"
                    >
                        <option value="option1">Option 1</option>
                        <option value="option2">Option 2</option>
                        <option value="option3">Option 3</option>
                        <option value="option4">Option 4</option>
                    </x-admin::form.control-group.control>
                </x-admin::form.control-group>

                <!-- URL Input -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        URL Input
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="url"
                        name="url_demo"
                        placeholder="https://example.com"
                    />
                </x-admin::form.control-group>

                <!-- File Upload -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        File Upload
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="file"
                        name="file_demo"
                    />
                </x-admin::form.control-group>

                <!-- Search Input -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Search Input
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="search"
                        name="search_demo"
                        placeholder="Search..."
                    />
                </x-admin::form.control-group>
            </div>
        </div>

        <!-- Component Status -->
        <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Component Status</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Entity Selectors</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Form Components</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Date Picker</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Advanced Inputs</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Interactive Elements</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">All Components Ready</span>
                </div>
            </div>
        </div>
    </div>

    @stack('scripts')
</x-admin::layouts>