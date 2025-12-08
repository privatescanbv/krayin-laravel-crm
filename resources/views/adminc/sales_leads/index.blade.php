<x-admin::layouts>
    <x-slot:title>
        Sales order
    </x-slot>

    <!-- Header -->
    {!! view_render_event('admin.sales-leads.index.header.before') !!}

    <div class="flex items-center justify-between text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 backdrop-blur-md pt-4 sticky top-16 z-10">
        {!! view_render_event('admin.sales-leads.index.header.left.before') !!}

        <div class="flex flex-col">
            <!-- Breadcrumb's -->
            <x-admin::breadcrumbs name="sales-leads" />

            <div class="text-xl font-bold dark:text-white">
                Sales order
            </div>
        </div>

        {!! view_render_event('admin.sales-leads.index.header.left.after') !!}

        {!! view_render_event('admin.sales-leads.index.header.right.before') !!}

        <div class="flex items-center gap-x-2.5">

            @include('adminc::components.kanban-toolbar', ['type' => 'sales'])
        </div>

        {!! view_render_event('admin.sales-leads.index.header.right.after') !!}
    </div>

    {!! view_render_event('admin.sales-leads.index.header.after') !!}

    {!! view_render_event('admin.sales-leads.index.content.before') !!}

    <!-- Content -->
    <div class="[&>*>*>*.toolbarRight]:max-lg:w-full [&>*>*>*.toolbarRight]:max-lg:justify-between [&>*>*>*.toolbarRight]:max-md:gap-y-2 [&>*>*>*.toolbarRight]:max-md:flex-wrap mt-3.5 [&>*>*:nth-child(1)]:max-lg:!flex-wrap">
        @include('admin::leads.index.kanban')
    </div>

    {!! view_render_event('admin.sales-leads.index.content.after') !!}
</x-admin::layouts>
