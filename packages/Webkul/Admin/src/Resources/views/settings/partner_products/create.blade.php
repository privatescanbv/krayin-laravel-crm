<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.partner_products.index.create.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.partner_products.store')" method="POST">
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
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.settings.partner_products.index.create.name')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            name="name"
                            rules="required|min:1|max:255"
                            :label="trans('admin::app.settings.partner_products.index.create.name')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.name')"
                        />

                        <x-admin::form.control-group.error control-name="name" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.settings.partner_products.index.create.currency')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="select"
                            name="currency"
                            value="{{ old('currency', $defaultCurrency) }}"
                            rules="required"
                            :label="trans('admin::app.settings.partner_products.index.create.currency')"
                        >
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency['code'] }}" @selected(old('currency', $defaultCurrency) === $currency['code'])>{{ $currency['label'] }}</option>
                            @endforeach
                        </x-admin::form.control-group.control>

                        <x-admin::form.control-group.error control-name="currency" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.settings.partner_products.index.create.sales_price')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="number"
                            step="0.01"
                            name="sales_price"
                            rules="required|numeric|min:0"
                            :label="trans('admin::app.settings.partner_products.index.create.sales_price')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.sales_price')"
                        />

                        <x-admin::form.control-group.error control-name="sales_price" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.partner_products.index.create.active')
                        </x-admin::form.control-group.label>

                        <input type="hidden" name="active" value="0" />
                        <x-admin::form.control-group.control
                            type="checkbox"
                            name="active"
                            value="1"
                            :label="trans('admin::app.settings.partner_products.index.create.active')"
                            :checked="old('active', 1)"
                        />

                        <x-admin::form.control-group.error control-name="active" />
                    </x-admin::form.control-group>
                </div>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.description')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="description"
                        :label="trans('admin::app.settings.partner_products.index.create.description')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.description')"
                    />

                    <x-admin::form.control-group.error control-name="description" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.discount_info')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="discount_info"
                        :label="trans('admin::app.settings.partner_products.index.create.discount_info')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.discount_info')"
                    />

                    <x-admin::form.control-group.error control-name="discount_info" />
                </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.partner_products.index.create.resource_type')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="select"
                            name="resource_type_id"
                            rules="required|numeric"
                            :label="trans('admin::app.settings.partner_products.index.create.resource_type')"
                        >
                            <option value="">@lang('admin::app.select')</option>
                            @foreach ($resourceTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </x-admin::form.control-group.control>

                        <x-admin::form.control-group.error control-name="resource_type_id" />
                    </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.clinics.index.title')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="select"
                        name="clinics[]"
                        rules="required"
                        multiple
                        :label="trans('admin::app.settings.clinics.index.title')"
                    >
                        @foreach ($clinics as $clinic)
                            <option value="{{ $clinic->id }}" @selected(collect(old('clinics', []))->contains($clinic->id))>{{ $clinic->name }}</option>
                        @endforeach
                    </x-admin::form.control-group.control>

                    <x-admin::form.control-group.error control-name="clinics" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('admin::app.settings.partner_products.index.create.partner_name')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="partner_name"
                        rules="required|min:1|max:100"
                        :label="trans('admin::app.settings.partner_products.index.create.partner_name')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.partner_name')"
                    />

                    <x-admin::form.control-group.error control-name="partner_name" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.clinic_description')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="clinic_description"
                        :label="trans('admin::app.settings.partner_products.index.create.clinic_description')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.clinic_description')"
                    />

                    <x-admin::form.control-group.error control-name="clinic_description" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.duration')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="number"
                        name="duration"
                        
                        :label="trans('admin::app.settings.partner_products.index.create.duration')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.duration')"
                    />

                    <x-admin::form.control-group.error control-name="duration" />
                </x-admin::form.control-group>

                <x-admin::partner-product-lookup
                    :src="route('admin.settings.partner_products.search')"
                    name="related_products"
                    :label="trans('admin::app.settings.partner_products.index.create.related_products')"
                    :search-placeholder="trans('admin::app.settings.partner_products.index.create.search_related_products')"
                    :value="[]"
                />
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>

