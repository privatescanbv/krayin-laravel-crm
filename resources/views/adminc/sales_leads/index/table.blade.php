{!! view_render_event('admin.sales-leads.index.table.before') !!}

<x-admin::datagrid :src="route('admin.sales-leads.index', ['view_type' => 'table'])">
    <!-- DataGrid Shimmer -->
    <x-admin::shimmer.datagrid />

    <x-slot:toolbar-right-after>
        @include('admin.sales_leads.index.view-switcher')
    </x-slot>
</x-admin::datagrid>

{!! view_render_event('admin.sales-leads.index.table.after') !!}
