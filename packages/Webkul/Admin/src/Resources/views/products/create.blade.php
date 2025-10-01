
<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.products.create.title')
    </x-slot>

    {!! view_render_event('admin.products.create.form.before') !!}

    <x-admin::form
        :action="route('admin.products.store')"
        method="POST"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    {!! view_render_event('admin.products.create.breadcrumbs.before') !!}

                    <!-- Breadcrumbs -->
                    <x-admin::breadcrumbs name="products.create" />

                    {!! view_render_event('admin.products.create.breadcrumbs.after') !!}
                    
                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.products.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.products.create.save_button.before') !!}

                        <!-- Create button for Product -->
                        @if (bouncer()->hasPermission('settings.user.groups.create'))
                            <button
                                type="submit"
                                class="primary-button"
                            >
                                @lang('admin::app.products.create.save-btn')
                            </button>
                        @endif

                        {!! view_render_event('admin.products.create.save_button.after') !!}
                    </div>
                </div>
            </div>

            <div class="flex gap-2.5 max-xl:flex-wrap">
                <!-- Left sub-component -->
                <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                            @lang('admin::app.products.create.general')
                        </p>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.settings.partner_products.index.create.currency')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    name="currency"
                                    rules="required"
                                    :label="trans('admin::app.settings.partner_products.index.create.currency')"
                                >
                                    @foreach ($currencies as $currency)
                                        <option value="{{ $currency['code'] }}" @selected(old('currency', 'EUR') === $currency['code'])>{{ $currency['label'] }}</option>
                                    @endforeach
                                </x-admin::form.control-group.control>

                                <x-admin::form.control-group.error control-name="currency" />
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.products.create.costs')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="price"
                                    name="costs"
                                    :label="trans('admin::app.products.create.costs')"
                                    :placeholder="trans('admin::app.products.create.costs')"
                                />

                                <x-admin::form.control-group.error control-name="costs" />
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.products.create.product_type')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    name="product_type_id"
                                    :label="trans('admin::app.products.create.product_type')"
                                >
                                    <option value="">@lang('admin::app.select')</option>
                                    @foreach (\App\Models\ProductType::orderBy('name')->get(['id', 'name']) as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </x-admin::form.control-group.control>

                                <x-admin::form.control-group.error control-name="product_type_id" />
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.products.create.resource_type')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    name="resource_type_id"
                                    :label="trans('admin::app.products.create.resource_type')"
                                >
                                    <option value="">@lang('admin::app.select')</option>
                                    @foreach (\App\Models\ResourceType::orderBy('name')->get(['id', 'name']) as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </x-admin::form.control-group.control>

                                <x-admin::form.control-group.error control-name="resource_type_id" />
                            </x-admin::form.control-group>

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.products.create.product_group')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    name="product_group_id"
                                    :label="trans('admin::app.products.create.product_group')"
                                >
                                    <option value="">@lang('admin::app.select')</option>
                                    @foreach (\Webkul\Product\Models\ProductGroup::orderBy('name')->get(['id', 'name']) as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                    @endforeach
                                </x-admin::form.control-group.control>

                                <x-admin::form.control-group.error control-name="product_group_id" />
                            </x-admin::form.control-group>
                        </div>

                        {!! view_render_event('admin.products.create.attributes.before') !!}

                        <x-admin::attributes
                            :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                'entity_type' => 'products',
                                ['code', 'NOTIN', ['price', 'costs', 'product_type_id', 'resource_type_id', 'product_group_id']],
                            ])"
                        />

                        {!! view_render_event('admin.products.create.attributes.after') !!}

                        <!-- Partner Products Selection -->
                        <x-admin::partner-product-lookup
                            :src="route('admin.settings.partner_products.search')"
                            name="partner_products"
                            :label="trans('admin::app.products.create.partner_products')"
                            :search-placeholder="trans('admin::app.products.create.search_partner_products')"
                            :value="[]"
                        />
                    </div>
                </div>

                <!-- Right sub-component -->
                <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                    {!! view_render_event('admin.products.create.accordion.before') !!}

                    <x-admin::accordion>
                        <x-slot:header>
                            {!! view_render_event('admin.products.create.accordion.header.before') !!}

                            <div class="flex items-center justify-between">
                                <p class="p-2.5 text-base font-semibold text-gray-800 dark:text-white">
                                    @lang('admin::app.products.create.price')
                                </p>
                            </div>

                            {!! view_render_event('admin.products.create.accordion.header.after') !!}
                        </x-slot>

                        <x-slot:content>
                            {!! view_render_event('admin.products.create.accordion.content.attributes.before') !!}

                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                    'entity_type' => 'products',
                                    ['code', 'IN', ['price']],
                                ])"
                            />

                            {!! view_render_event('admin.products.create.accordion.content.attributes.after') !!}
                        </x-slot>
                    </x-admin::accordion>

                    {!! view_render_event('admin.products.create.accordion.before') !!}
                </div>
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.products.create.form.after') !!}
</x-admin::layouts>
