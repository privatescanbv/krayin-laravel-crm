{!! view_render_event('admin.leads.index.table.before') !!}

<x-admin::datagrid :src="route('admin.leads.index')">
    <!-- DataGrid Shimmer -->
    <x-admin::shimmer.datagrid />

    <x-slot:toolbar-right-after>
        <x-adminc::sales_leads.index.view-switcher :pipeline="$pipeline" :columns="$columns" />
    </x-slot>
</x-admin::datagrid>

{!! view_render_event('admin.leads.index.table.after') !!}
