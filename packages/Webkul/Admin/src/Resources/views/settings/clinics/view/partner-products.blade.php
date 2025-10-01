<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h4 class="text-lg font-semibold dark:text-white">
            @lang('admin::app.settings.clinics.view.partner-products.title')
        </h4>
        @if (bouncer()->hasPermission('settings.clinics.edit'))
            <button
                @click="$refs.partnerProductModal.open()"
                class="primary-button"
            >
                @lang('admin::app.settings.clinics.view.partner-products.add-btn')
            </button>
        @endif
    </div>

    <x-admin::datagrid :src="route('admin.settings.clinics.partner_products.index', $clinic->id)">
        <!-- Empty State -->
        <template #body="{ available, isLoading }">
            <template v-if="! isLoading && ! available.records.length">
                <div class="py-16 text-center">
                    <img
                        class="m-auto h-[120px] w-[120px] dark:mix-blend-exclusion dark:invert"
                        src="{{ vite()->asset('images/empty-placeholders/products.svg') }}"
                        alt="@lang('admin::app.settings.clinics.view.partner-products.no-products')"
                    />

                    <p class="mt-4 text-base text-gray-600 dark:text-gray-300">
                        @lang('admin::app.settings.clinics.view.partner-products.no-products')
                    </p>
                </div>
            </template>
        </template>
    </x-admin::datagrid>

    <!-- Partner Product Selector Modal -->
    <x-admin::form
        method="POST"
        :action="route('admin.settings.clinics.partner_products.attach', $clinic->id)"
    >
        <x-admin::modal ref="partnerProductModal">
            <x-slot:header>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                    @lang('admin::app.settings.clinics.view.partner-products.modal.title')
                </h3>
            </x-slot>

            <x-slot:content>
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('admin::app.settings.clinics.view.partner-products.modal.select-products')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="select"
                        name="partner_product_ids[]"
                        rules="required"
                        multiple
                        :label="trans('admin::app.settings.clinics.view.partner-products.modal.select-products')"
                    >
                        @foreach (\App\Models\PartnerProduct::where('active', true)->orderBy('name')->get() as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </x-admin::form.control-group.control>

                    <x-admin::form.control-group.error control-name="partner_product_ids" />
                </x-admin::form.control-group>
            </x-slot>

            <x-slot:footer>
                <button
                    type="button"
                    class="secondary-button"
                    @click="$refs.partnerProductModal.close()"
                >
                    @lang('admin::app.settings.clinics.view.partner-products.modal.cancel')
                </button>

                <button type="submit" class="primary-button">
                    @lang('admin::app.settings.clinics.view.partner-products.modal.save')
                </button>
            </x-slot>
        </x-admin::modal>
    </x-admin::form>
</div>
