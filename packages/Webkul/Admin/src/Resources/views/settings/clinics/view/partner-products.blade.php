<div class="p-4">
    <x-admin::datagrid :src="route('admin.settings.clinics.partner_products.index', $clinic->id)">
        <!-- DataGrid Shimmer -->
        <x-admin::shimmer.datagrid />
    </x-admin::datagrid>
</div>