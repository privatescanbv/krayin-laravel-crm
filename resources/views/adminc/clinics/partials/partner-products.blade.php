<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h4 class="text-lg font-semibold dark:text-white">
            @lang('admin::app.settings.clinics.view.partner-products.title')
        </h4>
        @if (bouncer()->hasPermission('partner_products.create'))
            <a
                href="{{ route('admin.partner_products.create', ['clinic_id' => $clinic->id, 'return_to' => 'clinic_view']) }}"
                class="primary-button"
            >
                @lang('admin::app.settings.clinics.view.partner-products.add-btn')
            </a>
        @endif
    </div>

    <x-admin::datagrid :src="route('admin.clinics.partner_products.index', $clinic->id)">
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
</div>
