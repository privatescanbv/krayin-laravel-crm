<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.index.title')
    </x-slot>

    <!-- Header -->
    {!! view_render_event('admin.leads.index.header.before') !!}

    <div class="flex items-center justify-between text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 backdrop-blur-md pt-4 sticky top-16 z-10">
        {!! view_render_event('admin.leads.index.header.left.before') !!}

        <div class="flex flex-col">
            <!-- Breadcrumb's -->
            <x-admin::breadcrumbs name="leads" />

            <div class="text-xl font-bold dark:text-white">
                @lang('admin::app.leads.index.title')
            </div>
        </div>

        {!! view_render_event('admin.leads.index.header.left.after') !!}

        {!! view_render_event('admin.leads.index.header.right.before') !!}

        <div class="flex items-center gap-x-2.5">
            <!-- Upload File for Lead Creation -->
            @if(core()->getConfigData('general.magic_ai.doc_generation.enabled'))
                @include('admin::leads.index.upload')
            @endif

            @include('adminc::components.kanban-toolbar', ['type' => 'leads', 'currentPipelineId' => $pipeline->id])

        </div>

        {!! view_render_event('admin.leads.index.header.right.after') !!}
    </div>

    {!! view_render_event('admin.leads.index.header.after') !!}

    {!! view_render_event('admin.leads.index.content.before') !!}

    <!-- Content -->
    @include('admin::leads.index.kanban')

    {!! view_render_event('admin.leads.index.content.after') !!}
</x-admin::layouts>
