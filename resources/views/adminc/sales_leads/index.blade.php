<x-admin::layouts>
    <x-slot:title>
        Sales order
    </x-slot>

    <!-- Header -->
    {!! view_render_event('admin.sales-leads.index.header.before') !!}

    <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        {!! view_render_event('admin.sales-leads.index.header.left.before') !!}

        <div class="flex flex-col gap-2">
            <!-- Breadcrumb's -->
            <x-admin::breadcrumbs name="sales-leads" />
        </div>

        {!! view_render_event('admin.sales-leads.index.header.left.after') !!}

        {!! view_render_event('admin.sales-leads.index.header.right.before') !!}

        <div class="flex items-center gap-x-2.5">
            @if ((request()->view_type ?? "kanban") == "table")
                <!-- Export Modal -->
                <x-admin::datagrid.export :src="route('admin.sales-leads.index')" />
            @endif

            <!-- Pipeline Switcher -->
            <x-adminc::sales_leads.index.view-switcher :pipeline="$pipeline" />

        </div>

        {!! view_render_event('admin.sales-leads.index.header.right.after') !!}
    </div>

    {!! view_render_event('admin.sales-leads.index.header.after') !!}

    {!! view_render_event('admin.sales-leads.index.content.before') !!}

    <!-- Content -->
    <div class="[&>*>*>*.toolbarRight]:max-lg:w-full [&>*>*>*.toolbarRight]:max-lg:justify-between [&>*>*>*.toolbarRight]:max-md:gap-y-2 [&>*>*>*.toolbarRight]:max-md:flex-wrap mt-3.5 [&>*>*:nth-child(1)]:max-lg:!flex-wrap">
        @if ((request()->view_type ?? "kanban") == "table")
            <x-adminc::sales_leads.index.table :pipeline="$pipeline"/>
        @else
            <x-adminc::sales_leads.index.kanban :columns="$columns" :stages="$stages" :pipeline="$pipeline"/>
        @endif
    </div>

    {!! view_render_event('admin.sales-leads.index.content.after') !!}
</x-admin::layouts>
