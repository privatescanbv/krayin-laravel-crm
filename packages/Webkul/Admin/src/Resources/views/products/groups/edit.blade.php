<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.products.product-groups.edit.title')
    </x-slot>

    <x-admin::form
        :action="route('admin.productgroups.update', $productGroup->id)"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.products.product-groups.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <x-admin::button
                        type="submit"
                        class="primary-button"
                    >
                        @lang('admin::app.products.product-groups.edit.save-btn')
                    </x-admin::button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.products.product-groups.edit.name')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="name"
                        :value="old('name', $productGroup->name)"
                        rules="required"
                        :label="trans('admin::app.products.product-groups.edit.name')"
                        :placeholder="trans('admin::app.products.product-groups.edit.name')"
                    />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.products.product-groups.edit.description')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="description"
                        :value="old('description', $productGroup->description)"
                        :label="trans('admin::app.products.product-groups.edit.description')"
                        :placeholder="trans('admin::app.products.product-groups.edit.description')"
                    />
                </x-admin::form.control-group>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
