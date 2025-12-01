{!! view_render_event('admin.leads.index.kanban.toolbar.before') !!}

<div class="flex justify-between gap-2 rounded-lg border bg-white p-2 shadow-xs max-md:flex-wrap">
    <div class="flex w-full items-center gap-x-1.5 max-md:justify-between">
        {!! view_render_event('admin.leads.index.kanban.toolbar.search.before') !!}

        <!-- Search Panel -->
        @include('admin::leads.index.kanban.search')

        {!! view_render_event('admin.leads.index.kanban.toolbar.search.after') !!}

        {!! view_render_event('admin.leads.index.kanban.toolbar.filter.before') !!}

        <!-- Filter -->
        @include('admin::leads.index.kanban.filter')

        {!! view_render_event('admin::leads.index.kanban.toolbar.filter.after') !!}
        <!-- Collapse Won/Lost toggle -->
        <button type="button" class="secondary-button whitespace-nowrap"
            @click="$root.$refs.leadsKanban && $root.$refs.leadsKanban.toggleWonLost()">
            <span>@{{ $root.$refs.leadsKanban ? $root.$refs.leadsKanban.wonLostLabel : 'Toon gewonnen/verloren' }}</span>
        </button>

        <div class="z-10 hidden w-full divide-y divide-gray-100 rounded bg-white shadow dark:bg-gray-900"></div>
    </div>

</div>

{!! view_render_event('admin.leads.index.kanban.toolbar.after') !!}
