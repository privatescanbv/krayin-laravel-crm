{!! view_render_event('admin.workflow-leads.index.table.before') !!}

<x-admin::datagrid :src="route('admin.workflow-leads.index', ['view_type' => 'table'])">
    <!-- DataGrid Shimmer -->
    <x-admin::shimmer.datagrid />

    <x-slot:toolbar-right-after>
        @include('admin.sales_leads.index.view-switcher')
    </x-slot>
</x-admin::datagrid>

{!! view_render_event('admin.workflow-leads.index.table.after') !!}
