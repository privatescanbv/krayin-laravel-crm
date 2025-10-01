<div class="p-4">
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
</div>
